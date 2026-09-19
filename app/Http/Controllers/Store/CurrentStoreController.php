<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
            'redirect_to' => ['nullable', 'string', 'in:dashboard,orders,order,products,locations,taxes,delivery'],
            'order_id' => ['nullable', 'integer', 'required_if:redirect_to,order'],
        ]);

        $store = $request->user()
            ->activeMemberStores()
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

        $redirectTo = $validated['redirect_to'] ?? null;
        $switched = "Switched to store '{$store->name}'.";

        return match ($redirectTo) {
            'dashboard' => redirect()->route('dashboard')->with('success', $switched),
            'orders' => redirect()->route('orders')->with('success', $switched),
            'products' => redirect()->route('products')->with('success', $switched),
            'locations' => redirect()->route('settings.locations.index')->with('success', $switched),
            'taxes' => redirect()->route('settings.taxes.index')->with('success', $switched),
            'delivery' => redirect()->route('shippingAutomation')->with('success', $switched),
            'order' => redirect()
                ->route('orderViewDetails', Order::query()
                    ->where('store_id', $store->id)
                    ->whereKey((int) $validated['order_id'])
                    ->firstOrFail())
                ->with('success', $switched),
            default => back()->with('success', $switched),
        };
    }
}
