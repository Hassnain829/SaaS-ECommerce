@extends('layouts.user.user-sidebar')

@section('title', $product->name.' — Quick view')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="flex-1 overflow-y-auto p-4 lg:p-8">
        <div class="mx-auto max-w-3xl">
            <a href="{{ route('products') }}" class="text-sm font-semibold text-[#0052CC] hover:underline">← Back to products</a>

            <div class="mt-6 rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Quick view</p>
                <h1 class="mt-2 text-2xl font-semibold text-[#0F172A] font-[Poppins]">{{ $product->name }}</h1>
                <p class="mt-2 text-sm text-[#64748B]">SKU <span class="font-mono text-[#0F172A]">{{ $product->sku ?: '—' }}</span></p>

                <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                    <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                        <dt class="text-[#64748B]">Status</dt>
                        <dd class="mt-1 font-semibold text-[#0F172A]">{{ $product->status ? 'Published' : 'Draft' }}</dd>
                    </div>
                    <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                        <dt class="text-[#64748B]">Base price</dt>
                        <dd class="mt-1 font-semibold text-[#0F172A]">{{ $selectedStore?->currency ?? 'USD' }} {{ number_format((float) $product->base_price, 2) }}</dd>
                    </div>
                    @if ($product->brand)
                        <div class="rounded-xl bg-[#F8FAFC] px-4 py-3 sm:col-span-2">
                            <dt class="text-[#64748B]">Brand</dt>
                            <dd class="mt-1 font-semibold text-[#0F172A]">{{ $product->brand->name }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($product->categories->isNotEmpty())
                    <div class="mt-6">
                        <p class="text-xs font-semibold uppercase text-[#64748B]">Categories</p>
                        <p class="mt-2 text-sm text-[#334155]">{{ $product->categories->pluck('name')->implode(', ') }}</p>
                    </div>
                @endif

                @if ($product->tags->isNotEmpty())
                    <div class="mt-4">
                        <p class="text-xs font-semibold uppercase text-[#64748B]">Tags</p>
                        <p class="mt-2 text-sm text-[#334155]">{{ $product->tags->pluck('name')->implode(', ') }}</p>
                    </div>
                @endif

                @if ($product->variants->isNotEmpty())
                    <div class="mt-6 border-t border-[#E2E8F0] pt-6">
                        <p class="text-xs font-semibold uppercase text-[#64748B]">Variants (summary)</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($product->variants as $v)
                                <li class="flex justify-between gap-4 rounded-lg bg-[#F8FAFC] px-3 py-2">
                                    <span class="font-mono text-xs text-[#334155]">{{ $v->sku }}</span>
                                    <span class="text-[#0F172A]">Stock <strong>{{ (int) $v->stock }}</strong></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p class="mt-8 text-xs text-[#94A3B8]">A full product detail workspace is planned on the roadmap. Use <strong>Edit</strong> on the products list for rich changes.</p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('products') }}" class="inline-flex items-center rounded-xl border border-[#E2E8F0] px-5 py-2.5 text-sm font-semibold text-[#0052CC] hover:bg-[#F8FAFC]">Back to products</a>
                    <a href="{{ route('products', ['q' => $product->sku ?: $product->name]) }}" class="inline-flex items-center rounded-xl bg-[#0052CC] px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#0047B3]">Find in list</a>
                </div>
            </div>
        </div>
    </div>
@endsection
