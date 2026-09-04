<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @var list<array<string, mixed>> $products */
/** @var string $title */
/** @var string $empty */

$products = is_array($products ?? null) ? $products : [];
$title = (string) ($title ?? '');
$empty = (string) ($empty ?? '');
?>
<div class="eco-section eco-section--topsellers">
    <?php if ($title !== '') : ?>
        <h2 class="eco-section__title"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($products === []) : ?>
        <p class="eco-section__empty"><?php echo esc_html($empty); ?></p>
    <?php else : ?>
        <div class="eco-section__topseller-grid">
            <?php foreach ($products as $product) : ?>
                <?php
                $image = (string) ($product['primary_image_url'] ?? '');
                $product_id = (int) ($product['id'] ?? 0);
                $name = (string) ($product['name'] ?? 'Product');
                ?>
                <a class="eco-topseller" href="<?php echo esc_url(Eco_Portal_Storefront::product_url($product_id)); ?>" <?php echo $image !== '' ? 'style="background-image:url('.esc_url($image).')"' : ''; ?>>
                    <h3><?php echo esc_html($name); ?></h3>
                    <p>Shop this flavor</p>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
