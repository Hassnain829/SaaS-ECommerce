<?php

namespace App\Services\Store;

use App\Models\FedExTradeDocument;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImport;
use App\Models\Shipment;
use App\Models\Store;
use App\Services\SecurityLogRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Irreversible final store purge for already-closed stores.
 *
 * Merchant "Close Store" only soft-closes. Permanent deletion (owner-only, after
 * typed confirmation and password reauthentication) must call this service via
 * StorePurgeEligibilityService — never forceDelete() from controllers. This service:
 * 1) verifies the store is already soft-deleted
 * 2) deletes store-owned filesystem artifacts (Eloquent cascade will NOT run Product::deleting hooks)
 * 3) forceDeletes the store so DB cascades remove store-owned rows
 *
 * Required storage cleanup must succeed before DB forceDelete. Missing files are idempotent;
 * failed deletes abort the purge.
 */
final class StorePurgeService
{
    /**
     * @return array{
     *     store_id: int,
     *     store_name: string,
     *     files_deleted: int,
     *     files_missing: int,
     *     force_deleted: bool
     * }
     */
    public function purge(Store $store, ?\App\Models\User $actor = null): array
    {
        if (! $store->trashed()) {
            throw new InvalidArgumentException('Only a closed (soft-deleted) store can be hard-purged.');
        }

        $storeId = (int) $store->id;
        $storeName = (string) $store->name;

        $manifest = $this->buildArtifactManifest($store);
        $deleted = 0;
        $missing = 0;
        $fedexDisks = [];

        foreach ($manifest as $artifact) {
            $disk = (string) $artifact['disk'];
            $path = (string) $artifact['path'];
            $kind = (string) ($artifact['kind'] ?? 'generic');

            // Manifest only includes owned paths; re-check as a hard safety rail.
            $this->assertPersistedPathOwned($storeId, $kind, $path);

            if (str_starts_with(ltrim(str_replace('\\', '/', $path), '/'), 'fedex/')) {
                $fedexDisks[$disk] = true;
            }

            $result = $this->deleteFileOrAbort($disk, $path);
            if ($result === 'deleted') {
                $deleted++;
            } else {
                $missing++;
            }
        }

        $this->deleteDirectoryOrAbort('public', 'products/'.$storeId);
        $this->deleteDirectoryOrAbort('local', 'product-imports/'.$storeId);

        $fedexDisks[(string) config('carriers.fedex.label_storage_disk', 'local')] = true;
        foreach (array_keys($fedexDisks) as $disk) {
            $this->deleteDirectoryOrAbort((string) $disk, 'fedex/labels/'.$storeId);
            $this->deleteDirectoryOrAbort((string) $disk, 'fedex/etd/'.$storeId);
        }

        DB::transaction(function () use ($store): void {
            $locked = Store::onlyTrashed()->whereKey($store->id)->lockForUpdate()->firstOrFail();
            $locked->forceDelete();
        });

        app(SecurityLogRecorder::class)->record(
            request(),
            'store_purged',
            store: null,
            user: $actor,
            metadata: [
                'store_id' => $storeId,
                'store_name' => $storeName,
                'files_deleted' => $deleted,
                'files_missing' => $missing,
                'deletion_mode' => 'hard_purge',
            ]
        );

        return [
            'store_id' => $storeId,
            'store_name' => $storeName,
            'files_deleted' => $deleted,
            'files_missing' => $missing,
            'force_deleted' => true,
        ];
    }

    /**
     * @return list<array{disk: string, path: string, kind: string}>
     */
    public function buildArtifactManifest(Store $store): array
    {
        $storeId = (int) $store->id;
        $artifacts = [];
        $seen = [];

        $push = function (string $disk, string $path, string $kind) use (&$artifacts, &$seen): void {
            $key = $disk."\0".$path;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $artifacts[] = ['disk' => $disk, 'path' => $path, 'kind' => $kind];
        };

        if (filled($store->logo)) {
            $logo = (string) $store->logo;
            $this->assertPersistedPathOwned($storeId, 'logo', $logo);
            $push('public', $logo, 'logo');
        }

        $productIds = Product::withTrashed()
            ->where('store_id', $storeId)
            ->pluck('id');

        if ($productIds->isNotEmpty()) {
            ProductImage::query()
                ->whereIn('product_id', $productIds)
                ->orderBy('id')
                ->chunkById(500, function ($images) use ($push, $storeId): void {
                    foreach ($images as $image) {
                        $path = (string) ($image->image_path ?? '');
                        if ($path === '' || $path === ProductImage::PENDING_DISK_PATH) {
                            continue;
                        }
                        $this->assertPersistedPathOwned($storeId, 'product_image', $path);
                        $push('public', $path, 'product_image');
                    }
                });
        }

        if (class_exists(ProductImport::class) && Schema::hasTable('product_imports')) {
            ProductImport::query()
                ->where('store_id', $storeId)
                ->orderBy('id')
                ->chunkById(200, function ($imports) use ($push, $storeId): void {
                    foreach ($imports as $import) {
                        $path = (string) ($import->stored_path ?? '');
                        if ($path === '') {
                            continue;
                        }
                        $this->assertPersistedPathOwned($storeId, 'product_import', $path);
                        $disk = (string) ($import->stored_disk ?: 'local');
                        $push($disk, $path, 'product_import');
                    }
                });
        }

        if (Schema::hasTable('shipments')) {
            Shipment::withTrashed()
                ->where('store_id', $storeId)
                ->orderBy('id')
                ->chunkById(100, function ($shipments) use ($push, $storeId): void {
                    foreach ($shipments as $shipment) {
                        $labels = data_get($shipment->metadata, 'fedex.labels', []);
                        if (! is_array($labels)) {
                            continue;
                        }
                        foreach ($labels as $label) {
                            if (! is_array($label)) {
                                continue;
                            }
                            $path = (string) ($label['path'] ?? '');
                            if ($path === '') {
                                continue;
                            }
                            $disk = (string) ($label['disk'] ?? config('carriers.fedex.label_storage_disk', 'local'));
                            $this->assertPersistedPathOwned($storeId, 'fedex_label', $path);
                            $push($disk !== '' ? $disk : 'local', $path, 'fedex_label');
                        }
                    }
                });
        }

        if (class_exists(FedExTradeDocument::class) && Schema::hasTable('fedex_trade_documents')) {
            FedExTradeDocument::query()
                ->where('store_id', $storeId)
                ->orderBy('id')
                ->chunkById(200, function ($docs) use ($push, $storeId): void {
                    foreach ($docs as $doc) {
                        $path = (string) ($doc->storage_path ?? '');
                        if ($path === '') {
                            continue;
                        }
                        $kind = str_starts_with(ltrim(str_replace('\\', '/', $path), '/'), 'fedex/labels/')
                            ? 'fedex_label'
                            : 'fedex_etd';
                        $this->assertPersistedPathOwned($storeId, $kind, $path);
                        $disk = (string) ($doc->storage_disk ?: config('carriers.fedex.label_storage_disk', 'local'));
                        $push($disk !== '' ? $disk : 'local', $path, $kind);
                    }
                });
        }

        return $artifacts;
    }

    /**
     * Non-empty persisted paths that cannot be proven store-owned must abort purge
     * (do not skip then forceDelete — that orphans files).
     */
    private function assertPersistedPathOwned(int $storeId, string $kind, string $path): void
    {
        if ($this->pathBelongsToStore($storeId, $kind, $path)) {
            return;
        }

        throw new RuntimeException(
            'Store purge aborted: unsafe or unowned artifact path ['.$kind.':'.$path.'].'
        );
    }

    /**
     * Validate that a storage path is owned by this store (or a safe legacy logo path).
     */
    public function pathBelongsToStore(int $storeId, string $kind, string $path): bool
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if ($normalized === '' || str_contains($normalized, '..')) {
            return false;
        }

        return match ($kind) {
            'product_image' => str_starts_with($normalized, 'products/'.$storeId.'/'),
            'product_import' => str_starts_with($normalized, 'product-imports/'.$storeId.'/'),
            'fedex_label' => str_starts_with($normalized, 'fedex/labels/'.$storeId.'/'),
            'fedex_etd' => str_starts_with($normalized, 'fedex/etd/'.$storeId.'/'),
            'logo' => $this->logoPathIsDeletable($storeId, $normalized),
            default => false,
        };
    }

    private function logoPathIsDeletable(int $storeId, string $normalized): bool
    {
        if (str_starts_with($normalized, 'store-logos/'.$storeId.'/')) {
            return true;
        }

        // Legacy flat uploads: store-logos/filename — only delete if no other store shares the path.
        if (! str_starts_with($normalized, 'store-logos/')) {
            return false;
        }

        return ! Store::withTrashed()
            ->whereKeyNot($storeId)
            ->where('logo', $normalized)
            ->exists();
    }

    /**
     * @return 'deleted'|'missing'
     */
    private function deleteFileOrAbort(string $disk, string $path): string
    {
        try {
            $filesystem = Storage::disk($disk);
            if (! $filesystem->exists($path)) {
                return 'missing';
            }

            $ok = $filesystem->delete($path);
            if ($ok === false || $filesystem->exists($path)) {
                throw new RuntimeException(
                    'Store purge aborted: failed to delete storage artifact ['.$disk.':'.$path.'].'
                );
            }

            return 'deleted';
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Store purge aborted: failed to clean storage artifact on disk ['.$disk.']: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    private function deleteDirectoryOrAbort(string $disk, string $directory): void
    {
        if ($directory === '' || str_contains($directory, '..')) {
            return;
        }

        try {
            $filesystem = Storage::disk($disk);
            if (! $filesystem->exists($directory)) {
                return;
            }

            $ok = $filesystem->deleteDirectory($directory);
            if ($ok === false || $filesystem->exists($directory)) {
                throw new RuntimeException(
                    'Store purge aborted: failed to delete storage directory ['.$disk.':'.$directory.'].'
                );
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Store purge aborted: failed to clean storage directory on disk ['.$disk.']: '.$e->getMessage(),
                0,
                $e
            );
        }
    }
}
