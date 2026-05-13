@extends('layouts.user.user-sidebar')

@section('title', 'Add product')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="flex-1 overflow-y-auto bg-[#F1F5F9]/50 p-4 lg:p-10">
        <div class="mx-auto max-w-3xl space-y-6">
            @include('user_view.partials.flash_success')

            <header class="rounded-2xl border border-[#E2E8F0] bg-white px-5 py-5 shadow-sm ring-1 ring-slate-900/[0.03] sm:px-7 sm:py-6">
                <div class="space-y-2">
                    <a href="{{ route('products') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[#0052CC] hover:underline">
                        <span aria-hidden="true">←</span> Back to products
                    </a>
                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">Catalog</p>
                    <h1 class="mt-1 text-2xl font-semibold leading-tight text-[#0F172A] font-[Poppins] sm:text-3xl">Add product</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-[#64748B]">
                        Start with the essentials for your <span class="font-medium text-[#334155]">active store</span>. After you save, you will land in the full product workspace to add option groups, more images, variants, and other details.
                    </p>
                </div>
            </header>

            @include('user_view.partials.product_create_form', [
                'productModalSelectedStore' => $selectedStore,
                'catalogBrands' => $catalogBrands ?? collect(),
                'catalogTags' => $catalogTags ?? collect(),
                'catalogTaxonomyCategories' => $catalogTaxonomyCategories ?? collect(),
                'productCreateCancelUrl' => route('products'),
            ])
        </div>
    </div>
@endsection
