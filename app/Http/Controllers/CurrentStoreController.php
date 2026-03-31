<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentStoreController extends Controller
{
    /**
     * Update the current store in session.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer'],
        ]);

        $store = $request->user()
            ->memberStores()
            ->where('stores.id', (int) $validated['store_id'])
            ->firstOrFail();

        $request->session()->put('current_store_id', $store->id);

        return back()->with('success', "Switched to store '{$store->name}'.");
    }
}
