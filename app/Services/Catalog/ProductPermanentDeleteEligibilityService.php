<?php

namespace App\Services\Catalog;

use App\Exceptions\Catalog\ProductPermanentDeleteBlockedException;
use App\Models\Checkout;
use App\Models\CheckoutItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Guards product permanent delete against unresolved checkout lifecycles.
 *
 * Preserved checkout lines rely on SET NULL FKs — they must not be manually deleted.
 * Active payment flows must finish or be cancelled before catalog purge.
 */
final class ProductPermanentDeleteEligibilityService
{
    /** @var list<string> */
    private const BLOCKING_CHECKOUT_STATUSES = [
        Checkout::STATUS_PAYMENT_PENDING,
        Checkout::STATUS_PAID,
    ];

    public function assertCanForceDelete(Product $product): void
    {
        $reason = $this->blockingReason($product);
        if ($reason !== null) {
            throw new ProductPermanentDeleteBlockedException($reason);
        }
    }

    /**
     * @param  Collection<int, Product>|iterable<int, Product>  $products
     */
    public function assertCanForceDeleteMany(iterable $products): void
    {
        foreach ($products as $product) {
            $this->assertCanForceDelete($product);
        }
    }

    public function blockingReason(Product $product): ?string
    {
        if (! Schema::hasTable('checkout_items') || ! Schema::hasTable('checkouts')) {
            return null;
        }

        $productId = (int) $product->id;
        $variantIds = $product->variants()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $hasPaymentPending = CheckoutItem::query()
            ->where(function ($query) use ($productId, $variantIds): void {
                $query->where('product_id', $productId);

                if ($variantIds !== []) {
                    $query->orWhereIn('product_variant_id', $variantIds);
                }
            })
            ->whereHas('checkout', fn ($query) => $query->where('status', Checkout::STATUS_PAYMENT_PENDING))
            ->exists();

        if ($hasPaymentPending) {
            return "Cannot permanently delete '{$product->name}' while a customer checkout is waiting for payment.";
        }

        $hasPaidUnconverted = CheckoutItem::query()
            ->where(function ($query) use ($productId, $variantIds): void {
                $query->where('product_id', $productId);

                if ($variantIds !== []) {
                    $query->orWhereIn('product_variant_id', $variantIds);
                }
            })
            ->whereHas('checkout', fn ($query) => $query->where('status', Checkout::STATUS_PAID))
            ->exists();

        if ($hasPaidUnconverted) {
            return "Cannot permanently delete '{$product->name}' while a paid checkout is still being converted to an order.";
        }

        return null;
    }
}
