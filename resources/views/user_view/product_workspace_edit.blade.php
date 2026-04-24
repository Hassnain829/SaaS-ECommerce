@extends('layouts.user.user-sidebar')

@section('title', 'Edit — '.$product->name.' — Product workspace')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="flex-1 overflow-y-auto bg-[#F1F5F9]/50 p-4 lg:p-10">
        <div class="mx-auto max-w-[1480px] space-y-8">
            @include('user_view.partials.flash_success')

            <header class="rounded-2xl border border-[#E2E8F0] bg-white px-5 py-5 shadow-sm ring-1 ring-slate-900/[0.03] sm:px-7 sm:py-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-2">
                        <a href="{{ route('products.show', $product) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[#0052CC] hover:underline">
                            <span aria-hidden="true">←</span> Back to product workspace
                        </a>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">Edit catalog item</p>
                            <h1 class="mt-1 text-2xl font-semibold leading-tight text-[#0F172A] font-[Poppins] sm:text-3xl break-words">{{ $product->name }}</h1>
                            <p class="mt-1 text-sm text-[#64748B]">Store: <span class="font-medium text-[#334155]">{{ $selectedStore?->name }}</span></p>
                            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-[#64748B]">
                                Full-width catalog editor: media, pricing, organization, additional details, option groups, and sellable combinations. Use <span class="font-medium text-[#334155]">Save and return to workspace</span> when you are done.
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <script>
                window.__workspaceEditInitialPayload = @json($editProductPayload);
            </script>

            <div id="catalog-editor-workspace-layout" class="lg:grid lg:grid-cols-12 lg:items-start lg:gap-10">
                <div class="min-w-0 space-y-8 lg:col-span-8">
                    @include('user_view.partials.product_edit_modal', [
                        'productEditSurface' => 'page',
                        'productEditPageNative' => true,
                        'selectedStore' => $selectedStore,
                        'catalogBrands' => $catalogBrands,
                        'catalogTags' => $catalogTags,
                        'catalogTaxonomyCategories' => $catalogTaxonomyCategories,
                        'workspaceReturnProductId' => $workspaceReturnProductId,
                    ])
                </div>
                <aside class="mt-10 space-y-6 lg:col-span-4 lg:mt-0">
                    <div class="lg:sticky lg:top-6 space-y-6">
                        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-900/[0.04]">
                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Editor</p>
                            <p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $product->status ? 'Published' : 'Draft' }}</p>
                            <p class="mt-3 text-xs text-[#64748B]">Active store</p>
                            <p class="mt-0.5 text-sm font-medium text-[#334155]">{{ $selectedStore?->name }}</p>
                            @php
                                $product->loadMissing('variants');
                                $sumStock = (int) $product->variants->sum('stock');
                            @endphp
                            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Inventory summary</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-[#0F172A]">{{ number_format($sumStock) }}</p>
                            <p class="mt-0.5 text-xs text-[#64748B]">Total units across all variant rows</p>
                            <p class="mt-1 text-xs text-[#64748B]">Updated {{ optional($product->updated_at)->diffForHumans() }}</p>
                            <div class="mt-5 flex flex-col gap-2 border-t border-slate-100 pt-4">
                                <button type="submit" form="editProductForm" class="inline-flex w-full items-center justify-center rounded-xl bg-[#0052CC] px-4 py-3 text-sm font-bold text-white shadow-md shadow-[#0052CC]/20 transition hover:bg-[#0042a3]">Save and return to workspace</button>
                                <a href="{{ route('products.show', $product) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2.5 text-sm font-semibold text-[#475569] transition hover:bg-white">View workspace only</a>
                                <a href="{{ route('products') }}" class="inline-flex w-full items-center justify-center rounded-xl border border-transparent px-4 py-2 text-sm font-semibold text-[#0052CC] hover:underline">Back to product list</a>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-[#E0E7FF] bg-[#F8FAFF] p-4 text-xs leading-relaxed text-[#475569]">
                            <span class="font-semibold text-[#0F172A]">Additional details</span> are editable extra product information your team wants to track, such as supplier, material, origin, ingredients, care notes, warranty, or internal references. <span class="font-semibold text-[#0F172A]">Advanced imported data</span> on the workspace is read-only spreadsheet fields preserved because they were not mapped during import—use <span class="font-semibold text-[#0F172A]">Make editable</span> there when you want a copy in additional details.
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
@endsection
