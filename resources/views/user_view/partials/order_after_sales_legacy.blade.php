@php
    use App\Support\MoneyDisplay;
@endphp
                {{-- After-sales: returns, refunds, exchanges --}}
                <div id="returns-refunds" class="merchant-card scroll-mt-24 p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-heading text-xl font-semibold">After-sales service</h3>
                            <p class="mt-1 text-sm text-slate-600">Record and manage return, refund or exchange decisions after a customer contacts your store.</p>
                            <p class="mt-2 text-sm text-slate-500">Customer requests are received through your store’s normal support channels. Use this workspace to record and process the agreed action.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($canManageOrders && $canRecordReturn)
                                <button type="button" onclick="document.getElementById('start-return-form')?.classList.toggle('hidden')" class="inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-white transition hover:opacity-90">Record return</button>
                            @endif
                            @if ($canManageOrders && bccomp((string) $remainingRefundableAmount, '0', 4) > 0)
                                <button type="button" onclick="document.getElementById('issue-refund-form')?.classList.toggle('hidden')" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700 hover:bg-stone-50">Issue refund</button>
                            @endif
                            @if ($canManageOrders && $canCreateExchange)
                                <button type="button" onclick="document.getElementById('start-exchange-form')?.classList.toggle('hidden')" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700 hover:bg-stone-50">Create exchange</button>
                            @endif
                        </div>
                    </div>

                    @if ($canManageOrders && ! $canRecordReturn && $returnEligibilityMessage)
                        <p class="mt-4 text-sm text-slate-500">{{ $returnEligibilityMessage }}</p>
                    @endif

                    @if ($canManageOrders && ! $canCreateExchange && $exchangeEligibilityMessage)
                        <p class="mt-4 text-sm text-slate-500">{{ $exchangeEligibilityMessage }}</p>
                    @endif

                    @if ($canManageOrders && $canRecordReturn)
                        <form id="start-return-form" method="POST" action="{{ route('orders.returns.store', $order) }}" class="mt-5 hidden space-y-4 rounded-xl border border-stone-200 bg-stone-50/70 p-4" data-service-panel="return">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Return reason</span>
                                    <select name="return_reason_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                                        <option value="">Select a reason</option>
                                        @foreach ($returnReasons as $reason)
                                            <option value="{{ $reason->id }}" @selected((string) old('return_reason_id') === (string) $reason->id)>{{ $reason->label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Tracking reference</span>
                                    <input type="text" name="tracking_reference" value="{{ old('tracking_reference') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" placeholder="Optional carrier tracking">
                                </label>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm font-medium text-slate-700">Items to return</p>
                                @foreach ($returnableItems as $item)
                                    @php $remainingQty = (int) ($remainingReturnableQuantities[$item->id] ?? 0); @endphp
                                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 bg-white px-3 py-3 text-sm">
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $item->product_name }}</p>
                                            <p class="text-xs text-slate-500">{{ $item->variant_label ?: 'Standard' }} · {{ $remainingQty }} returnable</p>
                                        </div>
                                        <label class="flex items-center gap-2">
                                            <span class="text-xs text-slate-500">Qty</span>
                                            @php
                                                $oldReturnQty = old('items.'.$item->id, 0);
                                                if (is_array($oldReturnQty)) {
                                                    $oldReturnQty = $oldReturnQty['quantity'] ?? 0;
                                                }
                                            @endphp
                                            <input type="number" min="0" max="{{ $remainingQty }}" name="items[{{ $item->id }}]" value="{{ $oldReturnQty }}" class="w-20 rounded-lg border border-stone-200 px-2 py-1.5 text-sm">
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-700">Customer’s message</span>
                                <textarea name="customer_notes" rows="2" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" placeholder="Record what the customer reported by email, phone or chat.">{{ old('customer_notes') }}</textarea>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-700">Return instructions</span>
                                <textarea name="manual_instructions" rows="2" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" placeholder="Tell the customer how to send the items back">{{ old('manual_instructions') }}</textarea>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-700">Internal team notes</span>
                                <textarea name="merchant_notes" rows="2" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" placeholder="Visible to your team">{{ old('merchant_notes') }}</textarea>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-white">Create return record</button>
                                <button type="button" onclick="document.getElementById('start-return-form')?.classList.add('hidden')" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700">Cancel</button>
                            </div>
                        </form>
                    @endif

                    @if ($canManageOrders && bccomp((string) $remainingRefundableAmount, '0', 4) > 0)
                        <form id="issue-refund-form" method="POST" action="{{ route('orders.refunds.store', $order) }}" class="mt-5 hidden space-y-4 rounded-xl border border-stone-200 bg-stone-50/70 p-4" data-service-panel="refund">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                            <p class="text-sm text-slate-600">Remaining refundable: <span class="font-semibold text-slate-900">{{ MoneyDisplay::format($remainingRefundableAmount, $currency) }}</span>. Leave amount blank to refund the full remaining balance.</p>
                            <p class="text-sm text-slate-600">Issuing a refund returns money only. It does not return or restock products. If goods are coming back, record and receive the return separately.</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Refund amount</span>
                                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" placeholder="Full remaining">
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Shipping refund</span>
                                    <input type="number" step="0.01" min="0" name="shipping_amount" value="{{ old('shipping_amount') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Shipping tax refund</span>
                                    <input type="number" step="0.01" min="0" name="shipping_tax_amount" value="{{ old('shipping_tax_amount') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Tax refund</span>
                                    <input type="number" step="0.01" min="0" name="tax_amount" value="{{ old('tax_amount') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                                </label>
                                <label class="block text-sm sm:col-span-2">
                                    <span class="mb-1 block font-medium text-slate-700">Other adjustment</span>
                                    <input type="number" step="0.01" min="0" name="other_amount" value="{{ old('other_amount') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                                </label>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm font-medium text-slate-700">Item quantities (optional)</p>
                                @foreach ($order->items as $item)
                                    @php $refundableQty = max(0, (int) $item->quantity - (int) $item->refunded_quantity); @endphp
                                    @if ($refundableQty > 0)
                                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 bg-white px-3 py-3 text-sm">
                                            <div>
                                                <p class="font-medium text-slate-900">{{ $item->product_name }}</p>
                                                <p class="text-xs text-slate-500">{{ $refundableQty }} refundable</p>
                                            </div>
                                            @php
                                                $oldRefundQty = old('items.'.$item->id, 0);
                                                if (is_array($oldRefundQty)) {
                                                    $oldRefundQty = $oldRefundQty['quantity'] ?? 0;
                                                }
                                            @endphp
                                            <input type="number" min="0" max="{{ $refundableQty }}" name="items[{{ $item->id }}]" value="{{ $oldRefundQty }}" class="w-20 rounded-lg border border-stone-200 px-2 py-1.5 text-sm">
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-700">Reason</span>
                                <input type="text" name="reason" value="{{ old('reason') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                            </label>
                            @if ($isOrderExternallyManaged)
                                <input type="hidden" name="processed_externally" value="1">
                                <p class="text-xs text-slate-500">Record this refund only after it has been processed through the original selling channel or payment provider.</p>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-white">Process refund</button>
                                <button type="button" onclick="document.getElementById('issue-refund-form')?.classList.add('hidden')" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700">Cancel</button>
                            </div>
                        </form>
                    @endif

                    @if ($canManageOrders && $canCreateExchange)
                        <form id="start-exchange-form" method="POST" action="{{ route('orders.exchanges.store', $order) }}" class="mt-5 hidden space-y-4 rounded-xl border border-stone-200 bg-stone-50/70 p-4" data-service-panel="exchange">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Original item</span>
                                    <select name="order_item_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" required>
                                        @foreach ($exchangeableItems as $item)
                                            @php $exchangeQty = (int) ($remainingExchangeableQuantities[$item->id] ?? 0); @endphp
                                            <option value="{{ $item->id }}">{{ $item->product_name }} ({{ $item->variant_label ?: 'Standard' }}) · {{ $exchangeQty }} left</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Quantity</span>
                                    <input type="number" min="1" name="quantity" value="1" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" required>
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Replacement variant</span>
                                    <select name="replacement_variant_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" required>
                                        @foreach ($exchangeVariants as $variant)
                                            <option value="{{ $variant->id }}">{{ $variant->product?->name }} · {{ $variant->sku }} · {{ MoneyDisplay::format($variant->price, $currency) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-white">Create exchange</button>
                                <button type="button" onclick="document.getElementById('start-exchange-form')?.classList.add('hidden')" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700">Cancel</button>
                            </div>
                        </form>
                    @endif

                    <div class="mt-6 space-y-4">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Returns</h4>
                            <p class="mt-1 text-xs text-slate-500">Goods coming back to your store</p>
                        </div>
                        @php
                            $canCreateFedExReturnLabel = ($fedExActiveAccount ?? null)
                                && ($canManageOrders ?? false)
                                && ! ($isOrderExternallyManaged ?? false)
                                && filter_var(config('carriers.fedex.ops_ship_labels_enabled', false), FILTER_VALIDATE_BOOL);
                            $returnPackagePreset = $canCreateFedExReturnLabel
                                ? app(\App\Services\Delivery\StoreShippingPreferences::class)->defaultPackagePreset($selectedStore)
                                : null;
                        @endphp
                        @forelse ($order->returns as $return)
                            <div class="rounded-xl border border-stone-200 bg-white p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $return->return_number }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ \App\Support\ReturnLifecycle::statusLabel($return->status) }}
                                            @if ($return->reason)
                                                · {{ $return->reason->label }}
                                            @endif
                                        </p>
                                    </div>
                                    <span class="order-status-pill bg-stone-100 text-stone-700">{{ \App\Support\ReturnLifecycle::statusLabel($return->status) }}</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @foreach ($return->items as $returnItem)
                                        <div class="rounded-lg bg-stone-50 px-3 py-2 text-sm">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <p class="font-medium text-slate-800">{{ $returnItem->product_name_snapshot }}</p>
                                                <p class="text-xs text-slate-600">
                                                    Req {{ $returnItem->requested_quantity }}
                                                    @if ((int) $returnItem->received_quantity > 0)
                                                        · Recv {{ $returnItem->received_quantity }}
                                                    @endif
                                                    @if ((int) $returnItem->restocked_quantity > 0)
                                                        · Restocked {{ $returnItem->restocked_quantity }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if ($canManageOrders)
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @if (\App\Support\ReturnLifecycle::canTransition($return->status, \App\Support\ReturnLifecycle::STATUS_APPROVED))
                                            <form method="POST" action="{{ route('returns.approve', $return) }}">@csrf<button type="submit" class="inline-flex h-9 items-center rounded-xl bg-brand px-3 text-xs font-semibold text-white">Approve</button></form>
                                            <form method="POST" action="{{ route('returns.reject', $return) }}">@csrf<button type="submit" class="inline-flex h-9 items-center rounded-xl border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Reject</button></form>
                                        @endif
                                        @php
                                            $showFedExReturnLabel = $canCreateFedExReturnLabel
                                                && $return->status === \App\Support\ReturnLifecycle::STATUS_APPROVED;
                                        @endphp
                                        @if ($showFedExReturnLabel)
                                            <form method="POST" action="{{ route('orders.fedex.return-label', $order) }}" class="w-full space-y-3 rounded-xl border border-[#BFDBFE] bg-[#F8FBFF] p-3">
                                                @csrf
                                                <input type="hidden" name="carrier_account_id" value="{{ $fedExActiveAccount->id }}">
                                                <input type="hidden" name="order_return_id" value="{{ $return->id }}">
                                                <div>
                                                    <p class="text-sm font-semibold text-[#0F172A]">Create FedEx return label</p>
                                                    <p class="mt-1 text-xs leading-5 text-[#64748B]">
                                                        Uses the approved return items.
                                                        @if ($return->tracking_reference)
                                                            Current tracking: {{ $return->tracking_reference }}
                                                        @endif
                                                    </p>
                                                </div>
                                                @error('fedex_return')
                                                    <p class="text-xs text-red-700">{{ $message }}</p>
                                                @enderror
                                                @error('order_return_id')
                                                    <p class="text-xs text-red-700">{{ $message }}</p>
                                                @enderror
                                                @error('packages')
                                                    <p class="text-xs text-red-700">{{ $message }}</p>
                                                @enderror
                                                <label class="block text-xs font-medium text-slate-700">Return to
                                                    <select name="origin_location_id" required class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-sm">
                                                        @foreach ($fulfillmentLocations as $location)
                                                            <option value="{{ $location->id }}" @selected((string) old('origin_location_id') === (string) $location->id)>{{ $location->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <label class="block text-xs font-medium text-slate-700">Return service
                                                    <select name="service_type" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-sm">
                                                        @foreach (\App\Services\Carriers\FedEx\Support\FedExCheckoutServiceCatalog::services() as $service)
                                                            <option value="{{ $service['code'] }}" @selected((string) old('service_type', \App\Services\Carriers\FedEx\Support\FedExCheckoutServiceCatalog::defaultReturnServiceCode()) === $service['code'])>{{ $service['name'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <p class="text-[11px] leading-4 text-slate-500">
                                                    FedEx confirms whether the selected return service is available for this route before creating the label.
                                                </p>
                                                <div class="grid gap-2 sm:grid-cols-4">
                                                    <label class="text-xs font-medium text-slate-700">Weight (lb)
                                                        <input type="number" step="0.01" min="0.01" name="weight" value="{{ old('weight', $returnPackagePreset?->weight_value) }}" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-sm" placeholder="Required">
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-700">Length
                                                        <input type="number" step="0.01" min="1" name="length" value="{{ old('length', $returnPackagePreset?->length) }}" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-sm" placeholder="in">
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-700">Width
                                                        <input type="number" step="0.01" min="1" name="width" value="{{ old('width', $returnPackagePreset?->width) }}" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-sm" placeholder="in">
                                                    </label>
                                                    <label class="text-xs font-medium text-slate-700">Height
                                                        <input type="number" step="0.01" min="1" name="height" value="{{ old('height', $returnPackagePreset?->height) }}" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-sm" placeholder="in">
                                                    </label>
                                                </div>
                                                <p class="text-[11px] leading-4 text-slate-500">
                                                    Package details are required unless a default package is set in Shipping settings.
                                                </p>
                                                <button type="submit" class="inline-flex h-9 items-center rounded-xl bg-brand px-3 text-xs font-semibold text-white">Create FedEx return label</button>
                                            </form>
                                        @endif
                                        @if (\App\Support\ReturnLifecycle::canTransition($return->status, \App\Support\ReturnLifecycle::STATUS_RECEIVED))
                                            <form method="POST" action="{{ route('returns.receive', $return) }}" class="w-full space-y-3 rounded-xl border border-stone-200 bg-stone-50 p-3">
                                                @csrf
                                                @foreach ($return->items as $returnItem)
                                                    @php $maxRecv = (int) $returnItem->approved_quantity; @endphp
                                                    <div class="grid gap-2 sm:grid-cols-4">
                                                        <input type="hidden" name="items[{{ $returnItem->order_item_id }}][received_quantity]" value="{{ $maxRecv }}">
                                                        <p class="sm:col-span-4 text-xs font-medium text-slate-700">{{ $returnItem->product_name_snapshot }}</p>
                                                        <label class="text-xs">Condition
                                                            <select name="items[{{ $returnItem->order_item_id }}][condition]" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5">
                                                                <option value="sellable">Sellable</option>
                                                                <option value="damaged">Damaged</option>
                                                                <option value="defective">Defective</option>
                                                                <option value="non_sellable">Non-sellable</option>
                                                            </select>
                                                        </label>
                                                        <label class="text-xs">Restock?
                                                            <select name="items[{{ $returnItem->order_item_id }}][restock]" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5">
                                                                <option value="0">No</option>
                                                                <option value="1">Yes</option>
                                                            </select>
                                                        </label>
                                                        <label class="text-xs sm:col-span-2">Restock location
                                                            <select name="items[{{ $returnItem->order_item_id }}][restock_location_id]" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5">
                                                                <option value="">Select location</option>
                                                                @foreach ($fulfillmentLocations as $location)
                                                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                    </div>
                                                @endforeach
                                                <button type="submit" class="inline-flex h-9 items-center rounded-xl bg-brand px-3 text-xs font-semibold text-white">Mark received</button>
                                            </form>
                                        @endif
                                        @if (\App\Support\ReturnLifecycle::canTransition($return->status, \App\Support\ReturnLifecycle::STATUS_COMPLETED))
                                            <form method="POST" action="{{ route('returns.complete', $return) }}">@csrf<button type="submit" class="inline-flex h-9 items-center rounded-xl bg-brand px-3 text-xs font-semibold text-white">Complete return</button></form>
                                        @endif
                                        @if (\App\Support\ReturnLifecycle::canTransition($return->status, \App\Support\ReturnLifecycle::STATUS_CANCELLED))
                                            <form method="POST" action="{{ route('returns.cancel', $return) }}">@csrf<button type="submit" class="inline-flex h-9 items-center rounded-xl border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Cancel return</button></form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-600">
                                @if (! $canRecordReturn)
                                    {{ $returnEligibilityMessage ?: 'No returnable items are available on this order.' }}
                                @else
                                    No returns are recorded yet.
                                @endif
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-6 space-y-4">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Refunds</h4>
                            <p class="mt-1 text-xs text-slate-500">Money returned to the customer</p>
                        </div>
                        @forelse ($order->refunds as $refund)
                            <div class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-semibold text-slate-900">{{ $refund->refund_number }} · {{ MoneyDisplay::format($refund->amount, $refund->currency_code) }}</p>
                                    <span class="order-status-pill bg-stone-100 text-stone-700">{{ \App\Support\RefundLifecycle::statusLabel($refund->status) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ ucfirst($refund->method) }}
                                    @if ($refund->reason)
                                        · {{ $refund->reason }}
                                    @endif
                                </p>
                                @if (
                                    $canManageOrders
                                    && $refund->method === \App\Support\RefundLifecycle::METHOD_PROVIDER
                                    && in_array($refund->status, [
                                        \App\Support\RefundLifecycle::STATUS_PENDING,
                                        \App\Support\RefundLifecycle::STATUS_PROCESSING,
                                        \App\Support\RefundLifecycle::STATUS_FAILED,
                                    ], true)
                                )
                                    <form method="POST" action="{{ route('orders.refunds.recheck', [$order, $refund]) }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700 transition hover:border-stone-300 hover:bg-stone-50">
                                            {{ $refund->status === \App\Support\RefundLifecycle::STATUS_FAILED ? 'Retry refund' : 'Recheck refund' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-600">No refunds recorded yet.</div>
                        @endforelse
                    </div>

                    <div class="mt-6 space-y-4">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Exchanges</h4>
                            <p class="mt-1 text-xs text-slate-500">Replacement product for the customer</p>
                        </div>
                        @forelse ($order->exchanges as $exchange)
                            <div class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-semibold text-slate-900">{{ $exchange->exchange_number }}</p>
                                    <span class="order-status-pill bg-stone-100 text-stone-700">{{ \App\Support\ExchangeLifecycle::statusLabel($exchange->status) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Price difference: {{ MoneyDisplay::format($exchange->price_difference, $exchange->currency_code) }}</p>
                                @if ($canManageOrders && in_array($exchange->status, ['requested', 'reserved'], true) && bccomp((string) $exchange->balance_due, '0', 4) > 0)
                                    @php
                                        $remainingBalance = \App\Support\Money\CurrencyPrecision::roundMajor(
                                            bcsub((string) $exchange->balance_due, (string) $exchange->collected_amount, 8),
                                            (string) $exchange->currency_code
                                        );
                                    @endphp
                                    @if (bccomp($remainingBalance, '0', 4) > 0)
                                        <form method="POST" action="{{ route('exchanges.collect', $exchange) }}" class="mt-3 space-y-2 rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                                            @csrf
                                            <p class="text-xs text-amber-900">Remaining balance due: <span class="font-semibold">{{ MoneyDisplay::format($remainingBalance, $exchange->currency_code) }}</span></p>
                                            <input type="hidden" name="collected_amount" value="{{ $remainingBalance }}">
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <label class="block text-xs">
                                                    <span class="mb-1 block font-medium text-slate-700">Collection method</span>
                                                    <select name="collection_method" class="w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-sm" required>
                                                        <option value="manual">Manual</option>
                                                        <option value="external">External</option>
                                                    </select>
                                                </label>
                                                <label class="block text-xs">
                                                    <span class="mb-1 block font-medium text-slate-700">Collection reference</span>
                                                    <input type="text" name="collection_reference" class="w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-sm" required>
                                                </label>
                                            </div>
                                            <button type="submit" class="inline-flex h-9 items-center rounded-xl border border-amber-300 bg-white px-3 text-xs font-semibold text-amber-900">Record collection</button>
                                        </form>
                                    @endif
                                @endif
                                @if ($canManageOrders && in_array($exchange->status, ['requested', 'reserved', 'processing'], true))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('exchanges.complete', $exchange) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex h-9 items-center rounded-xl bg-brand px-3 text-xs font-semibold text-white">
                                                {{ $exchange->status === 'processing' ? 'Resume completion' : 'Complete exchange' }}
                                            </button>
                                        </form>
                                        @if ($exchange->status !== 'processing')
                                            <form method="POST" action="{{ route('exchanges.cancel', $exchange) }}">@csrf<button type="submit" class="inline-flex h-9 items-center rounded-xl border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Cancel</button></form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-600">No exchanges recorded yet.</div>
                        @endforelse
                    </div>
                </div>
