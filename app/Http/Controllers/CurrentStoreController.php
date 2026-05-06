<?php

namespace App\Http\Controllers;

use App\Services\SecurityLogRecorder;
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

        $previousStoreId = $request->session()->get('current_store_id');
        $request->session()->put('current_store_id', $store->id);

        app(SecurityLogRecorder::class)->record(
            $request,
            'store_switch',
            store: $store,
            metadata: [
                'previous_store_id' => $previousStoreId ? (int) $previousStoreId : null,
                'new_store_id' => $store->id,
                'new_store_name' => $store->name,
            ]
        );

        return back()->with('success', "Switched to store '{$store->name}'.");
    }
}
