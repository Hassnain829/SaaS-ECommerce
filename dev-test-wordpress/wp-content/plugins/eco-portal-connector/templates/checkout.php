<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @var array<string, array<string, mixed>> $cart */
/** @var string $shop_url */
/** @var array<string, mixed>|null $order_result */
/** @var string $error */

$subtotal = Eco_Portal_Storefront::cart_subtotal($cart);
?>
<div class="eco-portal">
    <header class="eco-portal__header">
        <div>
            <p class="eco-portal__eyebrow">Checkout</p>
            <h2 class="eco-portal__title">Place test order</h2>
            <p class="eco-portal__meta">Payment is simulated on this WordPress site, then the order is synced into your merchant portal.</p>
        </div>
        <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($shop_url); ?>">Back to shop</a>
    </header>

    <?php if ($error !== '') : ?>
        <div class="eco-portal-notice eco-portal-notice--error"><?php echo esc_html($error); ?></div>
    <?php endif; ?>

    <?php if (is_array($order_result)) : ?>
        <div class="eco-portal-notice eco-portal-notice--success">
            <p><strong>Order synced to the portal.</strong></p>
            <ul>
                <li>Portal order: <?php echo esc_html((string) ($order_result['portal_order_number'] ?? '')); ?></li>
                <li>WordPress order number: <?php echo esc_html((string) ($order_result['external_order_number'] ?? '')); ?></li>
                <li>Payment status: <?php echo esc_html((string) ($order_result['payment_status'] ?? '')); ?></li>
                <li>Total: <?php echo esc_html((string) ($order_result['total'] ?? '')); ?></li>
            </ul>
            <p>Open the merchant portal Orders list to confirm inventory and customer records.</p>
        </div>
    <?php elseif ($cart === []) : ?>
        <div class="eco-portal-notice eco-portal-notice--info">Your cart is empty. Add products from the shop first.</div>
    <?php else : ?>
        <div class="eco-portal__checkout-layout">
            <form class="eco-portal__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="eco_portal_place_order" />
                <?php wp_nonce_field('eco_portal_place_order'); ?>
                <input type="hidden" name="currency_code" value="USD" />

                <h3>Customer</h3>
                <label>Full name
                    <input type="text" name="customer_name" value="WP Test Buyer" required />
                </label>
                <label>Email
                    <input type="email" name="customer_email" value="wp.buyer@example.test" required />
                </label>
                <label>Phone
                    <input type="text" name="customer_phone" value="+1 555-0100" />
                </label>

                <h3>Shipping address</h3>
                <label>Address line 1
                    <input type="text" name="address_line1" value="100 WordPress Avenue" required />
                </label>
                <label>City
                    <input type="text" name="city" value="Austin" required />
                </label>
                <label>State / region
                    <input type="text" name="state" value="TX" />
                </label>
                <label>Postal code
                    <input type="text" name="postal_code" value="73301" required />
                </label>
                <label>Country
                    <input type="text" name="country" value="US" required />
                </label>

                <h3>Simulated payment &amp; totals</h3>
                <label>Payment status
                    <select name="payment_status">
                        <option value="paid" selected>Paid</option>
                        <option value="pending">Pending</option>
                        <option value="cod_pending">COD pending</option>
                        <option value="bank_transfer_pending">Bank transfer pending</option>
                    </select>
                </label>
                <label>Shipping amount
                    <input type="number" step="0.01" min="0" name="shipping_total" value="4.50" />
                </label>
                <label>Tax amount
                    <input type="number" step="0.01" min="0" name="tax_total" value="1.50" />
                </label>
                <label>Discount amount
                    <input type="number" step="0.01" min="0" name="discount_total" value="0" />
                </label>

                <button type="submit" class="eco-portal__button">Place order &amp; sync to portal</button>
            </form>

            <aside class="eco-portal__summary">
                <h3>Order summary</h3>
                <ul>
                    <?php foreach ($cart as $line) : ?>
                        <li>
                            <?php echo esc_html((string) ($line['product_name'] ?? 'Product')); ?>
                            (<?php echo esc_html((string) ($line['variant_label'] ?? 'Default')); ?>)
                            × <?php echo esc_html((string) ($line['quantity'] ?? 1)); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Subtotal:</strong> <?php echo esc_html($subtotal); ?></p>
            </aside>
        </div>
    <?php endif; ?>
</div>
