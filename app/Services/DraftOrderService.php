<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DraftOrder;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Support\ProductVariantLabel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DraftOrderService
{
    public function create(Store $store, User $actor, array $data): DraftOrder
    {
        return DB::transaction(function () use ($store, $actor, $data): DraftOrder {
            $customer = $this->resolveCustomer($store, $data);

            $draft = DraftOrder::query()->create([
                'store_id' => $store->id,
                'customer_id' => $customer?->id,
                'draft_number' => app(OrderNumberGenerator::class)->generateDraft($store),
                'status' => DraftOrder::STATUS_DRAFT,
                'currency' => $store->currency ?: 'USD',
                'discount_total' => $this->money($data['discount_total'] ?? 0),
                'tax_total' => $this->money($data['tax_total'] ?? 0),
                'shipping_total' => $this->money($data['shipping_total'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'metadata' => $this->addressMetadata($data),
            ]);

            $this->replaceItems($draft, $data['items'] ?? []);
            $this->recalculate($draft);

            return $draft->load(['customer', 'items.variant.product']);
        });
    }

    public function update(DraftOrder $draft, array $data): DraftOrder
    {
        if ($draft->status !== DraftOrder::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'draft_order' => 'Converted or cancelled draft orders cannot be changed.',
            ]);
        }

        return DB::transaction(function () use ($draft, $data): DraftOrder {
            $draft->update([
                'discount_total' => $this->money($data['discount_total'] ?? 0),
                'tax_total' => $this->money($data['tax_total'] ?? 0),
                'shipping_total' => $this->money($data['shipping_total'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'metadata' => $this->addressMetadata($data),
            ]);

            $this->replaceItems($draft, $data['items'] ?? []);
            $this->recalculate($draft);

            return $draft->fresh(['customer', 'items.variant.product']);
        });
    }

    public function cancel(DraftOrder $draft): void
    {
        if ($draft->status !== DraftOrder::STATUS_DRAFT) {
            return;
        }

        $draft->update([
            'status' => DraftOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function replaceItems(DraftOrder $draft, array $items): void
    {
        $draft->items()->delete();

        $mergedItems = [];

        foreach ($items as $row) {
            $variantId = (int) ($row['product_variant_id'] ?? 0);
            if ($variantId <= 0) {
                continue;
            }

            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::query()
                ->where('store_id', $draft->store_id)
                ->whereKey($variantId)
                ->with(['product.images', 'options.variationType'])
                ->first();

            if (! $variant || ! $variant->product || (int) $variant->product->store_id !== (int) $draft->store_id) {
                throw ValidationException::withMessages([
                    'items' => 'One of the selected products is not available for this store.',
                ]);
            }

            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            $rawUnitPrice = $row['unit_price'] ?? null;
            $unitPrice = $rawUnitPrice === null || trim((string) $rawUnitPrice) === ''
                ? $this->money($variant->price)
                : $this->money($rawUnitPrice);

            if (isset($mergedItems[$variant->id])) {
                $mergedItems[$variant->id]['quantity'] += $quantity;

                continue;
            }

            $mergedItems[$variant->id] = [
                'store_id' => $draft->store_id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_title' => ProductVariantLabel::forVariant($variant, 0, 1),
                'sku' => $variant->sku,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'metadata' => [
                    'product_type' => $variant->product->product_type,
                    'product_image' => $variant->product->images->sortByDesc('is_primary')->first()?->image_path,
                ],
            ];
        }

        if ($mergedItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product before saving the draft order.',
            ]);
        }

        foreach ($mergedItems as $item) {
            $item['line_total'] = bcmul((string) $item['unit_price'], (string) $item['quantity'], 2);

            $draft->items()->create($item);
        }
    }

    private function recalculate(DraftOrder $draft): void
    {
        $subtotal = $draft->items()
            ->get()
            ->reduce(fn (string $carry, $item): string => bcadd($carry, (string) $item->line_total, 2), '0');

        $total = bcadd($subtotal, (string) $draft->shipping_total, 2);
        $total = bcadd($total, (string) $draft->tax_total, 2);
        $total = bcsub($total, (string) $draft->discount_total, 2);

        if (bccomp($total, '0', 2) < 0) {
            $total = '0';
        }

        $draft->forceFill([
            'subtotal' => $subtotal,
            'total' => $total,
        ])->save();
    }

    private function resolveCustomer(Store $store, array $data): ?Customer
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        if ($customerId > 0) {
            return Customer::query()
                ->where('store_id', $store->id)
                ->whereKey($customerId)
                ->firstOrFail();
        }

        $email = trim((string) ($data['customer_email'] ?? ''));
        if ($email === '') {
            return null;
        }

        $name = trim((string) ($data['customer_name'] ?? ''));
        $phone = trim((string) ($data['customer_phone'] ?? ''));

        return Customer::query()->firstOrCreate(
            ['store_id' => $store->id, 'email' => $email],
            [
                'full_name' => $name !== '' ? $name : $email,
                'phone' => $phone !== '' ? $phone : null,
                'status' => 'active',
                'source' => 'manual_order',
            ]
        );
    }

    private function addressMetadata(array $data): array
    {
        return [
            'shipping_address' => [
                'name' => trim((string) ($data['shipping_name'] ?? '')),
                'email' => trim((string) ($data['customer_email'] ?? '')),
                'phone' => trim((string) ($data['shipping_phone'] ?? $data['customer_phone'] ?? '')),
                'address_line1' => trim((string) ($data['shipping_address_line1'] ?? '')),
                'address_line2' => trim((string) ($data['shipping_address_line2'] ?? '')),
                'city' => trim((string) ($data['shipping_city'] ?? '')),
                'state' => trim((string) ($data['shipping_state'] ?? '')),
                'postal_code' => trim((string) ($data['shipping_postal_code'] ?? '')),
                'country' => trim((string) ($data['shipping_country'] ?? '')),
            ],
            'billing_same_as_shipping' => (bool) ($data['billing_same_as_shipping'] ?? true),
            'billing_address' => [
                'name' => trim((string) ($data['billing_name'] ?? '')),
                'email' => trim((string) ($data['customer_email'] ?? '')),
                'phone' => trim((string) ($data['billing_phone'] ?? '')),
                'address_line1' => trim((string) ($data['billing_address_line1'] ?? '')),
                'address_line2' => trim((string) ($data['billing_address_line2'] ?? '')),
                'city' => trim((string) ($data['billing_city'] ?? '')),
                'state' => trim((string) ($data['billing_state'] ?? '')),
                'postal_code' => trim((string) ($data['billing_postal_code'] ?? '')),
                'country' => trim((string) ($data['billing_country'] ?? '')),
            ],
        ];
    }

    private function money(mixed $value): string
    {
        return number_format(max(0, (float) $value), 2, '.', '');
    }
}
