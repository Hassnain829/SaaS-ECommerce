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
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $store = $request->attributes->get('currentStore');

        $status = (string) $request->query('status', 'all');
        if ($status !== 'all' && ! in_array($status, OrderLifecycle::orderStatuses(), true)) {
            $status = 'all';
        }
        $search = trim((string) $request->query('q', ''));

        $query = Order::query()
            ->where('store_id', $store->id)
            ->with('customer:id,full_name,email');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('external_order_number', 'like', '%'.$search.'%')
                    ->orWhere('payment_reference', 'like', '%'.$search.'%')
                    ->orWhere('customer_email', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                        $customerQuery->where('full_name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        $orders = $query
            ->orderByDesc('placed_at')
            ->orderByDesc('created_at')
            ->get();

        app(SecurityLogRecorder::class)->record(
            $request,
            'orders_exported',
            store: $store,
            metadata: [
                'row_count' => $orders->count(),
                'status' => $status,
                'search' => $search !== '' ? $search : null,
            ]
        );

        $filename = 'orders-'.$store->id.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($orders): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'order_id',
                'order_number',
                'status',
                'payment_status',
                'fulfillment_status',
                'customer_name',
                'customer_email',
                'currency',
                'subtotal',
                'total',
                'grand_total',
                'item_count',
                'order_source',
                'channel',
                'placed_at',
                'created_at',
            ]);

            foreach ($orders as $order) {
                fputcsv($out, [
                    $order->id,
                    $order->order_number,
                    $order->status,
                    $order->payment_status,
                    $order->fulfillment_status,
                    $order->customer?->full_name,
                    $order->customer_email ?: $order->customer?->email,
                    $order->currency_code,
                    $order->subtotal,
                    $order->total,
                    $order->grand_total,
                    $order->item_count,
                    $order->order_source,
                    $order->channel,
                    optional($order->placed_at)?->toIso8601String(),
                    optional($order->created_at)?->toIso8601String(),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

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
