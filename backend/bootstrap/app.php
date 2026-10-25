<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'api/*',
            'storage/*',
        ]);

        $middleware->alias([
            'auth'              => \App\Http\Middleware\Authenticate::class,
            'cors'              => HandleCors::class,
            'admin'             => \App\Http\Middleware\AdminMiddleware::class,
            'role'              => \App\Http\Middleware\CheckRole::class,
            'vendor'            => \App\Http\Middleware\EnsureUserIsVendor::class,
            'paymongo.webhook'  => \App\Http\Middleware\VerifyPayMongoWebhook::class,
            'finance.manager'   => \App\Http\Middleware\CheckFinanceManager::class,
            'assignment'        => \App\Http\Middleware\CheckActiveAssignment::class,
            'employee.module'   => \App\Http\Middleware\EnsureEmployeeModuleAccess::class,
            'employee.leave-review' => \App\Http\Middleware\EnsureEmployeeLeaveReviewAccess::class,
            'subscription.module' => \App\Http\Middleware\EnsureSubscriptionModuleAccess::class,
            'resource.limit'    => \App\Http\Middleware\EnsureResourceLimit::class,
            'token.auth'        => \App\Http\Middleware\TokenAuth::class,
            'admin.can'         => \App\Http\Middleware\EnsureAdminPermission::class,
            'admin.audit'       => \App\Http\Middleware\AuditAdminAction::class,
        ]);

        // Keep a record of server errors the API returns (Security → API errors)
        $middleware->api(append: [\App\Http\Middleware\LogApiErrors::class]);

        // Programmer errors never reach people; they get a plain message and a code
        $middleware->api(append: [\App\Http\Middleware\FriendlyApiErrors::class]);

        // Staff & operations → Activity logs: what employees change
        $middleware->api(append: [\App\Http\Middleware\LogStaffActivity::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API errors in plain words, even with APP_DEBUG on. The full error
        // still goes to the Laravel log and, by its code, to the admin log.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }
            // A ready-made answer (rate limits, refusals from middleware) is not an error
            if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                return $e->getResponse();
            }
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json(['success' => false, 'code' => 'unauthenticated', 'message' => \App\Support\FriendlyErrors::forStatus(401)], 401);
            }
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json(['success' => false, 'message' => \App\Support\FriendlyErrors::forStatus(404)], 404);
            }
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $e->getStatusCode() < 500) {
                $status = $e->getStatusCode();
                $message = trim($e->getMessage());
                $readable = $message !== '' && ! \App\Support\FriendlyErrors::isTechnical($message) && ! \App\Support\FriendlyErrors::isFrameworkDefault($message);

                return response()->json([
                    'success' => false,
                    'message' => $readable ? $message : (\App\Support\FriendlyErrors::forStatus($status) ?? 'That request could not be completed.'),
                ], $status, $e->getHeaders());
            }

            \App\Support\FriendlyErrors::remember($request, $e->getMessage(), $e);
            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getStatusCode() : 500;

            return response()->json([
                'success' => false,
                'code' => 'server_error',
                'message' => $status === 503 ? \App\Support\FriendlyErrors::forStatus(503) : \App\Support\FriendlyErrors::message($request, $e),
                'reference' => \App\Support\FriendlyErrors::reference($request),
            ], $status);
        });
    })
    ->create();
