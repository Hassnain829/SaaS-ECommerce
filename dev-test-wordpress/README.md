# Sample WordPress site — Eco Portal connector

Deployable WordPress test site that connects to your Eco Commerce merchant portal.

It is a **sample integration channel**, not the final production WordPress/WooCommerce plugin (that comes later in Phase 9G). Use it to prove:

1. Catalog loads from the portal
2. Orders placed on WordPress appear in the merchant dashboard
3. Inventory is deducted on the portal

The API token stays **server-side in WordPress** (never in browser JavaScript).

---

## What you get

| Piece | Purpose |
|-------|---------|
| `docker-compose.yml` | WordPress + MySQL on your server |
| Plugin `eco-portal-connector` | Settings, shop, cart, checkout |
| Pages auto-created | `/portal-shop`, `/portal-cart`, `/portal-checkout` |

### API calls used today

| Direction | Endpoint |
|-----------|----------|
| WordPress → Portal | `GET /api/developer-storefront/catalog` |
| WordPress → Portal | `POST /api/v1/external/orders` |

Auth: `Authorization: Bearer <developer storefront token>` from **Settings → Developer storefront** in the merchant portal.

---

## Option A — Docker (recommended)

### Requirements

- Docker + Docker Compose on the server
- Your Eco Commerce portal reachable from that server over HTTPS/HTTP (public URL, not only `localhost` on your laptop unless WordPress also runs on the same machine)

### 1. Copy this folder to the server

```bash
# from your machine, or clone the whole repo and use this folder
scp -r dev-test-wordpress user@your-server:/opt/eco-wordpress-test
```

### 2. Start WordPress

```bash
cd /opt/eco-wordpress-test
cp .env.example .env
# optional: edit WP_PORT (default 8088)
docker compose up -d
```

Open:

```text
http://YOUR_SERVER_IP:8088
```

Complete the WordPress install wizard (site title, admin user, password).

### 3. Activate the plugin

1. WordPress admin → **Plugins**
2. Activate **Eco Portal Connector**
3. Confirm pages exist: **Portal Shop**, **Portal Cart**, **Portal Checkout**

### 4. Connect to the portal

1. In the **merchant portal**, open **Settings → Developer storefront**
2. Generate a token (starts with `baa_dev_…`) and copy it once
3. In WordPress: **Settings → Eco Portal**
4. Set:
   - **Portal base URL** — public URL of Laravel, no trailing slash  
     Example: `https://portal.yourdomain.com`
   - **Developer storefront token** — paste the token
5. Click **Save connection**, then **Test connection**

You should see the store name and product count.

### 5. Run a test order

1. Open **Portal Shop** (`/portal-shop`)
2. Add a product to the cart
3. Checkout → **Place order & sync to portal**
4. In the merchant portal, open **Orders** — the order should appear with source `external_checkout`

---

## Option B — Existing WordPress (no Docker)

1. Zip the plugin folder:

```bash
cd dev-test-wordpress/wp-content/plugins
zip -r eco-portal-connector.zip eco-portal-connector
```

2. In WordPress admin → **Plugins → Add New → Upload Plugin**
3. Upload `eco-portal-connector.zip`, activate it
4. Follow steps 4–5 from Option A

---

## Networking checklist

WordPress calls the portal **from the WordPress server**, not from the visitor’s browser.

| Setup | Works? |
|-------|--------|
| Portal public (`https://portal.example.com`), WP public | Yes |
| Portal and WP on same VPS, portal at `http://127.0.0.1:8000` | Yes (use that URL in WP settings) |
| Portal only on your laptop (`localhost`), WP on a remote VPS | **No** — WP cannot reach your laptop |
| Portal behind firewall blocking the WP server IP | No — allow outbound HTTPS from WP to portal |

Product images are loaded from the portal URL. If images fail to show, the portal storage URL must be publicly reachable.

---

## Merchant portal prerequisites

Before testing:

1. At least one **active product with variants** and stock
2. Developer storefront token generated
3. Portal API routes reachable (no auth wall in front of `/api/*` that strips Bearer tokens)

---

## Troubleshooting

| Symptom | Check |
|---------|--------|
| Test connection fails with “Could not resolve host” | Portal base URL wrong or DNS not set |
| HTTP 401 | Token missing, revoked, or wrong store |
| HTTP 422 on order | Variant/product not in that store, or insufficient stock |
| Catalog empty | Products inactive or have no variants |
| HTML error page from portal | URL points at a frontend route, not the Laravel app root |

---

## Security notes (test only)

- Treat the developer token like a password
- Do not commit tokens into git
- This sample simulates payment on WordPress; it does not process real cards
- For production connectors, Phase 9 replaces the single developer token with scoped API keys

---

## File map

```text
dev-test-wordpress/
  docker-compose.yml
  .env.example
  README.md
  wp-content/plugins/eco-portal-connector/
    eco-portal-connector.php
    includes/
    templates/
    assets/
```
