<section class="rounded-xl border border-slate-200 bg-white p-4">
    <h2 class="text-lg font-semibold text-slate-900">Trade documents (ETD)</h2>
    <p class="mt-1 text-xs text-slate-500">FedEx document IDs are masked. File contents and storage paths are not shown.</p>
    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-slate-500">
                <tr>
                    <th class="py-2 pr-3">Store</th>
                    <th class="py-2 pr-3">Order</th>
                    <th class="py-2 pr-3">Type</th>
                    <th class="py-2 pr-3">Route</th>
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2">Document</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($etdDocs as $doc)
                    <tr class="border-t border-slate-100">
                        <td class="py-2 pr-3">{{ $doc->store_id }}</td>
                        <td class="py-2 pr-3">{{ $doc->order_id ?: '—' }}</td>
                        <td class="py-2 pr-3">{{ $doc->document_type ?: '—' }}</td>
                        <td class="py-2 pr-3">{{ $doc->origin_country_code }}→{{ $doc->destination_country_code }}</td>
                        <td class="py-2 pr-3">{{ $doc->status }}</td>
                        <td class="py-2 text-slate-600">
                            {{ $doc->fedex_document_id ? 'doc…'.substr($doc->fedex_document_id, -6) : 'no id' }}
                            @if ($doc->uploaded_at)
                                <div class="text-xs text-slate-400">Uploaded {{ $doc->uploaded_at->format('Y-m-d H:i') }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-3 text-slate-500">No trade documents recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
