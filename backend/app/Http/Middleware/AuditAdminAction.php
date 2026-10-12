<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Support\AuditTargets;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every change an admin makes (any non-GET request) and every
 * export, including ones that were refused. Only a short list of harmless
 * fields is kept; passwords and message bodies never are.
 */
class AuditAdminAction
{
    private const KEPT_FIELDS = [
        'reason', 'note', 'status', 'priority', 'audience', 'title', 'action', 'type',
        'admin_role_id', 'name', 'kind', 'range', 'is_active', 'label',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Name the record before the action runs, while a deleted one still exists
        if (!$request->isMethod('GET') && ($id = $request->route()?->parameter('id')) !== null) {
            $request->attributes->set('audit_target_label', AuditTargets::label($this->actionName($request), (string) $id));
        }

        return $next($request);
    }

    private function actionName(Request $request): string
    {
        $action = $request->route()?->getActionName() ?? 'unknown';

        // "App\Http\Controllers\Admin\AdminVendorController@suspend" → "AdminVendorController@suspend"
        return class_basename(Str::before($action, '@')) . (Str::contains($action, '@') ? '@' . Str::after($action, '@') : '');
    }

    public function terminate(Request $request, Response $response): void
    {
        $isExport = $request->isMethod('GET') && Str::contains($request->path(), 'export');
        if ($request->isMethod('GET') && !$isExport) {
            return;
        }

        $user = $request->user();
        if (!$user instanceof User) {
            return;
        }

        try {
            $route = $request->route();
            $action = $this->actionName($request);
            $params = $route?->parameters() ?? [];
            $targetLabel = $request->attributes->get('audit_target_label')
                ?? AuditTargets::label($action, isset($params['id']) ? (string) $params['id'] : null, $request);

            $details = collect($request->only(self::KEPT_FIELDS))
                ->map(fn ($v) => is_string($v) ? Str::limit($v, 300) : $v)
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->all();
            if (isset($params['action'])) {
                $details['action'] = $params['action'];
            }
            if ($request->has('ids')) {
                $details['items'] = count((array) $request->input('ids'));
            }

            $details = AuditTargets::details($details);

            AdminAuditLog::create([
                'admin_id' => $user->id,
                'admin_name' => trim($user->name . ' ' . ($user->surname ?? '')),
                'action' => $action,
                'method' => $request->method(),
                'path' => Str::limit('/' . ltrim($request->path(), '/'), 250, ''),
                'target_id' => isset($params['id']) ? (string) $params['id'] : null,
                'target_label' => $targetLabel ? Str::limit($targetLabel, 185) : null,
                'status_code' => $response->getStatusCode(),
                'details' => $details ?: null,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Admin audit log could not be written: ' . $e->getMessage());
        }
    }
}
