<?php

namespace App\Services\Catalog;

use App\Exceptions\Catalog\ProductPermanentDeleteCleanupPendingException;
use App\Exceptions\Catalog\ProductPermanentDeleteStorageException;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Store\StorePurgeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Validates, quarantines, restores, and commits product gallery files for permanent delete.
 *
 * Files are moved to a reversible quarantine before DB hard-delete so a failed transaction
 * can restore the merchant's gallery for soft-deleted products.
 */
class ProductPermanentDeleteGalleryPurgeService
{
    private const DISK = 'public';

    private const QUARANTINE_PREFIX = 'product-delete-quarantine';

    public function __construct(
        private readonly StorePurgeService $storePurge,
    ) {}

    /**
     * @param  Collection<int, Product>|iterable<int, Product>  $products
     */
    public function beginQuarantine(iterable $products): ProductGalleryQuarantineSession
    {
        $products = $products instanceof Collection ? $products->values() : collect($products)->values();
        $operationId = (string) Str::uuid();
        $entries = [];

        foreach ($products as $product) {
            $this->assertGalleryPathsOwned($product);
        }

        try {
            foreach ($products as $product) {
                foreach ($this->galleryPaths($product) as $originalPath) {
                    $quarantinePath = $this->quarantinePath($operationId, $originalPath);
                    $this->moveFileOrAbort(self::DISK, $originalPath, $quarantinePath);
                    $entries[] = [
                        'original' => $originalPath,
                        'quarantine' => $quarantinePath,
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->restoreQuarantine(new ProductGalleryQuarantineSession($operationId, $entries));

            throw $e;
        }

        return new ProductGalleryQuarantineSession($operationId, $entries);
    }

    public function restoreQuarantine(ProductGalleryQuarantineSession $session): void
    {
        foreach (array_reverse($session->entries) as $entry) {
            $original = $entry['original'];
            $quarantine = $entry['quarantine'];
            $filesystem = Storage::disk(self::DISK);

            if (! $filesystem->exists($quarantine)) {
                if ($filesystem->exists($original)) {
                    continue;
                }

                throw new ProductPermanentDeleteStorageException(
                    'Product permanent delete aborted: missing quarantined gallery file ['.self::DISK.':'.$quarantine.'].'
                );
            }

            if ($filesystem->exists($original)) {
                throw new ProductPermanentDeleteStorageException(
                    'Product permanent delete aborted: cannot restore gallery file; original path already exists ['.$original.'].'
                );
            }

            $this->moveFileOrAbort(self::DISK, $quarantine, $original);
        }

        $this->deleteDirectoryIfEmpty($session->operationId);
    }

    public function commitQuarantine(ProductGalleryQuarantineSession $session): void
    {
        foreach ($session->entries as $entry) {
            try {
                $this->deleteFileOrAbort(self::DISK, $entry['quarantine']);
            } catch (ProductPermanentDeleteStorageException) {
                $this->throwCleanupPending($session);
            }
        }

        try {
            $this->deleteDirectoryIfEmpty($session->operationId);
        } catch (ProductPermanentDeleteStorageException) {
            $this->throwCleanupPending($session);
        }

        if ($this->quarantineHasFiles($session->operationId)) {
            $this->throwCleanupPending($session);
        }
    }

    public function retryPendingCleanup(string $operationId): bool
    {
        $filesystem = Storage::disk(self::DISK);
        $directory = self::QUARANTINE_PREFIX.'/'.$operationId;

        if (! $filesystem->exists($directory)) {
            return true;
        }

        foreach ($filesystem->allFiles($directory) as $path) {
            try {
                $this->deleteFileOrAbort(self::DISK, $path);
            } catch (ProductPermanentDeleteStorageException) {
                return false;
            }
        }

        try {
            $this->deleteDirectoryIfEmpty($operationId);
        } catch (ProductPermanentDeleteStorageException) {
            return false;
        }

        return ! $filesystem->exists($directory);
    }

    public function retryAllPendingCleanups(): void
    {
        $filesystem = Storage::disk(self::DISK);

        if (! $filesystem->exists(self::QUARANTINE_PREFIX)) {
            return;
        }

        foreach ($filesystem->directories(self::QUARANTINE_PREFIX) as $directory) {
            $operationId = basename(str_replace('\\', '/', $directory));
            if ($operationId !== '') {
                $this->retryPendingCleanup($operationId);
            }
        }
    }

    public function assertGalleryPathsOwned(Product $product): void
    {
        $storeId = (int) $product->store_id;

        foreach ($this->galleryPaths($product) as $path) {
            if (! $this->storePurge->pathBelongsToStore($storeId, 'product_image', $path)) {
                throw new ProductPermanentDeleteStorageException(
                    'Product permanent delete aborted: unsafe or unowned product image path ['.$path.'].'
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function galleryPaths(Product $product): array
    {
        return $product->images()
            ->pluck('image_path')
            ->map(fn ($path) => (string) $path)
            ->filter(fn (string $path): bool => $path !== '' && $path !== ProductImage::PENDING_DISK_PATH)
            ->values()
            ->all();
    }

    private function quarantinePath(string $operationId, string $originalPath): string
    {
        $normalized = ltrim(str_replace('\\', '/', $originalPath), '/');
        if ($normalized === '' || str_contains($normalized, '..')) {
            throw new ProductPermanentDeleteStorageException(
                'Product permanent delete aborted: unsafe gallery path ['.$originalPath.'].'
            );
        }

        return self::QUARANTINE_PREFIX.'/'.$operationId.'/'.$normalized;
    }

    private function moveFileOrAbort(string $disk, string $from, string $to): void
    {
        try {
            $filesystem = Storage::disk($disk);
            if (! $filesystem->exists($from)) {
                return;
            }

            if ($filesystem->exists($to)) {
                throw new ProductPermanentDeleteStorageException(
                    'Product permanent delete aborted: quarantine path already exists ['.$disk.':'.$to.'].'
                );
            }

            $ok = $filesystem->move($from, $to);
            if ($ok === false || $filesystem->exists($from) || ! $filesystem->exists($to)) {
                throw new ProductPermanentDeleteStorageException(
                    'Product permanent delete aborted: failed to move storage artifact ['.$disk.':'.$from.' -> '.$to.'].'
                );
            }
        } catch (ProductPermanentDeleteStorageException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ProductPermanentDeleteStorageException(
                'Product permanent delete aborted: failed to move storage artifact on disk ['.$disk.']: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    private function deleteFileOrAbort(string $disk, string $path): void
    {
        try {
            $filesystem = Storage::disk($disk);
            if (! $filesystem->exists($path)) {
                return;
            }

            $ok = $filesystem->delete($path);
            if ($ok === false || $filesystem->exists($path)) {
                throw new ProductPermanentDeleteStorageException(
                    'Product permanent delete aborted: failed to delete storage artifact ['.$disk.':'.$path.'].'
                );
            }
        } catch (ProductPermanentDeleteStorageException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ProductPermanentDeleteStorageException(
                'Product permanent delete aborted: failed to clean storage artifact on disk ['.$disk.']: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    private function throwCleanupPending(ProductGalleryQuarantineSession $session): never
    {
        throw new ProductPermanentDeleteCleanupPendingException(
            $session->operationId,
            $this->listQuarantineFiles($session->operationId),
        );
    }

    private function quarantineHasFiles(string $operationId): bool
    {
        return $this->listQuarantineFiles($operationId) !== [];
    }

    /**
     * @return list<string>
     */
    private function listQuarantineFiles(string $operationId): array
    {
        $directory = self::QUARANTINE_PREFIX.'/'.$operationId;
        $filesystem = Storage::disk(self::DISK);

        if (! $filesystem->exists($directory)) {
            return [];
        }

        return array_values($filesystem->allFiles($directory));
    }

    private function deleteDirectoryIfEmpty(string $operationId): void
    {
        $directory = self::QUARANTINE_PREFIX.'/'.$operationId;
        $filesystem = Storage::disk(self::DISK);

        if (! $filesystem->exists($directory)) {
            return;
        }

        $files = $filesystem->allFiles($directory);
        if ($files !== []) {
            return;
        }

        $ok = $filesystem->deleteDirectory($directory);
        if ($ok === false && $filesystem->exists($directory)) {
            throw new ProductPermanentDeleteStorageException(
                'Product permanent delete aborted: failed to delete empty quarantine directory ['.self::DISK.':'.$directory.'].'
            );
        }
    }
}
