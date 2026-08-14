<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @var array<string, array<string, mixed>> $cart */
/** @var string $shop_url */
/** @var array<string, mixed>|null $order_result */
/** @var string $error */
/** @var string $currency */
/** @var string $checkout_mode */
/** @var bool $platform_ready */
/** @var array<string, mixed> $checkout_state */

$subtotal = Eco_Portal_Storefront::cart_subtotal($cart);
$currency = $currency ?? Eco_Portal_Storefront::store_currency();
$checkout_mode = $checkout_mode ?? Eco_Portal_Storefront::checkout_mode();
$platform_ready = $platform_ready ?? Eco_Portal_Storefront::platform_ready();
$checkout_state = is_array($checkout_state ?? null) ? $checkout_state : [];
$is_platform = $checkout_mode === 'platform_checkout';
$step = (string) ($checkout_state['step'] ?? 'address');
$delivery_options = is_array($checkout_state['delivery_options'] ?? null) ? $checkout_state['delivery_options'] : [];
$checkout = is_array($checkout_state['checkout'] ?? null) ? $checkout_state['checkout'] : [];
$payment = is_array($checkout_state['payment'] ?? null) ? $checkout_state['payment'] : [];
$warning = (string) ($checkout_state['warning'] ?? '');
$address = is_array($checkout_state['address'] ?? null) ? $checkout_state['address'] : [];
?>
<div class="eco-portal">
    <header class="eco-portal__header">
        <div>
            <p class="eco-portal__eyebrow">Checkout</p>
            <h2 class="eco-portal__title"><?php echo $is_platform ? 'Pay through the merchant portal' : 'Place test order'; ?></h2>
            <p class="eco-portal__meta">
                <?php if ($is_platform) : ?>
                    Delivery rates and payment come from your merchant portal. When payment succeeds, the order and stock update there.
                <?php else : ?>
                    Payment is collected on this WordPress site, then the order is sent to your merchant portal.
                <?php endif; ?>
            </p>
        </div>
        <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($shop_url); ?>">Back to shop</a>
    </header>

    <?php if ($error !== '') : ?>
        <div class="eco-portal-notice eco-portal-notice--error"><?php echo esc_html($error); ?></div>
    <?php endif; ?>

    <?php if ($warning !== '') : ?>
        <div class="eco-portal-notice eco-portal-notice--info"><?php echo esc_html($warning); ?></div>
    <?php endif; ?>

    <?php if (is_array($order_result)) : ?>
        <div class="eco-portal-notice eco-portal-notice--success">
            <p><strong>Order received in the merchant portal.</strong></p>
            <ul>
                <li>Portal order: <?php echo esc_html((string) ($order_result['portal_order_number'] ?? '')); ?></li>
                <?php if (! empty($order_result['external_order_number'])) : ?>
                    <li>Website order number: <?php echo esc_html((string) $order_result['external_order_number']); ?></li>
                <?php endif; ?>
                <li>Payment status: <?php echo esc_html((string) ($order_result['payment_status'] ?? '')); ?></li>
                <li>Total: <?php echo esc_html((string) ($order_result['total'] ?? '')); ?></li>
            </ul>
            <p>Open Orders in the merchant portal. Stock is reduced there when this store uses dashboard inventory.</p>
        </div>
    <?php elseif ($cart === []) : ?>
        <div class="eco-portal-notice eco-portal-notice--info">Your cart is empty. Add products from the shop first.</div>
    <?php elseif ($is_platform) : ?>
        <?php if ($step === 'pay' && ($payment['publishable_key'] ?? '') !== '' && ($payment['client_secret'] ?? '') !== '') : ?>
            <div class="eco-portal__checkout-layout">
                <div class="eco-portal__form">
                    <h3>Pay with card</h3>
                    <p class="eco-portal__meta">Card details stay with Stripe. This site never sends raw card numbers to the portal.</p>
                    <p><strong>Total:</strong> <?php echo esc_html(Eco_Portal_Storefront::format_money((string) ($checkout['grand_total'] ?? '0'), $currency)); ?></p>
                    <div
                        id="eco-portal-stripe"
                        data-publishable-key="<?php echo esc_attr((string) $payment['publishable_key']); ?>"
                        data-client-secret="<?php echo esc_attr((string) $payment['client_secret']); ?>"
                        data-stripe-account="<?php echo esc_attr((string) ($payment['provider_account_id'] ?? '')); ?>"
                    >
                        <div id="eco-portal-card" class="eco-portal-card-element"></div>
                        <p id="eco-portal-card-error" class="eco-portal-notice eco-portal-notice--error" hidden></p>
                    </div>
                    <form id="eco-portal-confirm-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="eco_portal_confirm_checkout" />
                        <?php wp_nonce_field('eco_portal_confirm_checkout'); ?>
                    </form>
                    <button type="button" id="eco-portal-pay-button" class="eco-portal__button">
                        Pay <?php echo esc_html(Eco_Portal_Storefront::format_money((string) ($checkout['grand_total'] ?? '0'), $currency)); ?>
                    </button>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="eco_portal_reset_checkout" />
                        <?php wp_nonce_field('eco_portal_reset_checkout'); ?>
                        <button type="submit" class="eco-portal__button eco-portal__button--secondary">Start over</button>
                    </form>
                </div>
                <aside class="eco-portal__summary">
                    <h3>Order summary</h3>
                    <p>Shipping: <?php echo esc_html(Eco_Portal_Storefront::format_money((string) ($checkout['shipping_total'] ?? '0'), $currency)); ?></p>
                    <p>Tax: <?php echo esc_html(Eco_Portal_Storefront::format_money((string) ($checkout['tax_total'] ?? '0'), $currency)); ?></p>
                    <p><strong>Total:</strong> <?php echo esc_html(Eco_Portal_Storefront::format_money((string) ($checkout['grand_total'] ?? '0'), $currency)); ?></p>
                </aside>
            </div>
        <?php elseif ($step === 'rates') : ?>
            <div class="eco-portal__checkout-layout">
                <form class="eco-portal__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="eco_portal_select_shipping" />
                    <?php wp_nonce_field('eco_portal_select_shipping'); ?>
                    <h3>Delivery from the merchant portal</h3>
                    <?php if ($delivery_options === []) : ?>
                        <p>No rates were returned. Add a checkout-enabled delivery method in the merchant portal, then try again.</p>
                    <?php else : ?>
                        <div class="eco-portal-rates">
                            <?php foreach ($delivery_options as $index => $option) : ?>
                                <?php
                                $option_id = (string) ($option['shipping_method_id'] ?? $option['id'] ?? '');
                                $pickup_locations = is_array($option['pickup_locations'] ?? null) ? $option['pickup_locations'] : [];
                                ?>
                                <label class="eco-portal-rate">
                                    <input type="radio" name="shipping_method_id" value="<?php echo esc_attr($option_id); ?>" <?php checked($index === 0); ?> required />
                                    <span>
                                        <strong><?php echo esc_html((string) ($option['name'] ?? 'Delivery')); ?></strong>
                                        <em><?php echo esc_html((string) ($option['amount_formatted'] ?? ($option['amount'] ?? ''))); ?></em>
                                        <?php if (! empty($option['delivery_speed_label'])) : ?>
                                            <span class="eco-portal__meta"><?php echo esc_html((string) $option['delivery_speed_label']); ?></span>
                                        <?php endif; ?>
                                        <?php if (! empty($option['carrier_name'])) : ?>
                                            <span class="eco-portal__meta"><?php echo esc_html((string) $option['carrier_name']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                                <?php if (! empty($option['pickup_required']) && $pickup_locations !== []) : ?>
                                    <label>Pickup location
                                        <select name="pickup_location_id">
                                            <?php foreach ($pickup_locations as $location) : ?>
                                                <option value="<?php echo esc_attr((string) ($location['id'] ?? '')); ?>">
                                                    <?php echo esc_html((string) ($location['name'] ?? 'Location')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="eco-portal__button">Continue to payment</button>
                    <?php endif; ?>
                </form>
                <aside class="eco-portal__summary">
                    <h3>Order summary</h3>
                    <p><strong>Subtotal:</strong> <?php echo esc_html(Eco_Portal_Storefront::format_money((string) ($checkout['subtotal'] ?? $subtotal), $currency)); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="eco_portal_reset_checkout" />
                        <?php wp_nonce_field('eco_portal_reset_checkout'); ?>
                        <button type="submit" class="eco-portal__button eco-portal__button--secondary">Change address</button>
                    </form>
                </aside>
            </div>
        <?php else : ?>
            <?php if (! $platform_ready) : ?>
                <div class="eco-portal-notice eco-portal-notice--info">
                    Platform checkout is on, but Stripe is not ready in the merchant portal yet. Connect Stripe under Payments, then return here.
                </div>
            <?php endif; ?>
            <div class="eco-portal__checkout-layout">
                <form class="eco-portal__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="eco_portal_start_checkout" />
                    <?php wp_nonce_field('eco_portal_start_checkout'); ?>
                    <h3>Customer</h3>
                    <label>Full name
                        <input type="text" name="customer_name" value="<?php echo esc_attr((string) ($address['customer_name'] ?? 'WP Test Buyer')); ?>" required />
                    </label>
                    <label>Email
                        <input type="email" name="customer_email" value="<?php echo esc_attr((string) ($address['customer_email'] ?? 'wp.buyer@example.test')); ?>" required />
                    </label>
                    <label>Phone
                        <input type="text" name="customer_phone" value="<?php echo esc_attr((string) ($address['customer_phone'] ?? '+1 555-0100')); ?>" />
                    </label>
                    <h3>Shipping address</h3>
                    <label>Address line 1
                        <input type="text" name="address_line1" value="<?php echo esc_attr((string) ($address['address_line1'] ?? '100 WordPress Avenue')); ?>" required />
                    </label>
                    <label>City
                        <input type="text" name="city" value="<?php echo esc_attr((string) ($address['city'] ?? 'Austin')); ?>" required />
                    </label>
                    <label>State / region
                        <input type="text" name="state" value="<?php echo esc_attr((string) ($address['state'] ?? 'TX')); ?>" />
                    </label>
                    <label>Postal code
                        <input type="text" name="postal_code" value="<?php echo esc_attr((string) ($address['postal_code'] ?? '73301')); ?>" required />
                    </label>
                    <label>Country
                        <input type="text" name="country" value="<?php echo esc_attr((string) ($address['country'] ?? 'United States')); ?>" required />
                    </label>
                    <input type="hidden" name="country_code" value="<?php echo esc_attr((string) ($address['country_code'] ?? 'US')); ?>" />
                    <button type="submit" class="eco-portal__button">Get delivery rates</button>
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
                    <p><strong>Subtotal:</strong> <?php echo esc_html(Eco_Portal_Storefront::format_money($subtotal, $currency)); ?></p>
                </aside>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <div class="eco-portal__checkout-layout">
            <form class="eco-portal__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="eco_portal_place_order" />
                <?php wp_nonce_field('eco_portal_place_order'); ?>
                <input type="hidden" name="currency_code" value="<?php echo esc_attr($currency); ?>" />

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

                <h3>Website payment &amp; totals</h3>
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
                <p><strong>Subtotal:</strong> <?php echo esc_html(Eco_Portal_Storefront::format_money($subtotal, $currency)); ?></p>
            </aside>
        </div>
    <?php endif; ?>
</div>
