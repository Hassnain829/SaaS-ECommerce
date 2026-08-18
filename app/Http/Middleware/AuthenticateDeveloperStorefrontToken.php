<?php

namespace App\Http\Middleware;

use App\Models\ConnectedSite;
use App\Models\Store;
use App\Services\ConnectedSiteService;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeveloperStorefrontToken
{
    public function __construct(
        private readonly ConnectedSiteService $connectedSites,
        private readonly RateLimiter $rateLimiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            $this->fail($request, 'missing_token');

            return response()->json(['message' => 'Missing or invalid Bearer token.'], 401);
        }

        $throttleKey = 'connected-site-auth:'.$request->ip();
        if ($this->rateLimiter->tooManyAttempts($throttleKey, 30)) {
            return response()->json(['message' => 'Too many failed connection attempts. Try again shortly.'], 429);
        }

        $site = $this->connectedSites->resolveActiveByPlainToken($token);
        $store = $site?->store;

        if ($store === null) {
            $this->rateLimiter->hit($throttleKey, 60);
            $this->fail($request, 'invalid_token');

            return response()->json(['message' => 'Invalid storefront token.'], 401);
        }

        if ($site instanceof ConnectedSite) {
            $required = $this->connectedSites->requiredScopeForRequest($request);
            if (! $site->hasScope($required)) {
                $this->fail($request, 'missing_scope', $store instanceof Store ? $store : null);

                return response()->json(['message' => 'This connection is not allowed to perform that action.'], 403);
            }

            $this->connectedSites->assertSiteBinding($site, $request);
            $this->connectedSites->observeAuthenticatedRequest($site, $request);
            $request->attributes->set('connectedSite', $site);
        }

        $request->attributes->set('developerStorefrontStore', $store);
        $this->rateLimiter->clear($throttleKey);

        return $next($request);
    }

    private function fail(Request $request, string $reason, ?Store $store = null): void
    {
        $this->connectedSites->recordAuthFailure($request, $reason, $store);
    }
}
