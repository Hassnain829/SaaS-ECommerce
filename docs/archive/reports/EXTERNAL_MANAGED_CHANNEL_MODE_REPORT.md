# External Managed Channel Mode Report

## Summary

Patch A (Stage 1) introduces an explicit channel ownership model for external vs platform checkout, extends external order sync with shipping and fulfillment snapshots, adds an external shipment sync API, updates merchant order detail and payments UI, and refreshes the dev-test storefront simulator.

No migrations were added. Channel ownership settings are stored in `stores.settings` JSON (`channels` + existing `checkout_mode`).

Stripe sandbox Connect, live carrier APIs, label purchase, tracking sync jobs, and webhooks/outbox were not implemented in this patch.

## Files Inspected

- `AGENTS.md`, enterprise context/roadmap files, Phase 5/6 reports
- `app/Support/CheckoutMode.php`, `app/Http/Controllers/PaymentSettingsController.php`
- `app/Services/ExternalOrderSyncService.php`, `app/Http/Controllers/Api/ExternalOrderSyncController.php`
- `app/Models/Order.php`, `app/Models/Shipment.php`, `app/Support/OrderLifecycle.php`
- `resources/views/user_view/payment_settings.blade.php`, `orderViewDetails.blade.php`
- `dev-test-storefront/src/App.jsx`
- Existing Phase 5/6 feature tests

## Current Gaps Found (Stage 0)

- Checkout mode existed as a single `checkout_mode` flag only; ownership of payment/shipping/fulfillment was implicit.
- External order sync stored basic `orders.meta.shipping` flat fields but not structured `shipping` / `fulfillment` objects or ownership snapshots.
- No external shipment update endpoint; fulfillment on external orders always started `unfulfilled`.
- Order detail always promoted internal “Create shipment” even for external checkout orders.
- Dev storefront had external vs platform toggle but lacked external shipping/fulfillment test fields.

## Channel Ownership Decision

- Store ownership in `stores.settings.channels.external_checkout` and `stores.settings.channels.platform_checkout`.
- Active mode remains `stores.settings.checkout_mode` (`external_checkout` | `platform_checkout`).
- Helper: `app/Services/Channels/ChannelOwnershipService.php`.

## Store Settings / Ownership Storage

```json
{
  "checkout_mode": "external_checkout",
  "channels": {
    "external_checkout": {
      "enabled": true,
      "checkout_owner": "external",
      "payment_owner": "external",
      "shipping_owner": "external",
      "fulfillment_owner": "external",
      "inventory_owner": "external",
      "source_channel": "external_storefront"
    },
    "platform_checkout": {
      "enabled": true,
      "checkout_owner": "platform",
      "payment_owner": "platform",
      "shipping_owner": "platform",
      "fulfillment_owner": "platform",
      "inventory_owner": "platform",
      "source_channel": "platform_checkout"
    }
  }
}
```

## External Order Shipping Snapshot

- `POST /api/v1/external/orders` accepts optional `shipping` object (and legacy flat shipping fields).
- Snapshot stored in `orders.meta.shipping`.
- Event: `external_shipping.recorded`.

## External Fulfillment Snapshot

- Optional `fulfillment` object on order sync.
- Snapshot stored in `orders.meta.fulfillment`.
- Safe status mapping to `orders.fulfillment_status`.
- Event: `external_fulfillment.recorded`.
- `orders.meta.channel_ownership` records owners at sync time.

## External Shipment Sync

- `POST /api/v1/external/shipments`
- Idempotent by `external_shipment_id` per order (updates existing `shipments` row with `metadata.source = external`).
- Also mirrors summary under `orders.meta.external_shipments[]`.
- Event: `external_shipment.updated`.
- No internal carrier account or shipping method required.

## Order Detail UI

- External orders show “Fulfillment managed externally” with carrier, tracking, and status from snapshots.
- Primary internal create-shipment form hidden; optional advanced override in a collapsed details block.

## Dev Storefront Simulator

- “External managed order” mode with payment, shipping, fulfillment, and tracking fields.
- “Sync external shipment update” action after order sync.
- Platform mode unchanged (Phase 6B delivery options + Stripe).

## Tests Added / Updated

- `tests/Feature/ExternalManagedChannelModeTest.php` (new)
- `tests/Feature/ExternalShipmentSyncTest.php` (new)
- Phase 5/6 regression suites re-run successfully.

## Commands Run

```txt
composer dump-autoload
php artisan test --filter=ExternalManagedChannelModeTest   → 6 passed
php artisan test --filter=ExternalShipmentSyncTest         → 4 passed
php artisan test --filter=Phase6CheckoutDeliveryMethodsTest → 6 passed
php artisan test --filter=Phase6ManualFulfillmentTest      → 8 passed
php artisan test --filter=Phase5ExternalCheckoutSyncTest   → 8 passed
php artisan test                                           → full suite passed
```

## Remaining Deferrals

- Stripe sandbox Connect onboarding (not in Patch A)
- Live DHL/UPS/FedEx APIs, label purchase, pickup scheduling
- Tracking sync background jobs
- Full `sales_channels` table / multi-channel admin
- Production API keys, webhooks/outbox
- Returns/refunds/B2B

## Final Status

**Complete** — Patch A stages 1–5 implemented; new and regression tests pass; no migrations required.

---

## Patch A Cleanup Addendum

Browser review after Stage 1 found UX and optional-snapshot logic gaps. This cleanup patch addresses those only (no Stripe Connect, carrier APIs, or webhook work).

### Optional snapshot logic

- External order sync accepts a **minimum payload** without `shipping` or `fulfillment` objects.
- `shipping_total` alone does **not** create `orders.meta.shipping` or `external_shipping.recorded`.
- Shipping meta/events are created only when explicit shipping data is provided (`shipping` object or legacy flat shipping fields such as `shipping_method_name`).
- Fulfillment meta/events are created only when a non-empty `fulfillment` object is provided.
- External fulfillment snapshots are stored for merchant visibility; internal `orders.fulfillment_status` stays `unfulfilled` until platform-managed fulfillment applies.
- Optional `totals` object supported on order sync (`subtotal`, `shipping`, `tax`, `total`, etc.).
- `customer.name` accepted as alias for `customer.full_name`.

### Merchant UX

- Order detail: primary fulfillment badge **Externally managed**; internal “remaining to fulfill” de-emphasized in a collapsed details block for external orders.
- External fulfillment panel empty state: **No shipment update has been received yet**.
- Shipping settings page: banner when external managed checkout is active, with link to Payments & Channels.

### Dev storefront simulator

- Renamed and reframed as **Developer payload simulator** with integration-test disclaimer.
- Section order: **A** customer data → **B** payment snapshot → **C** optional shipping snapshot → **D** optional fulfillment update.
- Shipping and fulfillment snapshots are opt-in via checkboxes (defaults off).
- Primary actions: **Sync external checkout order** and **Send shipment update**.

### Verification against product decision

| Area | External managed | Platform managed |
|------|------------------|------------------|
| Checkout owner | External website | SaaS platform |
| Payment | Recorded snapshot only | Stripe/platform flow |
| Shipping selection | External snapshot optional | Phase 6B delivery methods |
| Fulfillment | External snapshots / shipment API | Internal shipment workflow |
| Dashboard role | History and visibility | Operational control |

### Tests added/updated (cleanup)

- `ExternalManagedChannelModeTest`: minimum payload, optional shipping/fulfillment, shipping page banner, order detail empty state, fulfillment status assertion fix.

### Remaining deferrals (unchanged)

Stripe sandbox Connect, live carriers, label purchase, tracking sync jobs, webhooks/outbox, returns/refunds, B2B, SaaS billing.

---

## Inventory Ownership Cleanup Addendum

External checkout now has explicit inventory ownership.

Supported modes:

- `inventory_owner = platform`: external orders reserve/deduct dashboard inventory.
- `inventory_owner = external`: external orders are recorded without changing dashboard inventory.

The previous ambiguity was removed because external checkout/payment/shipping/fulfillment ownership does not automatically decide inventory ownership.

Current default:

- Existing/missing external checkout inventory ownership defaults to `platform` to preserve current headless storefront behavior where products are fetched from SaaS and external orders reduce dashboard stock.
- Legacy Patch A stores that implicitly saved `inventory_owner = external` are normalized to `platform` until the merchant explicitly saves an inventory source.

Merchant-facing copy on Payments & Channels now explains whether external orders change dashboard stock. Merchants can save:

- **Use dashboard inventory**
- **Inventory managed by external storefront**

Order timeline events:

- Platform inventory: `inventory.reserved`, `inventory.deducted`
- External inventory: `inventory.external_managed`

Dev storefront catalog API exposes `store.external_checkout.inventory_owner` for simulator copy.
