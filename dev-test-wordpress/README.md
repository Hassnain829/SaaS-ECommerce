# Sample WordPress site — Eco Portal connector

WordPress is the customer-facing website. The merchant portal owns products, inventory, orders, customers, and shipping.

Use this connector to prove:

1. Catalog loads from the portal
2. Orders placed on WordPress appear in the merchant dashboard
3. Inventory reduces in the portal when platform checkout completes

The connection key stays **server-side in WordPress** (never in browser JavaScript). You do not need WooCommerce.

---

## Local XAMPP (this machine)

If WordPress already runs in XAMPP, use that site. Do **not** retarget the Docker `.env` in this folder to XAMPP MySQL credentials. Docker settings (`8088`, user/password `wordpress` / `wordpress`) are only for the optional Docker sample below.

Typical local pairing:

| App | Address / credentials |
|-----|------------------------|
| Merchant portal | `http://127.0.0.1:8000` |
| Existing XAMPP WordPress | `http://127.0.0.1:8080` |
| XAMPP MySQL | user `root`, blank password, database `wordpress` |

### 1. Get the plugin

In the merchant portal: **Website → Connect your website → Download plugin**.

Or zip the folder yourself:

```bash
cd dev-test-wordpress/wp-content/plugins
zip -r eco-portal-connector.zip eco-portal-connector
```

### 2. Install it on the XAMPP site

1. WordPress admin → **Plugins → Add New → Upload Plugin**
2. Upload `eco-portal-connector.zip` and activate it
3. Confirm pages exist: **Portal Shop**, **Portal Cart**, **Portal Checkout**, **Portal order status**

### 3. Connect to the portal

1. In the merchant portal, open **Website → Connect your website**
2. Save the exact WordPress home address for the selected store (for this XAMPP site: `http://localhost:8080/wordpress`)
3. Create that store's connection key and copy it once
4. In WordPress: **Settings → Eco Portal**
5. Set:
   - **Portal website address** — `http://127.0.0.1:8000` (no trailing slash)
   - **Connection key** — paste the key
6. Click **Save connection**, then **Test connection**

You should see the store name, product count, store currency, website-address match, and readiness notes (Stripe, location, catalog, plugin version).

The connection key is saved on the WordPress server only. After save, the settings field stays empty so the key is not printed back into HTML.

### 4. Run a test order

1. Open **Portal Shop** (`/portal-shop`)
2. Add a product to the cart
3. Checkout:
   - Enter the address, click **Get delivery rates**, choose a portal delivery method, then pay with Stripe. The order and stock update in this portal.
   - The checkout page creates one browser-bound attempt before showing the address form. Double submissions and retries after a lost WordPress response reuse that attempt; use **Start over** before intentionally changing a submitted checkout.
   - After Stripe submission, the checkout page asks the portal to verify the stored PaymentIntent directly with Stripe. A verified success creates the order without requiring a local Stripe CLI listener; signed webhooks remain the asynchronous recovery path. If confirmation is delayed, keep the processing page open or use **Check order status again**. Reloads and Stripe redirect returns resume the same status check; do not submit payment again.
4. In the merchant portal, open **Orders**

Shipping and labels stay in the portal after the order arrives.

---

## What you get

| Piece | Purpose |
|-------|---------|
| Plugin `eco-portal-connector` | Settings, shop, cart, checkout |
| Pages auto-created | `/portal-shop`, `/portal-cart`, `/portal-checkout` |
| `docker-compose.yml` | Optional WordPress + MySQL sample (not required for XAMPP) |

### API calls used today

| Direction | Endpoint |
|-----------|----------|
| WordPress → Portal | `GET /api/v1/catalog/products` |
| WordPress → Portal | `GET /api/v1/catalog/products/{id}` |
| WordPress → Portal | `GET /api/v1/catalog/categories` |
| WordPress → Portal | `GET /api/v1/site/health` |
| WordPress → Portal | `POST /api/v1/site/health` (WooCommerce/cache conflict report and catalog cache checkpoint) |
| WordPress → Portal | `GET /api/v1/site/events/config` (catalog event signing secret; server-side only) |
| WordPress → Portal | `GET /api/v1/catalog/events` (missed catalog updates) |
| Portal → WordPress | `POST /wp-json/eco-portal/v1/events` (signed catalog cache invalidation) |
| WordPress → Portal | `POST /api/v1/checkout` |
| WordPress → Portal | `POST /api/v1/checkout/{id}/confirm` (server-side Stripe retrieval, validation, and idempotent order confirmation) |
| WordPress → Portal | `GET /api/v1/orders/confirmation/{token}` |

Auth: `Authorization: Bearer <connection key>` from **Website → Connect your website** in the merchant portal.

---

## Option B — Docker (optional sample)

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
3. Confirm pages exist: **Portal Shop**, **Portal Cart**, **Portal Checkout**, **Portal order status**

### 4. Connect to the portal

1. In the **merchant portal**, open **Website → Connect your website**
2. Create a connection key (starts with `baa_dev_…`) and copy it once
3. In WordPress: **Settings → Eco Portal**
4. Set:
   - **Portal website address** — public URL of Laravel, no trailing slash  
     Example: `https://portal.yourdomain.com`
   - **Connection key** — paste the key
5. Click **Save connection**, then **Test connection**

You should see the store name, product count, store currency, website-address match, and readiness notes (Stripe, location, catalog, plugin version).

The connection key is saved on the WordPress server only. After save, the settings field stays empty so the key is not printed back into HTML.

### 5. Run a test checkout

1. Open **Portal Shop** (`/portal-shop`)
2. Add a product to the cart
3. Checkout → enter an address → **Get delivery rates** → pay with Stripe test card
4. In the merchant portal, open **Orders** — the order should appear from platform checkout

Website payment sync (`/api/v1/external/orders`) is no longer available.

---

## Option C — Other existing WordPress (no Docker, not XAMPP)

Follow the **Local XAMPP** plugin upload steps, then use your real portal URL instead of `http://127.0.0.1:8000`.

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
2. Connection key generated on **Website → Connect your website**
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

- Treat the connection key like a password
- Do not commit keys into git
This sample uses Stripe’s browser payment form from portal checkout data; it does not store Stripe secret keys.
- Shipping and labels stay in the merchant portal

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
