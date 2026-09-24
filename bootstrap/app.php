<?php

use App\Http\Middleware\EnsureOwnRecordVisibility;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: []);
        $middleware->appendToGroup('web', SecurityHeaders::class);
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'own-records' => EnsureOwnRecordVisibility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Central exception handling can be extended here.
    })->create();
