@extends('layouts.user.user-sidebar')

@php
    use App\Models\ProductImport;
    use App\Support\Catalog\ProductImportMerchantMessages;
    use App\Support\Catalog\ProductImportStatusPresenter;
    $summary = $import->result_summary ?? [];
    $headers = $import->headers ?? [];
    $mapping = $import->column_mapping ?? [];
@endphp

@section('title', 'Import details')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="flex-1 overflow-y-auto p-4 lg:p-8">
        <div class="mx-auto max-w-5xl">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Import details</p>
                    <h1 class="mt-1 text-2xl font-semibold text-[#0F172A] font-[Poppins]">{{ $import->original_filename }}</h1>
                    <p class="mt-2 text-sm text-[#64748B]">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ ProductImportStatusPresenter::badgeClass($import->status) }}">{{ ProductImportStatusPresenter::label($import->status) }}</span>
                        <span class="mx-2 text-[#CBD5E1]">·</span>
                        Started {{ $import->created_at?->timezone($selectedStore->timezone ?? config('app.timezone'))->format('M j, Y g:i a') ?? '—' }}
                        @if ($import->creator)
                            <span class="text-[#CBD5E1]">·</span> by {{ $import->creator->name }}
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-3 text-sm font-semibold">
                    <a href="{{ route('products.import.history') }}" class="text-[#0052CC] hover:text-[#0047B3]">Import history</a>
                    <a href="{{ route('products') }}" class="text-[#475569] hover:text-[#0F172A]">Products</a>
                </div>
            </div>

            @if (session('import_notice'))
                <div class="mb-6 rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3 text-sm text-[#1E3A8A]">
                    {{ session('import_notice') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#B42318]">
                    <ul class="ml-5 list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="space-y-6">
                <div class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-8">
                    @if (!empty($summary['merchant_summary']))
                        <p class="text-sm text-[#334155] leading-relaxed">{{ $summary['merchant_summary'] }}</p>
                    @elseif ($import->status === ProductImport::STATUS_FAILED && $import->failure_message)
                        <p class="text-sm font-semibold text-[#B42318]">We could not finish this import.</p>
                        <p class="mt-2 text-sm text-[#334155]">{{ $import->failure_message }}</p>
                    @else
                        <p class="text-sm text-[#334155]">Here is a concise summary of how this import affected your catalog.</p>
                    @endif

                    @if (!empty($summary['merchant_note']))
                        <p class="mt-4 rounded-xl border border-[#E0E7FF] bg-[#EEF2FF] px-4 py-3 text-sm text-[#312E81]">{{ $summary['merchant_note'] }}</p>
                    @endif

                    @if (!empty($summary['partial_success']))
                        <div class="mt-4 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                            <p class="font-semibold">Some rows still need your attention</p>
                            <p class="mt-1">That is expected when a few lines in your file are incomplete or do not match the format we need. Everything else that could be imported safely has already been applied.</p>
                            <p class="mt-2">You can review the rows below, fix your spreadsheet if needed, or use “Try failed rows again” after you have corrected the source data we saved for each row.</p>
                        </div>
                    @endif

                    <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                        <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><dt class="text-[#64748B]">Rows in file</dt><dd class="text-lg font-bold text-[#0F172A] tabular-nums">{{ (int) ($import->total_rows ?? $summary['total_rows'] ?? 0) }}</dd></div>
                        <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><dt class="text-[#64748B]">Products added</dt><dd class="text-lg font-bold text-[#0F172A] tabular-nums">{{ (int) ($summary['created'] ?? 0) }}</dd></div>
                        <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><dt class="text-[#64748B]">Products updated</dt><dd class="text-lg font-bold text-[#0F172A] tabular-nums">{{ (int) ($summary['updated'] ?? 0) }}</dd></div>
                        <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><dt class="text-[#64748B]">Rows skipped</dt><dd class="text-lg font-bold text-[#0F172A] tabular-nums">{{ (int) ($summary['skipped'] ?? 0) }}</dd></div>
                    </dl>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3"><dt class="text-[#991B1B]">Rows that need attention</dt><dd class="text-lg font-bold text-[#B42318] tabular-nums">{{ $failedCount }}</dd></div>
                        <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><dt class="text-[#64748B]">Warnings</dt><dd class="text-lg font-bold text-[#0F172A] tabular-nums">{{ (int) ($summary['warnings_count'] ?? 0) }}</dd></div>
                    </dl>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('products.import.result', ['productImportId' => $import->id]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#E2E8F0] bg-white px-4 py-2 text-sm font-semibold text-[#334155] shadow-sm hover:bg-[#F8FAFC]">Live status view</a>
                        @if ($canRetryFailed)
                            <form method="post" action="{{ route('products.import.retry-failed', ['productImportId' => $import->id]) }}" class="inline" onsubmit="this.querySelector('button').disabled=true;">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#0052CC] px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#0047B3]">Try failed rows again</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($reasonGroups->isNotEmpty())
                    <div class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-8">
                        <h2 class="text-sm font-semibold text-[#0F172A]">Common reasons this time</h2>
                        <p class="mt-1 text-xs text-[#64748B]">Grouped by the message we stored for each row (plain language shown).</p>
                        <ul class="mt-4 space-y-2 text-sm text-[#334155]">
                            @foreach ($reasonGroups as $g)
                                <li class="flex justify-between gap-4 rounded-xl bg-[#F8FAFC] px-4 py-2">
                                    <span>{{ ProductImportMerchantMessages::humanizeRowError($g->error_message) }}</span>
                                    <span class="shrink-0 font-semibold tabular-nums text-[#0F172A]">{{ $g->row_count }}×</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-sm font-semibold text-[#0F172A]">Rows that need attention</h2>
                    @if ($failedRows->isEmpty())
                        <p class="mt-3 text-sm text-[#64748B]">No problem rows are recorded for this import. If you expected issues here, open the live status view to confirm the latest state.</p>
                    @else
                        <p class="mt-1 text-xs text-[#64748B]">Row numbers match your spreadsheet (including the header row).</p>
                        <div class="mt-4 overflow-x-auto rounded-2xl border border-[#E2E8F0]">
                            <table class="min-w-full divide-y divide-[#E2E8F0] text-sm">
                                <thead class="bg-[#F8FAFC] text-left text-xs font-semibold uppercase tracking-wide text-[#64748B]">
                                    <tr>
                                        <th class="px-4 py-3">Row</th>
                                        <th class="px-4 py-3">Product</th>
                                        <th class="px-4 py-3">What to fix</th>
                                        <th class="px-4 py-3 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E2E8F0]">
                                    @foreach ($failedRows as $row)
                                        @php
                                            $payload = is_array($row->payload) ? $row->payload : [];
                                            $cells = isset($payload['cells']) && is_array($payload['cells']) ? $payload['cells'] : [];
                                            $desc = ProductImportMerchantMessages::describeRowForMerchant($headers, $cells, is_array($mapping) ? $mapping : []);
                                            $human = ProductImportMerchantMessages::humanizeRowError((string) ($row->error_message ?? ''));
                                        @endphp
                                        <tr class="align-top">
                                            <td class="px-4 py-3 font-semibold tabular-nums text-[#0F172A]">{{ (int) $row->row_number + 1 }}</td>
                                            <td class="px-4 py-3 text-[#334155]">{{ $desc }}</td>
                                            <td class="px-4 py-3 text-[#B42318]">{{ $human }}</td>
                                            <td class="px-4 py-3 text-right">
                                                <details class="text-left">
                                                    <summary class="cursor-pointer text-xs font-semibold text-[#0052CC]">Technical details</summary>
                                                    <div class="mt-2 rounded-lg bg-[#0F172A] p-3 text-xs font-mono text-[#E2E8F0] overflow-x-auto max-w-md ml-auto">
                                                        <p class="text-[#94A3B8] mb-1">Stored message</p>
                                                        <p class="whitespace-pre-wrap break-all">{{ $row->error_message ?: '—' }}</p>
                                                        <p class="text-[#94A3B8] mt-3 mb-1">Row snapshot (JSON)</p>
                                                        <pre class="whitespace-pre-wrap break-all">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                </details>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $failedRows->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
