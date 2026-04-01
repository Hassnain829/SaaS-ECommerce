<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('signin');
        }

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        if (empty($roles)) {
            abort(403, 'Store role middleware requires at least one role.');
        }

        $invalidRoles = array_filter($roles, fn (string $role): bool => ! Store::isValidMemberRole($role));

        if (! empty($invalidRoles)) {
            abort(500, 'One or more invalid store roles were provided to the route middleware.');
        }

        $currentStore = $request->attributes->get('currentStore');

        if (! $currentStore) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found. Please choose or create a store first.']);
        }

        if (! $user->hasStoreRole($currentStore, $roles)) {
            abort(403, 'You are not authorized to perform this action in the current store.');
        }

        return $next($request);
    }
}
