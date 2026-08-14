<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $store */
/** @var array<int, array<string, mixed>> $products */
/** @var array<int, array<string, mixed>> $categories */
/** @var array<string, mixed> $catalog_meta */
/** @var string $active_category */
/** @var string $cart_url */
/** @var string $currency */

$categories = is_array($categories ?? null) ? $categories : [];
$catalog_meta = is_array($catalog_meta ?? null) ? $catalog_meta : [];
$active_category = (string) ($active_category ?? '');
$current_page = max(1, (int) ($catalog_meta['current_page'] ?? 1));
$last_page = max(1, (int) ($catalog_meta['last_page'] ?? 1));
$shop_url = Eco_Portal_Storefront::page_url('portal-shop');
?>
<div class="eco-portal">
    <header class="eco-portal__header">
        <div>
            <p class="eco-portal__eyebrow">Connected store</p>
            <h2 class="eco-portal__title"><?php echo esc_html((string) ($store['name'] ?? 'Portal catalog')); ?></h2>
            <p class="eco-portal__meta">
                Currency: <?php echo esc_html($currency); ?>
                · Products load live from your Eco Commerce portal.
                <?php if (! empty($catalog_meta['total'])) : ?>
                    · <?php echo esc_html((string) $catalog_meta['total']); ?> published
                <?php endif; ?>
            </p>
        </div>
        <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url($cart_url); ?>">View cart</a>
    </header>

    <?php if ($categories !== []) : ?>
        <nav class="eco-portal__filters" aria-label="Categories">
            <a class="eco-portal__filter<?php echo $active_category === '' ? ' is-active' : ''; ?>" href="<?php echo esc_url($shop_url); ?>">All</a>
            <?php foreach ($categories as $category) : ?>
                <?php
                $slug = (string) ($category['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                $filter_url = add_query_arg(['eco_category' => $slug, 'eco_page' => false], $shop_url);
                ?>
                <a class="eco-portal__filter<?php echo $active_category === $slug ? ' is-active' : ''; ?>" href="<?php echo esc_url($filter_url); ?>">
                    <?php echo esc_html((string) ($category['name'] ?? $slug)); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($products === []) : ?>
        <div class="eco-portal-notice eco-portal-notice--info">
            No published products were returned. Add products in the merchant portal, then refresh this page.
        </div>
    <?php else : ?>
        <div class="eco-portal__grid">
            <?php foreach ($products as $product) : ?>
                <?php
                $variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
                $image = (string) ($product['primary_image_url'] ?? '');
                $product_id = (int) ($product['id'] ?? 0);
                $product_name = (string) ($product['name'] ?? 'Product');
                $brand = is_array($product['brand'] ?? null) ? $product['brand'] : [];
                $product_categories = is_array($product['categories'] ?? null) ? $product['categories'] : [];
                $tags = is_array($product['tags'] ?? null) ? $product['tags'] : [];
                ?>
                <article class="eco-portal__card">
                    <div class="eco-portal__image-wrap">
                        <?php if ($image !== '') : ?>
                            <a href="<?php echo esc_url(Eco_Portal_Storefront::product_url($product_id)); ?>">
                                <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($product_name); ?>" loading="lazy" />
                            </a>
                        <?php else : ?>
                            <div class="eco-portal__image-fallback">No image</div>
                        <?php endif; ?>
                    </div>
                    <div class="eco-portal__card-body">
                        <h3>
                            <a href="<?php echo esc_url(Eco_Portal_Storefront::product_url($product_id)); ?>">
                                <?php echo esc_html($product_name); ?>
                            </a>
                        </h3>
                        <?php if (! empty($product['product_type_label'])) : ?>
                            <p class="eco-portal__badge"><?php echo esc_html((string) $product['product_type_label']); ?></p>
                        <?php endif; ?>
                        <?php if ($brand !== [] || $product_categories !== []) : ?>
                            <p class="eco-portal__meta">
                                <?php if (! empty($brand['name'])) : ?>
                                    <?php echo esc_html((string) $brand['name']); ?>
                                <?php endif; ?>
                                <?php if ($product_categories !== []) : ?>
                                    <?php
                                    $category_names = array_values(array_filter(array_map(
                                        static fn ($row): string => is_array($row) ? (string) ($row['name'] ?? '') : '',
                                        $product_categories
                                    )));
                                    ?>
                                    <?php if ($category_names !== []) : ?>
                                        <?php echo $brand !== [] ? ' · ' : ''; ?>
                                        <?php echo esc_html(implode(', ', $category_names)); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($tags !== []) : ?>
                            <p class="eco-portal__meta">
                                <?php
                                $tag_names = array_values(array_filter(array_map(
                                    static fn ($row): string => is_array($row) ? (string) ($row['name'] ?? '') : '',
                                    $tags
                                )));
                                echo esc_html(implode(', ', $tag_names));
                                ?>
                            </p>
                        <?php endif; ?>
                        <?php if (! empty($product['description'])) : ?>
                            <p class="eco-portal__description"><?php echo esc_html(wp_trim_words((string) $product['description'], 24)); ?></p>
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
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($last_page > 1) : ?>
            <nav class="eco-portal__pagination" aria-label="Catalog pages">
                <?php if ($current_page > 1) : ?>
                    <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url(add_query_arg(['eco_page' => $current_page - 1, 'eco_category' => $active_category !== '' ? $active_category : false], $shop_url)); ?>">Previous</a>
                <?php endif; ?>
                <span class="eco-portal__meta">Page <?php echo esc_html((string) $current_page); ?> of <?php echo esc_html((string) $last_page); ?></span>
                <?php if ($current_page < $last_page) : ?>
                    <a class="eco-portal__button eco-portal__button--secondary" href="<?php echo esc_url(add_query_arg(['eco_page' => $current_page + 1, 'eco_category' => $active_category !== '' ? $active_category : false], $shop_url)); ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
