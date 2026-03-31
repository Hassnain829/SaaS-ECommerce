<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentStore
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasRole('admin')) {
            return $next($request);
        }

        $availableStores = $this->resolveAvailableStores($user);

        $currentStore = $this->resolveCurrentStore($request, $availableStores);

        $request->attributes->set('currentStore', $currentStore);
        $request->attributes->set('availableStores', $availableStores);

        view()->share('currentStore', $currentStore);
        view()->share('availableStores', $availableStores);

        return $next($request);
    }

    /**
     * Resolve accessible stores and backfill legacy owner stores into memberships when needed.
     */
    protected function resolveAvailableStores($user): Collection
    {
        $availableStores = $user->memberStores()
            ->orderBy('stores.name')
            ->get();

        if ($availableStores->isNotEmpty()) {
            return $availableStores;
        }

        $legacyOwnedStoreIds = $user->stores()->pluck('id');

        if ($legacyOwnedStoreIds->isEmpty()) {
            return $availableStores;
        }

        $user->memberStores()->syncWithoutDetaching(
            $legacyOwnedStoreIds
                ->mapWithKeys(fn (int $storeId): array => [$storeId => ['role' => 'owner']])
                ->all()
        );

        return $user->memberStores()
            ->orderBy('stores.name')
            ->get();
    }

    /**
     * Resolve the active store from session or fallback to the first accessible store.
     */
    protected function resolveCurrentStore(Request $request, Collection $availableStores)
    {
        if ($availableStores->isEmpty()) {
            $request->session()->forget('current_store_id');

            return null;
        }

        $sessionStoreId = $request->session()->get('current_store_id');

        $currentStore = $availableStores->firstWhere('id', (int) $sessionStoreId);

        if (! $currentStore) {
            $currentStore = $availableStores->first();
            $request->session()->put('current_store_id', $currentStore->id);
        }

        return $currentStore;
    }
}
