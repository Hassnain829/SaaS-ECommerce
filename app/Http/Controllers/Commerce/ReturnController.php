<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\Returns\ReturnRestockService;
use App\Services\ReturnService;
use App\Support\ReturnLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    public function store(Request $request, Order $order, ReturnService $returnService): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $order->store_id === (int) $store->id, 404);

        $validated = $request->validate([
            'return_reason_id' => ['nullable', 'integer'],
            'merchant_notes' => ['nullable', 'string', 'max:2000'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'manual_instructions' => ['nullable', 'string', 'max:2000'],
            'tracking_reference' => ['nullable', 'string', 'max:191'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable'],
        ]);

        $returnService->requestReturn($order, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Return recorded.')
            ->with('success_title', 'Return created');
    }

    public function approve(Request $request, OrderReturn $orderReturn, ReturnService $returnService): RedirectResponse
    {
        $this->authorizeReturn($request, $orderReturn);

        $validated = $request->validate([
            'merchant_notes' => ['nullable', 'string', 'max:2000'],
            'manual_instructions' => ['nullable', 'string', 'max:2000'],
            'tracking_reference' => ['nullable', 'string', 'max:191'],
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $returnService->approve($orderReturn, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Return approved.')
            ->with('success_title', 'Return updated');
    }

    public function reject(Request $request, OrderReturn $orderReturn, ReturnService $returnService): RedirectResponse
    {
        $this->authorizeReturn($request, $orderReturn);

        $validated = $request->validate([
            'merchant_notes' => ['nullable', 'string', 'max:2000'],
            'rejection_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $returnService->reject($orderReturn, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Return rejected.')
            ->with('success_title', 'Return updated');
    }

    public function receive(Request $request, OrderReturn $orderReturn, ReturnService $returnService): RedirectResponse
    {
        $this->authorizeReturn($request, $orderReturn);

        $validated = $request->validate([
            'merchant_notes' => ['nullable', 'string', 'max:2000'],
            'tracking_reference' => ['nullable', 'string', 'max:191'],
            'items' => ['nullable', 'array'],
            'items.*.received_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.condition' => ['nullable', 'string', Rule::in(ReturnLifecycle::conditions())],
            'items.*.restock' => ['nullable', 'boolean'],
            'items.*.restock_location_id' => ['nullable', 'integer'],
        ]);

        $returnService->receive($orderReturn, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Return marked as received.')
            ->with('success_title', 'Return received');
    }

    public function complete(Request $request, OrderReturn $orderReturn, ReturnService $returnService): RedirectResponse
    {
        $this->authorizeReturn($request, $orderReturn);

        $validated = $request->validate([
            'merchant_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $returnService->complete($orderReturn, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Return completed.')
            ->with('success_title', 'Return closed');
    }

    public function cancel(Request $request, OrderReturn $orderReturn, ReturnService $returnService): RedirectResponse
    {
        $this->authorizeReturn($request, $orderReturn);

        $validated = $request->validate([
            'merchant_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $returnService->cancel($orderReturn, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Return cancelled.')
            ->with('success_title', 'Return updated');
    }

    public function restock(Request $request, OrderReturn $orderReturn, ReturnRestockService $restockService): RedirectResponse
    {
        $this->authorizeReturn($request, $orderReturn);

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.restock' => ['nullable', 'boolean'],
            'items.*.condition' => ['nullable', 'string', Rule::in(ReturnLifecycle::conditions())],
            'items.*.restock_location_id' => ['nullable', 'integer'],
        ]);

        $restockService->restockReturn($orderReturn, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Return restock updated.')
            ->with('success_title', 'Inventory updated');
    }

    private function authorizeReturn(Request $request, OrderReturn $return): void
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $return->store_id === (int) $store->id, 404);
    }
}
