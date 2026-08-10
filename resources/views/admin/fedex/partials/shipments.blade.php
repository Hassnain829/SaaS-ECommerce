<section class="rounded-xl border border-slate-200 bg-white p-4">
    <h2 class="text-lg font-semibold text-slate-900">Uncertain / processing ship operations</h2>
    <p class="mt-1 text-xs text-slate-500">Do not blind-retry these. Confirm tracking with FedEx first.</p>
    <ul class="mt-3 space-y-2 text-sm">
        @forelse ($uncertainShipOps as $row)
            <li class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2">
                Store {{ $row->store_id }} · state {{ data_get($row->response_body, 'state') }} · shipment {{ $row->resource_id ?: 'none' }} · updated {{ optional($row->updated_at)->format('Y-m-d H:i') }}
            </li>
        @empty
            <li class="text-slate-500">No uncertain ship operations.</li>
        @endforelse
    </ul>
</section>

<section class="rounded-xl border border-slate-200 bg-white p-4">
    <h2 class="text-lg font-semibold text-slate-900">Recent FedEx shipments</h2>
    <ul class="mt-3 space-y-2 text-sm">
        @forelse ($recentShipments as $shipment)
            <li class="flex justify-between gap-3 border-b border-slate-100 py-2">
                <span>Store {{ $shipment->store_id }} · {{ $shipment->shipment_number }} · ···{{ $shipment->tracking_number ? substr($shipment->tracking_number, -4) : '----' }}</span>
                <span class="text-slate-500">{{ $shipment->status }}</span>
            </li>
        @empty
            <li class="text-slate-500">No FedEx shipments yet.</li>
        @endforelse
    </ul>
</section>
