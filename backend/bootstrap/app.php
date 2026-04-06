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
        $middleware->prepend(HandleCors::class);

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
            'token.auth'        => \App\Http\Middleware\TokenAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
