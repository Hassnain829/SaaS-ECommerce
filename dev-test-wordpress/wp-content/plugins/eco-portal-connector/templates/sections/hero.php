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
$first = $products[0] ?? null;
?>
<div class="eco-section eco-section--hero" data-eco-hero>
    <?php if ($title !== '') : ?>
        <h2 class="eco-section__title eco-section__title--light"><?php echo esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if ($products === []) : ?>
        <p class="eco-section__empty eco-section__empty--light"><?php echo esc_html($empty); ?></p>
    <?php else : ?>
        <div class="eco-hero-slides">
            <?php foreach ($products as $index => $product) : ?>
                <?php
                $variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
                $variant = $variants[0] ?? null;
                $variant_id = (int) ($variant['id'] ?? 0);
                $image = (string) ($product['primary_image_url'] ?? '');
                $product_id = (int) ($product['id'] ?? 0);
                $name = (string) ($product['name'] ?? 'Product');
                $description = wp_strip_all_tags((string) ($product['description'] ?? ''));
                $flavor = preg_replace('/\s*flavor\s*/i', '', $name) ?: $name;
                ?>
                <article class="eco-hero-slide<?php echo $index === 0 ? ' is-active' : ''; ?>" data-eco-slide="<?php echo esc_attr((string) $index); ?>">
                    <div class="eco-hero-slide__copy">
                        <p class="eco-hero-kicker">GET JIGGY WITH</p>
                        <h3><?php echo esc_html(strtoupper((string) $flavor)); ?>!</h3>
                        <p><?php echo esc_html(wp_html_excerpt($description, 220, '…')); ?></p>
                        <?php if ($variant_id > 0) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="eco-hero-slide__actions">
                                <input type="hidden" name="action" value="eco_portal_add_to_cart" />
                                <?php wp_nonce_field('eco_portal_add_to_cart'); ?>
                                <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product_id); ?>" />
                                <input type="hidden" name="variant_id" value="<?php echo esc_attr((string) $variant_id); ?>" />
                                <input type="hidden" name="quantity" value="1" />
                                <input type="hidden" name="redirect_to" value="<?php echo esc_url($cart_url); ?>" />
                                <button type="submit" class="eco-section__btn eco-section__btn--light">Add to cart</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="eco-hero-slide__visual">
                        <?php if ($image !== '') : ?>
                            <a href="<?php echo esc_url(Eco_Portal_Storefront::product_url($product_id)); ?>">
                                <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($name); ?>" />
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="eco-hero-thumbs" role="tablist" aria-label="Product slides">
            <?php foreach ($products as $index => $product) : ?>
                <?php
                $image = (string) ($product['primary_image_url'] ?? '');
                $name = (string) ($product['name'] ?? 'Product');
                ?>
                <button type="button" class="eco-hero-thumb<?php echo $index === 0 ? ' is-active' : ''; ?>" data-eco-thumb="<?php echo esc_attr((string) $index); ?>" aria-label="<?php echo esc_attr($name); ?>">
                    <?php if ($image !== '') : ?>
                        <img src="<?php echo esc_url($image); ?>" alt="" />
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
(function () {
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.classList.contains('eco-section--hero')) {
    root = document.querySelector('.eco-section--hero[data-eco-hero]:not([data-bound])');
  }
  if (!root || root.getAttribute('data-bound')) return;
  root.setAttribute('data-bound', '1');
  var slides = root.querySelectorAll('[data-eco-slide]');
  var thumbs = root.querySelectorAll('[data-eco-thumb]');
  function show(i) {
    slides.forEach(function (el, idx) { el.classList.toggle('is-active', idx === i); });
    thumbs.forEach(function (el, idx) { el.classList.toggle('is-active', idx === i); });
  }
  thumbs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      show(Number(btn.getAttribute('data-eco-thumb') || 0));
    });
  });
})();
</script>
