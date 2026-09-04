@extends('layouts.user.user-sidebar')

@php
    use Illuminate\Support\Str;
    $storeForView = $selectedStore ?? $currentStore;
    $currency = optional($storeForView)->currency ?? 'USD';
    $meta = is_array($product->meta) ? $product->meta : [];
    $compareAt = $catalog['compare_at_price'] ?? null;
    $costPrice = $catalog['cost_price'] ?? null;
    $shortDesc = $catalog['short_description'] ?? null;
    $readyImages = $product->images->filter(fn ($img) => $img->isReady())->values();
    $primaryImg = $readyImages->first(fn ($img) => $img->is_primary) ?? $readyImages->first();
    $primaryUrl = $primaryImg ? asset('storage/'.$primaryImg->image_path) : null;
    $lowStock = $totalStock > 0 && $effectiveLowThreshold > 0 && $totalStock <= $effectiveLowThreshold;
    $outOfStock = $totalStock === 0;
    $stockLabel = $outOfStock ? 'Out of stock' : ($lowStock ? 'Low stock' : 'In stock');
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
    $behaviorLabel = $productBehavior['label'] ?? Str::title(str_replace(['-', '_'], ' ', $product->product_type));
    $attributeRows = $attributeRows ?? [];
    $hasMedia = $readyImages->isNotEmpty() || $product->images->isNotEmpty();
    $workspaceStoreId = (int) (optional($storeForView)->id ?? 0);
    $storeCategories = $product->categories->filter(fn ($c) => (int) $c->store_id === $workspaceStoreId)->values();
    $storeTags = $product->tags->filter(fn ($t) => (int) $t->store_id === $workspaceStoreId)->values();
    $primaryCategory = $storeCategories->first();
    $hasOrganization = ($product->brand && (int) $product->brand->store_id === $workspaceStoreId)
        || $storeCategories->isNotEmpty()
        || $storeTags->isNotEmpty();
    $hasCustom = $customFieldRows !== [];
    $hasAttributes = $attributeRows !== [];
    $hasImportExtra = $importExtraRows !== [];
    $hasCopy = filled($shortDesc) || filled($product->description);
    $variantCount = count($variantSummaries);
    $multiVariant = $variantCount > 1 || ($optionGroupSummaries ?? []) !== [];
    $card = 'rounded-lg border border-[color:var(--color-border)] bg-white shadow-[var(--shadow-ui)]';
@endphp

@section('title', $product->name.' — Product workspace')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div id="product-workspace" class="product-workspace -m-4 flex min-h-full flex-col lg:-m-8">
        <div class="w-full flex-1">
            <div class="w-full space-y-5 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            @include('user_view.partials.flash_success')

            {{-- Breadcrumb + header --}}
            <header class="space-y-3">
                <nav class="flex flex-wrap items-center gap-1.5 text-sm text-[color:var(--color-ink-muted)]" aria-label="Breadcrumb">
                    <a href="{{ route('products') }}" class="font-medium text-[color:var(--color-ink-secondary)] hover:text-brand hover:underline">Products</a>
                    @if ($primaryCategory)
                        <span aria-hidden="true">/</span>
                        <span class="font-medium text-[color:var(--color-ink-secondary)]">{{ $primaryCategory->name }}</span>
                    @endif
                    <span aria-hidden="true">/</span>
                    <span class="truncate font-medium text-[color:var(--color-ink)]">{{ $product->name }}</span>
                </nav>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-2">
                        <p class="sr-only">Product workspace</p>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 class="break-words text-2xl font-semibold tracking-tight text-[color:var(--color-ink)] sm:text-[1.75rem]">{{ $product->name }}</h1>
                            @if ($product->status)
                                <x-ui.badge tone="success">Published</x-ui.badge>
                            @else
                                <x-ui.badge>Draft</x-ui.badge>
                            @endif
                        </div>
                        @if (! $product->status)
                            <p class="text-sm text-[color:var(--color-ink-muted)]">This product is not visible to customers yet. Continue editing, then publish it from Drafts.</p>
                        @endif
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[color:var(--color-ink-muted)]">
                            <span class="font-mono text-[color:var(--color-ink-secondary)]">{{ $product->sku ?: 'No SKU' }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ $primaryCategory?->name ?: 'Uncategorized' }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ $behaviorLabel }}</span>
                            <span aria-hidden="true">·</span>
                            <span @class([
                                'font-semibold',
                                'text-[color:var(--color-danger)]' => $outOfStock,
                                'text-[color:var(--color-warning)]' => $lowStock && ! $outOfStock,
                                'text-[color:var(--color-success)]' => ! $outOfStock && ! $lowStock,
                            ])>{{ $stockLabel }}</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <a href="{{ route('products', $product->status ? [] : ['view' => 'drafts']) }}" class="ui-btn ui-btn-secondary">{{ $product->status ? 'Back to catalog' : 'Back to drafts' }}</a>
                        @if ($canManageCatalog)
                            <x-ui.button :href="route('products.edit', $product)">{{ $product->status ? 'Edit product' : 'Continue editing' }}</x-ui.button>
                        @endif
                    </div>
                </div>
            </header>

            <div class="grid gap-5 lg:grid-cols-12 lg:items-start lg:gap-6">
                {{-- Main --}}
                <div class="min-w-0 space-y-5 lg:col-span-8">
                    {{-- Media: large primary + vertical thumbs --}}
                    <section class="{{ $card }} p-5" data-workspace-media>
                        <div class="mb-4 flex items-center justify-between gap-2">
                            <h2 class="text-[15px] font-semibold text-[color:var(--color-ink)]">Media</h2>
                            @if ($canManageCatalog)
                                <a href="{{ route('products.edit', $product) }}" class="text-sm font-semibold text-brand hover:underline">Manage media</a>
                            @endif
                        </div>

                        @if (! $hasMedia)
                            <div class="rounded-lg border border-dashed border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-4 py-10 text-center text-sm text-[color:var(--color-ink-muted)]">
                                @if ($canManageCatalog)
                                    No photos yet.
                                    <a href="{{ route('products.edit', $product) }}" class="font-semibold text-brand hover:underline">Add images in Edit product</a>
                                @else
                                    No catalog images yet.
                                @endif
                            </div>
                        @else
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-stretch">
                                <div class="min-w-0 flex-1">
                                    @if ($primaryUrl)
                                        <div class="overflow-hidden rounded-lg border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)]">
                                            <img
                                                data-workspace-primary-image
                                                src="{{ $primaryUrl }}"
                                                alt="{{ $product->name }}"
                                                class="mx-auto h-64 w-full max-w-md object-contain sm:h-72"
                                            >
                                        </div>
                                    @else
                                        <div class="flex h-64 items-center justify-center rounded-lg border border-dashed border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] text-sm text-[color:var(--color-ink-muted)] sm:h-72">
                                            @php $pi = $product->images->first(); @endphp
                                            @if ($pi && $pi->isPendingVisual())
                                                Image loading…
                                            @elseif ($pi && $pi->isFailed())
                                                Image unavailable
                                            @else
                                                No primary image
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div class="flex shrink-0 gap-2 overflow-x-auto sm:w-[4.5rem] sm:flex-col sm:overflow-y-auto sm:overflow-x-hidden">
                                    @foreach ($readyImages as $img)
                                        @php $thumbUrl = asset('storage/'.$img->image_path); @endphp
                                        <button
                                            type="button"
                                            data-workspace-thumb
                                            data-src="{{ $thumbUrl }}"
                                            class="h-16 w-16 shrink-0 overflow-hidden rounded-md border-2 transition focus:outline-none focus:ring-2 focus:ring-brand/30 {{ $primaryUrl === $thumbUrl ? 'border-brand' : 'border-[color:var(--color-border)] hover:border-[color:var(--color-border-strong)]' }}"
                                            aria-label="Show image {{ $loop->iteration }}"
                                        >
                                            <img src="{{ $thumbUrl }}" alt="" class="h-full w-full object-cover">
                                        </button>
                                    @endforeach
                                    @if ($canManageCatalog)
                                        <a
                                            href="{{ route('products.edit', $product) }}"
                                            class="flex h-16 w-16 shrink-0 items-center justify-center rounded-md border border-dashed border-[color:var(--color-border)] text-xl font-light text-[color:var(--color-ink-muted)] transition hover:border-brand hover:text-brand"
                                            aria-label="Add media"
                                        >+</a>
                                    @endif
                                </div>
                            </div>
                            @if ($product->images->contains(fn ($i) => $i->isPendingVisual() || $i->isFailed()))
                                <p class="mt-3 text-xs text-[color:var(--color-ink-muted)]">Some images are still processing or could not be loaded from your import.</p>
                            @endif
                        @endif
                    </section>

                    {{-- Storefront copy --}}
                    @if ($hasCopy)
                        <section class="{{ $card }} p-5">
                            <h2 class="mb-4 text-[15px] font-semibold text-[color:var(--color-ink)]">Storefront copy</h2>
                            @if (filled($shortDesc))
                                <div class="mb-5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Short description</p>
                                    <x-catalog.product-rich-text class="mt-2" :content="$shortDesc" />
                                </div>
                            @endif
                            @if (filled($product->description))
                                <div @class(['border-t border-[color:var(--color-border)] pt-5' => filled($shortDesc)])>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Description</p>
                                    <x-catalog.product-rich-text class="mt-2" :content="$product->description" />
                                </div>
                            @endif
                        </section>
                    @endif

                    {{-- Option groups --}}
                    @if ($optionGroupSummaries !== [])
                        <section class="{{ $card }} p-5">
                            <h2 class="mb-3 text-[15px] font-semibold text-[color:var(--color-ink)]">Option groups</h2>
                            <ul class="grid gap-3 sm:grid-cols-2">
                                @foreach ($optionGroupSummaries as $group)
                                    <li class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-3.5 py-3">
                                        <p class="text-sm font-semibold text-[color:var(--color-ink)]">{{ $group['name'] }}</p>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($group['values'] as $val)
                                                <span class="inline-flex rounded border border-[color:var(--color-border)] bg-white px-2 py-0.5 text-xs font-medium text-[color:var(--color-ink)]">{{ $val }}</span>
                                            @endforeach
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Sellable combinations / default inventory --}}
                    <section class="{{ $card }} overflow-hidden">
                        <div class="border-b border-[color:var(--color-border)] px-5 py-4">
                            @if ($multiVariant)
                                <h2 class="text-[15px] font-semibold text-[color:var(--color-ink)]">Sellable combinations</h2>
                                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">Each row is one variant with its own SKU, price, and stock.</p>
                            @else
                                <h2 class="text-[15px] font-semibold text-[color:var(--color-ink)]">Default inventory</h2>
                                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">
                                    One inventory row for this product.
                                    @if ($canManageCatalog)
                                        Add option groups in <span class="font-medium text-[color:var(--color-ink-secondary)]">Edit product</span> only if shoppers choose size, color, or similar.
                                    @endif
                                </p>
                            @endif
                        </div>

                        <div class="p-0 sm:p-0">
                            @if ($variantSummaries === [])
                                <p class="px-5 py-6 text-sm text-[color:var(--color-ink-muted)]">No sellable rows are linked to this product yet.</p>
                            @elseif (! $multiVariant)
                                @php $row = $variantSummaries[0]; @endphp
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[640px] text-left text-sm">
                                        <thead class="bg-[color:var(--color-surface-muted)] text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">
                                            <tr>
                                                <th class="px-4 py-3">Variant</th>
                                                <th class="px-4 py-3">SKU</th>
                                                <th class="px-4 py-3">Price</th>
                                                <th class="px-4 py-3">Available</th>
                                                <th class="px-4 py-3">Reserved</th>
                                                <th class="px-4 py-3">Location</th>
                                                <th class="px-4 py-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="border-t border-[color:var(--color-border)]">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-3">
                                                        @if (! empty($row['catalog_image_thumbs']))
                                                            <div class="flex -space-x-1">
                                                                @foreach (array_slice($row['catalog_image_thumbs'], 0, 3) as $thumb)
                                                                    <img src="{{ $thumb }}" alt="" class="h-10 w-10 rounded border border-[color:var(--color-border)] object-cover">
                                                                @endforeach
                                                            </div>
                                                        @elseif (! empty($row['catalog_image_thumb']))
                                                            <img src="{{ $row['catalog_image_thumb'] }}" alt="" class="h-10 w-10 rounded border border-[color:var(--color-border)] object-cover">
                                                        @else
                                                            <span class="flex h-10 w-10 items-center justify-center rounded border border-dashed border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] text-[10px] font-semibold text-[color:var(--color-ink-muted)]">No image</span>
                                                        @endif
                                                        <div>
                                                            <p class="font-medium text-[color:var(--color-ink)]">{{ $row['label'] }}</p>
                                                            @if ($row['is_first'])
                                                                <span class="mt-1 inline-flex rounded bg-brand-soft px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand">Default</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 font-mono text-xs text-[color:var(--color-ink-secondary)]">{{ $row['sku'] }}</td>
                                                <td class="px-4 py-3 font-semibold tabular-nums">{{ $currency }} {{ number_format((float) $row['price'], 2) }}</td>
                                                <td class="px-4 py-3 font-semibold tabular-nums">{{ number_format($row['stock']) }}</td>
                                                <td class="px-4 py-3 tabular-nums text-[color:var(--color-ink-secondary)]">{{ number_format($row['reserved'] ?? 0) }}</td>
                                                <td class="px-4 py-3 text-[color:var(--color-ink-muted)]">{{ $row['location_name'] ?? 'Main location' }}</td>
                                                <td class="px-4 py-3">
                                                    @if ($row['stock'] > 0)
                                                        <span class="inline-flex rounded-full bg-[color:var(--color-success-soft)] px-2 py-0.5 text-xs font-semibold text-[color:var(--color-success)]">Active</span>
                                                    @else
                                                        <span class="inline-flex rounded-full bg-[color:var(--color-danger-soft)] px-2 py-0.5 text-xs font-semibold text-[color:var(--color-danger)]">Out of stock</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                @if (! empty($row['additional_detail_rows']))
                                    <div class="border-t border-[color:var(--color-border)] px-5 py-4">
                                        <details>
                                            <summary class="cursor-pointer text-sm font-semibold text-brand hover:underline">Extra details</summary>
                                            <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                                                @foreach ($row['additional_detail_rows'] as $detailRow)
                                                    <div class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-3 py-2">
                                                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">{{ $detailRow['label'] }}</dt>
                                                        <dd class="mt-1 text-sm font-medium break-words text-[color:var(--color-ink)]">{{ $detailRow['value_display'] }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </details>
                                    </div>
                                @endif
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[880px] text-left text-sm" aria-describedby="variant-table-caption">
                                        <caption id="variant-table-caption" class="sr-only">Sellable combinations with photo, choices, SKU, pricing, and stock.</caption>
                                        <thead class="bg-[color:var(--color-surface-muted)] text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">
                                            <tr>
                                                <th class="px-4 py-3">Variant</th>
                                                <th class="min-w-[8rem] px-4 py-3">Extra details</th>
                                                <th class="px-4 py-3">SKU</th>
                                                <th class="px-4 py-3">Price</th>
                                                <th class="px-4 py-3">Compare-at</th>
                                                <th class="px-4 py-3">Available</th>
                                                <th class="px-4 py-3">Reserved</th>
                                                <th class="px-4 py-3">Location</th>
                                                <th class="px-4 py-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[color:var(--color-border)]">
                                            @foreach ($variantSummaries as $row)
                                                <tr class="bg-white">
                                                    <td class="px-4 py-3 align-top">
                                                        <div class="flex items-start gap-3">
                                                            @if (! empty($row['catalog_image_thumbs']))
                                                                <div class="flex -space-x-1">
                                                                    @foreach (array_slice($row['catalog_image_thumbs'], 0, 3) as $thumb)
                                                                        <img src="{{ $thumb }}" alt="" class="h-10 w-10 rounded border border-[color:var(--color-border)] object-cover">
                                                                    @endforeach
                                                                </div>
                                                            @elseif (! empty($row['catalog_image_thumb']))
                                                                <img src="{{ $row['catalog_image_thumb'] }}" alt="" class="h-10 w-10 rounded border border-[color:var(--color-border)] object-cover">
                                                            @else
                                                                <span class="flex h-10 w-10 items-center justify-center rounded border border-dashed border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] text-[10px] font-semibold text-[color:var(--color-ink-muted)]">No image</span>
                                                            @endif
                                                            <div>
                                                                <div class="font-medium text-[color:var(--color-ink)]">{{ $row['label'] }}</div>
                                                                @if (! empty($row['chips']))
                                                                    <div class="mt-1 flex flex-wrap gap-1">
                                                                        @foreach ($row['chips'] as $chip)
                                                                            <span class="inline-flex rounded border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-1.5 py-0.5 text-[11px] font-medium text-[color:var(--color-ink)]">{{ $chip['group'] }}: {{ $chip['value'] }}</span>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                                @if ($row['is_first'])
                                                                    <span class="mt-1 inline-flex rounded bg-brand-soft px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand">Default</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 align-top">
                                                        @if (! empty($row['additional_detail_rows']))
                                                            <details class="rounded border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-2 py-1.5">
                                                                <summary class="cursor-pointer text-xs font-semibold text-brand hover:underline">
                                                                    Extra details ({{ count($row['additional_detail_rows']) }})
                                                                </summary>
                                                                <dl class="mt-2 max-h-48 space-y-2 overflow-y-auto text-xs">
                                                                    @foreach ($row['additional_detail_rows'] as $detailRow)
                                                                        <div class="rounded border border-[color:var(--color-border)] bg-white px-2 py-1.5">
                                                                            <dt class="font-semibold text-[color:var(--color-ink-muted)]">{{ $detailRow['label'] }}</dt>
                                                                            <dd class="mt-0.5 break-words text-[color:var(--color-ink)]">{{ $detailRow['value_display'] }}</dd>
                                                                        </div>
                                                                    @endforeach
                                                                </dl>
                                                            </details>
                                                        @else
                                                            <span class="text-xs text-[color:var(--color-ink-muted)]">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 align-top font-mono text-xs text-[color:var(--color-ink-secondary)]">{{ $row['sku'] }}</td>
                                                    <td class="px-4 py-3 align-top font-semibold tabular-nums">{{ $currency }} {{ number_format((float) $row['price'], 2) }}</td>
                                                    <td class="px-4 py-3 align-top tabular-nums text-[color:var(--color-ink-muted)]">
                                                        @if (! empty($row['compare_at_price']))
                                                            {{ $currency }} {{ number_format((float) $row['compare_at_price'], 2) }}
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 align-top font-semibold tabular-nums">{{ number_format($row['stock']) }}</td>
                                                    <td class="px-4 py-3 align-top tabular-nums text-[color:var(--color-ink-secondary)]">{{ number_format($row['reserved'] ?? 0) }}</td>
                                                    <td class="px-4 py-3 align-top text-[color:var(--color-ink-muted)]">{{ $row['location_name'] ?? 'Main location' }}</td>
                                                    <td class="px-4 py-3 align-top">
                                                        @if ($row['stock'] > 0)
                                                            <span class="inline-flex rounded-full bg-[color:var(--color-success-soft)] px-2 py-0.5 text-xs font-semibold text-[color:var(--color-success)]">Active</span>
                                                        @else
                                                            <span class="inline-flex rounded-full bg-[color:var(--color-danger-soft)] px-2 py-0.5 text-xs font-semibold text-[color:var(--color-danger)]">Out of stock</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </section>

                    @if ($hasAttributes)
                        <section class="{{ $card }} p-5">
                            <h2 class="mb-3 text-[15px] font-semibold text-[color:var(--color-ink)]">Specifications</h2>
                            <dl class="grid gap-3 sm:grid-cols-2">
                                @foreach ($attributeRows as $attributeRow)
                                    <div class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-3 py-2.5">
                                        <dt class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">
                                            <span>{{ $attributeRow['name'] }}</span>
                                            @if (! empty($attributeRow['is_filterable']))
                                                <span class="rounded-full bg-brand-soft px-2 py-0.5 text-[10px] font-bold text-brand">Filterable</span>
                                            @endif
                                        </dt>
                                        <dd class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($attributeRow['terms'] as $term)
                                                <span class="inline-flex rounded border border-[color:var(--color-border)] bg-white px-2 py-0.5 text-xs font-semibold text-[color:var(--color-ink-secondary)]">{{ $term }}</span>
                                            @endforeach
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </section>
                    @endif

                    <section class="{{ $card }} p-5" aria-labelledby="workspace-additional-details-heading">
                        <h2 id="workspace-additional-details-heading" class="text-[15px] font-semibold text-[color:var(--color-ink)]">Additional product details</h2>
                        @if ($hasCustom)
                            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                @foreach ($customFieldRows as $row)
                                    <div class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-3 py-2.5">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">{{ $row['label'] }}</dt>
                                        <dd class="mt-1.5 text-sm font-medium break-words text-[color:var(--color-ink)]">
                                            @if (\App\Support\ProductDetailPresenter::isLong($row['value_display']))
                                                <details>
                                                    <summary class="cursor-pointer text-brand hover:underline">{{ Str::limit($row['value_display'], 120) }}</summary>
                                                    <pre class="mt-2 max-h-48 overflow-auto rounded bg-white p-2 text-xs text-[color:var(--color-ink-secondary)]">{{ $row['value_display'] }}</pre>
                                                </details>
                                            @else
                                                {{ $row['value_display'] }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @else
                            <p class="mt-3 text-sm text-[color:var(--color-ink-muted)]">
                                No additional details yet.
                                @if ($canManageCatalog)
                                    <a href="{{ route('products.edit', $product) }}" class="font-semibold text-brand hover:underline">Add them in Edit product</a>.
                                @endif
                            </p>
                        @endif
                    </section>
                </div>

                {{-- Right rail --}}
                <aside class="space-y-4 lg:col-span-4 lg:sticky lg:top-4">
                    @if ($canManageCatalog && (! $hasMedia || ! $hasOrganization))
                        @php
                            $workspaceNext = ! $hasMedia
                                ? 'Add product photos so the listing looks trustworthy.'
                                : 'Add a brand, category, or tags so this item is easier to find.';
                        @endphp
                        <x-ui.sticky-next
                            :message="$workspaceNext"
                            action-label="Edit product"
                            :action-href="route('products.edit', $product)"
                            class="!static rounded-lg border border-[color:var(--color-border)]"
                        />
                    @endif

                    <section class="{{ $card }} p-4">
                        <h2 class="text-[15px] font-semibold text-[color:var(--color-ink)]">Pricing</h2>
                        <dl class="mt-3 space-y-2.5 text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">Base price</dt>
                                <dd class="text-lg font-semibold tabular-nums text-[color:var(--color-ink)]">{{ $currency }} {{ number_format((float) $product->base_price, 2) }}</dd>
                            </div>
                            @if ($compareAt !== null && $compareAt !== '')
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="text-[color:var(--color-ink-muted)]">Compare-at</dt>
                                    <dd class="font-medium tabular-nums text-[color:var(--color-ink-secondary)]">{{ $currency }} {{ is_numeric($compareAt) ? number_format((float) $compareAt, 2) : e($compareAt) }}</dd>
                                </div>
                            @endif
                            @if ($costPrice !== null && $costPrice !== '')
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="text-[color:var(--color-ink-muted)]">Cost</dt>
                                    <dd class="font-medium tabular-nums text-[color:var(--color-ink-secondary)]">{{ $currency }} {{ is_numeric($costPrice) ? number_format((float) $costPrice, 2) : e($costPrice) }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>

                    <section class="{{ $card }} p-4">
                        <h2 class="text-[15px] font-semibold text-[color:var(--color-ink)]">Inventory</h2>
                        <dl class="mt-3 space-y-2.5 text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">In stock</dt>
                                <dd class="text-lg font-semibold tabular-nums text-[color:var(--color-ink)]">{{ number_format($totalStock) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">Low stock threshold</dt>
                                <dd class="font-semibold tabular-nums text-[color:var(--color-ink)]">{{ $effectiveLowThreshold > 0 ? number_format($effectiveLowThreshold) : '—' }}</dd>
                            </div>
                        </dl>
                        @if ($outOfStock)
                            <p class="mt-3 text-sm font-semibold text-[color:var(--color-danger)]">This product is out of stock.</p>
                        @elseif ($lowStock)
                            <p class="mt-3 text-sm font-semibold text-[color:var(--color-warning)]">Stock is at or below your low-stock alert.</p>
                        @endif
                    </section>

                    <section class="{{ $card }} p-4">
                        <h2 class="text-[15px] font-semibold text-[color:var(--color-ink)]">Organization</h2>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Brand</dt>
                                <dd class="mt-1 font-medium text-[color:var(--color-ink)]">
                                    {{ ($product->brand && (int) $product->brand->store_id === $workspaceStoreId) ? $product->brand->name : '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Category</dt>
                                <dd class="mt-1.5 flex flex-wrap gap-1.5">
                                    @forelse ($storeCategories as $cat)
                                        <span class="rounded-full border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-2 py-0.5 text-xs font-semibold text-[color:var(--color-ink-secondary)]">{{ $cat->name }}</span>
                                    @empty
                                        <span class="rounded-full border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-2 py-0.5 text-xs font-semibold text-[color:var(--color-ink-muted)]">Uncategorized</span>
                                    @endforelse
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Tags</dt>
                                <dd class="mt-1.5 flex flex-wrap gap-1.5">
                                    @forelse ($storeTags as $tag)
                                        <span class="rounded-full border border-brand/25 bg-brand-soft px-2 py-0.5 text-xs font-semibold text-brand">{{ $tag->name }}</span>
                                    @empty
                                        <span class="text-[color:var(--color-ink-muted)]">None</span>
                                    @endforelse
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Status</dt>
                                <dd class="mt-1.5">
                                    @if ($product->status)
                                        <span class="inline-flex rounded-full bg-[color:var(--color-success-soft)] px-2 py-0.5 text-xs font-semibold text-[color:var(--color-success)]">Published</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-[color:var(--color-surface-muted)] px-2 py-0.5 text-xs font-semibold text-[color:var(--color-ink-muted)]">Draft</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section class="{{ $card }} p-4">
                        <h2 class="text-[15px] font-semibold text-[color:var(--color-ink)]">Product facts</h2>
                        <dl class="mt-3 divide-y divide-[color:var(--color-border)] text-sm">
                            <div class="flex justify-between gap-3 py-2 first:pt-0">
                                <dt class="text-[color:var(--color-ink-muted)]">SKU</dt>
                                <dd class="font-mono font-medium text-[color:var(--color-ink)]">{{ $product->sku ?: '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 py-2">
                                <dt class="text-[color:var(--color-ink-muted)]">Product type</dt>
                                <dd class="font-medium text-[color:var(--color-ink)]">{{ $behaviorLabel }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 py-2">
                                <dt class="text-[color:var(--color-ink-muted)]">Shipping</dt>
                                <dd class="font-medium text-[color:var(--color-ink)]">{{ ! empty($productBehavior['requires_shipping']) ? 'Required' : 'Not required' }}</dd>
                            </div>
                            @if (! empty(($shippingWeightSummary ?? [])['visible']))
                                <div class="flex justify-between gap-3 py-2">
                                    <dt class="text-[color:var(--color-ink-muted)]">Shipping weight</dt>
                                    <dd class="text-right font-medium text-[color:var(--color-ink)]">
                                        {{ $shippingWeightSummary['value'] }}
                                        @if (! empty($shippingWeightSummary['hint']))
                                            <span class="mt-0.5 block text-xs font-normal text-[color:var(--color-ink-muted)]">{{ $shippingWeightSummary['hint'] }}</span>
                                        @endif
                                    </dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-3 py-2">
                                <dt class="text-[color:var(--color-ink-muted)]">Inventory tracking</dt>
                                <dd class="font-medium text-[color:var(--color-ink)]">{{ ! empty($productBehavior['track_inventory']) ? 'On' : 'Off' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 py-2">
                                <dt class="text-[color:var(--color-ink-muted)]">Tax</dt>
                                <dd class="font-medium text-[color:var(--color-ink)]">{{ $product->is_taxable ? 'Applies' : 'Off' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 py-2">
                                <dt class="text-[color:var(--color-ink-muted)]">Created</dt>
                                <dd class="font-medium text-[color:var(--color-ink)]">{{ optional($product->created_at)->format('M j, Y') ?: '—' }}</dd>
                            </div>
                            @if ($product->updated_at && $product->updated_at->ne($product->created_at))
                                <div class="flex justify-between gap-3 py-2 last:pb-0">
                                    <dt class="text-[color:var(--color-ink-muted)]">Updated</dt>
                                    <dd class="font-medium text-[color:var(--color-ink)]">{{ $product->updated_at->format('M j, Y') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>

                    @if ($recentMovements->isNotEmpty())
                        <section class="{{ $card }} p-4">
                            <h2 class="text-[15px] font-semibold text-[color:var(--color-ink)]">Stock activity</h2>
                            <ul class="mt-3 space-y-0 divide-y divide-[color:var(--color-border)] text-sm">
                                @foreach ($recentMovements as $mv)
                                    <li class="py-2.5 first:pt-0 last:pb-0">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="font-medium text-[color:var(--color-ink)]">{{ $movementLabels[$mv->movement_type] ?? Str::title(str_replace('_', ' ', $mv->movement_type)) }}</p>
                                            <p class="shrink-0 font-semibold tabular-nums text-[color:var(--color-ink)]">{{ (int) $mv->previous_stock }} → {{ (int) $mv->new_stock }}</p>
                                        </div>
                                        <p class="mt-0.5 text-xs text-[color:var(--color-ink-muted)]">
                                            {{ optional($mv->created_at)->format('M j, Y · g:i A') }}
                                            @if ($mv->location)
                                                · {{ $mv->location->name }}
                                            @endif
                                            @if ($mv->performer)
                                                · {{ $mv->performer->name }}
                                            @endif
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <details id="workspace-advanced-imported-panel" class="{{ $card }} group p-4">
                        <summary class="cursor-pointer list-none text-[15px] font-semibold text-[color:var(--color-ink)] [&::-webkit-details-marker]:hidden">
                            <span class="inline-flex w-full items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-2">
                                    <svg class="h-3.5 w-3.5 shrink-0 text-[color:var(--color-ink-muted)] transition group-open:rotate-90" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M6 4l4 4-4 4V4z"/></svg>
                                    Advanced imported data
                                </span>
                                @if ($hasImportExtra)
                                    <span class="rounded bg-[color:var(--color-surface-muted)] px-1.5 py-0.5 text-xs font-semibold text-[color:var(--color-ink-muted)]">{{ count($importExtraRows) }}</span>
                                @endif
                            </span>
                        </summary>
                        <p class="mt-2 text-sm text-[color:var(--color-ink-muted)]">
                            Read-only columns from import. Use <span class="font-medium text-[color:var(--color-ink-secondary)]">Make editable</span> to copy into additional details.
                        </p>
                        @if ($importExtraRows === [])
                            <p class="mt-3 text-sm text-[color:var(--color-ink-muted)]">No extra imported data for this product.</p>
                        @else
                            <dl class="mt-3 space-y-2.5 text-sm">
                                @foreach ($importExtraRows as $row)
                                    @php
                                        $rawKey = $row['raw_key'] ?? '';
                                        $looksCategory = $rawKey !== '' && \App\Support\ImportExtraColumnHints::looksLikeCategoryKey($rawKey);
                                    @endphp
                                    <div class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-3 py-2.5">
                                        <dt class="text-xs font-semibold text-[color:var(--color-ink-muted)]">{{ $row['label'] }}</dt>
                                        <dd class="mt-1 break-words text-[color:var(--color-ink-secondary)]">
                                            @if (\App\Support\ProductDetailPresenter::isLong($row['value_display']))
                                                <details>
                                                    <summary class="cursor-pointer text-xs font-semibold text-brand hover:underline">View full value</summary>
                                                    <pre class="mt-2 max-h-56 overflow-auto rounded border border-[color:var(--color-border)] bg-white p-2 text-xs leading-relaxed">{{ $row['value_display'] }}</pre>
                                                </details>
                                            @else
                                                {{ $row['value_display'] }}
                                            @endif
                                        </dd>
                                        @if ($canManageCatalog && $rawKey !== '')
                                            <div class="mt-2.5 flex flex-wrap gap-2 border-t border-[color:var(--color-border)] pt-2.5">
                                                <form method="post" action="{{ route('products.workspace.promote-import-extra', $product) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="source_key" value="{{ $rawKey }}">
                                                    <button type="submit" class="inline-flex rounded border border-[#BFDBFE] bg-[#EFF6FF] px-2.5 py-1 text-xs font-semibold text-[#1D4ED8] hover:bg-[#DBEAFE]">Make editable</button>
                                                </form>
                                                @if ($looksCategory)
                                                    <form method="post" action="{{ route('products.workspace.apply-import-category', $product) }}" class="inline">
                                                        @csrf
                                                        <input type="hidden" name="source_key" value="{{ $rawKey }}">
                                                        <button type="submit" class="inline-flex rounded border border-[#99F6E4] bg-[#ECFDF5] px-2.5 py-1 text-xs font-semibold text-[#047857] hover:bg-[#D1FAE5]">Use as catalog category</button>
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
    </div>

    @push('scripts')
        <script>
            (() => {
                const root = document.querySelector('[data-workspace-media]');
                if (!root) return;
                const primary = root.querySelector('[data-workspace-primary-image]');
                if (!primary) return;
                root.querySelectorAll('[data-workspace-thumb]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const src = btn.getAttribute('data-src');
                        if (!src) return;
                        primary.src = src;
                        root.querySelectorAll('[data-workspace-thumb]').forEach((b) => {
                            b.classList.remove('border-brand');
                            b.classList.add('border-[color:var(--color-border)]');
                        });
                        btn.classList.add('border-brand');
                        btn.classList.remove('border-[color:var(--color-border)]');
                    });
                });
            })();
        </script>
    @endpush
@endsection
