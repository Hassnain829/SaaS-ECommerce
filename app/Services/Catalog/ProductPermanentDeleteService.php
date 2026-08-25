<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Exceptions\Catalog\ProductPermanentDeleteCleanupPendingException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Central entry for merchant product permanent delete.
 *
 * Catalog children cascade via FK. Retention rows (order lines, stock ledger,
 * draft/checkout lines, outbox events, import history) rely on SET NULL and
 * snapshots — this service must not manually purge them.
 *
 * Gallery files are quarantined before DB hard-delete and restored if the
 * transaction fails; they are permanently removed only after commit succeeds.
 */
final class ProductPermanentDeleteService
{
    public function __construct(
        private readonly ProductPermanentDeleteEligibilityService $eligibility,
        private readonly ProductPermanentDeleteGalleryPurgeService $galleryPurge,
    ) {}

    public function forceDelete(Product $product): void
    {
        $this->forceDeleteMany(collect([$product]));
    }

    /**
     * @param  Collection<int, Product>|iterable<int, Product>  $products
     */
    public function forceDeleteMany(iterable $products): void
    {
        $products = $products instanceof Collection ? $products->values() : collect($products)->values();
        if ($products->isEmpty()) {
            return;
        }

        $this->eligibility->assertCanForceDeleteMany($products);

        $this->galleryPurge->retryAllPendingCleanups();

        $session = $this->galleryPurge->beginQuarantine($products);

        try {
            DB::transaction(function () use ($products): void {
                foreach ($products as $product) {
                    $product->forceDelete();
                }
            });
        } catch (\Throwable $e) {
            $this->galleryPurge->restoreQuarantine($session);

            throw $e;
        }

        try {
            $this->galleryPurge->commitQuarantine($session);
        } catch (ProductPermanentDeleteCleanupPendingException $e) {
            if ($this->galleryPurge->retryPendingCleanup($e->operationId)) {
                return;
            }

            throw $e;
        }
    }

    /**
     * @throws QueryException
     */
    public function forceDeleteOrFail(Product $product): void
    {
        $this->forceDeleteOrFailMany(collect([$product]));
    }

    /**
     * @param  Collection<int, Product>|iterable<int, Product>  $products
     *
     * @throws QueryException
     */
    public function forceDeleteOrFailMany(iterable $products): void
    {
        try {
            $this->forceDeleteMany($products);
        } catch (QueryException $e) {
            throw $e;
        }
    }
}
