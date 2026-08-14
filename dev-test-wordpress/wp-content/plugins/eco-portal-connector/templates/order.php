<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed>|null $order_result */
/** @var string $error */
/** @var string $shop_url */
/** @var list<string> $recent */

$order_result = is_array($order_result ?? null) ? $order_result : null;
$recent = is_array($recent ?? null) ? $recent : [];
$shipments = is_array($order_result['shipments'] ?? null) ? $order_result['shipments'] : [];
$items = is_array($order_result['items'] ?? null) ? $order_result['items'] : [];
?>
<div class="eco-portal">
    <header class="eco-portal__header">
        <div>
            <p class="eco-portal__eyebrow">Order status</p>
            <h2 class="eco-portal__title">Look up a portal order</h2>
            <p class="eco-portal__meta">Status and tracking load live from the merchant portal. This website does not keep its own order book.</p>
        </div>
        <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($shop_url); ?>">Back to shop</a>
    </header>

    <?php if ($error !== '') : ?>
        <div class="eco-portal-notice eco-portal-notice--error"><?php echo esc_html($error); ?></div>
    <?php endif; ?>

    <?php if (is_array($order_result)) : ?>
        <div class="eco-portal-notice eco-portal-notice--success">
            <p><strong>Order <?php echo esc_html((string) ($order_result['order_number'] ?? '')); ?></strong></p>
            <ul>
                <li>Payment status: <?php echo esc_html((string) ($order_result['payment_status'] ?? '')); ?></li>
                <li>Order status: <?php echo esc_html((string) ($order_result['status'] ?? '')); ?></li>
                <?php if (! empty($order_result['fulfillment_status'])) : ?>
                    <li>Fulfillment: <?php echo esc_html((string) $order_result['fulfillment_status']); ?></li>
                <?php endif; ?>
                <?php foreach ($items as $item) : ?>
                    <?php if (! is_array($item)) : ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <li>
                        <?php echo esc_html((string) ($item['product_name'] ?? 'Item')); ?>
                        <?php if (! empty($item['variant_label'])) : ?>
                            (<?php echo esc_html((string) $item['variant_label']); ?>)
                        <?php endif; ?>
                        × <?php echo esc_html((string) ($item['quantity'] ?? 1)); ?>
                        <?php if (! empty($item['total'])) : ?>
                            — <?php echo esc_html((string) $item['total']); ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (! empty($order_result['subtotal'])) : ?>
                    <li>Subtotal: <?php echo esc_html((string) $order_result['subtotal']); ?></li>
                <?php endif; ?>
                <?php if (! empty($order_result['shipping'])) : ?>
                    <li>Shipping: <?php echo esc_html((string) $order_result['shipping']); ?></li>
                <?php endif; ?>
                <?php if (! empty($order_result['tax'])) : ?>
                    <li>Tax: <?php echo esc_html((string) $order_result['tax']); ?></li>
                <?php endif; ?>
                <li>Total: <?php echo esc_html((string) ($order_result['total'] ?? '')); ?></li>
                <?php foreach ($shipments as $shipment) : ?>
                    <?php if (! is_array($shipment) || empty($shipment['tracking_number'])) : ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <li>
                        Tracking:
                        <?php if (! empty($shipment['tracking_url'])) : ?>
                            <a href="<?php echo esc_url((string) $shipment['tracking_url']); ?>"><?php echo esc_html((string) $shipment['tracking_number']); ?></a>
                        <?php else : ?>
                            <?php echo esc_html((string) $shipment['tracking_number']); ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="eco-portal__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="eco_portal_lookup_order" />
        <?php wp_nonce_field('eco_portal_lookup_order'); ?>
        <h3>Confirmation code</h3>
        <label>Code from your receipt
            <input type="text" name="confirmation_token" value="<?php echo esc_attr((string) ($_GET['eco_confirm'] ?? '')); ?>" placeholder="ordconf_…" required />
        </label>
        <button type="submit" class="eco-portal__button">Check status</button>
    </form>

    <?php if ($recent !== []) : ?>
        <div class="eco-portal__summary" style="margin-top:1rem;">
            <h3>Recent orders on this browser</h3>
            <ul>
                <?php foreach ($recent as $token) : ?>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg('eco_confirm', $token, Eco_Portal_Storefront::page_url('portal-order'))); ?>">
                            <?php echo esc_html($token); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
