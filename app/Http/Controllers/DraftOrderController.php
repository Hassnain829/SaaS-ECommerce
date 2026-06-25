<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DraftOrder;
use App\Models\ProductVariant;
use App\Services\Draft\DraftTaxService;
use App\Services\DraftOrderService;
use App\Services\ManualOrderConversionService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DraftOrderController extends Controller
{
    public function create(Request $request): View
    {
        $store = $request->attributes->get('currentStore');

        return view('user_view.draft_order_create', [
            'selectedStore' => $store,
            'customers' => $this->customers($store),
            'variants' => $this->variants($store),
        ]);
    }

    public function store(Request $request, DraftOrderService $draftOrderService): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');

        $draft = $draftOrderService->create($store, $request->user(), $this->validatedDraftPayload($request, $store->id));

        app(SecurityLogRecorder::class)->record(
            $request,
            'manual_draft_created',
            store: $store,
            metadata: [
                'draft_order_id' => $draft->id,
                'draft_number' => $draft->draft_number,
            ]
        );

        return redirect()
            ->route('draft-orders.show', $draft)
            ->with('success', 'Draft order saved.');
    }

    public function show(Request $request, DraftOrder $draftOrder): View
    {
        $store = $request->attributes->get('currentStore');
        $this->assertDraftBelongsToStore($draftOrder, $store->id);

        return view('user_view.draft_order_show', [
            'selectedStore' => $store,
            'draftOrder' => $draftOrder->load(['customer.addresses', 'items.variant.product', 'convertedOrder']),
            'customers' => $this->customers($store),
            'variants' => $this->variants($store),
        ]);
    }

    public function update(Request $request, DraftOrder $draftOrder, DraftOrderService $draftOrderService): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertDraftBelongsToStore($draftOrder, $store->id);

        $draftOrderService->update($draftOrder, $this->validatedDraftPayload($request, $store->id, requireCustomer: false));

        app(SecurityLogRecorder::class)->record(
            $request,
            'manual_draft_updated',
            store: $store,
            metadata: [
                'draft_order_id' => $draftOrder->id,
                'draft_number' => $draftOrder->draft_number,
            ]
        );

        return redirect()
            ->route('draft-orders.show', $draftOrder)
            ->with('success', 'Draft order updated.');
    }

    public function convert(
        Request $request,
        DraftOrder $draftOrder,
        DraftOrderService $draftOrderService,
        ManualOrderConversionService $conversionService
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        $this->assertDraftBelongsToStore($draftOrder, $store->id);

        if ($request->has('items')) {
            $draftOrder = $draftOrderService->update(
                $draftOrder,
                $this->validatedDraftPayload($request, $store->id, requireCustomer: false)
            );
        }

        $order = $conversionService->convert($draftOrder, $store, $request->user());

        app(SecurityLogRecorder::class)->record(
            $request,
            'manual_draft_converted',
            store: $store,
            metadata: [
                'draft_order_id' => $draftOrder->id,
                'draft_number' => $draftOrder->draft_number,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]
        );

        return redirect()
            ->route('orderViewDetails', $order)
            ->with('success', 'Manual order created.');
    }

    public function calculateTax(
        Request $request,
        DraftOrder $draftOrder,
        DraftOrderService $draftOrderService,
        DraftTaxService $draftTaxService
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        $this->assertDraftBelongsToStore($draftOrder, $store->id);

        $payload = $this->validatedDraftPayload($request, $store->id, requireCustomer: false);

        DB::transaction(function () use ($draftOrder, $draftOrderService, $draftTaxService, $store, $payload): void {
            $draft = $draftOrderService->update($draftOrder, $payload);
            $draftTaxService->calculate($draft, $store);
        });

        app(SecurityLogRecorder::class)->record(
            $request,
            'manual_draft_tax_calculated',
            store: $store,
            metadata: [
                'draft_order_id' => $draftOrder->id,
                'draft_number' => $draftOrder->draft_number,
            ]
        );

        return redirect()
            ->route('draft-orders.show', $draftOrder)
            ->with('success', 'Draft saved and tax calculated from store settings.');
    }

    public function cancel(Request $request, DraftOrder $draftOrder, DraftOrderService $draftOrderService): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertDraftBelongsToStore($draftOrder, $store->id);

        $draftOrderService->cancel($draftOrder);

        app(SecurityLogRecorder::class)->record(
            $request,
            'manual_draft_cancelled',
            store: $store,
            metadata: [
                'draft_order_id' => $draftOrder->id,
                'draft_number' => $draftOrder->draft_number,
            ]
        );

        return redirect()
            ->route('draft-orders.show', $draftOrder)
            ->with('success', 'Draft order cancelled.');
    }

    public function destroy(Request $request, DraftOrder $draftOrder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertDraftBelongsToStore($draftOrder, $store->id);

        if ($draftOrder->status === DraftOrder::STATUS_CONVERTED) {
            return back()->withErrors([
                'draft_order' => 'Converted draft orders are linked to their created order and cannot be deleted.',
            ]);
        }

        if (! in_array($draftOrder->status, [DraftOrder::STATUS_DRAFT, DraftOrder::STATUS_CANCELLED], true)) {
            return back()->withErrors([
                'draft_order' => 'Only draft or cancelled draft orders can be deleted.',
            ]);
        }

        $draftId = $draftOrder->id;
        $draftNumber = $draftOrder->draft_number;

        $draftOrder->delete();

        app(SecurityLogRecorder::class)->record(
            $request,
            'manual_draft_deleted',
            store: $store,
            metadata: [
                'draft_order_id' => $draftId,
                'draft_number' => $draftNumber,
            ]
        );

        return redirect()
            ->route('orders')
            ->with('success', 'Draft order deleted.');
    }

    private function assertDraftBelongsToStore(DraftOrder $draftOrder, int $storeId): void
    {
        if ((int) $draftOrder->store_id !== $storeId) {
            abort(404);
        }
    }

    private function customers($store)
    {
        return Customer::query()
            ->where('store_id', $store->id)
            ->orderBy('full_name')
            ->orderBy('email')
            ->limit(100)
            ->get();
    }

    private function variants($store)
    {
        return ProductVariant::query()
            ->where('store_id', $store->id)
            ->whereHas('product', fn ($query) => $query->where('store_id', $store->id)->where('status', true))
            ->with(['product', 'options.variationType'])
            ->orderBy('id')
            ->limit(300)
            ->get();
    }

    private function validatedDraftPayload(Request $request, int $storeId, bool $requireCustomer = true): array
    {
        return $request->validate([
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('store_id', $storeId),
            ],
            'customer_name' => [$requireCustomer ? 'required_without:customer_id' : 'nullable', 'nullable', 'string', 'max:160'],
            'customer_email' => [$requireCustomer ? 'required_without:customer_id' : 'nullable', 'nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:80'],
            'shipping_name' => ['nullable', 'string', 'max:160'],
            'shipping_phone' => ['nullable', 'string', 'max:80'],
            'shipping_address_line1' => ['nullable', 'string', 'max:255'],
            'shipping_address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['nullable', 'string', 'max:120'],
            'shipping_state' => ['nullable', 'string', 'max:32', 'alpha'],
            'shipping_postal_code' => ['nullable', 'string', 'max:40'],
            'shipping_country' => ['nullable', 'string', 'size:2', 'alpha'],
            'billing_same_as_shipping' => ['nullable', 'boolean'],
            'billing_name' => ['nullable', 'string', 'max:160'],
            'billing_phone' => ['nullable', 'string', 'max:80'],
            'billing_address_line1' => ['nullable', 'string', 'max:255'],
            'billing_address_line2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:120'],
            'billing_state' => ['nullable', 'string', 'max:32', 'alpha'],
            'billing_postal_code' => ['nullable', 'string', 'max:40'],
            'billing_country' => ['nullable', 'string', 'size:2', 'alpha'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'shipping_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where('store_id', $storeId),
            ],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ], [
            'shipping_country.size' => 'Enter a valid two-letter country code such as US, CA, GB, or AU.',
            'shipping_country.alpha' => 'Enter a valid two-letter country code such as US, CA, GB, or AU.',
            'billing_country.size' => 'Enter a valid two-letter country code such as US, CA, GB, or AU.',
            'billing_country.alpha' => 'Enter a valid two-letter country code such as US, CA, GB, or AU.',
        ]);
    }
}
