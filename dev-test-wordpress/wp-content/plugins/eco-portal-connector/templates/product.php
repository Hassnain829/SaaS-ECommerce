<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $product */
/** @var string $cart_url */
/** @var string $shop_url */
/** @var string $currency */

$product = is_array($product ?? null) ? $product : [];
$variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
$images = is_array($product['images'] ?? null) ? $product['images'] : [];
$categories = is_array($product['categories'] ?? null) ? $product['categories'] : [];
$tags = is_array($product['tags'] ?? null) ? $product['tags'] : [];
$brand = is_array($product['brand'] ?? null) ? $product['brand'] : [];
$attributes = is_array($product['attributes'] ?? null) ? $product['attributes'] : [];
$details = is_array($product['additional_details'] ?? null) ? $product['additional_details'] : [];
$product_id = (int) ($product['id'] ?? 0);
$product_name = (string) ($product['name'] ?? 'Product');
$image = (string) ($product['primary_image_url'] ?? '');
?>
<div class="eco-portal">
    <header class="eco-portal__header">
        <div>
            <p class="eco-portal__eyebrow">Product</p>
            <h2 class="eco-portal__title"><?php echo esc_html($product_name); ?></h2>
            <p class="eco-portal__meta">
                Prices, stock, and options come from the merchant portal.
                <?php if (! empty($product['sku'])) : ?>
                    · SKU <?php echo esc_html((string) $product['sku']); ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="eco-portal__action-buttons">
            <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($shop_url); ?>">Back to shop</a>
            <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($cart_url); ?>">View cart</a>
        </div>
    </header>

    <div class="eco-portal__checkout-layout">
        <div class="eco-portal__card">
            <div class="eco-portal__image-wrap">
                <?php if ($image !== '') : ?>
                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($product_name); ?>" />
                <?php else : ?>
                    <div class="eco-portal__image-fallback">No image</div>
                <?php endif; ?>
            </div>
            <?php if (count($images) > 1) : ?>
                <div class="eco-portal__filters" style="padding: 0.75rem;">
                    <?php foreach ($images as $row) : ?>
                        <?php if (! is_array($row) || empty($row['url'])) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <img src="<?php echo esc_url((string) $row['url']); ?>" alt="" width="72" height="72" style="object-fit:cover;border-radius:8px;" />
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="eco-portal__card-body eco-portal__summary">
            <?php if (! empty($product['product_type_label'])) : ?>
                <p class="eco-portal__badge"><?php echo esc_html((string) $product['product_type_label']); ?></p>
            <?php endif; ?>
            <?php if (! empty($brand['name']) || $categories !== []) : ?>
                <p class="eco-portal__meta">
                    <?php echo esc_html((string) ($brand['name'] ?? '')); ?>
                    <?php
                    $category_names = array_values(array_filter(array_map(
                        static fn ($row): string => is_array($row) ? (string) ($row['name'] ?? '') : '',
                        $categories
                    )));
                    echo $category_names !== [] ? esc_html(($brand !== [] ? ' · ' : '').implode(', ', $category_names)) : '';
                    ?>
                </p>
            <?php endif; ?>
            <?php if ($tags !== []) : ?>
                <p class="eco-portal__meta"><?php echo esc_html(implode(', ', array_map(static fn ($row): string => is_array($row) ? (string) ($row['name'] ?? '') : '', $tags))); ?></p>
            <?php endif; ?>
            <?php if (! empty($product['description'])) : ?>
                <p class="eco-portal__description"><?php echo esc_html((string) $product['description']); ?></p>
            <?php endif; ?>
            <?php if ($attributes !== []) : ?>
                <ul class="eco-portal__meta">
                    <?php foreach ($attributes as $attribute) : ?>
                        <?php if (! is_array($attribute)) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <li>
                            <?php echo esc_html((string) ($attribute['name'] ?? 'Detail')); ?>:
                            <?php
                            $terms = is_array($attribute['terms'] ?? null) ? $attribute['terms'] : [];
                            echo esc_html(implode(', ', array_map(static fn ($term): string => is_array($term) ? (string) ($term['name'] ?? '') : '', $terms)));
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($details !== []) : ?>
                <ul class="eco-portal__meta">
                    <?php foreach ($details as $label => $value) : ?>
                        <li><?php echo esc_html((string) $label); ?>: <?php echo esc_html(is_scalar($value) ? (string) $value : wp_json_encode($value)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($variants === []) : ?>
                <p class="eco-portal-notice eco-portal-notice--info">This product has no purchasable options yet.</p>
            <?php else : ?>
                <?php foreach ($variants as $variant) : ?>
                    <?php
                    $variant_id = (int) ($variant['id'] ?? 0);
                    $price = (string) ($variant['price'] ?? '0');
                    $compare = (string) ($variant['compare_at_price'] ?? '');
                    $stock = (int) ($variant['stock'] ?? 0);
                    $label = Eco_Portal_Storefront::variant_label(is_array($variant) ? $variant : []);
                    $sku = (string) ($variant['sku'] ?? '');
                    ?>
                    <form class="eco-portal__variant" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="eco_portal_add_to_cart" />
                        <?php wp_nonce_field('eco_portal_add_to_cart'); ?>
                        <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product_id); ?>" />
                        <input type="hidden" name="variant_id" value="<?php echo esc_attr((string) $variant_id); ?>" />
                        <div class="eco-portal__variant-row">
                            <div>
                                <strong><?php echo esc_html($label); ?></strong>
                                <div class="eco-portal__meta">
                                    <?php echo esc_html($currency.' '.$price); ?>
                                    <?php if ($compare !== '' && $compare !== $price) : ?>
                                        <s><?php echo esc_html($currency.' '.$compare); ?></s>
                                    <?php endif; ?>
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
    </div>
</div>
