@extends('layouts.user.user-sidebar')

@php
    use Illuminate\Support\Str;
    $storeForView = $selectedStore ?? $currentStore;
    $currency = optional($storeForView)->currency ?? 'USD';
    $meta = is_array($product->meta) ? $product->meta : [];
    $compareAt = $catalog['compare_at_price'] ?? null;
    $costPrice = $catalog['cost_price'] ?? null;
    $shortDesc = $catalog['short_description'] ?? null;
    $readyImages = $product->images->filter(fn ($img) => $img->isReady());
    $primaryImg = $readyImages->first(fn ($img) => $img->is_primary) ?? $readyImages->first();
    $primaryUrl = $primaryImg ? asset('storage/'.$primaryImg->image_path) : null;
    $lowStock = $totalStock > 0 && $effectiveLowThreshold > 0 && $totalStock <= $effectiveLowThreshold;
    $outOfStock = $totalStock === 0;
    $movementLabels = [
        'initial' => 'Initial stock',
        'manual_adjustment' => 'Manual adjustment',
        'edit_update' => 'Catalog edit',
        'import' => 'Catalog import',
        'backfill' => 'Inventory backfill',
        'order_reserved' => 'Order reserved',
        'order_committed' => 'Order committed',
        'order_deducted' => 'Order deducted',
        'reservation_released' => 'Reservation released',
    ];
    $optionGroupSummaries = $optionGroupSummaries ?? [];
    $productBehavior = $productBehavior ?? \App\Support\ProductTypeBehavior::behaviorFor($product->product_type);
    $attributeRows = $attributeRows ?? [];
    $hasMedia = $readyImages->isNotEmpty() || $product->images->isNotEmpty();
    $workspaceStoreId = (int) (optional($storeForView)->id ?? 0);
    $hasOrganization = ($product->brand && (int) $product->brand->store_id === $workspaceStoreId)
        || $product->categories->contains(fn ($c): bool => (int) $c->store_id === $workspaceStoreId)
        || $product->tags->contains(fn ($t): bool => (int) $t->store_id === $workspaceStoreId);
    $hasCustom = $customFieldRows !== [];
    $hasAttributes = $attributeRows !== [];
    $hasImportExtra = $importExtraRows !== [];
    $variantCount = count($variantSummaries);
    $multiVariant = $variantCount > 1;
@endphp

@section('title', $product->name.' — Product workspace')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('topbar')
    <x-ui.merchant-topbar title="Product workspace" :lead="$product->name">
        <x-slot:actions>
            <a href="{{ route('products') }}" class="hidden sm:inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-600 transition hover:bg-stone-50">
                Catalog
            </a>
            @if ($canManageCatalog)
                <a href="{{ route('products.edit', $product) }}" class="hidden sm:inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-bold text-white shadow-sm transition hover:bg-brand-hover">
                    Edit details
                </a>
            @endif
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="product-workspace-page -m-4 min-h-full bg-[#F4F1EA] p-4 lg:-m-8 lg:p-10">
        <div class="w-full space-y-8">
            @include('user_view.partials.flash_success')

            <header class="product-workspace-hero relative overflow-hidden rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-[0_4px_20px_rgba(71,85,105,0.06)] sm:p-10" aria-labelledby="product-workspace-overview-heading">
                <div class="absolute inset-y-0 left-0 w-1.5 bg-brand" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1 space-y-4">
                        <div>
                            <div class="mb-4 flex flex-wrap items-center gap-3">
                                @if ($product->status)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Published</span>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Draft</span>
                                @endif
                                <span class="text-sm text-[#454652]">Visibility follows status when you sell on the storefront.</span>
                            </div>
                            <h2 id="product-workspace-overview-heading" class="break-words text-2xl font-semibold leading-tight text-[#1A1B22] sm:text-4xl">{{ $product->name }}</h2>
                        </div>
                        <p class="max-w-3xl text-base leading-relaxed text-[#454652]">
                            One place to review catalog data, inventory, and storefront context for <span class="font-semibold text-[#1A1B22]">{{ $selectedStore?->name ?? 'this store' }}</span>.
                        </p>
                        <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-lg bg-[#F4F2FC] px-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Store SKU</dt>
                                <dd class="mt-1 font-mono text-sm font-semibold text-[#0F172A]">{{ $product->sku ?: '—' }}</dd>
                            </div>
                            <div class="rounded-lg bg-[#F4F2FC] px-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Product behavior</dt>
                                <dd class="mt-1 text-sm font-medium text-[#334155]">{{ $productBehavior['label'] ?? Str::title(str_replace(['-', '_'], ' ', $product->product_type)) }}</dd>
                            </div>
                            <div class="rounded-lg bg-[#F4F2FC] px-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Platform tax</dt>
                                <dd class="mt-1 text-sm font-medium text-[#334155]">{{ $product->is_taxable ? 'Tax applies' : 'Tax off' }}</dd>
                            </div>
                            <div class="rounded-lg bg-[#F4F2FC] px-4 py-3 sm:col-span-2 lg:col-span-1">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Timeline</dt>
                                <dd class="mt-1 text-sm text-[#334155]">
                                    Added {{ optional($product->created_at)->format('M j, Y') }}
                                    @if ($product->updated_at && $product->updated_at->ne($product->created_at))
                                        <span class="text-[#94A3B8]">·</span> Updated {{ $product->updated_at->format('M j, Y') }}
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </header>

            <div class="product-workspace-layout grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-start">
                <div class="product-workspace-contents">
                    <section class="product-workspace-card product-workspace-media-card rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-sm sm:p-8">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-[#0F172A]">Media</h2>
                                <p class="mt-1 text-sm text-[#64748B]">Photos and visuals shoppers see for this product.</p>
                            </div>
                            @if ($canManageCatalog)
                                <a href="{{ route('products.edit', $product) }}#catalog-edit-section-media" class="text-sm font-semibold text-[#24389C] hover:underline">Manage images</a>
                            @endif
                        </div>
                        @if (! $hasMedia)
                            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                                @if ($canManageCatalog)
                                    <a href="{{ route('products.edit', $product) }}#catalog-edit-section-media" class="flex aspect-square flex-col items-center justify-center rounded-xl border-2 border-dashed border-[#C5C5D4] bg-[#F4F2FC] text-center text-sm font-medium text-[#757684] transition hover:border-[#24389C] hover:text-[#24389C]">
                                        <span class="text-2xl leading-none" aria-hidden="true">＋</span>
                                        <span class="mt-2">Add photo</span>
                                    </a>
                                @else
                                    <div class="flex aspect-square flex-col items-center justify-center rounded-xl border-2 border-dashed border-[#C5C5D4] bg-[#F4F2FC] px-4 text-center text-sm text-[#757684]">
                                        No catalog images yet
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                                @foreach ($readyImages as $img)
                                    <div class="relative aspect-square overflow-hidden rounded-xl border border-[#E3E1EA] bg-[#F4F2FC]">
                                        <img src="{{ asset('storage/'.$img->image_path) }}" alt="" class="h-full w-full object-cover">
                                        @if ($img->is_primary)
                                            <span class="absolute bottom-2 left-2 rounded-full bg-white/90 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-[#24389C] shadow-sm">Primary</span>
                                        @endif
                                    </div>
                                @endforeach
                                @if ($canManageCatalog)
                                    <a href="{{ route('products.edit', $product) }}#catalog-edit-section-media" class="flex aspect-square flex-col items-center justify-center rounded-xl border-2 border-dashed border-[#C5C5D4] bg-[#F4F2FC] text-center text-sm font-medium text-[#757684] transition hover:border-[#24389C] hover:text-[#24389C]">
                                        <span class="text-2xl leading-none" aria-hidden="true">＋</span>
                                        <span class="mt-2">Add photo</span>
                                    </a>
                                @endif
                            </div>
                            @if ($product->images->contains(fn ($i) => $i->isPendingVisual() || $i->isFailed()))
                                <p class="mt-4 text-xs text-[#64748B]">Some images are still processing or could not be loaded from your import.</p>
                            @endif
                        @endif
                    </section>

                    @if (filled($product->description))
                        <section class="product-workspace-card product-workspace-copy-card rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-sm sm:p-8">
                            <div class="border-b border-[#F1F5F9] pb-4">
                                <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Selling information</p>
                                <h2 class="mt-1 text-lg font-semibold text-[#0F172A]">Storefront copy</h2>
                                <p class="mt-1 text-sm text-[#64748B]">Description shown to shoppers where your theme displays it.</p>
                            </div>
                            <div class="mt-6 max-w-none text-sm leading-relaxed text-[#334155] whitespace-pre-wrap">{{ $product->description }}</div>
                        </section>
                    @endif

                    <section class="product-workspace-card product-workspace-specifications-card rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-sm sm:p-8">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Structured catalog facts</p>
                            <h2 class="mt-1 text-lg font-semibold text-[#0F172A]">Product specifications</h2>
                            <p class="mt-1 text-sm text-[#64748B]">Reusable product facts for filtering and comparison. Shopper choices such as size or color combinations still live under option groups.</p>
                        </div>
                        @if ($hasAttributes)
                            <dl class="mt-6 grid gap-3 sm:grid-cols-2">
                                @foreach ($attributeRows as $attributeRow)
                                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                                        <dt class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-[#64748B]">
                                            <span>{{ $attributeRow['name'] }}</span>
                                            @if (! empty($attributeRow['is_filterable']))
                                                <span class="rounded-full bg-[#EEF4FF] px-2 py-0.5 text-[10px] font-bold text-[#0052CC]">Filterable</span>
                                            @endif
                                        </dt>
                                        <dd class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($attributeRow['terms'] as $term)
                                                <span class="inline-flex rounded-lg border border-[#CBD5E1] bg-white px-2.5 py-1 text-xs font-semibold text-[#334155]">{{ $term }}</span>
                                            @endforeach
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @else
                            <div class="mt-6 rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-5 py-6 text-sm text-[#64748B]">
                                @if ($canManageCatalog)
                                    <p class="font-medium text-[#334155]">No attributes selected yet.</p>
                                    <p class="mt-2">Create store attributes, then assign terms from <a href="{{ route('products.edit', $product) }}" class="font-semibold text-[#0052CC] hover:underline">Edit product</a>.</p>
                                @else
                                    <p>No structured attributes have been saved for this product yet.</p>
                                @endif
                            </div>
                        @endif
                    </section>

                    <section class="product-workspace-card product-workspace-behavior-card rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-sm sm:p-8">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            <h2 class="text-lg font-semibold text-[#0F172A]">Product behavior</h2>
                            <p class="mt-1 text-sm text-[#64748B]">How this product is sold and fulfilled in your catalog workflow.</p>
                        </div>
                        <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Behavior label</dt>
                                <dd class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $productBehavior['label'] }}</dd>
                            </div>
                            <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Shipping required</dt>
                                <dd class="mt-1 text-sm font-semibold text-[#0F172A]">{{ !empty($productBehavior['requires_shipping']) ? 'Yes' : 'No' }}</dd>
                            </div>
                            <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Inventory tracking</dt>
                                <dd class="mt-1 text-sm font-semibold text-[#0F172A]">{{ !empty($productBehavior['track_inventory']) ? 'Yes' : 'No' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="product-workspace-card product-workspace-additional-card rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-sm sm:p-8" aria-labelledby="workspace-additional-details-heading">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            <h2 id="workspace-additional-details-heading" class="text-lg font-semibold text-[#0F172A]">Additional product details</h2>
                            <p class="mt-1 text-sm text-[#64748B]"><span class="font-medium text-[#334155]">Additional details</span> are fields you choose and can edit (supplier, material, origin, care notes, ingredients, internal references). They are not the same as read-only spreadsheet columns: those live under <span class="font-medium text-[#334155]">Advanced imported data</span> in the sidebar when an import left columns unmapped.</p>
                        </div>
                        @if ($hasCustom)
                            <dl class="mt-6 grid gap-3 sm:grid-cols-2">
                                @foreach ($customFieldRows as $row)
                                    <div class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 shadow-sm">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">{{ $row['label'] }}</dt>
                                        <dd class="mt-1.5 text-sm font-medium text-[#0F172A] break-words">
                                            @if (\App\Support\ProductDetailPresenter::isLong($row['value_display']))
                                                <details class="group">
                                                    <summary class="cursor-pointer text-[#0052CC] hover:underline">{{ Str::limit($row['value_display'], 120) }}</summary>
                                                    <pre class="mt-2 max-h-48 overflow-auto rounded-md bg-[#F1F5F9] p-2 text-xs text-[#334155]">{{ $row['value_display'] }}</pre>
                                                </details>
                                            @else
                                                {{ $row['value_display'] }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @else
                            <div class="mt-6 rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-5 py-6 text-sm text-[#64748B]">
                                @if ($canManageCatalog)
                                    <p class="font-medium text-[#334155]">No additional details yet.</p>
                                    <p class="mt-2">Add supplier notes, material, origin, care instructions, ingredients, or other store-specific information from <a href="{{ route('products.edit', $product) }}" class="font-semibold text-[#0052CC] hover:underline">Edit product</a>. Fields from your import mapping also appear here when they target product-level additional details.</p>
                                @else
                                    <p>No additional details have been saved for this product yet. Ask a store owner or manager to add them in the catalog if your team needs this information on hand.</p>
                                @endif
                            </div>
                        @endif
                    </section>

                    @if ($optionGroupSummaries !== [])
                        <section class="product-workspace-card product-workspace-options-card rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-sm sm:p-8">
                            <div class="border-b border-[#F1F5F9] pb-4">
                                <h2 class="text-lg font-semibold text-[#0F172A]">Option groups</h2>
                                <p class="mt-1 text-sm text-[#64748B]">Each group is one set of choices shoppers pick (for example Size, then Color). <span class="font-medium text-[#334155]">Sellable combinations</span> below lists every combination you sell.</p>
                            </div>
                            <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                                @foreach ($optionGroupSummaries as $group)
                                    <li class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-4">
                                        <p class="text-sm font-semibold text-[#0F172A]">{{ $group['name'] }}</p>
                                        <p class="mt-2 text-xs font-medium uppercase tracking-wide text-[#94A3B8]">Values</p>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($group['values'] as $val)
                                                <span class="inline-flex rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-sm font-medium text-[#0F172A] shadow-sm">{{ $val }}</span>
                                            @endforeach
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <section class="product-workspace-card product-workspace-variants-card overflow-hidden rounded-xl border border-[#E3E1EA] bg-white shadow-sm">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            @if ($multiVariant)
                                <h2 class="text-lg font-semibold text-[#0F172A]">Sellable combinations and inventory</h2>
                                <p class="mt-1 text-sm text-[#64748B]">Each row is one variant (a combination of your option groups) with its own SKU, price, compare-at, available stock, optional photo, and optional extra details. Totals in <span class="font-medium text-[#334155]">Pricing &amp; inventory</span> roll up from inventory locations.</p>
                            @else
                                <h2 class="text-lg font-semibold text-[#0F172A]">Default inventory</h2>
                                <p class="mt-1 text-sm text-[#64748B]">
                                    This product has one inventory row. Available stock comes from its inventory location.
                                    @if ($canManageCatalog)
                                        Add option groups in <span class="font-medium text-[#334155]">Edit product</span> only if shoppers should choose size, color, pack, or similar variations.
                                    @else
                                        Ask a store owner or manager if you need option groups later.
                                    @endif
                                </p>
                            @endif
                        </div>
                        @if ($variantSummaries === [])
                            <p class="mt-6 text-sm text-[#64748B]">No sellable rows are linked to this product yet.</p>
                        @else
                            <div class="mt-6 overflow-x-auto rounded-2xl border border-[#E2E8F0] shadow-sm">
                                <table class="w-full min-w-[880px] text-left text-sm" aria-describedby="variant-table-caption">
                                    <caption id="variant-table-caption" class="sr-only">
                                        @if ($multiVariant)
                                            Sellable combinations: variant photo, shopper choices, optional extra details, SKU, pricing, available stock, location, and low-stock alert.
                                        @else
                                            Default inventory: listing photo, SKU, pricing, available stock, location, and low-stock alert.
                                        @endif
                                    </caption>
                                    <thead class="bg-[#F1F5F9] text-xs font-bold uppercase tracking-wide text-[#64748B]">
                                        <tr>
                                            <th class="px-4 py-3.5">{{ $multiVariant ? 'Variant photo' : 'Listing photo' }}</th>
                                            <th class="px-4 py-3.5">{{ $multiVariant ? 'Sellable combination' : 'Inventory row' }}</th>
                                            <th class="min-w-[8rem] px-4 py-3.5">Extra details</th>
                                            <th class="px-4 py-3.5">SKU</th>
                                            <th class="min-w-[7rem] px-4 py-3.5">Retail price</th>
                                            <th class="min-w-[7rem] px-4 py-3.5">Compare-at</th>
                                            <th class="min-w-[5rem] px-4 py-3.5">Available</th>
                                            <th class="min-w-[6rem] px-4 py-3.5">Location</th>
                                            <th class="min-w-[6rem] px-4 py-3.5">Low-stock alert</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#F1F5F9]">
                                        @foreach ($variantSummaries as $row)
                                            <tr class="bg-white even:bg-[#FAFBFC]">
                                                <td class="px-4 py-3.5 align-top">
                                                    @if (! empty($row['catalog_image_thumb']))
                                                        <img src="{{ $row['catalog_image_thumb'] }}" alt="" class="h-14 w-14 rounded-xl border border-[#E2E8F0] object-cover shadow-sm" title="{{ ! empty($row['catalog_image_is_product_fallback']) ? 'Catalog image (main product photo)' : 'Variant catalog image' }}">
                                                        @if (! empty($row['catalog_image_is_product_fallback']))
                                                            <p class="mt-1 max-w-[7rem] text-[10px] leading-tight text-[#64748B]">Main product image</p>
                                                        @endif
                                                    @else
                                                        <span class="text-xs text-[#94A3B8]">No image</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5 align-top">
                                                    <div class="font-medium text-[#0F172A]">{{ $row['label'] }}</div>
                                                    @if (! empty($row['chips']))
                                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                                            @foreach ($row['chips'] as $chip)
                                                                <span class="inline-flex rounded-lg border border-[#CBD5E1] bg-white px-2.5 py-1 text-xs font-medium text-[#0F172A] shadow-sm">{{ $chip['group'] }}: {{ $chip['value'] }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    @if ($row['is_first'])
                                                        <span class="mt-2 inline-flex rounded-md bg-[#EEF4FF] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#0052CC]">Default variant</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5 align-top">
                                                    @if (! empty($row['additional_detail_rows']))
                                                        <details class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2">
                                                            <summary class="cursor-pointer text-xs font-semibold text-[#0052CC] hover:underline">
                                                                Extra details ({{ count($row['additional_detail_rows']) }})
                                                            </summary>
                                                            <dl class="mt-2 max-h-48 space-y-2 overflow-y-auto text-xs">
                                                                @foreach ($row['additional_detail_rows'] as $detailRow)
                                                                    <div class="rounded-md border border-[#F1F5F9] bg-white px-2 py-1.5">
                                                                        <dt class="font-semibold text-[#64748B]">{{ $detailRow['label'] }}</dt>
                                                                        <dd class="mt-0.5 text-[#0F172A] break-words">{{ $detailRow['value_display'] }}</dd>
                                                                    </div>
                                                                @endforeach
                                                            </dl>
                                                        </details>
                                                    @else
                                                        <span class="text-xs text-[#94A3B8]">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5 align-top font-mono text-xs text-[#334155]">{{ $row['sku'] }}</td>
                                                <td class="px-4 py-3.5 align-top text-sm font-semibold tabular-nums text-[#0F172A]">{{ $currency }} {{ number_format((float) $row['price'], 2) }}</td>
                                                <td class="px-4 py-3.5 align-top text-sm font-semibold tabular-nums text-[#64748B]">
                                                    @if (! empty($row['compare_at_price']))
                                                        {{ $currency }} {{ number_format((float) $row['compare_at_price'], 2) }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5 align-top text-sm font-semibold tabular-nums text-[#0F172A]">{{ number_format($row['stock']) }}</td>
                                                <td class="px-4 py-3.5 align-top text-sm text-[#64748B]">{{ $row['location_name'] ?? 'Main location' }}</td>
                                                <td class="px-4 py-3.5 align-top text-sm tabular-nums text-[#64748B]">{{ $row['stock_alert'] > 0 ? number_format($row['stock_alert']) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    <section class="product-workspace-card product-workspace-inventory-card">
                        <div class="grid gap-6 lg:grid-cols-3">
                            <div class="rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-sm sm:p-8">
                                <h2 class="text-lg font-semibold text-[#0F172A]">Inventory summary</h2>
                                <dl class="mt-6 space-y-4">
                                    <div class="flex items-center justify-between rounded-lg bg-[#F4F2FC] px-4 py-4">
                                        <dt class="text-sm text-[#64748B]">Total available</dt>
                                        <dd class="text-xl font-bold tabular-nums text-[#24389C]">{{ number_format($totalStock) }}</dd>
                                    </div>
                                    <div class="flex items-center justify-between rounded-lg bg-[#F4F2FC] px-4 py-4">
                                        <dt class="text-sm text-[#64748B]">Low-stock threshold</dt>
                                        <dd class="text-xl font-bold tabular-nums {{ $lowStock || $outOfStock ? 'text-red-600' : 'text-[#24389C]' }}">{{ $effectiveLowThreshold > 0 ? number_format($effectiveLowThreshold) : '—' }}</dd>
                                    </div>
                                </dl>
                                @if ($outOfStock)
                                    <p class="mt-4 text-sm font-semibold text-red-600">This product is out of stock.</p>
                                @elseif ($lowStock)
                                    <p class="mt-4 text-sm font-semibold text-amber-700">Total stock is at or below your low-stock alert.</p>
                                @endif
                            </div>

                            <div class="rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-sm lg:col-span-2 sm:p-8">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <h2 class="text-lg font-semibold text-[#0F172A]">Stock activity</h2>
                                        <p class="mt-1 text-xs text-[#64748B]">Recent audited stock changes for this product.</p>
                                    </div>
                                </div>
                                @if ($recentMovements->isNotEmpty())
                                    <ul class="relative mt-6 space-y-0 border-l-2 border-[#E3E1EA] pl-7 text-sm text-[#334155]">
                                        @foreach ($recentMovements as $mv)
                                            <li class="relative pb-6 last:pb-0">
                                                <span class="absolute -left-[2.15rem] top-1 h-3 w-3 rounded-full border-2 border-white bg-brand ring-1 ring-[#E3E1EA]" aria-hidden="true"></span>
                                                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                                    <p class="font-semibold text-[#0F172A]">
                                                        {{ $movementLabels[$mv->movement_type] ?? Str::title(str_replace('_', ' ', $mv->movement_type)) }}
                                                        <span class="font-normal tabular-nums text-[#64748B]">· {{ (int) $mv->previous_stock }} → {{ (int) $mv->new_stock }}</span>
                                                    </p>
                                                    <span class="shrink-0 text-xs text-[#94A3B8]">{{ optional($mv->created_at)->format('M j, Y g:i A') }}</span>
                                                </div>
                                                <p class="mt-1 text-xs text-[#64748B]">@if ($mv->location){{ $mv->location->name }}@endif @if ($mv->reason) — {{ Str::limit($mv->reason, 80) }}@endif</p>
                                                @if ($mv->performer)
                                                    <p class="mt-1 text-[11px] font-semibold text-[#24389C]/70">{{ $mv->performer->name }}</p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-6 rounded-lg border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-5 text-sm text-[#64748B]">No stock activity has been recorded yet.</p>
                                @endif
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="product-workspace-contents">
                    <section class="product-workspace-card product-workspace-pricing-card rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-sm sm:p-8">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            <h2 class="text-lg font-semibold text-[#0F172A]">Store pricing</h2>
                            <p class="mt-1 text-xs text-[#64748B]">Base list price in {{ $currency }}.</p>
                        </div>
                        <dl class="mt-5 space-y-4 text-sm">
                            <div>
                                <dt class="text-[#64748B]">Base price</dt>
                                <dd class="mt-0.5 text-xl font-semibold text-[#0F172A] tabular-nums">{{ $currency }} {{ number_format((float) $product->base_price, 2) }}</dd>
                            </div>
                            @if ($compareAt !== null && $compareAt !== '')
                                <div>
                                    <dt class="text-[#64748B]">Compare-at price</dt>
                                    <dd class="mt-0.5 font-medium tabular-nums text-[#334155]">{{ $currency }} {{ is_numeric($compareAt) ? number_format((float) $compareAt, 2) : e($compareAt) }}</dd>
                                </div>
                            @endif
                            @if ($costPrice !== null && $costPrice !== '')
                                <div>
                                    <dt class="text-[#64748B]">Cost price</dt>
                                    <dd class="mt-0.5 font-medium tabular-nums text-[#334155]">{{ $currency }} {{ is_numeric($costPrice) ? number_format((float) $costPrice, 2) : e($costPrice) }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if (filled($shortDesc))
                            <div class="mt-6 border-t border-[#F1F5F9] pt-5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Short description</p>
                                <p class="mt-2 text-sm text-[#334155]">{{ $shortDesc }}</p>
                            </div>
                        @endif
                    </section>

                    <section class="product-workspace-card product-workspace-organization-card rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-sm sm:p-8">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            <h2 class="text-lg font-semibold text-[#0F172A]">Organization</h2>
                            <p class="mt-1 text-xs text-[#64748B]">Brand, categories, and tags for this store.</p>
                        </div>
                        <div class="mt-5 grid gap-5 text-sm sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Brand</p>
                                @if ($product->brand && (int) $product->brand->store_id === (int) $selectedStore->id)
                                    <p class="mt-1.5 font-medium text-[#0F172A]">{{ $product->brand->name }}</p>
                                @else
                                    <p class="mt-1.5 text-[#64748B]">No brand on this product yet.</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Categories</p>
                                @if ($product->categories->isNotEmpty())
                                    <ul class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($product->categories as $cat)
                                            @if ((int) $cat->store_id === (int) $selectedStore->id)
                                                <li class="rounded-md border border-[#99F6E4] bg-[#F0FDFA] px-2 py-0.5 text-xs font-semibold text-[#0F766E]">{{ $cat->name }}</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-1.5 text-[#64748B]">Not in a category yet.</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Tags</p>
                                @if ($product->tags->isNotEmpty())
                                    <ul class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($product->tags as $tag)
                                            @if ((int) $tag->store_id === (int) $selectedStore->id)
                                                <li class="rounded-md border border-[#E9D5FF] bg-[#FAF5FF] px-2 py-0.5 text-xs font-semibold text-[#581C87]">{{ $tag->name }}</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-1.5 text-[#64748B]">No tags yet.</p>
                                @endif
                            </div>
                        </div>
                        @if (! $hasOrganization && $canManageCatalog)
                            <p class="mt-5 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2.5 text-xs text-[#64748B]">
                                Next step: open <a href="{{ route('products.edit', $product) }}" class="font-semibold text-[#0052CC] hover:underline">Edit product</a> to add a brand, categories, or tags so this item is easier to find in your catalog.
                            </p>
                        @endif
                    </section>

                    <details id="workspace-advanced-imported-panel" class="product-workspace-card product-workspace-import-card group rounded-xl border border-[#E3E1EA] bg-white p-6 shadow-sm sm:p-8">
                        <summary class="cursor-pointer list-none text-base font-semibold text-[#0F172A] [&::-webkit-details-marker]:hidden">
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-[#64748B] transition group-open:rotate-90" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M6 4l4 4-4 4V4z"/></svg>
                                Advanced imported data
                            </span>
                        </summary>
                        <p class="mt-3 text-sm text-[#64748B]">
                            <span class="font-medium text-[#334155]">Advanced imported data</span> is read-only spreadsheet information that was preserved because it was not mapped during import. <span class="font-medium text-[#334155]">Additional details</span> are editable extra fields your team chooses (supplier, material, origin, ingredients, care notes, warranty, internal references). Use <span class="font-medium text-[#334155]">Make editable</span> to copy a preserved column into additional details without re-importing.
                        </p>
                        <p class="mt-2 text-xs text-[#64748B]">Recovery here is one column at a time per product. To promote many unmapped columns at once, run a new import with an updated mapping instead of using this panel alone.</p>
                        @if ($importExtraRows === [])
                            <div class="mt-4 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-4 text-sm text-[#64748B]">
                                <p class="font-medium text-[#334155]">No extra imported data for this product.</p>
                                <p class="mt-1">If you import again and leave columns unmapped, they will appear here for your team’s reference.</p>
                            </div>
                        @else
                            <dl class="mt-5 space-y-4 text-sm">
                                @foreach ($importExtraRows as $row)
                                    @php
                                        $rawKey = $row['raw_key'] ?? '';
                                        $looksCategory = $rawKey !== '' && \App\Support\ImportExtraColumnHints::looksLikeCategoryKey($rawKey);
                                    @endphp
                                    <div class="rounded-xl border border-[#F1F5F9] bg-[#FAFAFA] px-3 py-3">
                                        <dt class="text-xs font-semibold text-[#64748B]">{{ $row['label'] }}</dt>
                                        <dd class="mt-1.5 text-[#334155] break-words">
                                            @if (\App\Support\ProductDetailPresenter::isLong($row['value_display']))
                                                <details>
                                                    <summary class="cursor-pointer text-xs font-semibold text-[#0052CC] hover:underline">View full value</summary>
                                                    <pre class="mt-2 max-h-56 overflow-auto rounded-md border border-[#E2E8F0] bg-white p-2 text-xs leading-relaxed text-[#334155]">{{ $row['value_display'] }}</pre>
                                                </details>
                                            @else
                                                {{ $row['value_display'] }}
                                            @endif
                                        </dd>
                                        @if ($canManageCatalog && $rawKey !== '')
                                            <div class="mt-3 flex flex-wrap gap-2 border-t border-[#E2E8F0]/80 pt-3">
                                                <form method="post" action="{{ route('products.workspace.promote-import-extra', $product) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="source_key" value="{{ $rawKey }}">
                                                    <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-1.5 text-xs font-semibold text-[#1D4ED8] transition hover:bg-[#DBEAFE]">Make editable</button>
                                                </form>
                                                @if ($looksCategory)
                                                    <form method="post" action="{{ route('products.workspace.apply-import-category', $product) }}" class="inline">
                                                        @csrf
                                                        <input type="hidden" name="source_key" value="{{ $rawKey }}">
                                                        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-[#99F6E4] bg-[#ECFDF5] px-3 py-1.5 text-xs font-semibold text-[#047857] transition hover:bg-[#D1FAE5]">Use as catalog category</button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </details>
                </aside>
            </div>
        </div>
    </div>
@endsection
