<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Exchange;
use App\Models\Order;
use App\Services\ExchangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExchangeController extends Controller
{
    public function store(Request $request, Order $order, ExchangeService $exchangeService): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $order->store_id === (int) $store->id, 404);

        $validated = $request->validate([
            'order_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'replacement_variant_id' => ['required', 'integer'],
            'return_id' => ['nullable', 'integer'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exchange = $exchangeService->createExchange($order, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Exchange '.$exchange->exchange_number.' created.')
            ->with('success_title', 'Exchange started');
    }

    public function complete(Request $request, Exchange $exchange, ExchangeService $exchangeService): RedirectResponse
    {
        $this->authorizeExchange($request, $exchange);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exchangeService->complete($exchange, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Exchange completed.')
            ->with('success_title', 'Exchange updated');
    }

    public function collect(Request $request, Exchange $exchange, ExchangeService $exchangeService): RedirectResponse
    {
        $this->authorizeExchange($request, $exchange);

        $validated = $request->validate([
            'collected_amount' => ['required', 'numeric', 'min:0'],
            'collection_method' => ['required', 'string', 'in:manual,external'],
            'collection_reference' => ['required', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $exchangeService->recordCollection($exchange, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Collection recorded for this exchange.')
            ->with('success_title', 'Exchange updated');
    }

    public function cancel(Request $request, Exchange $exchange, ExchangeService $exchangeService): RedirectResponse
    {
        $this->authorizeExchange($request, $exchange);
        $exchangeService->cancel($exchange, $request->user(), $request);

        return back()
            ->with('success', 'Exchange cancelled.')
            ->with('success_title', 'Exchange updated');
    }

    private function authorizeExchange(Request $request, Exchange $exchange): void
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $exchange->store_id === (int) $store->id, 404);
    }
}
