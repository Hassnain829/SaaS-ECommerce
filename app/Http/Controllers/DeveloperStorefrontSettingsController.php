<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeveloperStorefrontSettingsController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $store->refresh();

        return view('user_view.developer_storefront', [
            'selectedStore' => $store,
            'tokenConfigured' => $store->hasDeveloperStorefrontToken(),
            'tokenCreatedAt' => $store->developer_storefront_token_created_at,
            'plainToken' => $request->session()->pull('developer_storefront_plain_token'),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $plain = 'baa_dev_'.Str::lower(Str::random(40));
        $hash = hash('sha256', $plain);

        Store::query()->whereKey($store->id)->update([
            'developer_storefront_token_hash' => $hash,
            'developer_storefront_token_created_at' => now(),
        ]);

        return redirect()
            ->route('developer-storefront.settings')
            ->with('success', 'A new developer storefront token was generated. Copy it now; it will not be shown again.')
            ->with('developer_storefront_plain_token', $plain);
    }

    public function revoke(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        Store::query()->whereKey($store->id)->update([
            'developer_storefront_token_hash' => null,
            'developer_storefront_token_created_at' => null,
        ]);

        return redirect()
            ->route('developer-storefront.settings')
            ->with('success', 'The developer storefront token was revoked.');
    }
}
