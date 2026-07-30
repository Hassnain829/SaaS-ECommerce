<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @var array<string, array<string, mixed>> $cart */
/** @var string $checkout_url */
/** @var string $shop_url */

$subtotal = Eco_Portal_Storefront::cart_subtotal($cart);
?>
<div class="eco-portal">
    <header class="eco-portal__header">
        <div>
            <p class="eco-portal__eyebrow">Cart</p>
            <h2 class="eco-portal__title">Portal cart</h2>
        </div>
        <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($shop_url); ?>">Continue shopping</a>
    </header>

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
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Line</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $key => $line) : ?>
                        <?php
                        $qty = (int) ($line['quantity'] ?? 1);
                        $unit = (string) ($line['unit_price'] ?? '0');
                        $line_total = number_format(((float) $unit) * $qty, 2, '.', '');
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html((string) ($line['product_name'] ?? 'Product')); ?></strong>
                                <div class="eco-portal__meta"><?php echo esc_html((string) ($line['variant_label'] ?? 'Default')); ?></div>
                            </td>
                            <td><?php echo esc_html($unit); ?></td>
                            <td>
                                <input type="number" name="quantity[<?php echo esc_attr((string) $key); ?>]" min="0" max="999" value="<?php echo esc_attr((string) $qty); ?>" />
                            </td>
                            <td><?php echo esc_html($line_total); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="eco-portal__actions">
                <p><strong>Subtotal:</strong> <?php echo esc_html($subtotal); ?></p>
                <div class="eco-portal__action-buttons">
                    <button type="submit" name="cart_action" value="update" class="eco-portal__button eco-portal__button--secondary">Update cart</button>
                    <button type="submit" name="cart_action" value="clear" class="eco-portal__button eco-portal__button--secondary">Clear cart</button>
                    <a class="eco-portal__button" href="<?php echo esc_url($checkout_url); ?>">Checkout</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>
