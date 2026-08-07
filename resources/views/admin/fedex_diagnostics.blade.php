@extends('layouts.admin.admin-Sidebar')

@section('title', 'FedEx diagnostics — '.config('app.name'))

@section('content')
    <div class="mx-auto max-w-6xl space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">FedEx operations diagnostics</h1>
            <p class="mt-1 text-sm text-slate-600">Safe summaries only. Credentials, tokens, and raw FedEx payloads are never shown.</p>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Platform flags</h2>
            <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <li>Production: <strong>{{ ($flags['production'] ?? false) ? 'on' : 'off' }}</strong></li>
                <li>Checkout rates: <strong>{{ ($flags['checkout_rates'] ?? false) ? 'on' : 'off' }}</strong></li>
                <li>Ship labels: <strong>{{ ($flags['ship_labels'] ?? false) ? 'on' : 'off' }}</strong></li>
                <li>Tracking: <strong>{{ ($flags['tracking'] ?? false) ? 'on' : 'off' }}</strong></li>
            </ul>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="text-lg font-semibold text-slate-900">Failed connections</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-3">Store</th>
                            <th class="py-2 pr-3">Account</th>
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2">Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($failedConnections as $row)
                            <tr class="border-t border-slate-100">
                                <td class="py-2 pr-3">{{ $row->store_id }}</td>
                                <td class="py-2 pr-3">{{ $row->display_name }} · {{ $row->environment }}</td>
                                <td class="py-2 pr-3">{{ $row->connection_status }}</td>
                                <td class="py-2 text-slate-600">{{ \Illuminate\Support\Str::limit((string) $row->last_error_message, 120) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-slate-500">No failed FedEx connections.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="text-lg font-semibold text-slate-900">Failed / incomplete API events</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-3">When</th>
                            <th class="py-2 pr-3">Store</th>
                            <th class="py-2 pr-3">Action</th>
                            <th class="py-2 pr-3">Code</th>
                            <th class="py-2">Txn</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($failedEvents as $event)
                            <tr class="border-t border-slate-100">
                                <td class="py-2 pr-3 whitespace-nowrap">{{ optional($event->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="py-2 pr-3">{{ $event->store_id }}</td>
                                <td class="py-2 pr-3">{{ $event->action }}</td>
                                <td class="py-2 pr-3">{{ $event->error_code ?: $event->status }}</td>
                                <td class="py-2 text-slate-600">{{ data_get($event->response_summary, 'fedex_transaction_id') ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-3 text-slate-500">No failed FedEx API events.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

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

        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="text-lg font-semibold text-slate-900">ETD documents</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($etdDocs as $doc)
                    <li class="flex justify-between gap-3 border-b border-slate-100 py-2">
                        <span>Store {{ $doc->store_id }} · order {{ $doc->order_id ?: '—' }} · {{ $doc->origin_country_code }}→{{ $doc->destination_country_code }}</span>
                        <span class="text-slate-500">{{ $doc->status }} · {{ $doc->fedex_document_id ? 'doc…'.substr($doc->fedex_document_id, -6) : 'no id' }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">No trade documents recorded.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
