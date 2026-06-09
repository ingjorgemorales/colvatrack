<?php

use App\Http\Middleware\AuditImportantAction;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureLocationEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MustChangePassword;
use App\Http\Middleware\NoCache;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [NoCache::class, HandleInertiaRequests::class]);
        $middleware->alias([
            'audit' => AuditImportantAction::class,
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
            'location.enabled' => EnsureLocationEnabled::class,
            'must.change.password' => MustChangePassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

