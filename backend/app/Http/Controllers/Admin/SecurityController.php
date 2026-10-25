<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminRole;
use App\Models\ApiErrorLog;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Support\ApiAreas;
use App\Support\AuditTargets;
use App\Support\PaymentLedger;
use App\Support\PlatformSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Security & system settings: administrators and their roles, the audit
 * log, sign-in monitoring, platform settings and system health.
 */
class SecurityController extends Controller
{
    /**
     * GET /api/admin/security/me
     * The signed-in admin's permissions (any admin may ask).
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('adminRole');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => PaymentLedger::personName($user),
                'role' => $user->adminRole?->name ?? 'No role',
                'is_super_admin' => (bool) $user->adminRole?->is_system,
                'is_owner' => (bool) $user->is_admin_owner,
                'permissions' => $user->adminRole?->expanded() ?? [],
            ],
        ]);
    }

    // ── Administrators ───────────────────────────────────────────────────

    /** GET /api/admin/security/admins */
    public function admins(): JsonResponse
    {
        $admins = User::query()->where('role', User::ROLE_ADMIN)->with('adminRole:id,name,is_system')
            // The owner first, then everyone by name
            ->orderByDesc('is_admin_owner')->orderBy('name')
            ->get(['id', 'name', 'surname', 'username', 'email', 'contact_number', 'is_suspended', 'admin_role_id', 'is_admin_owner', 'created_at']);
        $lastLogin = DB::table('login_audit_logs')->whereIn('user_id', $admins->pluck('id'))
            ->selectRaw('user_id, max(logged_in_at) as at')->groupBy('user_id')->pluck('at', 'user_id');
        $actions = AdminAuditLog::query()->whereIn('admin_id', $admins->pluck('id'))->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('admin_id, count(*) as total')->groupBy('admin_id')->pluck('total', 'admin_id');

        return response()->json([
            'success' => true,
            'data' => $admins->map(fn (User $a) => [
                'id' => $a->id,
                'name' => PaymentLedger::personName($a),
                'first_name' => $a->name,
                'surname' => $a->surname,
                'username' => $a->username,
                'email' => $a->email,
                'role_id' => $a->admin_role_id,
                'role' => $a->adminRole?->name,
                'is_super_admin' => (bool) $a->adminRole?->is_system,
                'is_owner' => (bool) $a->is_admin_owner,
                'active' => !$a->is_suspended,
                'last_login_at' => isset($lastLogin[$a->id]) ? CarbonImmutable::parse($lastLogin[$a->id])->toIso8601String() : null,
                'actions_30d' => (int) ($actions[$a->id] ?? 0),
                'created_at' => $a->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /** POST /api/admin/security/admins */
    public function createAdmin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'surname' => 'required|string|max:120',
            'username' => 'required|string|max:60|alpha_dash|unique:users,username',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => ['required', 'string', Password::min(10)->letters()->mixedCase()->numbers()],
            'admin_role_id' => 'required|integer|exists:admin_roles,id',
        ]);

        $admin = User::create([
            'name' => trim($data['name']),
            'surname' => trim($data['surname']),
            'username' => $data['username'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'is_verified' => true,
            'role' => User::ROLE_ADMIN,
        ]);
        $admin->forceFill(['admin_role_id' => $data['admin_role_id']])->save();

        return response()->json(['success' => true, 'message' => 'Administrator added. Share the password with them privately.'], 201);
    }

    /**
     * PUT /api/admin/security/admins/{id}  { name, surname, username, email, admin_role_id }
     * Name, username and email: the owner only. Role: anyone who manages security,
     * never on the owner.
     */
    public function updateAdmin(Request $request, int $id): JsonResponse
    {
        $admin = $this->findAdmin($id);
        if (!$admin) {
            return $this->notFound();
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'surname' => 'sometimes|required|string|max:120',
            'username' => ['sometimes', 'required', 'string', 'max:60', 'alpha_dash', $this->freeIgnoringCase('username', $admin->id, 'This username is taken.')],
            'email' => ['sometimes', 'required', 'email', 'max:190', $this->freeIgnoringCase('email', $admin->id, 'Another account already uses this email.')],
            'admin_role_id' => 'sometimes|required|integer|exists:admin_roles,id',
        ]);

        $editsDetails = array_intersect_key($data, array_flip(['name', 'surname', 'username', 'email']));
        if ($editsDetails && !$this->isOwner($request)) {
            return $this->refuse('Only the account owner can edit administrator details.', 403);
        }

        if (isset($data['admin_role_id']) && (int) $data['admin_role_id'] !== (int) $admin->admin_role_id) {
            if ($admin->id === $request->user()->id) {
                return $this->refuse("You can't change your own role.");
            }
            if ($admin->is_admin_owner) {
                return $this->refuse("The account owner's role can't be changed.");
            }
            $newRole = AdminRole::find($data['admin_role_id']);
            if ($admin->adminRole?->is_system && !$newRole->is_system && $this->activeSuperAdmins() <= 1) {
                return $this->refuse('This is the only active super admin. Make someone else a super admin first.');
            }
        }

        foreach (['name', 'surname', 'username'] as $text) {
            if (isset($data[$text])) {
                $data[$text] = trim($data[$text]);
            }
        }
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }
        $admin->forceFill(array_intersect_key($data, array_flip(['name', 'surname', 'username', 'email', 'admin_role_id'])))->save();

        return response()->json(['success' => true, 'message' => 'Saved.']);
    }

    /**
     * DELETE /api/admin/security/admins/{id}: the owner only. Their audit
     * history stays readable (it keeps their name).
     */
    public function deleteAdmin(Request $request, int $id): JsonResponse
    {
        $admin = $this->findAdmin($id);
        if (!$admin) {
            return $this->notFound();
        }
        if (!$this->isOwner($request)) {
            return $this->refuse('Only the account owner can remove administrators.', 403);
        }
        if ($admin->id === $request->user()->id || $admin->is_admin_owner) {
            return $this->refuse("The account owner can't be removed.");
        }
        if ($admin->adminRole?->is_system && !$admin->is_suspended && $this->activeSuperAdmins() <= 1) {
            return $this->refuse('This is the only active super admin.');
        }

        $name = PaymentLedger::personName($admin);
        try {
            DB::transaction(fn () => $admin->delete());
        } catch (\Illuminate\Database\QueryException) {
            // Other records still point at this account: keep it, but with no way in
            $admin->forceFill(['is_suspended' => true, 'api_token' => null])->save();

            return response()->json(['success' => true, 'message' => "{$name} is linked to other records, so their access was turned off instead of removing the account."]);
        }

        return response()->json(['success' => true, 'message' => "{$name} was removed. Their past actions stay in the audit log."]);
    }

    /** POST /api/admin/security/admins/{id}/active  { active } */
    public function setAdminActive(Request $request, int $id): JsonResponse
    {
        $admin = $this->findAdmin($id);
        if (!$admin) {
            return $this->notFound();
        }
        $active = $request->validate(['active' => 'required|boolean'])['active'];

        if (!$active) {
            if ($admin->id === $request->user()->id) {
                return $this->refuse("You can't turn off your own access.");
            }
            if ($admin->is_admin_owner) {
                return $this->refuse("The account owner's access can't be turned off.");
            }
            if ($admin->adminRole?->is_system && $this->activeSuperAdmins() <= 1) {
                return $this->refuse('This is the only active super admin.');
            }
        }

        // Turning access off also signs them out
        $admin->forceFill(['is_suspended' => !$active] + ($active ? [] : ['api_token' => null]))->save();

        return response()->json(['success' => true, 'message' => $active ? 'Access turned back on.' : 'Access turned off. They have been signed out.']);
    }

    /** POST /api/admin/security/admins/{id}/password  { password } */
    public function resetAdminPassword(Request $request, int $id): JsonResponse
    {
        $admin = $this->findAdmin($id);
        if (!$admin) {
            return $this->notFound();
        }
        if ($admin->is_admin_owner && $admin->id !== $request->user()->id) {
            return $this->refuse("Only the account owner can change the owner's password.", 403);
        }
        $data = $request->validate(['password' => ['required', 'string', Password::min(10)->letters()->mixedCase()->numbers()]]);

        $admin->forceFill(['password' => Hash::make($data['password'])] + ($admin->id === $request->user()->id ? [] : ['api_token' => null]))->save();

        return response()->json(['success' => true, 'message' => $admin->id === $request->user()->id ? 'Your password was changed.' : 'Password changed. They have been signed out.']);
    }

    // ── Roles ────────────────────────────────────────────────────────────

    /** GET /api/admin/security/roles */
    public function roles(): JsonResponse
    {
        $roles = AdminRole::query()->withCount('admins')->orderByDesc('is_system')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'is_system' => $r->is_system,
                'permissions' => $r->expanded(),
                'admins' => $r->admins_count,
            ]),
            'catalog' => AdminRole::PERMISSIONS,
        ]);
    }

    /** POST /api/admin/security/roles  |  PUT /api/admin/security/roles/{id} */
    public function saveRole(Request $request, ?int $id = null): JsonResponse
    {
        $role = $id ? AdminRole::find($id) : new AdminRole();
        if (!$role) {
            return $this->notFound();
        }
        if ($role->is_system) {
            return $this->refuse('The super admin role always has every permission and can\'t be changed.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('admin_roles', 'name')->ignore($role->id)],
            'description' => 'nullable|string|max:255',
            'permissions' => 'required|array|min:1',
            'permissions.*' => ['string', Rule::in(AdminRole::allKeys())],
        ], ['permissions.min' => 'Pick at least one permission.']);

        $role->fill([
            'name' => trim($data['name']),
            'description' => isset($data['description']) ? trim($data['description']) ?: null : null,
            'permissions' => array_values(array_unique($data['permissions'])),
        ])->save();

        return response()->json(['success' => true, 'message' => 'Role saved.'], $id ? 200 : 201);
    }

    /** DELETE /api/admin/security/roles/{id} */
    public function deleteRole(int $id): JsonResponse
    {
        $role = AdminRole::withCount('admins')->find($id);
        if (!$role) {
            return $this->notFound();
        }
        if ($role->is_system) {
            return $this->refuse('The super admin role can\'t be deleted.');
        }
        if ($role->admins_count > 0) {
            return $this->refuse('Move its administrators to another role first.');
        }
        $role->delete();

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }

    // ── Audit log ────────────────────────────────────────────────────────

    /** GET /api/admin/security/audit */
    public function audit(Request $request): JsonResponse
    {
        $query = AdminAuditLog::query()->latest('created_at')->orderByDesc('id');

        if ($request->filled('admin_id')) {
            $query->where('admin_id', (int) $request->input('admin_id'));
        }
        if ($request->filled('area')) {
            $query->where('action', 'like', $request->input('area') . '%');
        }
        if ($request->input('outcome') === 'refused') {
            $query->where('status_code', '>=', 400);
        } elseif ($request->input('outcome') === 'done') {
            $query->where('status_code', '<', 400);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', CarbonImmutable::parse($request->input('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', CarbonImmutable::parse($request->input('to'))->endOfDay());
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(fn ($q) => $q->whereRaw('lower(admin_name) like ?', [$like])->orWhereRaw('lower(target_label) like ?', [$like]));
        }

        $page = $query->paginate(min(max((int) $request->input('per_page', 25), 10), 100));

        return response()->json([
            'success' => true,
            // No database IDs or API paths: the record is named instead
            'data' => collect($page->items())->map(fn ($l) => [
                'id' => $l->id,
                'admin_name' => $l->admin_name,
                'action' => $l->action,
                'target' => $l->target_label ?? ($l->target_id ? AuditTargets::label($l->action, $l->target_id) : null),
                'had_target' => $l->target_id !== null || $l->target_label !== null,
                'status_code' => $l->status_code,
                'details' => AuditTargets::details($l->details),
                'ip_address' => $l->ip_address,
                'device' => $this->device($l->user_agent),
                'created_at' => $l->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
            'admins' => User::query()->where('role', User::ROLE_ADMIN)->orderBy('name')->get(['id', 'name', 'surname'])
                ->map(fn ($a) => ['id' => $a->id, 'name' => PaymentLedger::personName($a)]),
        ]);
    }

    // ── Sign-ins ─────────────────────────────────────────────────────────

    /** GET /api/admin/security/sign-ins?hours=24 */
    public function signIns(Request $request): JsonResponse
    {
        $hours = in_array((int) $request->input('hours'), [1, 24, 168, 720], true) ? (int) $request->input('hours') : 24;
        $since = now()->subHours($hours);
        $failures = LoginAttempt::query()->where('created_at', '>=', $since);

        $max = (int) PlatformSettings::get('login_max_attempts');
        $window = (int) PlatformSettings::get('login_lockout_minutes');
        $lockedNow = LoginAttempt::query()->where('created_at', '>=', now()->subMinutes($window))->where('reason', '!=', 'locked')
            ->selectRaw('identifier, count(*) as total, max(created_at) as last_at')->groupBy('identifier')->havingRaw('count(*) >= ?', [$max])
            ->get()->map(fn ($r) => ['identifier' => $r->identifier, 'failures' => (int) $r->total, 'last_at' => CarbonImmutable::parse($r->last_at)->toIso8601String()]);

        $successful = DB::table('login_audit_logs')->where('logged_in_at', '>=', $since)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'hours' => $hours,
                'failed' => (clone $failures)->count(),
                'successful' => $successful,
                'by_reason' => (clone $failures)->selectRaw('reason, count(*) as total')->groupBy('reason')->pluck('total', 'reason'),
                'locked_now' => $lockedNow,
                'top_ips' => (clone $failures)->whereNotNull('ip_address')->selectRaw('ip_address, count(*) as total, count(distinct identifier) as accounts')
                    ->groupBy('ip_address')->orderByDesc('total')->limit(6)->get()
                    ->map(fn ($r) => ['ip' => $r->ip_address, 'failures' => (int) $r->total, 'accounts' => (int) $r->accounts, 'suspicious' => $r->accounts >= 3 || $r->total >= $max * 2]),
                'top_accounts' => (clone $failures)->selectRaw('identifier, count(*) as total')->groupBy('identifier')->orderByDesc('total')->limit(6)->get()
                    ->map(fn ($r) => ['identifier' => $r->identifier, 'failures' => (int) $r->total]),
                'recent' => (clone $failures)->latest('created_at')->limit(40)->get()->map(fn ($a) => [
                    'id' => $a->id,
                    'identifier' => $a->identifier,
                    'actor_type' => $a->actor_type,
                    'reason' => $a->reason,
                    'ip' => $a->ip_address,
                    'device' => $this->device($a->user_agent),
                    'at' => $a->created_at?->toIso8601String(),
                ]),
                'admin_sign_ins' => DB::table('login_audit_logs')->where('role', User::ROLE_ADMIN)->orderByDesc('logged_in_at')->limit(10)
                    ->get(['actor_name', 'ip_address', 'browser', 'platform', 'location_label', 'logged_in_at'])
                    ->map(fn ($l) => [
                        'name' => $l->actor_name,
                        'ip' => $l->ip_address,
                        'device' => collect([$l->browser, $l->platform])->filter()->implode(' · ') ?: 'Unknown device',
                        'location' => $l->location_label,
                        'at' => CarbonImmutable::parse($l->logged_in_at)->toIso8601String(),
                    ]),
                'lockout' => ['max_attempts' => $max, 'minutes' => $window],
            ],
        ]);
    }

    // ── Platform settings ────────────────────────────────────────────────

    public function settings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PlatformSettings::all()]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registrations_open' => 'required|boolean',
            'vendor_applications_open' => 'required|boolean',
            'checkout_paused' => 'required|boolean',
            'checkout_pause_message' => 'required|string|min:5|max:200',
            'stale_pending_days' => 'required|integer|min:1|max:30',
            'login_max_attempts' => 'required|integer|min:3|max:20',
            'login_lockout_minutes' => 'required|integer|min:1|max:1440',
            'login_show_attempts' => 'sometimes|boolean',
            'login_ip_per_minute' => 'sometimes|integer|min:10|max:300',
        ]);

        foreach (array_intersect(['registrations_open', 'vendor_applications_open', 'checkout_paused', 'login_show_attempts'], array_keys($data)) as $flag) {
            $data[$flag] = (bool) $data[$flag];
        }
        foreach (array_intersect(['stale_pending_days', 'login_max_attempts', 'login_lockout_minutes', 'login_ip_per_minute'], array_keys($data)) as $number) {
            $data[$number] = (int) $data[$number];
        }

        return response()->json(['success' => true, 'message' => 'Platform settings saved.', 'data' => PlatformSettings::save($data)]);
    }

    // ── System health & API errors ───────────────────────────────────────

    /** GET /api/admin/security/health */
    public function health(): JsonResponse
    {
        $checks = [];
        $add = function ($group, $name, $status, $detail) use (&$checks) {
            $checks[] = compact('group', 'name', 'status', 'detail');
        };

        // Database
        try {
            $t = microtime(true);
            DB::select('select 1');
            $ms = (int) round((microtime(true) - $t) * 1000);
            $add('Core', 'Database', $ms > 800 ? 'warn' : 'ok', DB::connection()->getDriverName() . " · answered in {$ms} ms");
        } catch (\Throwable $e) {
            $add('Core', 'Database', 'fail', 'Not reachable');
        }

        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = collect(File::files(database_path('migrations')))->map(fn ($f) => $f->getFilenameWithoutExtension())->all();
            $pending = count(array_diff($files, $ran));
            $add('Core', 'Database migrations', $pending ? 'warn' : 'ok', $pending ? "{$pending} not run yet" : 'All up to date');
        } catch (\Throwable) {
            $add('Core', 'Database migrations', 'warn', 'Could not be checked');
        }

        try {
            Cache::put('health:ping', 'pong', 10);
            $add('Core', 'Cache', Cache::get('health:ping') === 'pong' ? 'ok' : 'fail', config('cache.default'));
        } catch (\Throwable) {
            $add('Core', 'Cache', 'fail', 'Not working');
        }

        $storageWritable = is_writable(storage_path('logs')) && is_writable(storage_path('framework'));
        $add('Core', 'Server storage', $storageWritable ? 'ok' : 'fail', $storageWritable ? 'Writable' : 'Not writable');
        $free = @disk_free_space(storage_path());
        if ($free !== false) {
            $gb = round($free / 1073741824, 1);
            $add('Core', 'Disk space', $gb < 1 ? 'warn' : 'ok', "{$gb} GB free");
        }
        $logSize = is_file(storage_path('logs/laravel.log')) ? filesize(storage_path('logs/laravel.log')) : 0;
        $add('Core', 'Log file', $logSize > 200 * 1048576 ? 'warn' : 'ok', round($logSize / 1048576, 1) . ' MB');

        // Configuration (only whether things are set, never their values)
        $production = app()->environment('production');
        $add('Configuration', 'Debug mode', config('app.debug') ? ($production ? 'fail' : 'warn') : 'ok',
            config('app.debug') ? 'On: error pages show file paths and stack traces' . ($production ? ' in production' : '') : 'Off');
        $add('Configuration', 'Environment', 'ok', app()->environment());
        $add('Configuration', 'HTTPS app address', str_starts_with((string) config('app.url'), 'https://') || !$production ? 'ok' : 'warn', (string) config('app.url'));

        // Integrations
        $cloudinary = config('cloudinary.cloud_url') || (config('cloudinary.cloud.cloud_name') && config('cloudinary.cloud.api_key'));
        $add('Integrations', 'Cloudinary (images & 3D models)', $cloudinary ? 'ok' : 'fail', $cloudinary ? 'Configured' : 'Not configured: uploads will fail');
        $add('Integrations', 'PayMongo payments', config('services.paymongo.secret_key') ? 'ok' : 'fail', config('services.paymongo.secret_key') ? 'Configured' : 'No secret key: online payments are off');
        $add('Integrations', 'PayMongo webhook', config('services.paymongo.webhook_secret') ? 'ok' : 'warn', config('services.paymongo.webhook_secret') ? 'Signature checking on' : 'No webhook secret set');
        $add('Integrations', 'Email (Brevo)', config('services.brevo.key') ? 'ok' : 'warn', config('services.brevo.key') ? 'Configured' : 'No API key: emails won\'t send');
        $add('Integrations', 'Route distances (OSRM)', config('services.osrm.url') ? 'ok' : 'warn', config('services.osrm.url') ? parse_url((string) config('services.osrm.url'), PHP_URL_HOST) : 'Not set: straight-line distances are used');

        // Recent behaviour
        $errors24 = ApiErrorLog::where('created_at', '>=', now()->subDay())->count();
        $add('Last 24 hours', 'API server errors', $errors24 >= 20 ? 'fail' : ($errors24 ? 'warn' : 'ok'), $errors24 . ' ' . ($errors24 === 1 ? 'error' : 'errors'));
        $failed24 = LoginAttempt::where('created_at', '>=', now()->subDay())->count();
        $add('Last 24 hours', 'Failed sign-ins', $failed24 >= 50 ? 'warn' : 'ok', (string) $failed24);

        return response()->json([
            'success' => true,
            'data' => [
                'checks' => $checks,
                'overall' => collect($checks)->contains('status', 'fail') ? 'fail' : (collect($checks)->contains('status', 'warn') ? 'warn' : 'ok'),
                'versions' => ['php' => PHP_VERSION, 'laravel' => app()->version()],
                'server_time' => now()->toIso8601String(),
                'timezone' => config('app.timezone'),
                'checked_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /** GET /api/admin/security/api-errors?hours=24 */
    public function apiErrors(Request $request): JsonResponse
    {
        $hours = in_array((int) $request->input('hours'), [1, 24, 168, 720], true) ? (int) $request->input('hours') : 24;
        $since = now()->subHours($hours);
        $rows = ApiErrorLog::query()->where('created_at', '>=', $since);

        // Someone quoted a code ("BC-7K2Q9F") to support: find it at any date
        $code = Str::upper(trim((string) $request->input('reference')));
        if ($code !== '') {
            $found = ApiErrorLog::query()->where('reference', Str::startsWith($code, 'BC-') ? $code : "BC-{$code}")->latest('created_at')->limit(5)->get();

            return response()->json(['success' => true, 'data' => ['lookup' => $found->map(fn ($e) => $this->errorRow($e))->values()]]);
        }

        // Errors per hour (or per day for longer spans) for the chart
        $bucket = $hours > 48 ? 'day' : 'hour';
        $series = (clone $rows)->get(['created_at'])->groupBy(fn ($r) => $bucket === 'day' ? $r->created_at->format('Y-m-d') : $r->created_at->format('Y-m-d H:00'))
            ->map->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (clone $rows)->count(),
                'bucket' => $bucket,
                'series' => $series,
                // Grouped by feature, never by API path
                'by_feature' => (clone $rows)->get(['method', 'path', 'action', 'created_at'])
                    ->groupBy(fn ($e) => $this->feature($e))
                    ->map(fn ($group, $feature) => ['feature' => $feature, 'total' => $group->count(), 'last_at' => $group->max('created_at')->toIso8601String()])
                    ->sortByDesc('total')->take(10)->values(),
                // Shown to people as a plain message instead of the programmer error
                'shielded' => (clone $rows)->whereNotNull('reference')->count(),
                'recent' => (clone $rows)->latest('created_at')->limit(50)->get()->map(fn ($e) => $this->errorRow($e)),
            ],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** One error for the admin list: the technical side, and what the person saw */
    private function errorRow(ApiErrorLog $e): array
    {
        return [
            'id' => $e->id,
            'reference' => $e->reference,
            'feature' => $this->feature($e),
            'status' => $e->status_code,
            'exception' => $e->exception ? class_basename($e->exception) : null,
            'message' => ApiAreas::cleanMessage($e->message),
            'location' => $e->location,
            'shown' => $e->shown_message,
            'account' => $e->account_type,
            'account_name' => $e->account_name,
            'duration_ms' => $e->duration_ms,
            'at' => $e->created_at?->toIso8601String(),
        ];
    }

    private function feature(ApiErrorLog $e): string
    {
        return ApiAreas::fromAction($e->action) ?? ApiAreas::fromPath($e->method, $e->path);
    }

    private function findAdmin(int $id): ?User
    {
        return User::query()->where('role', User::ROLE_ADMIN)->with('adminRole')->find($id);
    }

    private function activeSuperAdmins(): int
    {
        return User::query()->where('role', User::ROLE_ADMIN)
            ->where(fn ($q) => $q->where('is_suspended', false)->orWhereNull('is_suspended'))
            ->whereIn('admin_role_id', AdminRole::where('is_system', true)->select('id'))
            ->count();
    }

    private function device(?string $agent): string
    {
        $agent = (string) $agent;
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            $agent === '' => null,
            default => 'Other',
        };
        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return collect([$browser, $os])->filter()->implode(' · ') ?: 'Unknown';
    }

    private function refuse(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    /** The signed-in admin is the account owner */
    private function isOwner(Request $request): bool
    {
        return (bool) $request->user()?->is_admin_owner;
    }

    /** No other account uses this value, ignoring capitals */
    private function freeIgnoringCase(string $column, int $exceptId, string $message): \Closure
    {
        return function ($attribute, $value, $fail) use ($column, $exceptId, $message) {
            if (User::query()->whereKeyNot($exceptId)->whereRaw("lower({$column}) = ?", [mb_strtolower(trim((string) $value))])->exists()) {
                $fail($message);
            }
        };
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Not found.'], 404);
    }
}
