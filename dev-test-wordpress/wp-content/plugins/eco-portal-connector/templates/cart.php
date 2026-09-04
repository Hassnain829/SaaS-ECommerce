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
<div class="eco-portal eco-portal--cart">
    <header class="eco-portal__header">
        <div>
            <p class="eco-portal__eyebrow">Your bag</p>
            <h1 class="eco-portal__title">Cart</h1>
            <p class="eco-portal__meta">Review your flavors, then continue to checkout.</p>
        </div>
        <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($shop_url); ?>">Continue shopping</a>
    </header>

    <?php if (! empty($connection['message']) && empty($connection['ok'])) : ?>
        <div class="eco-portal-notice eco-portal-notice--error"><?php echo esc_html((string) $connection['message']); ?></div>
    <?php endif; ?>

    <?php if ($cart === []) : ?>
        <div class="eco-portal-notice eco-portal-notice--info">Your cart is empty.</div>
        <p><a class="eco-portal__button eco-portal__button--cta" href="<?php echo esc_url($shop_url); ?>">Shop flavors</a></p>
    <?php else : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="eco-portal__cart-form">
            <input type="hidden" name="action" value="eco_portal_update_cart" />
            <?php wp_nonce_field('eco_portal_update_cart'); ?>

            <table class="eco-portal__table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
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
                                <?php if (! empty($line['variant_label']) && strtolower((string) $line['variant_label']) !== 'default') : ?>
                                    <div class="eco-portal__meta"><?php echo esc_html((string) $line['variant_label']); ?></div>
                                <?php endif; ?>
                                <?php if (! $available) : ?>
                                    <div class="eco-portal-notice eco-portal-notice--info">This option is no longer available.</div>
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

            <div class="eco-portal__cart-footer">
                <button type="submit" class="eco-portal__button eco-portal__button--secondary">Update cart</button>
                <div class="eco-portal__cart-totals">
                    <p><strong>Subtotal:</strong> <?php echo esc_html(Eco_Portal_Storefront::format_money($catalog_subtotal, $currency)); ?></p>
                    <p class="eco-portal__meta">Shipping and tax are calculated at checkout.</p>
                    <a class="eco-portal__button eco-portal__button--cta" href="<?php echo esc_url($checkout_url); ?>">Proceed to checkout</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>
