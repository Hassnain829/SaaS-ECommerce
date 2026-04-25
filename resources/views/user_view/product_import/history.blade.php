@extends('layouts.user.user-sidebar')

@php
    use App\Models\ProductImport;
    use App\Support\Catalog\ProductImportStatusPresenter;
@endphp

@section('title', 'Import history')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="flex-1 overflow-y-auto p-4 lg:p-8">
        <div class="mx-auto max-w-6xl">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Products</p>
                    <h1 class="mt-1 text-2xl font-semibold text-[#0F172A] font-[Poppins]">Import history</h1>
                    <p class="mt-2 max-w-2xl text-sm text-[#64748B]">Review past uploads for this store, see what changed in your catalog, and open any import that needs a follow-up.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('products.import.create') }}" class="text-sm font-semibold text-[#0052CC] hover:text-[#0047B3]">New import</a>
                    <a href="{{ route('products') }}" class="text-sm font-semibold text-[#475569] hover:text-[#0F172A]">Back to products</a>
                </div>
            </div>

            <div class="rounded-3xl border border-[#E2E8F0] bg-white p-5 shadow-sm sm:p-6">
                <form method="get" action="{{ route('products.import.history') }}" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label for="import_q" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Search by file name</label>
                        <input id="import_q" type="search" name="q" value="{{ $searchQ }}" placeholder="e.g. catalog.xlsx"
                               class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-[#0F172A] placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                    </div>
                    <div class="sm:w-48">
                        <label for="import_status" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Status</label>
                        <select id="import_status" name="status" class="w-full rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            <option value="">All</option>
                            <option value="{{ ProductImport::STATUS_COMPLETED }}" @selected($statusFilter === ProductImport::STATUS_COMPLETED)>Finished</option>
                            <option value="{{ ProductImport::STATUS_FAILED }}" @selected($statusFilter === ProductImport::STATUS_FAILED)>Could not finish</option>
                            <option value="{{ ProductImport::STATUS_PROCESSING }}" @selected($statusFilter === ProductImport::STATUS_PROCESSING)>In progress</option>
                            <option value="{{ ProductImport::STATUS_QUEUED }}" @selected($statusFilter === ProductImport::STATUS_QUEUED)>Waiting to start</option>
                            <option value="{{ ProductImport::STATUS_PREVIEWED }}" @selected($statusFilter === ProductImport::STATUS_PREVIEWED)>Ready to run</option>
                            <option value="{{ ProductImport::STATUS_PARSED }}" @selected($statusFilter === ProductImport::STATUS_PARSED)>Columns detected</option>
                            <option value="{{ ProductImport::STATUS_UPLOADED }}" @selected($statusFilter === ProductImport::STATUS_UPLOADED)>File received</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#0F172A] px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#1E293B]">Apply</button>
                        @if ($searchQ !== '' || $statusFilter !== '')
                            <a href="{{ route('products.import.history') }}" class="inline-flex items-center justify-center rounded-xl border border-[#E2E8F0] bg-white px-4 py-2 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC]">Clear</a>
                        @endif
                    </div>
                </form>

                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#B42318]">
                        <ul class="ml-5 list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($imports->isEmpty())
                    <div class="rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-6 py-12 text-center">
                        <p class="text-sm font-semibold text-[#334155]">No imports yet</p>
                        <p class="mt-2 text-sm text-[#64748B]">When you import a spreadsheet, it will show up here so you can review results anytime.</p>
                        <a href="{{ route('products.import.create') }}" class="mt-6 inline-flex items-center justify-center rounded-xl bg-[#0052CC] px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#0047B3]">Import products</a>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-[#E2E8F0]">
                        <table class="min-w-full divide-y divide-[#E2E8F0] text-sm">
                            <thead class="bg-[#F8FAFC] text-left text-xs font-semibold uppercase tracking-wide text-[#64748B]">
                                <tr>
                                    <th class="px-4 py-3">File</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Started</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Imported by</th>
                                    <th class="px-4 py-3 text-right">Rows</th>
                                    <th class="px-4 py-3 text-right">Processed</th>
                                    <th class="px-4 py-3 text-right">Added</th>
                                    <th class="px-4 py-3 text-right">Updated</th>
                                    <th class="px-4 py-3 text-right">Skipped</th>
                                    <th class="px-4 py-3 text-right">Needs attention</th>
                                    <th class="px-4 py-3 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E8F0] bg-white text-[#334155]">
                                @foreach ($imports as $import)
                                    @php
                                        $rs = $import->result_summary ?? [];
                                        $totalRows = $import->total_rows;
                                        $processed = $rs['processed_rows'] ?? $rs['total_processed'] ?? $import->last_processed_row;
                                        $isDone = in_array($import->status, [ProductImport::STATUS_COMPLETED, ProductImport::STATUS_FAILED], true);
                                        $detailRoute = $isDone ? 'products.import.report' : 'products.import.result';
                                    @endphp
                                    <tr class="hover:bg-[#F8FAFC]/80">
                                        <td class="px-4 py-3 font-medium text-[#0F172A] max-w-[200px] truncate" title="{{ $import->original_filename }}">{{ $import->original_filename }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-[#64748B]">{{ $import->created_at?->timezone($selectedStore->timezone ?? config('app.timezone'))->format('M j, Y g:i a') ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ ProductImportStatusPresenter::badgeClass($import->status) }}">
                                                {{ ProductImportStatusPresenter::label($import->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-[#64748B]">{{ $import->creator?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $totalRows !== null ? (int) $totalRows : '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $processed !== null && $processed !== '' ? (int) $processed : '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $isDone ? (int) ($rs['created'] ?? 0) : '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $isDone ? (int) ($rs['updated'] ?? 0) : '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $isDone ? (int) ($rs['skipped'] ?? 0) : '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums font-semibold {{ ($isDone && (int) ($rs['failed'] ?? 0) > 0) ? 'text-[#B42318]' : 'text-[#334155]' }}">
                                            {{ $isDone ? (int) ($rs['failed'] ?? 0) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route($detailRoute, ['productImportId' => $import->id]) }}" class="font-semibold text-[#0052CC] hover:text-[#0047B3]">Open</a>
                                            @if ($import->canReopenMapping())
                                                @php
                                                    $mapBtnLabel = in_array($import->normalizedStatus(), [ProductImport::STATUS_COMPLETED, ProductImport::STATUS_FAILED], true)
                                                        ? 'Re-edit mapping'
                                                        : 'Adjust mapping';
                                                @endphp
                                                <span class="mx-2 text-[#CBD5E1]" aria-hidden="true">·</span>
                                                <form method="post" action="{{ route('products.import.reopen-mapping', ['productImportId' => $import->id]) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="font-semibold text-[#0052CC] hover:text-[#0047B3]">{{ $mapBtnLabel }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $imports->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
