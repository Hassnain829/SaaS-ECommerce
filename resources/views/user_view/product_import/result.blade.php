@extends('layouts.user.user-sidebar')

@php
    use App\Models\ProductImport;
    $summary = $import->result_summary ?? [];
    $isQueued = $import->status === ProductImport::STATUS_QUEUED;
    $isProcessing = $import->status === ProductImport::STATUS_PROCESSING;
    $waiting = $isQueued || $isProcessing;
    $hint = $summary['hint'] ?? null;
    $staleReason = $summary['stale_reason'] ?? null;
    $canResume = $canResume ?? false;
    $importUsesBackgroundQueue = $importUsesBackgroundQueue ?? ! \App\Support\Catalog\ProductImportQueue::runsInline();
    $queuedWaitMinutes = ($import->queued_at && $isQueued) ? $import->queued_at->diffInMinutes(now()) : 0;
@endphp

@section('title', 'Import result')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="flex-1 overflow-y-auto p-4 lg:p-8">
        <div class="mx-auto max-w-3xl">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Step 4 of 4</p>
                    <h1 class="mt-1 text-2xl font-semibold text-[#0F172A] font-[Poppins]">Import status</h1>
                    <p class="mt-2 text-sm text-[#64748B]">{{ $import->original_filename }}</p>
                </div>
                <div class="flex flex-wrap gap-3 text-sm font-semibold">
                    <a href="{{ route('products.import.history') }}" class="text-[#0052CC] hover:text-[#0047B3]">Import history</a>
                    <a href="{{ route('products') }}" class="text-[#475569] hover:text-[#0F172A]">Back to products</a>
                </div>
            </div>

            <div class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-8">
                @if (session('import_notice'))
                    <div class="mb-6 rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3 text-sm text-[#1E3A8A]">
                        {{ session('import_notice') }}
                    </div>
                @endif
                @if ($import->status === ProductImport::STATUS_FAILED)
                    <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#B42318]">
                        <p class="font-semibold">We could not finish this import</p>
                        <p class="mt-1 text-[#334155]">{{ $import->failure_message }}</p>
                        @if ($canResume)
                            <form method="POST" action="{{ route('products.import.resume', ['productImportId' => $import->id]) }}" class="mt-4">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded-lg bg-[#0F172A] px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#1E293B]">
                                    Continue from where it stopped
                                </button>
                            </form>
                            <p class="mt-2 text-xs text-[#64748B]">We saved progress through row {{ (int) ($import->last_processed_row ?? 0) }} of {{ (int) ($import->total_rows ?? 0) }} in your file.</p>
                        @endif
                        @if ($staleReason)
                            <details class="mt-4 text-xs text-[#64748B]">
                                <summary class="cursor-pointer font-semibold text-[#475569]">Technical details</summary>
                                <p class="mt-2 font-mono text-[#64748B]">Code: {{ $staleReason }}</p>
                            </details>
                        @endif
                    </div>
                    @if ($import->canReopenMapping())
                        <div class="mt-4 rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3 text-sm text-[#1E3A8A]">
                            <p class="font-semibold text-[#1E40AF]">Wrong column mapping?</p>
                            <p class="mt-1">If the file stopped because of settings—or you need to map columns (for example images) differently—open the same upload again. Re-editing restarts the import from the first row with your new mapping; continuing from where it stopped keeps the old mapping.</p>
                            <form method="post" action="{{ route('products.import.reopen-mapping', ['productImportId' => $import->id]) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-[#93C5FD] bg-white px-4 py-2 text-sm font-bold text-[#1D4ED8] shadow-sm hover:bg-[#EFF6FF]">Re-edit column mapping</button>
                            </form>
                        </div>
                    @endif
                @elseif ($waiting)
                    <div class="flex items-start gap-3 text-[#0F172A]">
                        <span class="mt-0.5 inline-block h-8 w-8 animate-spin rounded-full border-2 border-[#E2E8F0] border-t-[#0052CC]"></span>
                        <div class="space-y-2 text-sm flex-1">
                            @if ($isQueued)
                                <p class="font-semibold">We received your file and are preparing the import</p>
                                <p class="text-[#475569]">Your import is waiting to start. On many stores this begins within a few moments.</p>
                                @if ($importUsesBackgroundQueue && $queuedWaitMinutes >= 2)
                                    <div class="mt-3 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-xs text-[#92400E]">
                                        <p class="font-semibold">This is taking longer than usual</p>
                                        <p class="mt-1">Your store may be waiting on a background task to pick up the import. Ask your technical contact to confirm that catalog import workers are running (for example the same command used for other background jobs on this site).</p>
                                        <p class="mt-2">You can leave this page open—it refreshes automatically—or check <a href="{{ route('products.import.history') }}" class="font-bold text-[#B45309] underline">Import history</a> for the latest status.</p>
                                    </div>
                                @endif
                            @else
                                <p class="font-semibold">We’re importing your products now</p>
                                <p class="text-[#475569]">Your file is being processed. You can stay on this page — it refreshes on its own — or come back later from Import history.</p>
                            @endif
                            @php $prog = $summary['progress'] ?? null; @endphp
                            @if (is_array($prog) && isset($prog['processed_rows']))
                                @php
                                    $pct = isset($prog['progress_percentage']) ? (float) $prog['progress_percentage'] : null;
                                    $eta = isset($prog['eta_seconds']) && $prog['eta_seconds'] !== null ? (int) $prog['eta_seconds'] : null;
                                    $chunkCur = isset($prog['current_chunk']) ? (int) $prog['current_chunk'] : null;
                                    $chunkTot = isset($prog['total_chunks']) ? (int) $prog['total_chunks'] : null;
                                @endphp
                                <div class="mt-3 space-y-2">
                                    @if ($pct !== null)
                                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-[#E2E8F0]">
                                            <div class="h-full rounded-full bg-[#0052CC] transition-all duration-500" style="width: {{ min(100, max(0, $pct)) }}%"></div>
                                        </div>
                                        <p class="text-xs text-[#64748B]">
                                            <span class="font-semibold text-[#0F172A]">{{ number_format($pct, 1) }}%</span>
                                            @if ($eta !== null && $eta > 0 && ($prog['phase'] ?? '') === 'processing')
                                                <span class="text-[#64748B]"> — about {{ max(1, (int) ceil($eta / 60)) }} min left (estimate)</span>
                                            @endif
                                        </p>
                                    @endif
                                    <p class="text-xs text-[#64748B]">
                                        Rows handled so far:
                                        <span class="font-semibold text-[#0F172A]">{{ (int) $prog['processed_rows'] }}</span>
                                        @if (isset($prog['total_rows']) || isset($prog['total_rows_estimated']))
                                            <span class="text-[#64748B]"> of </span><span class="font-semibold text-[#0F172A]">{{ (int) ($prog['total_rows'] ?? $prog['total_rows_estimated'] ?? 0) }}</span>
                                        @endif
                                    </p>
                                    @if ($chunkCur !== null && $chunkTot !== null && $chunkTot > 0)
                                        <details class="text-xs text-[#94A3B8]">
                                            <summary class="cursor-pointer font-semibold text-[#64748B]">Advanced progress</summary>
                                            <p class="mt-1">Internal step {{ $chunkCur }} of {{ $chunkTot }} (large files are split automatically).</p>
                                        </details>
                                    @endif
                                    @if (isset($prog['created']) || isset($prog['updated']))
                                        <p class="text-xs text-[#64748B]">
                                            So far: <span class="font-semibold text-[#0F172A]">{{ (int) ($prog['created'] ?? 0) }}</span> added,
                                            <span class="font-semibold text-[#0F172A]">{{ (int) ($prog['updated'] ?? 0) }}</span> updated,
                                            <span class="font-semibold text-[#B42318]">{{ (int) ($prog['failed'] ?? 0) }}</span> need attention,
                                            <span class="font-semibold text-[#0F172A]">{{ (int) ($prog['skipped'] ?? 0) }}</span> skipped
                                            @if (isset($prog['warnings_count']) && (int) $prog['warnings_count'] > 0)
                                                <span class="text-[#94A3B8]"> — {{ (int) $prog['warnings_count'] }} notice(s)</span>
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            @endif
                            @if ($hint)
                                <p class="text-xs text-[#64748B]">{{ $hint }}</p>
                            @endif
                            <details class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">
                                <summary class="cursor-pointer font-semibold text-[#475569]">If this screen does not update</summary>
                                <p class="mt-2">This page checks for new progress every few seconds. If it seems stuck for several minutes, your technical contact can verify that background work is enabled for your environment.</p>
                            </details>
                        </div>
                    </div>
                @elseif ($import->status === ProductImport::STATUS_COMPLETED)
                    @php
                        $failed = (int) ($summary['failed'] ?? 0);
                        $partial = !empty($summary['partial_success']) || $failed > 0;
                        $imgTotal = (int) ($summary['total_images'] ?? 0);
                        $imgProcessed = (int) ($summary['processed_images'] ?? 0);
                        $imgFailed = (int) ($summary['failed_images'] ?? 0);
                        $imagesStillRunning = $imgTotal > 0 && ($imgProcessed + $imgFailed) < $imgTotal;
                        $imagesAllDone = $imgTotal > 0 && ($imgProcessed + $imgFailed) >= $imgTotal;
                    @endphp
                    @if ($partial)
                        <div class="mb-6 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                            <p class="font-semibold">Most of your file was imported successfully</p>
                            <p class="mt-1">{{ $summary['merchant_summary'] ?? 'Some rows need changes before they can be added to your catalog. Everything else is already live in your store.' }}</p>
                            @if ($failed > 0)
                                <a href="{{ route('products.import.report', ['productImportId' => $import->id]) }}" class="mt-3 inline-flex text-sm font-bold text-[#B45309] underline decoration-2 underline-offset-2">Review the {{ $failed }} {{ $failed === 1 ? 'row' : 'rows' }} that need attention</a>
                            @endif
                        </div>
                    @else
                        <p class="text-sm font-semibold text-[#059669]">{{ $summary['merchant_summary'] ?? 'Your import finished without any rows needing attention.' }}</p>
                    @endif
                    @if ($imgTotal > 0)
                        <div id="js-import-image-progress" class="mt-6 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm">
                            <p class="font-semibold text-[#0F172A]">Catalog media</p>
                            <ul class="mt-2 space-y-1 text-[#475569]">
                                <li>
                                    Products imported:
                                    <span id="js-import-prod-count" class="font-bold tabular-nums text-[#0F172A]">{{ (int) ($summary['processed_products'] ?? ($summary['created'] ?? 0) + ($summary['updated'] ?? 0) + ($summary['skipped'] ?? 0) + ($summary['failed'] ?? 0)) }}</span>
                                    /
                                    <span class="font-bold tabular-nums text-[#0F172A]">{{ (int) ($summary['total_products'] ?? ($summary['total_rows'] ?? $import->total_rows ?? 0)) }}</span>
                                    <span class="text-[#059669]" aria-hidden="true">✅</span>
                                </li>
                                <li>
                                    Images processed:
                                    <span id="js-import-img-processed" class="font-bold tabular-nums text-[#0F172A]">{{ $imgProcessed }}</span>
                                    /
                                    <span id="js-import-img-total" class="font-bold tabular-nums text-[#0F172A]">{{ $imgTotal }}</span>
                                    @if ($imagesStillRunning)
                                        <span class="text-[#64748B]" aria-hidden="true">⏳</span>
                                    @elseif ($imgFailed > 0)
                                        <span class="text-amber-600" title="Some images failed">⚠</span>
                                    @else
                                        <span class="text-[#059669]" aria-hidden="true">✅</span>
                                    @endif
                                    <span class="text-xs text-[#64748B]">(<span id="js-import-img-failed">{{ $imgFailed }}</span> failed)</span>
                                </li>
                            </ul>
                            <p id="js-import-image-note" class="mt-2 text-xs text-[#64748B]">
                                @if ($imagesStillRunning)
                                    Your products are ready. Images are being prepared in the background.
                                @elseif ($imgFailed > 0)
                                    Your products are ready. Some images could not be downloaded — you can edit products to add images manually.
                                @else
                                    All images processed successfully.
                                @endif
                            </p>
                        </div>
                    @endif
                    <dl class="mt-6 grid gap-4 sm:grid-cols-2 text-sm">
                        <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><dt class="text-[#64748B]">Products added</dt><dd class="text-lg font-bold text-[#0F172A] tabular-nums">{{ $summary['created'] ?? 0 }}</dd></div>
                        <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><dt class="text-[#64748B]">Products updated</dt><dd class="text-lg font-bold text-[#0F172A] tabular-nums">{{ $summary['updated'] ?? 0 }}</dd></div>
                        <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><dt class="text-[#64748B]">Rows skipped</dt><dd class="text-lg font-bold text-[#0F172A] tabular-nums">{{ $summary['skipped'] ?? 0 }}</dd></div>
                        <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><dt class="text-[#64748B]">Rows that need attention</dt><dd class="text-lg font-bold text-[#B42318] tabular-nums">{{ $summary['failed'] ?? 0 }}</dd></div>
                    </dl>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('products.import.history') }}" class="inline-flex items-center justify-center rounded-xl border border-[#E2E8F0] bg-white px-4 py-2 text-sm font-semibold text-[#334155] shadow-sm hover:bg-[#F8FAFC]">View import history</a>
                        @if ($failed > 0)
                            <a href="{{ route('products.import.report', ['productImportId' => $import->id]) }}" class="inline-flex items-center justify-center rounded-xl bg-[#0052CC] px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#0047B3]">Open full report</a>
                        @endif
                        @if ($import->canReopenMapping())
                            <form method="post" action="{{ route('products.import.reopen-mapping', ['productImportId' => $import->id]) }}" class="inline-flex">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-[#93C5FD] bg-[#EFF6FF] px-4 py-2 text-sm font-bold text-[#1D4ED8] shadow-sm hover:bg-[#DBEAFE]">Re-edit column mapping</button>
                            </form>
                        @endif
                    </div>
                    @if (!empty($summary['failures']))
                        <div class="mt-6 border-t border-[#E2E8F0] pt-6">
                            <h2 class="text-sm font-semibold text-[#64748B]">Quick list (first {{ count($summary['failures']) }} rows)</h2>
                            <p class="mt-1 text-xs text-[#64748B]">For the complete list, filters, and retry, use the full report.</p>
                            <ul class="mt-3 max-h-64 overflow-y-auto text-sm text-[#B42318]">
                                @foreach ($summary['failures'] as $f)
                                    <li class="py-1 border-b border-[#F1F5F9] last:border-0">Row {{ $f['row'] }}: {{ $f['message'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @else
                    <p class="text-sm text-[#64748B]">This import is at an earlier step. You can pick up where you left off from your products area or import history.</p>
                    <a href="{{ route('products.import.history') }}" class="mt-4 inline-flex text-sm font-semibold text-[#0052CC]">Go to import history</a>
                @endif
            </div>
        </div>
    </div>
    @if ($waiting)
        <script>setTimeout(function () { window.location.reload(); }, 3000);</script>
    @endif
    @if ($import->status === ProductImport::STATUS_COMPLETED && ($summary['total_images'] ?? 0) > 0)
        @php
            $__imgTot = (int) ($summary['total_images'] ?? 0);
            $__imgProc = (int) ($summary['processed_images'] ?? 0);
            $__imgFail = (int) ($summary['failed_images'] ?? 0);
            $__imgRun = $__imgTot > 0 && ($__imgProc + $__imgFail) < $__imgTot;
        @endphp
        @if ($__imgRun)
            <script>
                (function () {
                    var url = @json(route('products.import.progress', ['productImportId' => $import->id]));
                    function apply(data) {
                        if (!data || !data.images) return;
                        var ip = document.getElementById('js-import-img-processed');
                        var it = document.getElementById('js-import-img-total');
                        var ifa = document.getElementById('js-import-img-failed');
                        var note = document.getElementById('js-import-image-note');
                        if (ip) ip.textContent = String(data.images.processed);
                        if (it) it.textContent = String(data.images.total);
                        if (ifa) ifa.textContent = String(data.images.failed);
                        var done = data.images.total > 0 && (data.images.processed + data.images.failed) >= data.images.total;
                        if (note && done) {
                            if (data.images.failed > 0) {
                                note.textContent = 'Your products are ready. Some images could not be downloaded — you can edit products to add images manually.';
                            } else {
                                note.textContent = 'All images processed successfully.';
                            }
                        }
                    }
                    function poll() {
                        fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                            .then(function (r) { return r.ok ? r.json() : null; })
                            .then(function (d) {
                                apply(d);
                                var t = d && d.images ? d.images.total : 0;
                                var p = d && d.images ? d.images.processed : 0;
                                var f = d && d.images ? d.images.failed : 0;
                                if (t > 0 && (p + f) < t) {
                                    setTimeout(poll, 4000);
                                }
                            })
                            .catch(function () { setTimeout(poll, 5000); });
                    }
                    setTimeout(poll, 4000);
                })();
            </script>
        @endif
    @endif
@endsection
