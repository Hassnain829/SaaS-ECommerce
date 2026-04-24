@extends('layouts.user.user-sidebar')

@section('title', 'Import products - BaaS Core')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="flex-1 overflow-y-auto p-4 lg:p-8">
        <div class="mx-auto max-w-3xl">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Catalog</p>
                    <h1 class="mt-1 text-2xl font-semibold text-[#0F172A] font-[Poppins]">Bulk product import</h1>
                    <p class="mt-2 max-w-xl text-sm text-[#64748B]">Upload a CSV or Excel file with a header row. We detect common column names automatically; you will land on a quick preview when we are confident, or on a short mapping step only when your headers need a manual match.</p>
                </div>
                <a href="{{ route('products') }}" class="text-sm font-semibold text-[#0052CC] hover:text-[#0047B3]">← Back to products</a>
            </div>

            <div class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-8">
                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#B42318]">
                        <ul class="ml-5 list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <div>
                        <label for="import_file" class="mb-2 block text-sm font-medium text-[#334155]">Spreadsheet file</label>
                        <input id="import_file" name="file" type="file" accept=".csv,.txt,.xlsx" required
                               class="w-full rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-4 text-sm text-[#0F172A] file:mr-4 file:rounded-lg file:border-0 file:bg-[#0052CC] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-[#0047B3]">
                        <p class="mt-2 text-xs text-[#64748B]">Accepted: CSV, TXT, XLSX. Max 15 MB. First row must be column headers. Each column name must be unique (duplicates, including different capitalization of the same name, are rejected so data does not collide).</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#0052CC] px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#0047B3]">
                            Upload and continue
                        </button>
                        <a href="{{ route('products.import.template') }}" class="text-sm font-semibold text-[#475569] underline decoration-[#CBD5E1] underline-offset-4 hover:text-[#0F172A]">Download sample CSV</a>
                    </div>
                </form>
                @if (config('app.debug'))
                    @php $qdiag = \App\Support\Catalog\ProductImportQueue::diagnostics(); @endphp
                    <div class="mt-8 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-3 text-xs text-[#475569]">
                        <p class="font-semibold text-[#334155]">Developer: import queue resolution</p>
                        <dl class="mt-2 grid gap-1 font-mono text-[11px]">
                            @foreach ($qdiag as $k => $v)
                                <div class="flex gap-2"><dt class="shrink-0 text-[#64748B]">{{ $k }}</dt><dd class="break-all">{{ is_bool($v) ? ($v ? 'true' : 'false') : (string) json_encode($v) }}</dd></div>
                            @endforeach
                        </dl>
                        <p class="mt-2 text-[#64748B]">If this does not match your .env, run <span class="font-mono">php artisan optimize:clear</span> (stale config cache).</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
