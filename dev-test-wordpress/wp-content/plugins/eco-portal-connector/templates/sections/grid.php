<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @var list<array<string, mixed>> $products */
/** @var string $title */
/** @var string $empty */
/** @var string $currency */
/** @var string $cart_url */

$products = is_array($products ?? null) ? $products : [];
$title = (string) ($title ?? '');
$empty = (string) ($empty ?? '');
$currency = (string) ($currency ?? 'USD');
?>
<div class="eco-section eco-section--grid">
    <?php if ($title !== '') : ?>
        <h2 class="eco-section__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($products === []) : ?>
        <p class="eco-section__empty"><?php echo esc_html($empty); ?></p>
    <?php else : ?>
        <div class="eco-section__shop-grid">
            <?php foreach ($products as $product) : ?>
                <?php
                $variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
                $variant = $variants[0] ?? null;
                $variant_id = (int) ($variant['id'] ?? 0);
                $price = (string) ($variant['price'] ?? $product['base_price'] ?? '0');
                $image = (string) ($product['primary_image_url'] ?? '');
                $product_id = (int) ($product['id'] ?? 0);
                $name = (string) ($product['name'] ?? 'Product');
                ?>
                <article class="eco-section-shop-card">
                    <a href="<?php echo esc_url(Eco_Portal_Storefront::product_url($product_id)); ?>">
                        <?php if ($image !== '') : ?>
                            <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy" />
                        <?php endif; ?>
                        <h3><?php echo esc_html($name); ?></h3>
                    </a>
                    <div class="eco-section-card__price"><?php echo esc_html($currency.' '.$price); ?></div>
                    <?php if ($variant_id > 0) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="eco_portal_add_to_cart" />
                            <?php wp_nonce_field('eco_portal_add_to_cart'); ?>
                            <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product_id); ?>" />
                                <input type="hidden" name="variant_id" value="<?php echo esc_attr((string) $variant_id); ?>" />
                            <input type="hidden" name="quantity" value="1" />
                            <input type="hidden" name="redirect_to" value="<?php echo esc_url($cart_url); ?>" />
                            <button type="submit" class="eco-section__btn">Add to cart</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
