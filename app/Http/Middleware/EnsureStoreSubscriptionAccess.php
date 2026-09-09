<?php

namespace App\Http\Middleware;

use App\Services\Billing\StoreSubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreSubscriptionAccess
{
    /**
     * Route names merchants may still reach when store access is expired/suspended.
     *
     * @var list<string>
     */
    public const ALLOWED_ROUTE_NAMES = [
        'logout',
        'profileSettings',
        'profile.update',
        'profile.password.update',
        'profile.deactivate',
        'store.access-expired',
        'password.confirm',
        'password.confirm.store',
        'verification.notice',
        'verification.verify',
        'verification.send',
        'current-store.update',
    ];

    public function __construct(
        protected StoreSubscriptionService $subscriptions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasRole('admin')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if (is_string($routeName) && $this->isAllowedRoute($request, $routeName)) {
            return $next($request);
        }

        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return $next($request);
        }

        if ($this->subscriptions->isAccessible($store)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Store access has expired. Contact the platform administrator.',
            ], 403);
        }

        return redirect()->route('store.access-expired');
    }

    protected function isAllowedRoute(Request $request, string $routeName): bool
    {
        if (in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return true;
        }

        // Profile settings now live on General Settings → Account tab.
        if ($routeName === 'generalSettings' && $request->query('tab') === 'account') {
            return true;
        }

        return false;
    }
}
