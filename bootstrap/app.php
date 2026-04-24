<?php

use App\Http\Middleware\AuthenticateDeveloperStorefrontToken;
use App\Http\Middleware\EnsureCurrentStore;
use App\Http\Middleware\EnsureStoreRole;
use App\Http\Middleware\RoleMiddleware;
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
        $middleware->alias([
            'current.store' => EnsureCurrentStore::class,
            'store.role' => EnsureStoreRole::class,
            'role' => RoleMiddleware::class,
            'dev.storefront.token' => AuthenticateDeveloperStorefrontToken::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('signin'));
        $middleware->redirectUsersTo(function ($request) {
            $user = $request->user();

            if (! $user) {
                return route('signin');
            }

            return $user->role?->name === 'admin'
                ? route('admin-dashboard')
                : route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
