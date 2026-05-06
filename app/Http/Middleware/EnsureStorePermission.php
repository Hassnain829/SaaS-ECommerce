<?php

namespace App\Http\Middleware;

use App\Support\StorePermission;
use App\Support\StorePermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStorePermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('signin');
        }

        if ($permissions === []) {
            abort(403, 'Store permission middleware requires at least one permission.');
        }

        $invalid = array_filter($permissions, static fn (string $permission): bool => ! StorePermission::exists($permission));
        if ($invalid !== []) {
            abort(500, 'One or more invalid store permissions were provided to the route middleware.');
        }

        $currentStore = $request->attributes->get('currentStore');
        if (! $currentStore) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found. Please choose or create a store first.']);
        }

        if (! StorePermissionResolver::userCanAny($user, $currentStore, $permissions)) {
            abort(403, 'You are not authorized to perform this action in the current store.');
        }

        return $next($request);
    }
}
