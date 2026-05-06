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

@section('content')
    <div class="flex-1 overflow-y-auto bg-[#F1F5F9]/40 p-4 lg:p-10">
        <div class="mx-auto max-w-[1400px] space-y-9 lg:space-y-10">
            @include('user_view.partials.flash_success')

            <div class="flex flex-col gap-4 border-b border-[#E2E8F0]/80 pb-7 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <div class="space-y-1.5">
                    <a href="{{ route('products') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[#0052CC] hover:underline">
                        <span aria-hidden="true">←</span> Catalog
                    </a>
                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">Product workspace</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    <a href="{{ route('products', ['q' => $product->sku ?: $product->name]).'#product-row-'.$product->id }}"
                       class="inline-flex items-center justify-center rounded-xl border border-[#E2E8F0] bg-white px-4 py-2.5 text-sm font-semibold text-[#475569] shadow-sm transition hover:border-[#CBD5E1] hover:bg-[#F8FAFC]">
                        Find in table
                    </a>
                    @if ($canManageCatalog)
                        <a href="{{ route('products.edit', $product) }}"
                           class="inline-flex items-center justify-center rounded-xl bg-[#0052CC] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#0052CC]/20 transition hover:bg-[#0047B3]">
                            Edit product
                        </a>
                    @endif
                </div>
            </div>

            <header class="relative overflow-hidden rounded-3xl border border-[#E2E8F0] bg-gradient-to-br from-white via-[#F8FAFC] to-[#E0E7FF]/50 p-6 shadow-md ring-1 ring-black/[0.03] sm:p-10" aria-labelledby="product-workspace-overview-heading">
                <div class="pointer-events-none absolute -right-20 -top-20 h-56 w-56 rounded-full bg-[#0052CC]/[0.07] blur-3xl" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-24 left-1/3 h-40 w-40 rounded-full bg-[#6366F1]/10 blur-2xl" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1 space-y-4">
                        <div>
                            <h2 id="product-workspace-overview-heading" class="text-xs font-bold uppercase tracking-[0.14em] text-[#64748B]">Overview</h2>
                            <h1 class="mt-2 text-2xl font-semibold leading-tight text-[#0F172A] font-[Poppins] sm:text-4xl sm:leading-tight break-words">{{ $product->name }}</h1>
                        </div>
                        <p class="max-w-3xl text-base leading-relaxed text-[#475569]">
                            One place to review catalog data, inventory, and storefront context for <span class="font-semibold text-[#0F172A]">{{ $selectedStore?->name ?? 'this store' }}</span>.
                        </p>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            @if ($product->status)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800 ring-1 ring-emerald-100">Published</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 ring-1 ring-slate-200">Draft</span>
                            @endif
                            <span class="text-[#94A3B8]">·</span>
                            <span class="text-[#64748B]">Visibility follows status when you sell on the storefront.</span>
                        </div>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="rounded-2xl border border-[#E2E8F0]/80 bg-white/80 px-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Store SKU</dt>
                                <dd class="mt-1 font-mono text-sm font-semibold text-[#0F172A]">{{ $product->sku ?: '—' }}</dd>
                            </div>
                            <div class="rounded-2xl border border-[#E2E8F0]/80 bg-white/80 px-4 py-3">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Product behavior</dt>
                                <dd class="mt-1 text-sm font-medium text-[#334155]">{{ $productBehavior['label'] ?? Str::title(str_replace(['-', '_'], ' ', $product->product_type)) }}</dd>
                            </div>
                            <div class="rounded-2xl border border-[#E2E8F0]/80 bg-white/80 px-4 py-3 sm:col-span-2 lg:col-span-1">
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

            <div class="grid gap-10 lg:grid-cols-12 lg:items-start lg:gap-12">
                <div class="space-y-10 lg:col-span-8">
                    <section class="rounded-3xl border border-[#E2E8F0] bg-white p-7 shadow-sm ring-1 ring-black/[0.02] sm:p-9">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Selling information</p>
                            <h2 class="mt-1 text-lg font-semibold text-[#0F172A] font-[Poppins]">Media</h2>
                            <p class="mt-1 text-sm text-[#64748B]">Photos and visuals shoppers see for this product.@if ($canManageCatalog) Add or replace images from <span class="font-medium text-[#334155]">Edit product</span> when you need more coverage.@endif</p>
                        </div>
                        @if (! $hasMedia)
                            <p class="mt-6 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-4 text-sm text-[#64748B]">
                                @if ($canManageCatalog)
                                    No catalog images yet. Add photos from <span class="font-medium text-[#334155]">Edit product</span> so the listing feels trustworthy.
                                @else
                                    No catalog images yet. Ask a store owner or manager to add photos when this product is ready for the storefront.
                                @endif
                            </p>
                        @else
                            <div class="mt-6 flex flex-wrap gap-6">
                                <div class="shrink-0">
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[#64748B]">Primary</p>
                                    @if ($primaryUrl)
                                        <img src="{{ $primaryUrl }}" alt="" class="h-40 w-40 rounded-2xl border border-[#E2E8F0] object-cover bg-[#F8FAFC] shadow-sm">
                                    @else
                                        <div class="flex h-40 w-40 items-center justify-center rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] text-center text-xs text-[#64748B]">
                                            @php $pi = $product->images->first(); @endphp
                                            @if ($pi && $pi->isPendingVisual())
                                                <span>Image loading…</span>
                                            @elseif ($pi && $pi->isFailed())
                                                <span>Image unavailable</span>
                                            @else
                                                <span>No primary image</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                @if ($readyImages->count() > 1)
                                    <div class="min-w-0 flex-1">
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[#64748B]">Gallery</p>
                                        <div class="flex flex-wrap gap-3">
                                            @foreach ($readyImages as $img)
                                                <img src="{{ asset('storage/'.$img->image_path) }}" alt="" class="h-20 w-20 rounded-xl border border-[#E2E8F0] object-cover shadow-sm">
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                            @if ($product->images->contains(fn ($i) => $i->isPendingVisual() || $i->isFailed()))
                                <p class="mt-4 text-xs text-[#64748B]">Some images are still processing or could not be loaded from your import.</p>
                            @endif
                        @endif
                    </section>

                    @if (filled($product->description))
                        <section class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-8">
                            <div class="border-b border-[#F1F5F9] pb-4">
                                <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Selling information</p>
                                <h2 class="mt-1 text-lg font-semibold text-[#0F172A] font-[Poppins]">Storefront copy</h2>
                                <p class="mt-1 text-sm text-[#64748B]">Description shown to shoppers where your theme displays it.</p>
                            </div>
                            <div class="mt-6 max-w-none text-sm leading-relaxed text-[#334155] whitespace-pre-wrap">{{ $product->description }}</div>
                        </section>
                    @endif

                    <section class="rounded-3xl border border-[#E2E8F0] bg-white p-7 shadow-sm ring-1 ring-black/[0.02] sm:p-9">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Structured catalog facts</p>
                            <h2 class="mt-1 text-lg font-semibold text-[#0F172A] font-[Poppins]">Attributes</h2>
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

                    <section class="rounded-3xl border border-[#E2E8F0] bg-gradient-to-b from-white to-[#F8FAFF]/30 p-7 shadow-sm ring-1 ring-[#0052CC]/[0.07] sm:p-9" aria-labelledby="workspace-additional-details-heading">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            <h2 id="workspace-additional-details-heading" class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Additional product details</h2>
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
                        <section class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm ring-1 ring-black/[0.02] sm:p-8">
                            <div class="border-b border-[#F1F5F9] pb-4">
                                <h2 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Option groups</h2>
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

                    <section class="rounded-3xl border border-[#E2E8F0] bg-white p-7 shadow-sm ring-1 ring-black/[0.02] sm:p-9">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            @if ($multiVariant)
                                <h2 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Sellable combinations and inventory</h2>
                                <p class="mt-1 text-sm text-[#64748B]">Each row is one variant (a combination of your option groups) with its own SKU, price, compare-at, stock, optional photo, and optional extra details. Totals in <span class="font-medium text-[#334155]">Pricing &amp; inventory</span> roll up from these rows.</p>
                            @else
                                <h2 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Default inventory</h2>
                                <p class="mt-1 text-sm text-[#64748B]">
                                    One SKU and stock row for this product.
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
                        @elseif (! $multiVariant)
                            @php $row = $variantSummaries[0]; @endphp
                            <div class="mt-6 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-6 sm:p-8">
                                @if ($row['is_first'])
                                    <p class="mb-4 inline-flex rounded-md bg-[#EEF4FF] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#0052CC]">Default variant</p>
                                @endif
                                <div class="mb-4">
                                    <p class="text-sm font-semibold text-[#0F172A]">{{ $row['label'] }}</p>
                                    @if (! empty($row['chips']))
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($row['chips'] as $chip)
                                                <span class="inline-flex rounded-lg border border-[#CBD5E1] bg-white px-2.5 py-1 text-xs font-medium text-[#0F172A] shadow-sm">{{ $chip['group'] }}: {{ $chip['value'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                                    <div class="shrink-0 text-center sm:text-left">
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[#64748B]">Photo for listings</p>
                                        @if (! empty($row['catalog_image_thumb']))
                                            <img src="{{ $row['catalog_image_thumb'] }}" alt="" title="{{ ! empty($row['catalog_image_is_product_fallback']) ? 'Catalog image (main product photo)' : 'Variant catalog image' }}" class="mx-auto h-20 w-20 rounded-xl border border-[#E2E8F0] object-cover shadow-sm sm:mx-0">
                                            <p class="mt-2 text-xs text-[#64748B]">
                                                @if (! empty($row['catalog_image_is_product_fallback']))
                                                    Uses your main product image until you assign a variant-specific photo in Edit product.
                                                @else
                                                    Variant-specific catalog image.
                                                @endif
                                            </p>
                                        @else
                                            <div class="mx-auto flex h-20 w-20 flex-col items-center justify-center rounded-xl border border-dashed border-[#CBD5E1] bg-white px-1 text-center text-[10px] font-medium leading-tight text-[#94A3B8] sm:mx-0">No variant image</div>
                                            <p class="mt-2 max-w-xs text-xs text-[#64748B]">
                                                @if ($canManageCatalog)
                                                    Optional: choose a catalog photo for this row in the product editor (upload under Media, then assign variant photo).
                                                @else
                                                    Optional: a manager can attach a catalog photo to this row.
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                    <dl class="min-w-0 flex-1 grid gap-4 sm:grid-cols-2">
                                        <div class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-3">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">SKU</dt>
                                            <dd class="mt-1 font-mono text-sm font-semibold text-[#0F172A]">{{ $row['sku'] }}</dd>
                                        </div>
                                        <div class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-3">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Retail price</dt>
                                            <dd class="mt-1 text-lg font-semibold tabular-nums text-[#0F172A]">{{ $currency }} {{ number_format((float) $row['price'], 2) }}</dd>
                                        </div>
                                        <div class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-3">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Compare-at</dt>
                                            <dd class="mt-1 text-sm font-semibold tabular-nums text-[#64748B]">
                                                @if (! empty($row['compare_at_price']))
                                                    {{ $currency }} {{ number_format((float) $row['compare_at_price'], 2) }}
                                                @else
                                                    —
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-3">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">On hand</dt>
                                            <dd class="mt-1 text-lg font-semibold tabular-nums text-[#0F172A]">{{ number_format($row['stock']) }}</dd>
                                        </div>
                                        <div class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 sm:col-span-2">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">Low-stock alert</dt>
                                            <dd class="mt-1 text-sm font-medium tabular-nums text-[#334155]">{{ $row['stock_alert'] > 0 ? number_format($row['stock_alert']) : 'Not set' }}</dd>
                                        </div>
                                    </dl>
                                    @if (! empty($row['additional_detail_rows']))
                                        <details class="mt-4 rounded-xl border border-[#E2E8F0] bg-white px-4 py-3">
                                            <summary class="cursor-pointer text-sm font-semibold text-[#0052CC] hover:underline">Extra information for this variant</summary>
                                            <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                                                @foreach ($row['additional_detail_rows'] as $detailRow)
                                                    <div class="rounded-lg border border-[#F1F5F9] bg-[#FAFAFA] px-3 py-2">
                                                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]">{{ $detailRow['label'] }}</dt>
                                                        <dd class="mt-1 text-sm font-medium text-[#0F172A] break-words">{{ $detailRow['value_display'] }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </details>
                                    @else
                                        <p class="mt-4 text-xs text-[#94A3B8]">No variant-specific extra information.</p>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="mt-6 overflow-x-auto rounded-2xl border border-[#E2E8F0] shadow-sm">
                                <table class="w-full min-w-[880px] text-left text-sm" aria-describedby="variant-table-caption">
                                    <caption id="variant-table-caption" class="sr-only">Sellable combinations: variant photo, shopper choices, optional extra details, SKU, pricing, stock, and low-stock alert.</caption>
                                    <thead class="bg-[#F1F5F9] text-xs font-bold uppercase tracking-wide text-[#64748B]">
                                        <tr>
                                            <th class="px-4 py-3.5">Variant photo</th>
                                            <th class="px-4 py-3.5">Sellable combination</th>
                                            <th class="min-w-[8rem] px-4 py-3.5">Extra details</th>
                                            <th class="px-4 py-3.5">SKU</th>
                                            <th class="min-w-[7rem] px-4 py-3.5">Retail price</th>
                                            <th class="min-w-[7rem] px-4 py-3.5">Compare-at</th>
                                            <th class="min-w-[5rem] px-4 py-3.5">On hand</th>
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
                                                <td class="px-4 py-3.5 align-top text-sm tabular-nums text-[#64748B]">{{ $row['stock_alert'] > 0 ? number_format($row['stock_alert']) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    <section class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-8">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            <h2 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Pricing &amp; inventory</h2>
                            <p class="mt-1 text-sm text-[#64748B]">Roll-up totals across variants and the latest stock movements for this product.</p>
                        </div>
                        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl bg-[#F8FAFC] px-4 py-4 ring-1 ring-[#E2E8F0]/80">
                                <dt class="text-sm text-[#64748B]">Total units across variants</dt>
                                <dd class="mt-1 text-2xl font-semibold text-[#0F172A] tabular-nums">{{ number_format($totalStock) }}</dd>
                            </div>
                            <div class="rounded-2xl bg-[#F8FAFC] px-4 py-4 ring-1 ring-[#E2E8F0]/80">
                                <dt class="text-sm text-[#64748B]">Low-stock threshold</dt>
                                <dd class="mt-1 text-2xl font-semibold text-[#0F172A] tabular-nums">{{ $effectiveLowThreshold > 0 ? number_format($effectiveLowThreshold) : '—' }}</dd>
                            </div>
                        </dl>
                        @if ($outOfStock)
                            <p class="mt-4 text-sm font-semibold text-red-600">This product is out of stock.</p>
                        @elseif ($lowStock)
                            <p class="mt-4 text-sm font-semibold text-amber-700">Total stock is at or below your low-stock alert.</p>
                        @endif

                        @if ($recentMovements->isNotEmpty())
                            <h3 class="mt-10 text-xs font-bold uppercase tracking-wide text-[#64748B]">Stock activity</h3>
                            <ul class="mt-4 space-y-3 text-sm text-[#334155]">
                                @foreach ($recentMovements as $mv)
                                    <li class="rounded-xl border border-[#F1F5F9] bg-[#FAFAFA] px-4 py-3">
                                        <span class="font-medium text-[#0F172A]">{{ $movementLabels[$mv->movement_type] ?? Str::title(str_replace('_', ' ', $mv->movement_type)) }}</span>
                                        <span class="text-[#64748B]"> · </span>
                                        <span class="tabular-nums">{{ (int) $mv->previous_stock }} → {{ (int) $mv->new_stock }}</span>
                                        @if ($mv->reason)
                                            <span class="text-[#94A3B8]"> — {{ Str::limit($mv->reason, 80) }}</span>
                                        @endif
                                        <span class="mt-1 block text-xs text-[#94A3B8]">{{ optional($mv->created_at)->format('M j, Y g:i A') }}@if ($mv->performer) · {{ $mv->performer->name }}@endif</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>

                <aside class="space-y-8 lg:col-span-4 lg:pl-2">
                    <section class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm ring-1 ring-black/[0.02] sm:p-7">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            <h2 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Store pricing</h2>
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

                    <section class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-7">
                        <div class="border-b border-[#F1F5F9] pb-4">
                            <h2 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Organization</h2>
                            <p class="mt-1 text-xs text-[#64748B]">Brand, categories, and tags for this store.</p>
                        </div>
                        <div class="mt-5 space-y-5 text-sm">
                            <div>
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

                    <details id="workspace-advanced-imported-panel" class="group rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm ring-1 ring-black/[0.02] sm:p-7">
                        <summary class="cursor-pointer list-none text-base font-semibold text-[#0F172A] font-[Poppins] [&::-webkit-details-marker]:hidden">
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
