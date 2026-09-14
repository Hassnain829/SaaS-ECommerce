                {{-- Fulfillment --}}
                <div class="merchant-card p-6">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <h3 class="font-heading text-xl font-semibold">Fulfillment</h3>
                        <span @class([
                            'order-status-pill',
                            'bg-sky-100 text-sky-800' => $isOrderExternallyManaged,
                            'bg-danger-soft text-danger' => ! $isOrderExternallyManaged && $order->fulfillment_status === \App\Support\OrderLifecycle::FULFILLMENT_UNFULFILLED,
                            'bg-amber-100 text-amber-800' => ! $isOrderExternallyManaged && $order->fulfillment_status === \App\Support\OrderLifecycle::FULFILLMENT_PARTIAL,
                            'bg-green-100 text-green-700' => ! $isOrderExternallyManaged && $order->fulfillment_status === \App\Support\OrderLifecycle::FULFILLMENT_FULFILLED,
                            'bg-slate-100 text-slate-700' => ! $isOrderExternallyManaged
                                && ! in_array($order->fulfillment_status, [
                                    \App\Support\OrderLifecycle::FULFILLMENT_UNFULFILLED,
                                    \App\Support\OrderLifecycle::FULFILLMENT_PARTIAL,
                                    \App\Support\OrderLifecycle::FULFILLMENT_FULFILLED,
                                ], true),
                        ])>{{ $fulfillmentStatusLabel }}</span>
                    </div>

                    @if ($isOrderExternallyManaged)
                        <div class="mb-4 rounded-xl border border-sky-100 bg-sky-50/80 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-sky-800/80">Fulfillment managed externally</p>
                            @if ($hasExternalFulfillmentDetails)
                                <dl class="mt-3 grid gap-3 text-sm">
                                    @if ($externalCarrierName)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Carrier</dt>
                                            <dd class="mt-1 font-semibold">{{ $externalCarrierName }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalFulfillmentStatus)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">External status</dt>
                                            <dd class="mt-1 font-semibold">{{ str($externalFulfillmentStatus)->replace('_', ' ')->title() }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalTrackingNumber)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tracking number</dt>
                                            <dd class="mt-1 break-all font-semibold">{{ $externalTrackingNumber }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalTrackingUrl)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tracking link</dt>
                                            <dd class="mt-1"><a href="{{ $externalTrackingUrl }}" target="_blank" rel="noopener" class="text-brand font-semibold hover:underline">Open tracking</a></dd>
                                        </div>
                                    @elseif (! $externalTrackingNumber)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tracking</dt>
                                            <dd class="mt-1 text-sm text-slate-600">No tracking update received yet.</dd>
                                        </div>
                                    @endif
                                    @if ($externalShippedAt)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Shipped at</dt>
                                            <dd class="mt-1 font-semibold">{{ $externalShippedAt }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalDeliveredAt)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Delivered at</dt>
                                            <dd class="mt-1 font-semibold">{{ $externalDeliveredAt }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            @else
                                <p class="mt-3 text-sm leading-relaxed text-slate-700">
                                    Fulfillment is managed by the external storefront. No shipment update has been received yet.
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($isOrderExternallyManaged)
                        <details class="mb-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-4">
                            <summary class="cursor-pointer text-xs font-bold uppercase tracking-wide text-slate-500">Internal fulfillment quantities (advanced)</summary>
                            <div class="mt-3 space-y-2">
                                @foreach ($order->items as $item)
                                    @php $remaining = (int) ($remainingFulfillmentQuantities[$item->id] ?? 0); @endphp
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate text-slate-700">{{ $item->product_name }}</span>
                                        <span class="font-semibold tabular-nums">{{ $remaining }} / {{ $item->quantity }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-3 text-xs leading-relaxed text-slate-500">These counts reflect dashboard-managed shipments only. External storefront fulfillment is shown above.</p>
                        </details>
                    @else
                        <div class="mb-4 rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Remaining to fulfill</p>
                            <div class="mt-3 space-y-2">
                                @foreach ($order->items as $item)
                                    @php $remaining = (int) ($remainingFulfillmentQuantities[$item->id] ?? 0); @endphp
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate text-slate-700">{{ $item->product_name }}</span>
                                        <span class="font-semibold tabular-nums">{{ $remaining }} / {{ $item->quantity }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @php
                        $fedExPrimaryFulfillment = ($fedExActiveAccount ?? null)
                            && ($canManageOrders ?? false)
                            && ! ($isOrderExternallyManaged ?? false)
                            && (
                                filter_var(config('carriers.fedex.ops_ship_labels_enabled', false), FILTER_VALIDATE_BOOL)
                                || filter_var(config('carriers.fedex.ops_negotiated_rates_enabled', false), FILTER_VALIDATE_BOOL)
                            );
                    @endphp

                    @if ($fedExPrimaryFulfillment)
                        <div class="method-switch" style="margin-bottom:14px">
                            <button type="button" class="active" data-method="fedex">FedEx</button>
                            <button type="button" data-method="manual">Manual shipment</button>
                        </div>
                    @endif

                    @include('user_view.orders.partials.fedex_shipping_ops')

                    <div id="manual-shipment-panel" @if ($fedExPrimaryFulfillment) hidden @endif>
                    @if ($canManageOrders && $remainingTotal > 0 && ! $isOrderExternallyManaged)
                        @if ($fedExPrimaryFulfillment)
                            <details class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/70 p-4">
                                <summary class="cursor-pointer text-sm font-semibold text-slate-700">Record a manual shipment</summary>
                                <p class="mt-2 text-xs leading-relaxed text-slate-500">Use this only when you need to record a non-FedEx shipment (outside carrier, already labeled elsewhere, or fixed tracking).</p>
                                <form method="POST" action="{{ route('orders.shipments.store', $order) }}" class="mt-4 space-y-4">
                                    @csrf
                                    @if ($routedOriginLocationId || $pickupLocationName)
                                        <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs leading-relaxed text-indigo-900">
                                            This order will ship from {{ $routedOriginLocationId ? ($fulfillmentLocations->firstWhere('id', $routedOriginLocationId)?->name ?? data_get($fulfillmentRouting, 'origin_name', 'the selected inventory location')) : 'the selected inventory location' }}.
                                            @if ($pickupLocationName)
                                                Pickup location selected: {{ $pickupLocationName }}.
                                            @endif
                                            You can override the ship-from location before creating the shipment.
                                        </div>
                                    @endif
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-700">Ship from</label>
                                        <select name="origin_location_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                                            <option value="">No location selected</option>
                                            @foreach ($fulfillmentLocations as $location)
                                                <option value="{{ $location->id }}" @selected((string) old('origin_location_id', $routedOriginLocationId ?: '') === (string) $location->id)>{{ $location->name }}{{ $location->is_default ? ' (default)' : '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-700">Carrier</label>
                                        <select name="carrier_account_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                                            <option value="">No carrier selected</option>
                                            @foreach ($carrierAccounts as $account)
                                                @continue($account->isFedEx() && $account->usesFedExIntegratorProvider())
                                                <option value="{{ $account->id }}">{{ $account->display_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-700">Delivery method</label>
                                        <select name="shipping_method_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                                            <option value="">No delivery method selected</option>
                                            @foreach ($shippingMethods as $method)
                                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Items</p>
                                        @foreach ($order->items as $item)
                                            @php $remaining = (int) ($remainingFulfillmentQuantities[$item->id] ?? 0); @endphp
                                            @if ($remaining > 0)
                                                <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                                                    <span class="min-w-0">
                                                        <span class="block truncate font-medium text-slate-800">{{ $item->product_name }}</span>
                                                        <span class="text-xs text-slate-500">{{ $remaining }} remaining</span>
                                                    </span>
                                                    <input name="items[{{ $item->id }}]" type="number" min="0" max="{{ $remaining }}" value="{{ $remaining }}" class="h-9 w-20 rounded-lg border border-slate-200 bg-white px-2 text-right text-sm">
                                                </label>
                                            @endif
                                        @endforeach
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-700">Tracking Number</label>
                                        <input name="tracking_number" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" placeholder="Enter tracking #">
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-700">Tracking link</label>
                                        <input name="tracking_url" type="url" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" placeholder="https://">
                                    </div>
                                    <div class="grid grid-cols-3 gap-2">
                                        <label class="space-y-1">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Packages</span>
                                            <input name="package_count" type="number" min="1" value="1" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                        </label>
                                        <label class="space-y-1">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Weight</span>
                                            <input name="package_weight" type="number" min="0" step="0.001" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                        </label>
                                        <label class="space-y-1">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cost</span>
                                            <input name="shipping_cost" type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                        </label>
                                    </div>
                                    <label class="space-y-1">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Internal note</span>
                                        <textarea name="note" rows="2" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional"></textarea>
                                    </label>
                                    <button type="submit" class="w-full rounded-xl border border-slate-300 bg-white py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">
                                        Record manual shipment
                                    </button>
                                </form>
                            </details>
                        @else
                        <form method="POST" action="{{ route('orders.shipments.store', $order) }}" class="space-y-4">
                            @csrf
                            @if ($routedOriginLocationId || $pickupLocationName)
                                <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs leading-relaxed text-indigo-900">
                                    This order will ship from {{ $routedOriginLocationId ? ($fulfillmentLocations->firstWhere('id', $routedOriginLocationId)?->name ?? data_get($fulfillmentRouting, 'origin_name', 'the selected inventory location')) : 'the selected inventory location' }}.
                                    @if ($pickupLocationName)
                                        Pickup location selected: {{ $pickupLocationName }}.
                                    @endif
                                    You can override the ship-from location before creating the shipment.
                                </div>
                            @endif
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Ship from</label>
                                <select name="origin_location_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                                    <option value="">No location selected</option>
                                    @foreach ($fulfillmentLocations as $location)
                                        <option value="{{ $location->id }}" @selected((string) old('origin_location_id', $routedOriginLocationId ?: '') === (string) $location->id)>{{ $location->name }}{{ $location->is_default ? ' (default)' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Carrier</label>
                                <select name="carrier_account_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                                    <option value="">No carrier selected</option>
                                    @foreach ($carrierAccounts as $account)
                                        @continue($account->isFedEx() && $account->usesFedExIntegratorProvider())
                                        <option value="{{ $account->id }}">{{ $account->display_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Delivery method</label>
                                <select name="shipping_method_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                                    <option value="">No delivery method selected</option>
                                    @foreach ($shippingMethods as $method)
                                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Items</p>
                                @foreach ($order->items as $item)
                                    @php $remaining = (int) ($remainingFulfillmentQuantities[$item->id] ?? 0); @endphp
                                    @if ($remaining > 0)
                                        <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium text-slate-800">{{ $item->product_name }}</span>
                                                <span class="text-xs text-slate-500">{{ $remaining }} remaining</span>
                                            </span>
                                            <input name="items[{{ $item->id }}]" type="number" min="0" max="{{ $remaining }}" value="{{ $remaining }}" class="h-9 w-20 rounded-lg border border-slate-200 bg-white px-2 text-right text-sm">
                                        </label>
                                    @endif
                                @endforeach
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Tracking Number</label>
                                <input name="tracking_number" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" placeholder="Enter tracking #">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Tracking link</label>
                                <input name="tracking_url" type="url" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" placeholder="https://">
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Packages</span>
                                    <input name="package_count" type="number" min="1" value="1" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Weight</span>
                                    <input name="package_weight" type="number" min="0" step="0.001" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cost</span>
                                    <input name="shipping_cost" type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                </label>
                            </div>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Internal note</span>
                                <textarea name="note" rows="2" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional"></textarea>
                            </label>
                            <button type="submit" class="w-full rounded-xl border-2 border-brand py-3 text-sm font-bold text-brand transition hover:bg-brand-soft">
                                Create shipment
                            </button>
                        </form>
                        @endif
                    @elseif ($canManageOrders && $remainingTotal === 0 && ! $isOrderExternallyManaged)
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            All items on this order are fulfilled.
                        </div>
                    @elseif ($isOrderExternallyManaged && $canManageOrders && $remainingTotal > 0)
                        <details class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-700">Advanced: create internal shipment override</summary>
                            <p class="mt-2 text-xs leading-relaxed text-slate-500">Only use this if you need to record fulfillment inside the dashboard in addition to external updates.</p>
                            <form method="POST" action="{{ route('orders.shipments.store', $order) }}" class="mt-4 space-y-4 rounded-xl border border-slate-200 bg-white p-4">
                                @csrf
                                <p class="font-semibold text-slate-900">Create shipment</p>
                                <div class="grid gap-3">
                                    <label class="space-y-1">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ship from</span>
                                        <select name="origin_location_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                            <option value="">No location selected</option>
                                            @foreach ($fulfillmentLocations as $location)
                                                <option value="{{ $location->id }}" @selected((string) old('origin_location_id', $routedOriginLocationId ?: '') === (string) $location->id)>{{ $location->name }}{{ $location->is_default ? ' (default)' : '' }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                <div class="space-y-2">
                                    @foreach ($order->items as $item)
                                        @php $remaining = (int) ($remainingFulfillmentQuantities[$item->id] ?? 0); @endphp
                                        @if ($remaining > 0)
                                            <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                                                <span class="min-w-0 truncate font-medium text-slate-800">{{ $item->product_name }}</span>
                                                <input name="items[{{ $item->id }}]" type="number" min="0" max="{{ $remaining }}" value="{{ $remaining }}" class="h-9 w-20 rounded-lg border border-slate-200 bg-white px-2 text-right text-sm">
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                                <button class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-800">Create internal shipment</button>
                            </form>
                        </details>
                    @endif
                    </div>

                    <div class="mt-5 space-y-4">
                        @forelse ($order->shipments as $shipment)
                            @php
                                $isExternalShipment = data_get($shipment->metadata, 'source') === 'external';
                                $externalShipmentCarrier = data_get($shipment->metadata, 'carrier_name');
                                $isFedExManagedShipment = $shipment->isFedExManagedShipment($fedExActiveAccount ?? null);
                            @endphp
                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $shipment->shipment_number }}</p>
                                        <p class="mt-1 text-sm text-slate-600">
                                            @if ($isExternalShipment)
                                                {{ $externalShipmentCarrier ?: 'External carrier' }} · Synced from external storefront
                                            @elseif ($isFedExManagedShipment)
                                                FedEx · Manage with FedEx shipment actions above
                                            @else
                                                {{ $shipment->carrierAccount?->display_name ?? 'No carrier account' }}{{ $shipment->shippingMethod ? ' | '.$shipment->shippingMethod->name : '' }}
                                            @endif
                                        </p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ \App\Support\OrderLifecycle::shipmentStatusBadgeClass($shipment->status) }}">
                                        {{ \App\Support\OrderLifecycle::shipmentStatusLabel($shipment->status) }}
                                    </span>
                                </div>
                                <div class="mt-3 space-y-1 text-sm text-slate-600">
                                    @foreach ($shipment->items as $shipmentItem)
                                        <p>{{ $shipmentItem->quantity }} x {{ $shipmentItem->orderItem?->product_name ?? 'Order item' }}</p>
                                    @endforeach
                                    <p>Created {{ $shipment->created_at?->format('M j, Y g:i A') }}</p>
                                    @if ($shipment->shipped_at)<p>Shipped {{ $shipment->shipped_at->format('M j, Y g:i A') }}</p>@endif
                                    @if ($shipment->delivered_at)<p>Delivered {{ $shipment->delivered_at->format('M j, Y g:i A') }}</p>@endif
                                </div>
                                @if ($shipment->tracking_number || $shipment->tracking_url)
                                    <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                        <p class="font-semibold text-slate-900">{{ $shipment->tracking_number ?: 'Tracking link' }}</p>
                                        <div class="mt-1 flex flex-wrap gap-2">
                                            @if ($shipment->tracking_url)
                                                <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener" class="text-brand inline-flex hover:underline">Open tracking</a>
                                            @endif
                                            @if ($shipment->tracking_number)
                                                <button type="button" class="text-brand inline-flex text-sm font-semibold hover:underline" data-action="copy-tracking" data-tracking="{{ $shipment->tracking_number }}">Copy tracking</button>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                @if ($canManageOrders && ! $isExternalShipment && ! $isFedExManagedShipment)
                                    <form method="POST" action="{{ route('shipments.tracking.update', $shipment) }}" class="mt-4 grid gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input name="tracking_number" value="{{ $shipment->tracking_number }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Tracking number">
                                        <input name="tracking_url" value="{{ $shipment->tracking_url }}" type="url" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Tracking link">
                                        <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Update tracking</button>
                                    </form>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if (in_array($shipment->status, [\App\Models\Shipment::STATUS_PENDING, \App\Models\Shipment::STATUS_LABEL_CREATED], true))
                                            <form method="POST" action="{{ route('shipments.mark-shipped', $shipment) }}">@csrf<button class="rounded-lg bg-brand px-3 py-2 text-xs font-bold text-white transition hover:bg-brand-hover">Mark shipped</button></form>
                                            <form method="POST" action="{{ route('shipments.cancel', $shipment) }}">@csrf<button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Cancel</button></form>
                                        @endif
                                        @if (in_array($shipment->status, [\App\Models\Shipment::STATUS_SHIPPED, \App\Models\Shipment::STATUS_IN_TRANSIT], true))
                                            <form method="POST" action="{{ route('shipments.mark-delivered', $shipment) }}">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Mark delivered</button></form>
                                        @endif
                                        @if (! in_array($shipment->status, [\App\Models\Shipment::STATUS_DELIVERED, \App\Models\Shipment::STATUS_FAILED, \App\Models\Shipment::STATUS_CANCELLED], true))
                                            <form method="POST" action="{{ route('shipments.mark-failed', $shipment) }}">@csrf<button class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">Mark failed</button></form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-600">
                                No shipments have been created yet.
                            </div>
                        @endforelse
                    </div>
                </div>
