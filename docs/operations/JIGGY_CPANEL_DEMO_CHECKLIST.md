# Jiggy `/jiggy` cPanel demo — one-time checklist

Use this once to put the manager WordPress demo live under the existing ecommerce cPanel host. After this, every successful portal deploy refreshes **only** the Eco Portal Connector plugin and Jiggy brand mu-plugins (see `docs/operations/CPANEL_DEPLOYMENT.md`).

**Target URLs**

| Surface | URL |
|---------|-----|
| Portal admin | `https://ecom.resolutedigitalspk.com` |
| WordPress home | `https://ecom.resolutedigitalspk.com/jiggy/` |
| Portal shop | `https://ecom.resolutedigitalspk.com/jiggy/portal-shop/` |
| Product example | `https://ecom.resolutedigitalspk.com/jiggy/portal-shop/?eco_product=8` |
| Cart | `https://ecom.resolutedigitalspk.com/jiggy/portal-cart/` |
| Checkout | `https://ecom.resolutedigitalspk.com/jiggy/portal-checkout/` |

**Server path:** `/home/resolutedigita2/public_html/ecom.resolutedigitalspk.com/jiggy`

**Local source (developer machine):** `C:\xampp\htdocs\jiggy\backupsite` (or Softaculous WP + All-in-One `.wpress` from the same site).

---

## A. One-time WordPress import

### 1. Create MySQL database (cPanel)

- [ ] cPanel → **MySQL Databases** → create database (e.g. `…_jiggy`)
- [ ] Create a DB user with a strong password; grant **All Privileges** on that database
- [ ] Note: host (usually `localhost`), DB name, user, password

### 2. Create the `/jiggy` directory

- [ ] SSH or File Manager: ensure docroot exists  
  `…/public_html/ecom.resolutedigitalspk.com/`
- [ ] Create empty folder `jiggy` there (do **not** put WP inside Laravel `public/` if this host uses the flat docroot layout with `index.php` at the app root)

### 3. Upload / restore WordPress

Pick one path:

**Option A — zip of local `backupsite`**

- [ ] Zip `wp-admin`, `wp-content`, `wp-includes`, and root WP PHP files from local `backupsite` (exclude huge caches if needed)
- [ ] Upload to `…/jiggy/` and extract so `wp-config.php` / `wp-content` sit directly under `jiggy/`
- [ ] Import the matching MySQL dump into the new database (phpMyAdmin or `mysql` CLI)

**Option B — Softaculous + All-in-One WP Migration**

- [ ] Softaculous install WordPress into subdirectory `jiggy`
- [ ] Install **All-in-One WP Migration** (and Unlimited Extension if the `.wpress` is large)
- [ ] Import the site `.wpress` backup
- [ ] Confirm site loads at `/jiggy/` (URLs may still be wrong until search-replace)

### 4. `wp-config.php`

- [ ] Set `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`
- [ ] Confirm `$table_prefix` matches the imported dump
- [ ] Prefer HTTPS: if the host forces SSL, ensure `WP_HOME` / `WP_SITEURL` (or DB options) use `https://ecom.resolutedigitalspk.com/jiggy`

### 5. Search-replace URLs

Replace old hosts (e.g. `http://localhost/jiggy/backupsite`, `https://jiggyjerky.com`) with:

`https://ecom.resolutedigitalspk.com/jiggy`

- [ ] Use WP-CLI `search-replace` if available, or a trusted serialized-safe Search Replace tool / Better Search Replace
- [ ] Do **not** use naive SQL replace on serialized Elementor data without a serialized-safe tool

### 6. Permalinks and Elementor

- [ ] WP Admin → **Settings → Permalinks** → Save (flush rewrite rules)
- [ ] If styles look broken: Elementor → **Tools → Regenerate CSS & Data** (and clear any page cache)
- [ ] Confirm homepage is still the Elementor home (design), not a blank theme page

### 7. Plugins / commerce cleanup on live

- [ ] Keep **Eco Portal Connector** active (pipeline will overwrite its files on next deploy)
- [ ] Keep brand mu-plugins under `wp-content/mu-plugins/` (`eco-portal-only.php`, `jiggy-eco-brand.css`) — pipeline refreshes these from `deploy/wordpress-jiggy/mu-plugins/`
- [ ] Leave WooCommerce / Square / Payments disabled or removed if they were disabled locally — cart must go through Eco Portal
- [ ] After first successful portal deploy post-install, confirm plugin files updated under `jiggy/wp-content/plugins/eco-portal-connector/`

---

## B. Connect Eco Portal (demo wiring)

### 1. On the Laravel portal

- [ ] Sign in as store owner/manager for the demo catalog store
- [ ] Set store **Website URL** to `https://ecom.resolutedigitalspk.com/jiggy`
- [ ] **Website → Connect your website** (or equivalent): create/rotate a **connection key**; copy it once

### 2. On WordPress

- [ ] **Settings → Eco Portal**
- [ ] **Portal website address** = `https://ecom.resolutedigitalspk.com` (portal origin, **not** `/jiggy`)
- [ ] Paste the connection key → **Save**
- [ ] **Test connection** — must succeed

### 3. Smoke checks

- [ ] Home: `…/jiggy/` — Elementor design loads; Add to Cart still posts to portal cart (bridge)
- [ ] Product: `…/jiggy/portal-shop/?eco_product=8` (Teriyaki / demo product)
- [ ] Catalog: products from the connected store appear on shop / shortcodes
- [ ] Cart: `…/jiggy/portal-cart/`
- [ ] Checkout: `…/jiggy/portal-checkout/`
- [ ] Edit a product title/price in the portal → refresh WP shop → change visible

### 4. Truthful checkout note for the manager demo

- [ ] Full **Stripe** checkout only works if Payments/Stripe is connected on the **live** portal
- [ ] If Stripe is not ready: show catalog + cart confidently; say checkout is gated until Stripe is connected (matches current connector behavior — do not claim live card capture)

---

## C. Manager demo click path

1. Open `https://ecom.resolutedigitalspk.com/jiggy/` (brand site)
2. Open a product (home Add to Cart or `portal-shop/?eco_product=8`)
3. Cart → Checkout pages under `/jiggy/`
4. Portal admin: `https://ecom.resolutedigitalspk.com` — catalog edits appear on WP after refresh

---

## D. After go-live (ongoing)

- [ ] Portal deploys via existing git → cPanel pipeline; WP core/DB stay on the server
- [ ] Connector + brand mu-plugins refresh automatically when `jiggy/wp-content/plugins` exists
- [ ] Do not re-upload the full WP tree on every code push

Related: `deploy/wordpress-jiggy/README.md`, `docs/operations/CPANEL_DEPLOYMENT.md`
