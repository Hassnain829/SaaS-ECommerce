<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderEventRecorder;
use App\Services\SecurityLogRecorder;
use App\Support\OrderLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
}
