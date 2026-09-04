# Eco Portal UI — CMS developer guide

## Why pasting Elementor CSS into Eco Portal did nothing

Two different CSS systems:

| Markup | Who owns the CSS |
|--------|------------------|
| Elementor widgets (headings, columns, backgrounds, native buttons) | **Elementor** Site Settings / widget Custom CSS |
| Portal blocks (`.eco-portal…`, `.eco-section…`) | **Eco Portal** Settings → Custom CSS or theme `eco-portal/storefront.css` |

If you copy a rule for `.elementor-heading-title` into Eco Portal settings, it will not restyle the Elementor homepage. Put that rule back in Elementor.

## Fixed in v1.9.1

- Portal CSS now loads on **Elementor pages** that use Portal Products (not only classic shortcode pages).
- Custom CSS is attached as real stylesheet inline CSS (higher chance to apply).
- Settings screen explains where each kind of CSS belongs.

## Simple workflow for CMS teams

### Homepage look (Elementor)

1. Edit with Elementor  
2. Change layout/colors there  
3. Site Settings → Custom CSS for Elementor classes  
4. Tools → Regenerate CSS & Data after big changes  
5. Hard refresh (Ctrl+F5)

### Shop / Cart / Checkout / Product pages

1. Open those pages (URLs like `/portal-shop/`, `/portal-cart/`, `/portal-checkout/`)  
2. Style with **Settings → Eco Portal → Shop appearance**  
3. Custom CSS examples:

```css
.eco-portal__button {
  border-radius: 999px;
  font-size: 1.25rem;
}
.eco-portal__title {
  letter-spacing: 0.04em;
}
```

### Portal Products widget on the homepage

Target section classes:

```css
.eco-section-card {
  border-radius: 20px;
}
.eco-section__btn {
  border-radius: 999px;
}
```

Paste that in Eco Portal Custom CSS **or** Elementor Custom CSS.

## Full HTML control (developers)

Copy templates into the active theme:

```text
wp-content/themes/your-theme/eco-portal/checkout.php
wp-content/themes/your-theme/eco-portal/product.php
wp-content/themes/your-theme/eco-portal/cart.php
wp-content/themes/your-theme/eco-portal/storefront.css
```

Theme files win over the plugin. Safe for agencies; does not change other sites.

## Checklist when “CSS didn’t apply”

1. Are you targeting the right class family? (Elementor vs `.eco-portal` / `.eco-section`)  
2. Did you Save settings?  
3. Hard refresh Ctrl+F5  
4. Elementor → Tools → Regenerate CSS  
5. Confirm you are looking at Shop/Cart/Checkout for portal CSS, not only the Elementor homepage chrome
