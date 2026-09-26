<?php

use App\Http\Middleware\CheckModuleAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LockRecordChanges;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ────────────────────────────────────────────────
        // Web middleware (Laravel default + Inertia)
        // ────────────────────────────────────────────────
        $middleware->web(append: [
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // ────────────────────────────────────────────────
        // ⭐ Middleware Aliases — RBAC + Module Access
        // ────────────────────────────────────────────────
        $middleware->alias([
            'role'       => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'module'     => CheckModuleAccess::class,
            'lock.records' => LockRecordChanges::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();