<section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">Total accounts</div>
        <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $accountCounts['total'] ?? 0 }}</div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">Connected</div>
        <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ $accountCounts['connected'] ?? 0 }}</div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">Failed / blocked</div>
        <div class="mt-1 text-2xl font-semibold text-rose-700">{{ $accountCounts['failed'] ?? 0 }}</div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">Pending verification</div>
        <div class="mt-1 text-2xl font-semibold text-amber-700">{{ $accountCounts['pending_validation'] ?? 0 }}</div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">Setup required</div>
        <div class="mt-1 text-2xl font-semibold text-slate-700">{{ $accountCounts['setup_required'] ?? 0 }}</div>
    </div>
</section>

<section class="rounded-xl border border-slate-200 bg-white p-4">
    <h2 class="text-lg font-semibold text-slate-900">Recent successful API events</h2>
    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-slate-500">
                <tr>
                    <th class="py-2 pr-3">When</th>
                    <th class="py-2 pr-3">Store</th>
                    <th class="py-2 pr-3">Action</th>
                    <th class="py-2 pr-3">Duration</th>
                    <th class="py-2">Txn</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentSuccessEvents as $event)
                    <tr class="border-t border-slate-100">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ optional($event->created_at)->format('Y-m-d H:i') }}</td>
                        <td class="py-2 pr-3">{{ $event->store_id }}</td>
                        <td class="py-2 pr-3">{{ $event->action }}</td>
                        <td class="py-2 pr-3">{{ $event->duration_ms ? $event->duration_ms.' ms' : '—' }}</td>
                        <td class="py-2 text-slate-600">{{ $event->fedex_transaction_id ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-3 text-slate-500">No recent successful FedEx API events.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="rounded-xl border border-slate-200 bg-white p-4">
    <h2 class="text-lg font-semibold text-slate-900">Failed connections snapshot</h2>
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
