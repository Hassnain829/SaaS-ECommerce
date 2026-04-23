<?php

namespace App\Services\Catalog;

use App\Jobs\ProcessProductImageJob;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImport;
use App\Models\Store;
use App\Support\ProductImageStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Downloads remote images into product_images (used by import + async image jobs).
 */
final class ProductCatalogImageDownloader
{
    /**
     * @param  list<string>  $urls
     * @return int number of images stored (ready immediately)
     */
    public function importUrls(Product $product, Store $store, array $urls, ?int $userId): int
    {
        $urls = array_values(array_filter(array_map('trim', $urls)));
        if ($urls === []) {
            return 0;
        }

        $nextOrder = (int) $product->images()->max('sort_order') + 1;
        $downloaded = 0;

        foreach ($urls as $url) {
            if ($downloaded >= 8) {
                break;
            }
            if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                continue;
            }

            $relative = $this->downloadRemoteImageToDisk($store, $url);
            if ($relative === null) {
                continue;
            }

            ProductImage::query()->create([
                'product_id' => $product->id,
                'image_path' => $relative,
                'source_url' => null,
                'alt_text' => null,
                'sort_order' => $nextOrder,
                'is_primary' => false,
                'status' => ProductImage::STATUS_READY,
                'processing_started_at' => null,
                'processed_at' => now(),
                'failure_reason' => null,
                'product_import_id' => null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $nextOrder++;
            $downloaded++;
        }

        if ($downloaded > 0) {
            $this->normalizePrimaryProductImage($product);
        }

        return $downloaded;
    }

    /**
     * Create queued rows and dispatch one job per URL (import must stay non-blocking).
     *
     * @param  list<string>  $urls
     * @return int number of queued image rows
     */
    public function enqueueRemoteUrlsForImport(
        ProductImport $import,
        Product $product,
        Store $store,
        array $urls,
        ?int $userId,
    ): int {
        $urls = array_values(array_filter(array_map('trim', $urls)));
        if ($urls === []) {
            return 0;
        }

        $nextOrder = (int) $product->images()->max('sort_order') + 1;
        $queued = 0;
        $first = true;

        foreach ($urls as $url) {
            if ($queued >= 8) {
                break;
            }
            if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                continue;
            }

            $row = ProductImage::query()->create([
                'product_id' => $product->id,
                'image_path' => ProductImage::PENDING_DISK_PATH,
                'source_url' => $url,
                'alt_text' => null,
                'sort_order' => $nextOrder,
                'is_primary' => $first,
                'status' => ProductImage::STATUS_QUEUED,
                'processing_started_at' => null,
                'processed_at' => null,
                'failure_reason' => null,
                'product_import_id' => $import->id,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $first = false;
            $nextOrder++;
            $queued++;

            ProcessProductImageJob::dispatch($row->id, $import->id);
        }

        if ($queued > 0) {
            $this->normalizePrimaryProductImage($product->fresh());
        }

        return $queued;
    }

    public function normalizePrimaryProductImage(Product $product): void
    {
        $rows = $product->images()->orderBy('sort_order')->orderBy('id')->get();
        $sorted = $rows->sort(function (ProductImage $a, ProductImage $b): int {
            $ar = $a->status === ProductImage::STATUS_READY ? 0 : 1;
            $br = $b->status === ProductImage::STATUS_READY ? 0 : 1;
            if ($ar !== $br) {
                return $ar <=> $br;
            }
            if ((int) $a->sort_order !== (int) $b->sort_order) {
                return (int) $a->sort_order <=> (int) $b->sort_order;
            }

            return (int) $a->id <=> (int) $b->id;
        })->values();

        foreach ($sorted as $index => $row) {
            $row->update([
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ]);
        }
    }

    /**
     * Download a single remote image to the public disk; returns relative path or null on failure.
     */
    public function downloadRemoteImageToDisk(Store $store, string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $maxBytes = 3 * 1024 * 1024;

        try {
            $response = Http::timeout(25)
                ->withOptions(['verify' => true])
                ->withHeaders(['User-Agent' => 'BaaS-Core-ProductImport/1.0'])
                ->get($url);
            if (! $response->successful()) {
                return null;
            }
            $body = $response->body();
            if (strlen($body) > $maxBytes) {
                return null;
            }
            $contentType = strtolower((string) $response->header('Content-Type'));
            if ($contentType !== '' && ! str_starts_with($contentType, 'image/')) {
                return null;
            }

            $ext = 'jpg';
            if (str_contains($contentType, 'png')) {
                $ext = 'png';
            } elseif (str_contains($contentType, 'webp')) {
                $ext = 'webp';
            } elseif (str_contains($contentType, 'gif')) {
                $ext = 'gif';
            }

            $relative = ProductImageStorage::directoryForStore($store).'/import-'.Str::random(12).'.'.$ext;
            Storage::disk('public')->put($relative, $body);

            return $relative;
        } catch (\Throwable $e) {
            Log::channel('import')->warning('product_image_download_failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
