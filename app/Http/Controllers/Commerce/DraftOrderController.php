<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DraftOrder;
use App\Models\ProductVariant;
use App\Models\TaxRate;
use App\Services\Draft\DraftTaxService;
use App\Services\DraftOrderService;
use App\Services\ManualOrderConversionService;
use App\Services\SecurityLogRecorder;
use App\Support\Tax\TaxDisplayPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'taxSetting' => $store->taxSetting,
            'currency' => $store->currency ?: 'USD',
            'defaultTaxMode' => ($store->taxSetting?->enabled ?? false)
                ? DraftOrder::TAX_SOURCE_CALCULATED
                : DraftOrder::TAX_SOURCE_MANUAL,
        ]);
    }

    public function store(Request $request, DraftOrderService $draftOrderService, DraftTaxService $draftTaxService): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $taxMode = $this->resolvedTaxModeForCreate($request, $store);
        $payload = $this->normalizeTaxInPayload(
            $request,
            $this->validatedDraftPayload($request, $store->id, store: $store),
            $taxMode
        );

        $draft = DB::transaction(function () use ($store, $request, $draftOrderService, $draftTaxService, $payload, $taxMode) {
            if ($taxMode === DraftOrder::TAX_SOURCE_CALCULATED) {
                $draft = $draftOrderService->create($store, $request->user(), $payload, DraftOrder::TAX_SOURCE_CALCULATED);

                return $draftTaxService->calculate($draft, $store);
            }

            return $draftOrderService->create($store, $request->user(), $payload, DraftOrder::TAX_SOURCE_MANUAL);
        });

        app(SecurityLogRecorder::class)->record(
            $request,
            'manual_draft_created',
            store: $store,
            metadata: [
                'draft_order_id' => $draft->id,
                'draft_number' => $draft->draft_number,
                'tax_mode' => $taxMode,
            ]
        );

        return redirect()
            ->route('draft-orders.show', $draft)
            ->with('success', $taxMode === DraftOrder::TAX_SOURCE_CALCULATED
                ? 'Draft saved with calculated tax.'
                : 'Draft order saved.');
    }

    public function show(Request $request, DraftOrder $draftOrder): View
    {
        $store = $request->attributes->get('currentStore');
        $this->assertDraftBelongsToStore($draftOrder, $store->id);

        return view('user_view.draft_order_show', [
            'selectedStore' => $store,
            'draftOrder' => $draftOrder->load(['customer.addresses', 'items.variant.product', 'convertedOrder', 'taxLines']),
            'customers' => $this->customers($store),
            'variants' => $this->variants($store),
            'taxDisplay' => TaxDisplayPresenter::forDraft($draftOrder),
            'taxSetting' => $store->taxSetting,
        ]);
    }

    public function update(Request $request, DraftOrder $draftOrder, DraftOrderService $draftOrderService, DraftTaxService $draftTaxService): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $this->assertDraftBelongsToStore($draftOrder, $store->id);

        $taxMode = $this->resolvedTaxMode($request, $draftOrder);
        $payload = $this->normalizeTaxInPayload(
            $request,
            $this->validatedDraftPayload($request, $store->id, requireCustomer: false, draftOrder: $draftOrder, store: $store),
            $taxMode,
            $draftOrder
        );
        $wasCalculated = $draftOrder->taxSource() === DraftOrder::TAX_SOURCE_CALCULATED;

        if ($taxMode === DraftOrder::TAX_SOURCE_MANUAL && $wasCalculated) {
            if (! $request->boolean('confirm_manual_tax_switch')) {
                throw ValidationException::withMessages([
                    'confirm_manual_tax_switch' => 'Confirm that calculated tax lines and rate details will be removed before switching to manual tax.',
                ]);
            }

            $draftOrderService->switchToManualTax($draftOrder, $payload);

            app(SecurityLogRecorder::class)->record(
                $request,
                'manual_draft_updated',
                store: $store,
                metadata: [
                    'draft_order_id' => $draftOrder->id,
                    'draft_number' => $draftOrder->draft_number,
                    'tax_mode' => 'manual',
                ]
            );

            return redirect()
                ->route('draft-orders.show', $draftOrder)
                ->with('success', 'Draft order updated.');
        }

        if ($taxMode === DraftOrder::TAX_SOURCE_CALCULATED) {
            DB::transaction(function () use ($draftOrder, $draftOrderService, $draftTaxService, $store, $payload): void {
                $draft = $draftOrderService->updateForRecalculation($draftOrder, $payload);
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
                ->with('success', 'Draft saved.');
        }

        $draftOrderService->updateManual($draftOrder, $payload);

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
        DraftTaxService $draftTaxService,
        ManualOrderConversionService $conversionService
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        $this->assertDraftBelongsToStore($draftOrder, $store->id);

        $taxMode = $request->has('items')
            ? $this->resolvedTaxMode($request, $draftOrder)
            : $draftOrder->taxSource();
        $payload = $request->has('items')
            ? $this->normalizeTaxInPayload(
                $request,
                $this->validatedDraftPayload($request, $store->id, requireCustomer: false, draftOrder: $draftOrder, store: $store),
                $taxMode,
                $draftOrder
            )
            : null;

        if ($payload !== null && $taxMode === DraftOrder::TAX_SOURCE_MANUAL && $draftOrder->taxSource() === DraftOrder::TAX_SOURCE_CALCULATED) {
            if (! $request->boolean('confirm_manual_tax_switch')) {
                throw ValidationException::withMessages([
                    'confirm_manual_tax_switch' => 'Confirm that calculated tax lines and rate details will be removed before switching to manual tax.',
                ]);
            }
        }

        $order = DB::transaction(function () use (
            $request,
            $draftOrder,
            $draftOrderService,
            $draftTaxService,
            $conversionService,
            $store,
            $payload,
            $taxMode
        ) {
            if ($payload !== null) {
                if ($taxMode === DraftOrder::TAX_SOURCE_CALCULATED) {
                    $draft = $draftOrderService->updateForRecalculation($draftOrder, $payload);
                    $draftOrder = $draftTaxService->calculate($draft, $store);
                } else {
                    $draftOrder = $draftOrderService->updateManual($draftOrder, $payload);
                }
            } elseif ($draftOrder->taxSource() === DraftOrder::TAX_SOURCE_CALCULATED) {
                $draftOrder = $draftTaxService->calculate($draftOrder->fresh(['items', 'taxLines']), $store);
            }

            return $conversionService->convert($draftOrder, $store, $request->user());
        });

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

        $taxMode = DraftOrder::TAX_SOURCE_CALCULATED;
        $payload = $this->normalizeTaxInPayload(
            $request,
            $this->validatedDraftPayload($request, $store->id, requireCustomer: false, draftOrder: $draftOrder, store: $store),
            $taxMode,
            $draftOrder
        );

        DB::transaction(function () use ($draftOrder, $draftOrderService, $draftTaxService, $store, $payload): void {
            $draft = $draftOrderService->updateForRecalculation($draftOrder, $payload);
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
            ->with('success', 'Draft saved.');
    }

    public function previewTax(Request $request, DraftTaxService $draftTaxService): JsonResponse
    {
        $store = $request->attributes->get('currentStore');
        $taxMode = $this->submittedTaxMode($request, null, $store);

        if ($taxMode !== DraftOrder::TAX_SOURCE_CALCULATED) {
            return response()->json([
                'ready' => false,
                'guidance' => 'Select automatic tax mode to preview store tax.',
            ]);
        }

        $payload = $this->normalizeTaxInPayload(
            $request,
            $this->validatedDraftPayload($request, $store->id, requireCustomer: false, store: $store),
            $taxMode
        );

        return response()->json($draftTaxService->previewFromFormPayload($store, $payload));
    }

    private function resolvedTaxModeForCreate(Request $request, $store): string
    {
        $requested = (string) $request->input('tax_mode', '');

        if ($requested === DraftOrder::TAX_SOURCE_CALCULATED) {
            return DraftOrder::TAX_SOURCE_CALCULATED;
        }

        if ($requested === DraftOrder::TAX_SOURCE_MANUAL) {
            return DraftOrder::TAX_SOURCE_MANUAL;
        }

        return ($store->taxSetting?->enabled ?? false)
            ? DraftOrder::TAX_SOURCE_CALCULATED
            : DraftOrder::TAX_SOURCE_MANUAL;
    }

    private function resolvedTaxMode(Request $request, DraftOrder $draftOrder): string
    {
        $requested = (string) $request->input('tax_mode', '');

        if ($requested === DraftOrder::TAX_SOURCE_CALCULATED) {
            return DraftOrder::TAX_SOURCE_CALCULATED;
        }

        if ($requested === DraftOrder::TAX_SOURCE_MANUAL) {
            return DraftOrder::TAX_SOURCE_MANUAL;
        }

        return $draftOrder->taxSource() === DraftOrder::TAX_SOURCE_CALCULATED
            ? DraftOrder::TAX_SOURCE_CALCULATED
            : DraftOrder::TAX_SOURCE_MANUAL;
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

    private function validatedDraftPayload(
        Request $request,
        int $storeId,
        bool $requireCustomer = true,
        ?DraftOrder $draftOrder = null,
        $store = null
    ): array {
        $billingSameAsShipping = $request->boolean('billing_same_as_shipping');
        $taxMode = $this->submittedTaxMode($request, $draftOrder, $store);
        $isCalculatedMode = $taxMode === DraftOrder::TAX_SOURCE_CALCULATED;
        $requiresRegionForTax = $isCalculatedMode && $this->storeRequiresRegionForTax($store);

        $rules = [
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
            'shipping_state' => [
                Rule::requiredIf($requiresRegionForTax),
                'nullable',
                'string',
                'max:32',
                'not_regex:/,/',
                'regex:/\A[A-Za-z0-9\-]+\z/',
            ],
            'shipping_postal_code' => ['nullable', 'string', 'max:40'],
            'shipping_country' => [
                Rule::requiredIf($isCalculatedMode),
                'nullable',
                'string',
                'size:2',
                'regex:/\A[A-Za-z]{2}\z/',
            ],
            'billing_same_as_shipping' => ['boolean'],
            'billing_name' => [Rule::excludeIf($billingSameAsShipping), 'required', 'string', 'max:160'],
            'billing_phone' => [Rule::excludeIf($billingSameAsShipping), 'nullable', 'string', 'max:80'],
            'billing_address_line1' => [Rule::excludeIf($billingSameAsShipping), 'required', 'string', 'max:255'],
            'billing_address_line2' => [Rule::excludeIf($billingSameAsShipping), 'nullable', 'string', 'max:255'],
            'billing_city' => [Rule::excludeIf($billingSameAsShipping), 'required', 'string', 'max:120'],
            'billing_state' => [Rule::excludeIf($billingSameAsShipping), 'nullable', 'string', 'max:32'],
            'billing_postal_code' => [Rule::excludeIf($billingSameAsShipping), 'nullable', 'string', 'max:40'],
            'billing_country' => [Rule::excludeIf($billingSameAsShipping), 'required', 'string', 'size:2', 'regex:/\A[A-Za-z]{2}\z/'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'manual_tax_total' => [Rule::excludeIf($isCalculatedMode), 'nullable', 'numeric', 'min:0'],
            'shipping_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tax_mode' => ['nullable', 'string', Rule::in([DraftOrder::TAX_SOURCE_MANUAL, DraftOrder::TAX_SOURCE_CALCULATED])],
            'confirm_manual_tax_switch' => [Rule::excludeIf($isCalculatedMode), 'nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where('store_id', $storeId),
            ],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];

        $payload = $request->validate($rules, [
            'shipping_country.required' => 'Enter a shipping country code when using automatic tax.',
            'shipping_country.size' => 'Enter a valid two-letter country code such as US, CA, GB, or AU.',
            'shipping_country.regex' => 'Enter a valid two-letter country code such as US, CA, GB, or AU.',
            'shipping_state.required' => 'Enter the shipping state or region code that matches your tax rate (for example NY).',
            'shipping_state.not_regex' => 'Enter one state or region code only, such as NY or CA.',
            'shipping_state.regex' => 'Enter one state or region code only, such as NY or CA.',
            'billing_country.size' => 'Enter a valid two-letter country code such as US, CA, GB, or AU.',
            'billing_country.regex' => 'Enter a valid two-letter country code such as US, CA, GB, or AU.',
            'billing_name.required' => 'Enter a billing recipient name when billing differs from shipping.',
            'billing_address_line1.required' => 'Enter a billing address when billing differs from shipping.',
            'billing_city.required' => 'Enter a billing city when billing differs from shipping.',
            'billing_country.required' => 'Enter a billing country code when billing differs from shipping.',
        ]);

        $payload['billing_same_as_shipping'] = $billingSameAsShipping;

        return $payload;
    }

    private function storeRequiresRegionForTax($store): bool
    {
        return TaxRate::query()
            ->forStore((int) $store->id)
            ->active()
            ->where('region_code', '!=', '')
            ->exists();
    }

    private function submittedTaxMode(Request $request, ?DraftOrder $draftOrder = null, $store = null): string
    {
        $requested = (string) $request->input('tax_mode', '');

        if ($requested === DraftOrder::TAX_SOURCE_CALCULATED) {
            return DraftOrder::TAX_SOURCE_CALCULATED;
        }

        if ($requested === DraftOrder::TAX_SOURCE_MANUAL) {
            return DraftOrder::TAX_SOURCE_MANUAL;
        }

        if ($draftOrder !== null) {
            return $draftOrder->taxSource() === DraftOrder::TAX_SOURCE_CALCULATED
                ? DraftOrder::TAX_SOURCE_CALCULATED
                : DraftOrder::TAX_SOURCE_MANUAL;
        }

        return ($store?->taxSetting?->enabled ?? false)
            ? DraftOrder::TAX_SOURCE_CALCULATED
            : DraftOrder::TAX_SOURCE_MANUAL;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeTaxInPayload(Request $request, array $payload, string $taxMode, ?DraftOrder $draft = null): array
    {
        if ($taxMode === DraftOrder::TAX_SOURCE_CALCULATED) {
            unset($payload['tax_total'], $payload['manual_tax_total'], $payload['confirm_manual_tax_switch']);

            return $payload;
        }

        if ($request->filled('manual_tax_total')) {
            $payload['tax_total'] = $request->input('manual_tax_total');
        } elseif (! array_key_exists('tax_total', $payload) || $payload['tax_total'] === null) {
            $payload['tax_total'] = $draft?->tax_total ?? '0.00';
        }

        unset($payload['manual_tax_total']);

        return $payload;
    }
}
