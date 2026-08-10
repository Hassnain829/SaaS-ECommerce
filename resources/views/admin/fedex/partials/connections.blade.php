<section class="rounded-xl border border-slate-200 bg-white p-4">
    <h2 class="text-lg font-semibold text-slate-900">FedEx connections</h2>
    <p class="mt-1 text-xs text-slate-500">Account numbers are masked. Secrets, child keys, and OAuth tokens are never displayed.</p>
    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-slate-500">
                <tr>
                    <th class="py-2 pr-3">Store</th>
                    <th class="py-2 pr-3">Label</th>
                    <th class="py-2 pr-3">Account</th>
                    <th class="py-2 pr-3">Env</th>
                    <th class="py-2 pr-3">Mode</th>
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2 pr-3">Owner</th>
                    <th class="py-2">Updated</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($connections as $row)
                    <tr class="border-t border-slate-100">
                        <td class="py-2 pr-3">{{ $row['store_id'] }}</td>
                        <td class="py-2 pr-3">{{ $row['display_name'] }}</td>
                        <td class="py-2 pr-3 font-mono text-xs">{{ $row['masked_account'] }}</td>
                        <td class="py-2 pr-3">{{ $row['environment'] }}</td>
                        <td class="py-2 pr-3">{{ $row['connection_mode'] ?: '—' }}</td>
                        <td class="py-2 pr-3">{{ $row['connection_status'] }}</td>
                        <td class="py-2 pr-3">{{ $row['connection_owner'] ?: '—' }}</td>
                        <td class="py-2 text-slate-600">
                            {{ optional($row['updated_at'])->format('Y-m-d H:i') }}
                            @if ($row['last_error_message'])
                                <div class="mt-0.5 text-xs text-rose-600">{{ $row['last_error_message'] }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-3 text-slate-500">No FedEx connections found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
