<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Services\Catalog\ProductCatalogImageDownloader;
use App\Services\Catalog\ProductImportMediaProgress;
use App\Support\Catalog\ProductImportMerchantMessages;
use App\Support\Catalog\ProductImportQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Downloads one catalog import image (non-blocking vs the main import worker).
 */
class ProcessProductImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [15, 45, 90];

    public function __construct(
        public int $productImageId,
        public ?int $productImportId,
    ) {
        $this->onConnection(ProductImportQueue::connection());
    }

    public function handle(ProductCatalogImageDownloader $downloader): void
    {
        $image = ProductImage::query()->find($this->productImageId);
        if (! $image) {
            return;
        }

        if ($image->status === ProductImage::STATUS_READY && $image->isReady()) {
            return;
        }

        $product = Product::query()->find($image->product_id);
        $store = $product ? Store::query()->find($product->store_id) : null;
        if (! $product || ! $store || (int) $product->store_id !== (int) $store->id) {
            $this->markFailed($image, 'Product or store was removed before this image could finish.');

            return;
        }

        $url = trim((string) ($image->source_url ?? ''));
        if ($url === '' && preg_match('#^https?://#i', (string) $image->image_path)) {
            $url = (string) $image->image_path;
        }
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            $this->markFailed($image, 'No valid image URL was stored for this row.');

            return;
        }

        if ($image->status === ProductImage::STATUS_QUEUED) {
            $image->update([
                'status' => ProductImage::STATUS_PROCESSING,
                'processing_started_at' => now(),
                'failure_reason' => null,
            ]);
        }

        $relative = $downloader->downloadRemoteImageToDisk($store, $url);
        if ($relative === null) {
            throw new \RuntimeException('The image could not be downloaded or was not a valid image file.');
        }

        $image->refresh();
        $image->update([
            'image_path' => $relative,
            'source_url' => null,
            'status' => ProductImage::STATUS_READY,
            'processed_at' => now(),
            'failure_reason' => null,
        ]);

        $downloader->normalizePrimaryProductImage($product->fresh());

        ProductImportMediaProgress::adjust($this->productImportId, 0, 1, 0);

        Log::channel('import')->info('product_import_image_ready', [
            'product_image_id' => $this->productImageId,
            'product_id' => $product->id,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $image = ProductImage::query()->find($this->productImageId);
        if (! $image || $image->status === ProductImage::STATUS_READY) {
            return;
        }
        if ($image->status === ProductImage::STATUS_FAILED) {
            return;
        }
        $this->markFailed(
            $image,
            ProductImportMerchantMessages::truncateForStorage($exception?->getMessage() ?? 'Job failed', 2000)
        );
    }

    private function markFailed(ProductImage $image, string $reason): void
    {
        $image->update([
            'status' => ProductImage::STATUS_FAILED,
            'failure_reason' => ProductImportMerchantMessages::truncateForStorage($reason, 2000),
            'processed_at' => now(),
        ]);

        ProductImportMediaProgress::adjust($this->productImportId, 0, 0, 1);

        Log::channel('import')->warning('product_import_image_failed', [
            'product_image_id' => $image->id,
            'product_id' => $image->product_id,
            'reason' => $reason,
        ]);
    }
}
