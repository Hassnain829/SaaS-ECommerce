<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\MerchantCutoverService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MerchantCutoverController extends Controller
{
    public function acknowledge(Request $request, MerchantCutoverService $cutovers): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $validated = $request->validate([
            'acknowledgement' => ['required', 'string', 'in:backup,import_exceptions,tax_off,cache,rollback,woo_archive'],
        ]);

        $cutovers->acknowledge($store, $request->user(), $validated['acknowledgement']);

        app(SecurityLogRecorder::class)->record(
            $request,
            'website_cutover_acknowledged',
            store: $store,
            metadata: ['acknowledgement' => $validated['acknowledgement']]
        );

        return redirect()
            ->route('developer-storefront.settings')
            ->with('success', 'Saved. This is your confirmation, not a proof that WordPress itself changed.');
    }

    public function activate(Request $request, MerchantCutoverService $cutovers): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $cutover = $cutovers->activate($store, $request->user());

        app(SecurityLogRecorder::class)->record(
            $request,
            'website_cutover_activated',
            store: $store,
            metadata: [
                'cutover_id' => $cutover->id,
                'smoke_order_id' => $cutover->smoke_order_id,
            ]
        );

        return redirect()
            ->route('developer-storefront.settings')
            ->with('success', 'This store is marked live for the connected WordPress website. Keep the WooCommerce backup until the rollback period ends.');
    }

    public function rollback(Request $request, MerchantCutoverService $cutovers): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $cutover = $cutovers->rollback($store, $request->user());

        app(SecurityLogRecorder::class)->record(
            $request,
            'website_cutover_rolled_back',
            store: $store,
            metadata: ['cutover_id' => $cutover->id]
        );

        return redirect()
            ->route('developer-storefront.settings')
            ->with('success', 'Go-live is marked rolled back in this portal. WordPress files, plugins, and WooCommerce data were not changed or deleted.');
    }
}
