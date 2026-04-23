@extends('layouts.user.user-sidebar')

@section('title', 'Import preview')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="flex-1 overflow-y-auto p-4 lg:p-8">
        <div class="mx-auto max-w-4xl">
            <div class="mb-8">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Step 3 of 4</p>
                <h1 class="mt-1 text-2xl font-semibold text-[#0F172A] font-[Poppins]">Validation preview</h1>
                <p class="mt-2 text-sm text-[#64748B]">Review what we found in your file, including categories and brands we will create if they are new, before you start the import.</p>
            </div>

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#B42318]">
                    <ul class="ml-5 list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-[#64748B]">Row summary</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-[#64748B]">Columns used</dt><dd class="font-semibold text-[#0F172A]">{{ $preview['columns_used_count'] ?? '—' }} / {{ $preview['columns_total_count'] ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#64748B]">Rows sampled</dt><dd class="font-semibold text-[#0F172A]">{{ $preview['total_rows_sampled'] ?? 0 }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#64748B]">Valid rows</dt><dd class="font-semibold text-[#059669]">{{ $preview['valid_rows'] ?? 0 }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#64748B]">Invalid rows</dt><dd class="font-semibold text-[#B42318]">{{ $preview['invalid_rows'] ?? 0 }}</dd></div>
                        @if (!empty($preview['total_rows_truncated']))
                            <p class="text-xs text-[#B45309]">Preview capped at 5,000 rows; full file still imports.</p>
                        @endif
                    </dl>
                </div>
                <div class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-[#64748B]">Taxonomy (will be created if missing)</h2>
                    <ul class="mt-4 space-y-2 text-sm text-[#334155]">
                        <li><span class="font-medium text-[#0F172A]">Brands:</span> {{ empty($preview['brands_to_create']) ? '—' : implode(', ', $preview['brands_to_create']) }}</li>
                        <li><span class="font-medium text-[#0F172A]">Categories:</span> {{ empty($preview['categories_to_create']) ? '—' : implode(', ', $preview['categories_to_create']) }}</li>
                        <li><span class="font-medium text-[#0F172A]">Tags:</span> {{ empty($preview['tags_to_create']) ? '—' : implode(', ', $preview['tags_to_create']) }}</li>
                    </ul>
                </div>
            </div>

            @if (!empty($preview['custom_field_preview_lines']))
                <div class="mt-6 rounded-2xl border border-[#DCFCE7] bg-[#F0FDF4] px-4 py-3 text-sm text-[#14532D]">
                    <p class="font-semibold">Custom fields (structured meta)</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 font-mono text-xs">
                        @foreach ($preview['custom_field_preview_lines'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (!empty($preview['duplicate_skus_in_file']))
                <div class="mt-6 rounded-2xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                    <p class="font-semibold">Duplicate SKUs in file</p>
                    <p class="mt-1">SKUs: {{ implode(', ', $preview['duplicate_skus_in_file']) }}. Duplicate rows are rejected during import so stock and identity stay consistent.</p>
                </div>
            @endif

            @if (!empty($preview['unmapped_headers']))
                <div class="mt-6 rounded-2xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3 text-sm text-[#1E3A8A]">
                    <p class="font-semibold">Extra columns we will keep with each product</p>
                    <p class="mt-1 text-xs text-[#1E3A8A]">These columns are not mapped to a catalog field or custom key. We still save non-empty values so you do not lose data.</p>
                    <p class="mt-2 font-mono text-xs">{{ implode(', ', $preview['unmapped_headers']) }}</p>
                </div>
            @endif

            @if (!empty($preview['invalid_samples']))
                <div class="mt-6 rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold text-[#64748B]">Sample issues</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($preview['invalid_samples'] as $sample)
                            <li class="text-[#B42318]"><span class="font-medium">Row {{ $sample['row'] }}:</span> {{ implode(' ', $sample['messages']) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-8 flex flex-wrap items-center gap-4">
                <form method="post" action="{{ route('products.import.confirm', ['productImportId' => $import->id]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#0052CC] px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-[#0047B3]">
                        Confirm &amp; run import
                    </button>
                </form>
                <a href="{{ route('products.import.mapping', ['productImportId' => $import->id]) }}" class="text-sm font-semibold text-[#475569] underline">Adjust mapping</a>
                <a href="{{ route('products') }}" class="text-sm text-[#64748B]">Cancel</a>
            </div>
        </div>
    </div>
@endsection
