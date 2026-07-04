<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $store */
/** @var array<int, array<string, mixed>> $products */
/** @var string $cart_url */
/** @var string $currency */
?>
<div class="eco-portal">
    <header class="eco-portal__header">
        <div>
            <p class="eco-portal__eyebrow">Connected store</p>
            <h2 class="eco-portal__title"><?php echo esc_html((string) ($store['name'] ?? 'Portal catalog')); ?></h2>
            <p class="eco-portal__meta">Currency: <?php echo esc_html($currency); ?> · Products load live from your Eco Commerce portal.</p>
        </div>
        <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($cart_url); ?>">View cart</a>
    </header>

    <?php if ($products === []) : ?>
        <div class="eco-portal-notice eco-portal-notice--info">
            No active products with variants were returned. Add products in the merchant portal, then refresh this page.
        </div>
    <?php else : ?>
        <div class="eco-portal__grid">
            <?php foreach ($products as $product) : ?>
                <?php
                $variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
                $image = (string) ($product['primary_image_url'] ?? '');
                $product_id = (int) ($product['id'] ?? 0);
                $product_name = (string) ($product['name'] ?? 'Product');
                ?>
                <article class="eco-portal__card">
                    <div class="eco-portal__image-wrap">
                        <?php if ($image !== '') : ?>
                            <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($product_name); ?>" loading="lazy" />
                        <?php else : ?>
                            <div class="eco-portal__image-fallback">No image</div>
                        <?php endif; ?>
                    </div>
                    <div class="eco-portal__card-body">
                        <h3><?php echo esc_html($product_name); ?></h3>
                        <?php if (! empty($product['product_type_label'])) : ?>
                            <p class="eco-portal__badge"><?php echo esc_html((string) $product['product_type_label']); ?></p>
                        <?php endif; ?>
                        <?php if (! empty($product['description'])) : ?>
                            <p class="eco-portal__description"><?php echo esc_html(wp_trim_words((string) $product['description'], 24)); ?></p>
                        <?php endif; ?>

                        <?php if ($variants === []) : ?>
                            <p class="eco-portal-notice eco-portal-notice--info">No variants available.</p>
                        <?php else : ?>
                            <?php foreach ($variants as $variant) : ?>
                                <?php
                                $variant_id = (int) ($variant['id'] ?? 0);
                                $price = (string) ($variant['price'] ?? '0');
                                $stock = (int) ($variant['stock'] ?? 0);
                                $label = Eco_Portal_Storefront::variant_label(is_array($variant) ? $variant : []);
                                $sku = (string) ($variant['sku'] ?? '');
                                ?>
                                <form class="eco-portal__variant" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="eco_portal_add_to_cart" />
                                    <?php wp_nonce_field('eco_portal_add_to_cart'); ?>
                                    <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product_id); ?>" />
                                    <input type="hidden" name="variant_id" value="<?php echo esc_attr((string) $variant_id); ?>" />
                                    <input type="hidden" name="product_name" value="<?php echo esc_attr($product_name); ?>" />
                                    <input type="hidden" name="variant_label" value="<?php echo esc_attr($label); ?>" />
                                    <input type="hidden" name="unit_price" value="<?php echo esc_attr($price); ?>" />
                                    <input type="hidden" name="sku" value="<?php echo esc_attr($sku); ?>" />

                                    <div class="eco-portal__variant-row">
                                        <div>
                                            <strong><?php echo esc_html($label); ?></strong>
                                            <div class="eco-portal__meta">
                                                <?php echo esc_html($currency.' '.$price); ?>
                                                · Stock <?php echo esc_html((string) $stock); ?>
                                                <?php if ($sku !== '') : ?>
                                                    · SKU <?php echo esc_html($sku); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="eco-portal__variant-actions">
                                            <label>
                                                <span class="screen-reader-text">Quantity</span>
                                                <input type="number" name="quantity" min="1" max="999" value="1" <?php disabled($stock < 1); ?> />
                                            </label>
                                            <button type="submit" class="eco-portal__button" <?php disabled($stock < 1); ?>>
                                                <?php echo $stock < 1 ? 'Out of stock' : 'Add to cart'; ?>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
