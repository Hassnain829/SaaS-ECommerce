<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function store(Request $request, Order $order, RefundService $refundService): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $order->store_id === (int) $store->id, 404);

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_tax_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'other_amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'return_id' => ['nullable', 'integer'],
            'processed_externally' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable'],
        ]);

        $validated['processed_externally'] = $request->boolean('processed_externally', true);

        $refund = $refundService->refundOrder($order, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Refund '.$refund->refund_number.' recorded.')
            ->with('success_title', 'Refund processed');
    }

    public function recheck(Request $request, Order $order, Refund $refund, RefundService $refundService): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $order->store_id === (int) $store->id, 404);
        abort_unless((int) $refund->order_id === (int) $order->id && (int) $refund->store_id === (int) $store->id, 404);

        $refund = $refundService->recheckOrRetryRefund($refund, $request->user(), $request);

        $message = match ($refund->status) {
            \App\Support\RefundLifecycle::STATUS_SUCCEEDED => 'Refund '.$refund->refund_number.' is complete.',
            \App\Support\RefundLifecycle::STATUS_FAILED => 'Refund '.$refund->refund_number.' still needs attention.',
            default => 'Refund '.$refund->refund_number.' was rechecked.',
        };

        return back()
            ->with('success', $message)
            ->with('success_title', 'Refund updated');
    }
}
