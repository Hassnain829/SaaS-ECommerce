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
    $hasOrganization = ($product->brand && (int) $product->brand->store_id === $workspaceStoreId)
        || $product->categories->contains(fn ($c): bool => (int) $c->store_id === $workspaceStoreId)
        || $product->tags->contains(fn ($t): bool => (int) $t->store_id === $workspaceStoreId);
    $hasCustom = $customFieldRows !== [];
    $hasAttributes = $attributeRows !== [];
    $hasImportExtra = $importExtraRows !== [];
    $hasCopy = filled($shortDesc) || filled($product->description);
    $variantCount = count($variantSummaries);
    $multiVariant = $variantCount > 1;
@endphp

@section('title', $product->name.' — Product workspace')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="ui-page-enter flex-1 overflow-y-auto p-4 lg:p-8">
        <div class="mx-auto max-w-[1400px] space-y-6">
            @include('user_view.partials.flash_success')

            {{-- Compact product header (name once) --}}
            <header class="flex flex-col gap-4 border-b border-[color:var(--color-border)] pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[color:var(--color-ink-muted)]">Product workspace</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="break-words font-heading text-2xl font-semibold leading-tight text-[color:var(--color-ink)] sm:text-3xl">{{ $product->name }}</h1>
                        @if ($product->status)
                            <x-ui.badge tone="success">Published</x-ui.badge>
                        @else
                            <x-ui.badge>Draft</x-ui.badge>
                        @endif
                    </div>
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[color:var(--color-ink-muted)]">
                        <span class="font-mono font-medium text-[color:var(--color-ink-secondary)]">{{ $product->sku ?: 'No SKU' }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $behaviorLabel }}</span>
                        <span aria-hidden="true">·</span>
                        <span @class([
                            'font-medium',
                            'text-red-600' => $outOfStock,
                            'text-amber-700' => $lowStock && ! $outOfStock,
                            'text-[color:var(--color-ink-secondary)]' => ! $outOfStock && ! $lowStock,
                        ])>{{ $stockLabel }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <a href="{{ route('products') }}" class="ui-btn ui-btn-ghost">Catalog</a>
                    @if ($canManageCatalog)
                        <x-ui.button :href="route('products.edit', $product)">Edit details</x-ui.button>
                    @endif
                </div>
            </header>

            <div class="grid gap-6 lg:grid-cols-12 lg:items-start lg:gap-8">
                {{-- Main column --}}
                <div class="min-w-0 space-y-6 lg:col-span-8">
                    {{-- Media --}}
                    <section class="rounded-xl border border-[color:var(--color-border)] bg-white p-5 sm:p-6" data-workspace-media>
                        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <h2 class="text-base font-semibold text-[color:var(--color-ink)]">Media</h2>
                                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">Photos shoppers see for this product.</p>
                            </div>
                            @if ($canManageCatalog && $hasMedia)
                                <a href="{{ route('products.edit', $product) }}" class="text-sm font-semibold text-brand hover:underline">Manage images</a>
                            @endif
                        </div>

                        @if (! $hasMedia)
                            <p class="rounded-lg border border-dashed border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-4 py-5 text-sm text-[color:var(--color-ink-muted)]">
                                @if ($canManageCatalog)
                                    No photos yet. Add them from <a href="{{ route('products.edit', $product) }}" class="font-semibold text-brand hover:underline">Edit product</a>.
                                @else
                                    No catalog images yet.
                                @endif
                            </p>
                        @else
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                <div class="shrink-0">
                                    @if ($primaryUrl)
                                        <img
                                            data-workspace-primary-image
                                            src="{{ $primaryUrl }}"
                                            alt="{{ $product->name }}"
                                            class="h-52 w-52 rounded-xl border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] object-cover sm:h-56 sm:w-56"
                                        >
                                    @else
                                        <div class="flex h-52 w-52 items-center justify-center rounded-xl border border-dashed border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] text-center text-xs text-[color:var(--color-ink-muted)] sm:h-56 sm:w-56">
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
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Gallery</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($readyImages as $img)
                                                @php $thumbUrl = asset('storage/'.$img->image_path); @endphp
                                                <button
                                                    type="button"
                                                    data-workspace-thumb
                                                    data-src="{{ $thumbUrl }}"
                                                    class="rounded-lg border border-[color:var(--color-border)] p-0.5 transition hover:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30 {{ $primaryUrl === $thumbUrl ? 'border-brand ring-2 ring-brand/20' : '' }}"
                                                    aria-label="Show gallery image"
                                                >
                                                    <img src="{{ $thumbUrl }}" alt="" class="h-16 w-16 rounded-md object-cover">
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                            @if ($product->images->contains(fn ($i) => $i->isPendingVisual() || $i->isFailed()))
                                <p class="mt-3 text-xs text-[color:var(--color-ink-muted)]">Some images are still processing or could not be loaded from your import.</p>
                            @endif
                        @endif
                    </section>

                    {{-- Storefront copy --}}
                    @if ($hasCopy)
                        <section class="rounded-xl border border-[color:var(--color-border)] bg-white p-5 sm:p-6">
                            <div class="mb-4">
                                <h2 class="text-base font-semibold text-[color:var(--color-ink)]">Storefront copy</h2>
                                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">Short and full description shown to shoppers.</p>
                            </div>
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
                        <section class="rounded-xl border border-[color:var(--color-border)] bg-white p-5 sm:p-6">
                            <div class="mb-4">
                                <h2 class="text-base font-semibold text-[color:var(--color-ink)]">Option groups</h2>
                                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">Choices shoppers pick, such as size or color.</p>
                            </div>
                            <ul class="grid gap-3 sm:grid-cols-2">
                                @foreach ($optionGroupSummaries as $group)
                                    <li class="rounded-lg border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-4 py-3">
                                        <p class="text-sm font-semibold text-[color:var(--color-ink)]">{{ $group['name'] }}</p>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($group['values'] as $val)
                                                <span class="inline-flex rounded-md border border-[color:var(--color-border)] bg-white px-2.5 py-1 text-sm font-medium text-[color:var(--color-ink)]">{{ $val }}</span>
                                            @endforeach
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Inventory / variants --}}
                    <section class="rounded-xl border border-[color:var(--color-border)] bg-white p-5 sm:p-6">
                        <div class="mb-4">
                            @if ($multiVariant)
                                <h2 class="text-base font-semibold text-[color:var(--color-ink)]">Sellable combinations</h2>
                                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">Each row is one variant with its own SKU, price, and stock.</p>
                            @else
                                <h2 class="text-base font-semibold text-[color:var(--color-ink)]">Default inventory</h2>
                                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">
                                    One inventory row for this product.
                                    @if ($canManageCatalog)
                                        Add option groups in <span class="font-medium text-[color:var(--color-ink-secondary)]">Edit product</span> only if shoppers choose size, color, or similar.
                                    @endif
                                </p>
                            @endif
                        </div>

                        @if ($variantSummaries === [])
                            <p class="text-sm text-[color:var(--color-ink-muted)]">No sellable rows are linked to this product yet.</p>
                        @elseif (! $multiVariant)
                            @php $row = $variantSummaries[0]; @endphp
                            <div class="rounded-lg border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] p-4 sm:p-5">
                                @if ($row['is_first'])
                                    <p class="mb-3 inline-flex rounded-md bg-brand-soft px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand">Default variant</p>
                                @endif
                                <div class="mb-4">
                                    <p class="text-sm font-semibold text-[color:var(--color-ink)]">{{ $row['label'] }}</p>
                                    @if (! empty($row['chips']))
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($row['chips'] as $chip)
                                                <span class="inline-flex rounded-md border border-[color:var(--color-border)] bg-white px-2.5 py-1 text-xs font-medium text-[color:var(--color-ink)]">{{ $chip['group'] }}: {{ $chip['value'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                                    <div class="shrink-0 text-center sm:text-left">
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Photo</p>
                                        @if (! empty($row['catalog_image_thumb']))
                                            <img src="{{ $row['catalog_image_thumb'] }}" alt="" class="mx-auto h-20 w-20 rounded-lg border border-[color:var(--color-border)] object-cover sm:mx-0">
                                            <p class="mt-2 text-xs text-[color:var(--color-ink-muted)]">
                                                @if (! empty($row['catalog_image_is_product_fallback']))
                                                    Uses main product image.
                                                @else
                                                    Variant-specific image.
                                                @endif
                                            </p>
                                        @else
                                            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-lg border border-dashed border-[color:var(--color-border)] bg-white text-[10px] font-medium text-[color:var(--color-ink-muted)] sm:mx-0">No image</div>
                                        @endif
                                    </div>
                                    <dl class="min-w-0 flex-1 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-lg border border-[color:var(--color-border)] bg-white px-3 py-2.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">SKU</dt>
                                            <dd class="mt-1 font-mono text-sm font-semibold text-[color:var(--color-ink)]">{{ $row['sku'] }}</dd>
                                        </div>
                                        <div class="rounded-lg border border-[color:var(--color-border)] bg-white px-3 py-2.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Retail price</dt>
                                            <dd class="mt-1 text-base font-semibold tabular-nums text-[color:var(--color-ink)]">{{ $currency }} {{ number_format((float) $row['price'], 2) }}</dd>
                                        </div>
                                        <div class="rounded-lg border border-[color:var(--color-border)] bg-white px-3 py-2.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Compare-at</dt>
                                            <dd class="mt-1 text-sm font-semibold tabular-nums text-[color:var(--color-ink-secondary)]">
                                                @if (! empty($row['compare_at_price']))
                                                    {{ $currency }} {{ number_format((float) $row['compare_at_price'], 2) }}
                                                @else
                                                    —
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="rounded-lg border border-[color:var(--color-border)] bg-white px-3 py-2.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Available</dt>
                                            <dd class="mt-1 text-base font-semibold tabular-nums text-[color:var(--color-ink)]">{{ number_format($row['stock']) }}</dd>
                                        </div>
                                        <div class="rounded-lg border border-[color:var(--color-border)] bg-white px-3 py-2.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Reserved</dt>
                                            <dd class="mt-1 text-base font-semibold tabular-nums text-[color:var(--color-ink)]">{{ number_format($row['reserved'] ?? 0) }}</dd>
                                        </div>
                                        <div class="rounded-lg border border-[color:var(--color-border)] bg-white px-3 py-2.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Committed</dt>
                                            <dd class="mt-1 text-base font-semibold tabular-nums text-[color:var(--color-ink)]">{{ number_format($row['committed'] ?? 0) }}</dd>
                                        </div>
                                        <div class="rounded-lg border border-[color:var(--color-border)] bg-white px-3 py-2.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Location</dt>
                                            <dd class="mt-1 text-sm font-semibold text-[color:var(--color-ink)]">{{ $row['location_name'] ?? 'Main location' }}</dd>
                                        </div>
                                        <div class="rounded-lg border border-[color:var(--color-border)] bg-white px-3 py-2.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Low-stock alert</dt>
                                            <dd class="mt-1 text-sm font-medium tabular-nums text-[color:var(--color-ink-secondary)]">{{ $row['stock_alert'] > 0 ? number_format($row['stock_alert']) : 'Not set' }}</dd>
                                        </div>
                                    </dl>
                                </div>
                                @if (! empty($row['additional_detail_rows']))
                                    <details class="mt-4 rounded-lg border border-[color:var(--color-border)] bg-white px-3 py-2.5">
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
                                @endif
                            </div>
                        @else
                            <div class="overflow-x-auto rounded-lg border border-[color:var(--color-border)]">
                                <table class="w-full min-w-[880px] text-left text-sm" aria-describedby="variant-table-caption">
                                    <caption id="variant-table-caption" class="sr-only">Sellable combinations with photo, choices, SKU, pricing, and stock.</caption>
                                    <thead class="bg-[color:var(--color-surface-muted)] text-xs font-bold uppercase tracking-wide text-[color:var(--color-ink-muted)]">
                                        <tr>
                                            <th class="px-3 py-3">Photo</th>
                                            <th class="px-3 py-3">Combination</th>
                                            <th class="min-w-[8rem] px-3 py-3">Extra</th>
                                            <th class="px-3 py-3">SKU</th>
                                            <th class="min-w-[7rem] px-3 py-3">Price</th>
                                            <th class="min-w-[7rem] px-3 py-3">Compare-at</th>
                                            <th class="min-w-[5rem] px-3 py-3">Available</th>
                                            <th class="min-w-[5rem] px-3 py-3">Reserved</th>
                                            <th class="min-w-[6rem] px-3 py-3">Location</th>
                                            <th class="min-w-[6rem] px-3 py-3">Alert</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[color:var(--color-border)]">
                                        @foreach ($variantSummaries as $row)
                                            <tr class="bg-white even:bg-[color:var(--color-surface-muted)]/40">
                                                <td class="px-3 py-3 align-top">
                                                    @if (! empty($row['catalog_image_thumb']))
                                                        <img src="{{ $row['catalog_image_thumb'] }}" alt="" class="h-12 w-12 rounded-lg border border-[color:var(--color-border)] object-cover">
                                                    @else
                                                        <span class="text-xs text-[color:var(--color-ink-muted)]">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 align-top">
                                                    <div class="font-medium text-[color:var(--color-ink)]">{{ $row['label'] }}</div>
                                                    @if (! empty($row['chips']))
                                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                                            @foreach ($row['chips'] as $chip)
                                                                <span class="inline-flex rounded-md border border-[color:var(--color-border)] bg-white px-2 py-0.5 text-xs font-medium text-[color:var(--color-ink)]">{{ $chip['group'] }}: {{ $chip['value'] }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    @if ($row['is_first'])
                                                        <span class="mt-1.5 inline-flex rounded-md bg-brand-soft px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand">Default</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 align-top">
                                                    @if (! empty($row['additional_detail_rows']))
                                                        <details class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-2 py-1.5">
                                                            <summary class="cursor-pointer text-xs font-semibold text-brand hover:underline">
                                                                Extra details ({{ count($row['additional_detail_rows']) }})
                                                            </summary>
                                                            <dl class="mt-2 max-h-48 space-y-2 overflow-y-auto text-xs">
                                                                @foreach ($row['additional_detail_rows'] as $detailRow)
                                                                    <div class="rounded-md border border-[color:var(--color-border)] bg-white px-2 py-1.5">
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
                                                <td class="px-3 py-3 align-top font-mono text-xs text-[color:var(--color-ink-secondary)]">{{ $row['sku'] }}</td>
                                                <td class="px-3 py-3 align-top text-sm font-semibold tabular-nums text-[color:var(--color-ink)]">{{ $currency }} {{ number_format((float) $row['price'], 2) }}</td>
                                                <td class="px-3 py-3 align-top text-sm font-semibold tabular-nums text-[color:var(--color-ink-muted)]">
                                                    @if (! empty($row['compare_at_price']))
                                                        {{ $currency }} {{ number_format((float) $row['compare_at_price'], 2) }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 align-top text-sm font-semibold tabular-nums text-[color:var(--color-ink)]">{{ number_format($row['stock']) }}</td>
                                                <td class="px-3 py-3 align-top text-sm font-semibold tabular-nums text-[color:var(--color-ink-secondary)]">{{ number_format($row['reserved'] ?? 0) }}</td>
                                                <td class="px-3 py-3 align-top text-sm text-[color:var(--color-ink-muted)]">{{ $row['location_name'] ?? 'Main location' }}</td>
                                                <td class="px-3 py-3 align-top text-sm tabular-nums text-[color:var(--color-ink-muted)]">{{ $row['stock_alert'] > 0 ? number_format($row['stock_alert']) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($recentMovements->isNotEmpty())
                            <div class="mt-6 border-t border-[color:var(--color-border)] pt-5">
                                <h3 class="text-xs font-bold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Stock activity</h3>
                                <ul class="mt-3 space-y-2 text-sm text-[color:var(--color-ink-secondary)]">
                                    @foreach ($recentMovements as $mv)
                                        <li class="rounded-lg border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-3 py-2.5">
                                            <span class="font-medium text-[color:var(--color-ink)]">{{ $movementLabels[$mv->movement_type] ?? Str::title(str_replace('_', ' ', $mv->movement_type)) }}</span>
                                            <span class="text-[color:var(--color-ink-muted)]"> · </span>
                                            <span class="tabular-nums">{{ (int) $mv->previous_stock }} → {{ (int) $mv->new_stock }}</span>
                                            @if ($mv->location)
                                                <span class="text-[color:var(--color-ink-muted)]"> · {{ $mv->location->name }}</span>
                                            @endif
                                            @if ($mv->reason)
                                                <span class="text-[color:var(--color-ink-muted)]"> — {{ Str::limit($mv->reason, 80) }}</span>
                                            @endif
                                            <span class="mt-1 block text-xs text-[color:var(--color-ink-muted)]">{{ optional($mv->created_at)->format('M j, Y g:i A') }}@if ($mv->performer) · {{ $mv->performer->name }}@endif</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </section>

                    {{-- Specifications (only when present) --}}
                    @if ($hasAttributes)
                        <section class="rounded-xl border border-[color:var(--color-border)] bg-white p-5 sm:p-6">
                            <div class="mb-4">
                                <h2 class="text-base font-semibold text-[color:var(--color-ink)]">Specifications</h2>
                                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">Structured facts for filtering and comparison.</p>
                            </div>
                            <dl class="grid gap-3 sm:grid-cols-2">
                                @foreach ($attributeRows as $attributeRow)
                                    <div class="rounded-lg border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-3 py-2.5">
                                        <dt class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">
                                            <span>{{ $attributeRow['name'] }}</span>
                                            @if (! empty($attributeRow['is_filterable']))
                                                <span class="rounded-full bg-brand-soft px-2 py-0.5 text-[10px] font-bold text-brand">Filterable</span>
                                            @endif
                                        </dt>
                                        <dd class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($attributeRow['terms'] as $term)
                                                <span class="inline-flex rounded-md border border-[color:var(--color-border)] bg-white px-2.5 py-1 text-xs font-semibold text-[color:var(--color-ink-secondary)]">{{ $term }}</span>
                                            @endforeach
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </section>
                    @endif

                    {{-- Additional details: full card when present; one-line CTA when empty --}}
                    <section class="rounded-xl border border-[color:var(--color-border)] bg-white p-5 sm:p-6" aria-labelledby="workspace-additional-details-heading">
                        <div class="mb-4">
                            <h2 id="workspace-additional-details-heading" class="text-base font-semibold text-[color:var(--color-ink)]">Additional product details</h2>
                            @if ($hasCustom)
                                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">Editable extra fields for this product.</p>
                            @endif
                        </div>
                        @if ($hasCustom)
                            <dl class="grid gap-3 sm:grid-cols-2">
                                @foreach ($customFieldRows as $row)
                                    <div class="rounded-lg border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-3 py-2.5">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">{{ $row['label'] }}</dt>
                                        <dd class="mt-1.5 text-sm font-medium break-words text-[color:var(--color-ink)]">
                                            @if (\App\Support\ProductDetailPresenter::isLong($row['value_display']))
                                                <details class="group">
                                                    <summary class="cursor-pointer text-brand hover:underline">{{ Str::limit($row['value_display'], 120) }}</summary>
                                                    <pre class="mt-2 max-h-48 overflow-auto rounded-md bg-white p-2 text-xs text-[color:var(--color-ink-secondary)]">{{ $row['value_display'] }}</pre>
                                                </details>
                                            @else
                                                {{ $row['value_display'] }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @else
                            <p class="text-sm text-[color:var(--color-ink-muted)]">
                                No additional details yet.
                                @if ($canManageCatalog)
                                    <a href="{{ route('products.edit', $product) }}" class="font-semibold text-brand hover:underline">Add them in Edit product</a>.
                                @endif
                            </p>
                        @endif
                    </section>
                </div>

                {{-- Sticky right rail --}}
                <aside class="space-y-4 lg:col-span-4 lg:sticky lg:top-4">
                    @if ($canManageCatalog && (! $hasMedia || ! $hasOrganization))
                        @php
                            $workspaceNext = ! $hasMedia
                                ? 'Add product photos so the listing looks trustworthy.'
                                : 'Add a brand, category, or tags so this item is easier to find.';
                        @endphp
                        <x-ui.sticky-next
                            :message="$workspaceNext"
                            action-label="Edit details"
                            :action-href="route('products.edit', $product)"
                            class="!static rounded-xl border border-[color:var(--color-border)]"
                        />
                    @endif

                    <section class="rounded-xl border border-[color:var(--color-border)] bg-white p-5">
                        <h2 class="text-base font-semibold text-[color:var(--color-ink)]">Pricing</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">Base price</dt>
                                <dd class="text-xl font-semibold tabular-nums text-[color:var(--color-ink)]">{{ $currency }} {{ number_format((float) $product->base_price, 2) }}</dd>
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

                    <section class="rounded-xl border border-[color:var(--color-border)] bg-white p-5">
                        <h2 class="text-base font-semibold text-[color:var(--color-ink)]">Stock</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">Total available</dt>
                                <dd class="text-xl font-semibold tabular-nums text-brand">{{ number_format($totalStock) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">Low-stock threshold</dt>
                                <dd class="font-semibold tabular-nums text-[color:var(--color-ink)]">{{ $effectiveLowThreshold > 0 ? number_format($effectiveLowThreshold) : '—' }}</dd>
                            </div>
                        </dl>
                        @if ($outOfStock)
                            <p class="mt-3 text-sm font-semibold text-red-600">This product is out of stock.</p>
                        @elseif ($lowStock)
                            <p class="mt-3 text-sm font-semibold text-amber-700">Stock is at or below your low-stock alert.</p>
                        @endif
                    </section>

                    <section class="rounded-xl border border-[color:var(--color-border)] bg-white p-5">
                        <h2 class="text-base font-semibold text-[color:var(--color-ink)]">Organization</h2>
                        <div class="mt-4 space-y-4 text-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Brand</p>
                                @if ($product->brand && (int) $product->brand->store_id === $workspaceStoreId)
                                    <p class="mt-1 font-medium text-[color:var(--color-ink)]">{{ $product->brand->name }}</p>
                                @else
                                    <p class="mt-1 text-[color:var(--color-ink-muted)]">None</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Categories</p>
                                @if ($product->categories->contains(fn ($c) => (int) $c->store_id === $workspaceStoreId))
                                    <ul class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach ($product->categories as $cat)
                                            @if ((int) $cat->store_id === $workspaceStoreId)
                                                <li class="rounded-md border border-brand/30 bg-brand-soft px-2 py-0.5 text-xs font-semibold text-brand">{{ $cat->name }}</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-1 text-[color:var(--color-ink-muted)]">None</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-[color:var(--color-ink-muted)]">Tags</p>
                                @if ($product->tags->contains(fn ($t) => (int) $t->store_id === $workspaceStoreId))
                                    <ul class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach ($product->tags as $tag)
                                            @if ((int) $tag->store_id === $workspaceStoreId)
                                                <li class="rounded-md border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-2 py-0.5 text-xs font-semibold text-[color:var(--color-ink-secondary)]">{{ $tag->name }}</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-1 text-[color:var(--color-ink-muted)]">None</p>
                                @endif
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-[color:var(--color-border)] bg-white p-5">
                        <h2 class="text-base font-semibold text-[color:var(--color-ink)]">Product facts</h2>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">SKU</dt>
                                <dd class="font-mono font-medium text-[color:var(--color-ink)]">{{ $product->sku ?: '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">Behavior</dt>
                                <dd class="font-medium text-[color:var(--color-ink)]">{{ $behaviorLabel }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">Shipping</dt>
                                <dd class="font-medium text-[color:var(--color-ink)]">{{ ! empty($productBehavior['requires_shipping']) ? 'Required' : 'Not required' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">Inventory tracking</dt>
                                <dd class="font-medium text-[color:var(--color-ink)]">{{ ! empty($productBehavior['track_inventory']) ? 'On' : 'Off' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">Tax</dt>
                                <dd class="font-medium text-[color:var(--color-ink)]">{{ $product->is_taxable ? 'Applies' : 'Off' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-[color:var(--color-ink-muted)]">Added</dt>
                                <dd class="font-medium text-[color:var(--color-ink)]">{{ optional($product->created_at)->format('M j, Y') ?: '—' }}</dd>
                            </div>
                            @if ($product->updated_at && $product->updated_at->ne($product->created_at))
                                <div class="flex justify-between gap-3">
                                    <dt class="text-[color:var(--color-ink-muted)]">Updated</dt>
                                    <dd class="font-medium text-[color:var(--color-ink)]">{{ $product->updated_at->format('M j, Y') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>
                </aside>
            </div>

            {{-- Advanced imported data: bottom, collapsed --}}
            <details id="workspace-advanced-imported-panel" class="group rounded-xl border border-[color:var(--color-border)] bg-white p-5">
                <summary class="cursor-pointer list-none text-base font-semibold text-[color:var(--color-ink)] [&::-webkit-details-marker]:hidden">
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 shrink-0 text-[color:var(--color-ink-muted)] transition group-open:rotate-90" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M6 4l4 4-4 4V4z"/></svg>
                        Advanced imported data
                        @if ($hasImportExtra)
                            <span class="rounded-md bg-[color:var(--color-surface-muted)] px-2 py-0.5 text-xs font-semibold text-[color:var(--color-ink-muted)]">{{ count($importExtraRows) }}</span>
                        @endif
                    </span>
                </summary>
                <p class="mt-3 text-sm text-[color:var(--color-ink-muted)]">
                    Read-only columns preserved from import. Use <span class="font-medium text-[color:var(--color-ink-secondary)]">Make editable</span> to copy a column into additional details.
                </p>
                @if ($importExtraRows === [])
                    <p class="mt-4 text-sm text-[color:var(--color-ink-muted)]">No extra imported data for this product.</p>
                @else
                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach ($importExtraRows as $row)
                            @php
                                $rawKey = $row['raw_key'] ?? '';
                                $looksCategory = $rawKey !== '' && \App\Support\ImportExtraColumnHints::looksLikeCategoryKey($rawKey);
                            @endphp
                            <div class="rounded-lg border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-3 py-3">
                                <dt class="text-xs font-semibold text-[color:var(--color-ink-muted)]">{{ $row['label'] }}</dt>
                                <dd class="mt-1.5 break-words text-[color:var(--color-ink-secondary)]">
                                    @if (\App\Support\ProductDetailPresenter::isLong($row['value_display']))
                                        <details>
                                            <summary class="cursor-pointer text-xs font-semibold text-brand hover:underline">View full value</summary>
                                            <pre class="mt-2 max-h-56 overflow-auto rounded-md border border-[color:var(--color-border)] bg-white p-2 text-xs leading-relaxed text-[color:var(--color-ink-secondary)]">{{ $row['value_display'] }}</pre>
                                        </details>
                                    @else
                                        {{ $row['value_display'] }}
                                    @endif
                                </dd>
                                @if ($canManageCatalog && $rawKey !== '')
                                    <div class="mt-3 flex flex-wrap gap-2 border-t border-[color:var(--color-border)] pt-3">
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
                            b.classList.remove('border-brand', 'ring-2', 'ring-brand/20');
                        });
                        btn.classList.add('border-brand', 'ring-2', 'ring-brand/20');
                    });
                });
            })();
        </script>
    @endpush
@endsection
