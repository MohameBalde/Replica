<?php

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
        $middleware->alias([
            'store' => \App\Http\Middleware\EnsureUserHasStore::class,
            'store.available' => \App\Http\Middleware\EnsureStoreIsAvailable::class,
            'team' => \App\Http\Middleware\EnsureUserIsTeam::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('admin.home'));
        // Proxys de confiance : config/trustedproxy.php (variable TRUSTED_PROXIES).
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
