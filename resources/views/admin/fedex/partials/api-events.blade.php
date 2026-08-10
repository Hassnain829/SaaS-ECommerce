<section class="rounded-xl border border-slate-200 bg-white p-4">
    <h2 class="text-lg font-semibold text-slate-900">FedEx API events</h2>
    <p class="mt-1 text-xs text-slate-500">Summary fields only. Raw request/response bodies are never shown.</p>

    <form method="get" action="{{ route('admin.fedex.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="tab" value="api-events">
        <div>
            <label for="event-status" class="block text-xs font-medium text-slate-500">Status</label>
            <select id="event-status" name="status" class="mt-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">All statuses</option>
                @foreach ($eventFilters['statuses'] as $statusOption)
                    <option value="{{ $statusOption }}" @selected($activeEventFilters['status'] === $statusOption)>{{ $statusOption }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="event-action" class="block text-xs font-medium text-slate-500">Action</label>
            <select id="event-action" name="action" class="mt-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">All actions</option>
                @foreach ($eventFilters['actions'] as $actionOption)
                    <option value="{{ $actionOption }}" @selected($activeEventFilters['action'] === $actionOption)>{{ $actionOption }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Apply filters</button>
        @if ($activeEventFilters['status'] || $activeEventFilters['action'])
            <a href="{{ route('admin.fedex.index', ['tab' => 'api-events']) }}" class="text-sm text-slate-500 hover:text-slate-800">Clear</a>
        @endif
    </form>

    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-slate-500">
                <tr>
                    <th class="py-2 pr-3">When</th>
                    <th class="py-2 pr-3">Store</th>
                    <th class="py-2 pr-3">Action</th>
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2 pr-3">HTTP</th>
                    <th class="py-2 pr-3">Code</th>
                    <th class="py-2">Summary</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($apiEvents as $event)
                    <tr class="border-t border-slate-100">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ optional($event->created_at)->format('Y-m-d H:i') }}</td>
                        <td class="py-2 pr-3">{{ $event->store_id }}</td>
                        <td class="py-2 pr-3">
                            {{ $event->action }}
                            @if ($event->test_case_key)
                                <div class="text-xs text-slate-500">{{ $event->test_case_key }}</div>
                            @endif
                        </td>
                        <td class="py-2 pr-3">{{ $event->status }}</td>
                        <td class="py-2 pr-3">{{ $event->http_status ?: '—' }}</td>
                        <td class="py-2 pr-3">{{ $event->error_code ?: '—' }}</td>
                        <td class="py-2 text-slate-600">
                            {{ \Illuminate\Support\Str::limit((string) ($event->error_message ?: data_get($event->response_summary, 'message')), 100) }}
                            @if ($event->fedex_transaction_id)
                                <div class="text-xs text-slate-400">Txn {{ $event->fedex_transaction_id }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-3 text-slate-500">No FedEx API events match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($apiEvents->hasPages())
        <div class="mt-4">{{ $apiEvents->links() }}</div>
    @endif
</section>
