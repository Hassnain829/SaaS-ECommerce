<?php

use App\Http\Middleware\AuthenticateDeveloperStorefrontToken;
use App\Http\Middleware\EnsureCurrentStore;
use App\Http\Middleware\EnsureStorePermission;
use App\Http\Middleware\EnsureStoreRole;
use App\Http\Middleware\RecordUserSession;
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
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'current.store' => EnsureCurrentStore::class,
            'store.permission' => EnsureStorePermission::class,
            'store.role' => EnsureStoreRole::class,
            'role' => RoleMiddleware::class,
            'dev.storefront.token' => AuthenticateDeveloperStorefrontToken::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        ]);

        $middleware->web(append: [
            RecordUserSession::class,
        ]);

        // Password-reset and verification absolute URLs must only trust configured hosts.
        $middleware->trustHosts(at: function (): array {
            $configured = array_values(array_filter(array_map(
                static fn ($host) => is_string($host) ? trim($host) : '',
                (array) config('app.trusted_hosts', [])
            )));

            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

            return array_values(array_unique(array_filter([
                is_string($appHost) ? $appHost : null,
                'localhost',
                '127.0.0.1',
                ...$configured,
            ])));
        });

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
