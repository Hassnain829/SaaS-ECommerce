# Security hardening notes

## Remote product image downloads (SSRF)

`App\Services\Catalog\ProductCatalogImageDownloader` validates URLs with `App\Support\Security\ServerSideImageHttpUrlValidator` before issuing HTTP requests:

- Only `http` and `https` schemes; no URL credentials.
- Hostname is resolved to IP addresses; **every** resolved address must be publicly routable (no loopback, RFC1918, link-local, unique local IPv6, etc.).
- HTTP redirects are **disabled** for the download client so a safe first hop cannot turn into an internal redirect target.
- Connect and response timeouts are bounded; response body size is capped; `Content-Type` must look like `image/*`.

If you add new server-side URL fetchers, reuse the same validation pattern.

## API throttling

Bearer-token API groups in `routes/api.php` use named rate limiters from `App\Providers\AppServiceProvider`:

- Catalog-style reads: `api-dev-catalog`
- Developer storefront order placement: `api-dev-orders`
- External order sync: `api-dev-external`
- Platform checkout: `api-dev-checkout`

Limits are per store id when the developer storefront middleware has resolved the store, otherwise per IP.

### Stripe webhooks

`POST /api/webhooks/stripe` and `POST /api/webhooks/stripe/connect` are **not** throttled by Laravel middleware. Stripe expects timely `2xx` responses; aggressive HTTP throttling can interact badly with retries and idempotency. Protection relies on signature verification (`STRIPE_WEBHOOK_SECRET` / connect secret) and infrastructure-level controls (WAF, IP allowlisting) if you deploy to production.

## Secrets

Never commit `.env` or real keys. After any leak, use `SECURITY_ROTATION_REQUIRED.md`.
