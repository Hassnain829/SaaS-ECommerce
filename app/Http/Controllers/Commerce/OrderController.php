<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ManualOrderPaymentService;
use App\Services\OrderEventRecorder;
use App\Services\SecurityLogRecorder;
use App\Support\OrderLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function storeNote(Request $request, Order $order, OrderEventRecorder $events): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        if ((int) $order->store_id !== (int) $store->id) {
            abort(404);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $events->record(
            $order,
            OrderLifecycle::EVENT_ORDER_NOTE_ADDED,
            'Note added',
            $validated['body'],
            [],
            $request->user()
        );

        app(SecurityLogRecorder::class)->record(
            $request,
            'order_note_added',
            store: $store,
            metadata: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]
        );

        return redirect()
            ->route('orderViewDetails', $order)
            ->with('success', 'Order note added.');
    }

    public function recordPayment(Request $request, Order $order, ManualOrderPaymentService $payments): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        if ((int) $order->store_id !== (int) $store->id) {
            abort(404);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(array_keys(ManualOrderPaymentService::methods()))],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ], [
            'payment_method.required' => 'Choose how this payment was collected.',
            'payment_method.in' => 'Choose how this payment was collected.',
        ]);

        $payments->record(
            $order,
            $request->user(),
            $validated['payment_method'],
            $validated['payment_reference'] ?? null,
        );

        app(SecurityLogRecorder::class)->record(
            $request,
            'manual_payment_recorded',
            store: $store,
            metadata: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_method' => $validated['payment_method'],
            ]
        );

        return redirect()
            ->route('orderViewDetails', $order)
            ->with('success', 'Payment recorded.');
    }
}
