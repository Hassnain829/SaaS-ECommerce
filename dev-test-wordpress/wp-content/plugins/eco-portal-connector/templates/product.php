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
$brand = is_array($product['brand'] ?? null) ? $product['brand'] : [];
$attributes = is_array($product['attributes'] ?? null) ? $product['attributes'] : [];
$details = is_array($product['additional_details'] ?? null) ? $product['additional_details'] : [];
$product_id = (int) ($product['id'] ?? 0);
$product_name = (string) ($product['name'] ?? 'Product');
$image = (string) ($product['primary_image_url'] ?? '');
$description = wp_strip_all_tags((string) ($product['description'] ?? ''));
$first_variant = is_array($variants[0] ?? null) ? $variants[0] : null;
$display_price = (string) ($first_variant['price'] ?? $product['base_price'] ?? '0');

$detail_rows = [];
foreach ($details as $key => $value) {
    if (is_array($value)) {
        $label = (string) ($value['label'] ?? $value['key'] ?? $key);
        $content = (string) ($value['value'] ?? $value['content'] ?? '');
    } else {
        $label = is_string($key) && ! is_numeric($key) ? $key : 'Detail';
        $content = is_scalar($value) ? (string) $value : '';
    }
    $label = trim($label);
    $content = trim($content);
    if ($label === '' || $content === '') {
        continue;
    }
    $detail_rows[] = ['label' => $label, 'value' => $content];
}
?>
<div class="eco-portal eco-portal--product">
    <nav class="eco-portal__breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
        <span>/</span>
        <a href="<?php echo esc_url($shop_url); ?>">Shop</a>
        <span>/</span>
        <span><?php echo esc_html($product_name); ?></span>
    </nav>

    <div class="eco-portal__product-layout">
        <div class="eco-portal__product-gallery">
            <div class="eco-portal__image-wrap eco-portal__image-wrap--product">
                <?php if ($image !== '') : ?>
                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($product_name); ?>" />
                <?php else : ?>
                    <div class="eco-portal__image-fallback">No image</div>
                <?php endif; ?>
            </div>
            <?php if (count($images) > 1) : ?>
                <div class="eco-portal__thumbs">
                    <?php foreach ($images as $row) : ?>
                        <?php if (! is_array($row) || empty($row['url'])) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <img src="<?php echo esc_url((string) $row['url']); ?>" alt="" loading="lazy" />
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="eco-portal__product-buy">
            <p class="eco-portal__eyebrow">Get Jiggy With</p>
            <h1 class="eco-portal__product-title"><?php echo esc_html($product_name); ?></h1>

            <div class="eco-portal__price-row">
                <span class="eco-portal__price"><?php echo esc_html(Eco_Portal_Storefront::format_money($display_price, $currency)); ?></span>
                <?php if (! empty($product['sku'])) : ?>
                    <span class="eco-portal__sku">SKU <?php echo esc_html((string) $product['sku']); ?></span>
                <?php endif; ?>
            </div>

            <?php if ($description !== '') : ?>
                <p class="eco-portal__description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>

            <?php if ($detail_rows !== []) : ?>
                <ul class="eco-portal__stats">
                    <?php foreach ($detail_rows as $row) : ?>
                        <li>
                            <strong><?php echo esc_html($row['label']); ?></strong>
                            <span><?php echo esc_html($row['value']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($attributes !== []) : ?>
                <ul class="eco-portal__meta-list">
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

            <?php if ($variants === []) : ?>
                <p class="eco-portal-notice eco-portal-notice--info">This product is not available to buy yet.</p>
            <?php else : ?>
                <?php foreach ($variants as $variant) : ?>
                    <?php
                    $variant_id = (int) ($variant['id'] ?? 0);
                    $price = (string) ($variant['price'] ?? '0');
                    $compare = (string) ($variant['compare_at_price'] ?? '');
                    $stock = (int) ($variant['stock'] ?? 0);
                    $label = Eco_Portal_Storefront::variant_label(is_array($variant) ? $variant : []);
                    $show_label = count($variants) > 1 && strtolower($label) !== 'default';
                    ?>
                    <form class="eco-portal__buy-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="eco_portal_add_to_cart" />
                        <?php wp_nonce_field('eco_portal_add_to_cart'); ?>
                        <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product_id); ?>" />
                        <input type="hidden" name="variant_id" value="<?php echo esc_attr((string) $variant_id); ?>" />
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($cart_url); ?>" />

                        <?php if ($show_label) : ?>
                            <div class="eco-portal__option-label">
                                <strong><?php echo esc_html($label); ?></strong>
                                <span>
                                    <?php echo esc_html(Eco_Portal_Storefront::format_money($price, $currency)); ?>
                                    <?php if ($compare !== '' && $compare !== $price) : ?>
                                        <s><?php echo esc_html(Eco_Portal_Storefront::format_money($compare, $currency)); ?></s>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="eco-portal__buy-row">
                            <label class="eco-portal__qty">
                                <span>Qty</span>
                                <input type="number" name="quantity" min="1" max="999" value="1" <?php disabled($stock < 1); ?> />
                            </label>
                            <button type="submit" class="eco-portal__button eco-portal__button--cta" <?php disabled($stock < 1); ?>>
                                <?php echo $stock < 1 ? 'Out of stock' : 'Add to cart'; ?>
                            </button>
                        </div>
                        <?php if ($stock > 0 && $stock <= 10) : ?>
                            <p class="eco-portal__stock-note">Only <?php echo esc_html((string) $stock); ?> left</p>
                        <?php endif; ?>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="eco-portal__action-buttons">
                <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($shop_url); ?>">Back to shop</a>
                <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($cart_url); ?>">View cart</a>
            </div>
        </div>
    </div>

    <?php if ($description !== '' || $detail_rows !== []) : ?>
        <section class="eco-portal__product-story">
            <h2>Why Jiggy Jerky’s <?php echo esc_html($product_name); ?> Stands Out</h2>
            <?php if ($description !== '') : ?>
                <p><?php echo esc_html($description); ?></p>
            <?php endif; ?>
            <?php if ($detail_rows !== []) : ?>
                <div class="eco-portal__story-stats">
                    <?php foreach ($detail_rows as $row) : ?>
                        <div>
                            <strong><?php echo esc_html($row['value']); ?></strong>
                            <span><?php echo esc_html($row['label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
