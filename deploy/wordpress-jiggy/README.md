# Jiggy WordPress demo pack (`/jiggy`)

This folder is **not** a full WordPress install. It holds the small files the Laravel→cPanel pipeline can refresh on every deploy after you place WordPress once under the same document root.

## Live shape

| Surface | URL |
|---------|-----|
| Laravel portal | `https://ecom.resolutedigitalspk.com` |
| WordPress demo | `https://ecom.resolutedigitalspk.com/jiggy/` |

On the server, WordPress lives at:

`$CPANEL_DEPLOY_PATH/jiggy`

(same tree as the Laravel app root / subdomain docroot). Laravel’s docroot `.htaccess` skips existing directories, so `/jiggy` is not routed to `index.php`.

## What git deploys every time

After a successful portal deploy, `scripts/deploy/sync-wordpress-jiggy.sh` runs (from post-deploy / go-live) **only if** `jiggy/wp-content/plugins` already exists:

1. **Eco Portal Connector** → `jiggy/wp-content/plugins/eco-portal-connector/`
2. **Brand mu-plugins** from this pack → `jiggy/wp-content/mu-plugins/`
   - `eco-portal-only.php` — Elementor/Woo bridge → portal cart
   - `jiggy-eco-brand.css` — Jiggy-only storefront polish

Full WordPress core, uploads, Elementor data, and the MySQL database stay **out** of git (`dev-test-wordpress/` remains excluded from app rsync). GitHub Actions stages only the connector plugin under `deploy/wordpress-jiggy/eco-portal-connector/` at build time (gitignored); cPanel Git checkouts can use `dev-test-wordpress/wp-content/plugins/eco-portal-connector/` instead.

## One-time: put WordPress in `/jiggy`

Do this once on cPanel (not on every push). Prefer the ops checklist:

`docs/operations/JIGGY_CPANEL_DEMO_CHECKLIST.md`

Summary:

1. Create a MySQL database + user for WordPress.
2. Upload/import the local backup (`backupsite` zip or Softaculous WP + All-in-One `.wpress`) into `…/jiggy`.
3. Set `wp-config.php` DB credentials and table prefix.
4. Search-replace site URL to `https://ecom.resolutedigitalspk.com/jiggy`.
5. Flush permalinks; regenerate Elementor CSS if needed.
6. Confirm `eco-portal-connector` is active (pipeline will overwrite plugin files on next deploy).
7. Connect Eco Portal (Website URL + connection key) per the checklist.

## Do not commit

- Full WP trees
- `wp-config.php` with production secrets
- Staged `deploy/wordpress-jiggy/eco-portal-connector/` (build artifact only)
