<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @var array<string, array<string, mixed>> $cart */
/** @var string $checkout_url */
/** @var string $shop_url */
/** @var string $currency */
/** @var string $catalog_subtotal */
/** @var array<string, mixed> $connection */

$catalog_subtotal = $catalog_subtotal ?? Eco_Portal_Storefront::cart_subtotal($cart);
$currency = $currency ?? Eco_Portal_Storefront::store_currency();
$connection = is_array($connection ?? null) ? $connection : [];
?>
<div class="eco-portal">
    <header class="eco-portal__header">
        <div>
            <p class="eco-portal__eyebrow">Cart</p>
            <h2 class="eco-portal__title">Portal cart</h2>
            <p class="eco-portal__meta">This cart holds product options and quantities only. Delivery, tax, and the amount to pay come from the merchant portal at checkout.</p>
        </div>
        <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($shop_url); ?>">Continue shopping</a>
    </header>

    <?php if (! empty($connection['message']) && empty($connection['ok'])) : ?>
        <div class="eco-portal-notice eco-portal-notice--error"><?php echo esc_html((string) $connection['message']); ?></div>
    <?php endif; ?>

    <?php if ($cart === []) : ?>
        <div class="eco-portal-notice eco-portal-notice--info">Your cart is empty.</div>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="eco_portal_update_cart" />
            <?php wp_nonce_field('eco_portal_update_cart'); ?>

            <table class="eco-portal__table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Catalog price</th>
                        <th>Qty</th>
                        <th>Line</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $key => $line) : ?>
                        <?php
                        $qty = (int) ($line['quantity'] ?? 1);
                        $available = ! empty($line['available']);
                        $unit = (string) ($line['unit_price'] ?? '');
                        $line_total = $available && $unit !== '' ? number_format(((float) $unit) * $qty, 2, '.', '') : '';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html((string) ($line['product_name'] ?? 'Product')); ?></strong>
                                <div class="eco-portal__meta"><?php echo esc_html((string) ($line['variant_label'] ?? 'Default')); ?></div>
                                <?php if (! $available) : ?>
                                    <div class="eco-portal-notice eco-portal-notice--info">This option is no longer available in the merchant portal.</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $available && $unit !== '' ? esc_html(Eco_Portal_Storefront::format_money($unit, $currency)) : '—'; ?>
                            </td>
                            <td>
                                <input type="number" name="quantity[<?php echo esc_attr((string) $key); ?>]" min="0" max="999" value="<?php echo esc_attr((string) $qty); ?>" />
                            </td>
                            <td><?php echo $line_total !== '' ? esc_html(Eco_Portal_Storefront::format_money($line_total, $currency)) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="eco-portal__actions">
                <p>
                    <strong>Catalog subtotal:</strong> <?php echo esc_html(Eco_Portal_Storefront::format_money($catalog_subtotal, $currency)); ?>
                    <span class="eco-portal__meta">Not the checkout total.</span>
                </p>
                <div class="eco-portal__action-buttons">
                    <button type="submit" name="cart_action" value="update" class="eco-portal__button eco-portal__button--secondary">Update cart</button>
                    <button type="submit" name="cart_action" value="clear" class="eco-portal__button eco-portal__button--secondary">Clear cart</button>
                    <?php if (! empty($connection['ok'])) : ?>
                        <a class="eco-portal__button" href="<?php echo esc_url($checkout_url); ?>">Checkout</a>
                    <?php else : ?>
                        <span class="eco-portal__button" style="opacity:0.55;pointer-events:none;">Checkout blocked</span>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>
