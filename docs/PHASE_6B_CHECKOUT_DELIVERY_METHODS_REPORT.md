# Phase 6B Checkout Delivery Methods Report

## Summary

Phase 6B connects Shipping & Delivery settings to platform checkout without starting live carrier APIs, label purchase, pickup scheduling, tracking sync, returns, B2B, automation, or billing.

Customers can now receive simple delivery choices from store-scoped shipping zones and delivery methods. Checkout selections update shipping totals, refresh the Stripe sandbox payment intent when needed, create checkout events, and snapshot the selected delivery method onto the final order.

## Files Inspected

- `AGENTS.md`
- `ENTERPRISE_PROJECT_CONTEXT.md`
- `ENTERPRISE_ROADMAP_2026.md`
- `PROJECT_BRAIN.md`
- `.agents/rules/PROJECT-CONTEXT.txt`
- `.agents/rules/ROADMAP.txt`
- `.agents/rules/Updated ERD similar to Shopify.txt`
- `docs/PHASE_5_EXTERNAL_CHECKOUT_SYNC_REPORT.md`
- `docs/PHASE_5_PLATFORM_CHECKOUT_STRIPE_SANDBOX_REPORT.md`
- `docs/PHASE_5_STRIPE_CONNECT_FOUNDATION_REPORT.md`
- `docs/PHASE_6A_MANUAL_FULFILLMENT_REPORT.md`
- Existing checkout, payment, external sync, shipping settings, order detail, and dev storefront files.

## Files Changed

- `database/migrations/2026_05_22_010000_add_shipping_selection_to_checkouts_table.php`
- `app/Models/Checkout.php`
- `app/Services/Shipping/ShippingZoneMatcher.php`
- `app/Services/Shipping/DeliveryOptionService.php`
- `app/Services/Shipping/CheckoutShippingService.php`
- `app/Services/CheckoutService.php`
- `app/Services/CheckoutConversionService.php`
- `app/Http/Controllers/Api/PlatformCheckoutController.php`
- `app/Http/Controllers/Api/ExternalOrderSyncController.php`
- `app/Services/ExternalOrderSyncService.php`
- `routes/api.php`
- `resources/views/user_view/shippingAutomation.blade.php`
- `resources/views/user_view/orderViewDetails.blade.php`
- `dev-test-storefront/src/App.jsx`
- `tests/Feature/Phase6CheckoutDeliveryMethodsTest.php`
- `docs/PHASE_6A_MANUAL_FULFILLMENT_REPORT.md`

## Shipping Selection Schema

Added checkout-level shipping selection fields:

- `shipping_method_id`
- `shipping_snapshot`

Orders use the existing `orders.meta` snapshot storage for selected delivery method details.

## Delivery Option Logic

Implemented store-scoped matching and pricing:

- country, region, and postal-pattern zone matching
- active/enabled checkout delivery methods only
- carrier account checkout availability checks
- supported country checks
- flat rate delivery
- free delivery
- free-over-threshold handling
- order minimum/maximum amount constraints
- safe fallback handling for future carrier-calculated methods

Customer-facing output uses delivery method names such as Standard delivery, Economy delivery, Express delivery, Local delivery, and Store pickup.

## Checkout API

Added API endpoints under the existing storefront-token protected checkout namespace:

- `POST /api/v1/checkout/{checkout}/delivery-options`
- `POST /api/v1/checkout/{checkout}/shipping-method`

The delivery-options response is trimmed to customer-facing option fields. Internal snapshots are stored only after selection.

Selecting a delivery method:

- verifies checkout store ownership
- verifies the checkout is still payment-pending
- verifies the method belongs to the same store
- verifies the method is available for the checkout address
- recalculates `shipping_total` and `grand_total`
- records `shipping.method_selected`
- refreshes the Stripe sandbox payment intent so the payment form uses the updated total

## Platform Checkout Conversion

Platform checkout conversion now copies the checkout shipping snapshot into the final order metadata. The order keeps the numeric shipping amount in the existing `orders.shipping` column.

## External Checkout Sync Compatibility

External checkout sync still accepts externally calculated shipping amounts. It does not require internal shipping zones or delivery methods.

Optional external shipping fields are preserved in `orders.meta.shipping`:

- `shipping_method_name`
- `shipping_carrier_name`
- `shipping_delivery_speed_label`

## Dashboard UI

Shipping & Delivery settings now exposes the Phase 6B configuration fields needed by checkout:

- postal patterns on zones
- zone sort order
- delivery speed label
- free-over amount
- min/max order amount
- estimated delivery days
- method sort order
- checkout/active toggles
- method edit forms

Order detail now displays the selected delivery method when a real snapshot exists.

## Dev Storefront Simulator

The local dev storefront now pauses platform checkout on delivery options before showing Stripe. After a customer chooses a delivery option, the simulator saves the method, receives a refreshed Stripe client secret, and then mounts the payment form with the updated total.

## Tests Added

Added `tests/Feature/Phase6CheckoutDeliveryMethodsTest.php`.

Coverage includes:

- delivery zone matching and flat/free shipping calculation
- shipping method selection updates checkout totals
- shipping selection creates checkout events
- payment intent refresh after shipping selection
- platform checkout order snapshot
- cross-store shipping method rejection
- external checkout shipping snapshot compatibility
- Shipping & Delivery UI field visibility

## Commands Run

- `php -l` on new/changed PHP services, controllers, migration, and tests: passed.
- `composer dump-autoload`: first run timed out while generating optimized files; rerun passed.
- `php artisan optimize:clear`: passed.
- `php artisan migrate:fresh --seed`: passed.
- `php artisan inventory:backfill`: passed.
- `php artisan test --filter=Phase6CheckoutDeliveryMethodsTest`: `6 passed, 45 assertions`.
- `php artisan migrate:rollback --step=2`: passed.
- `php artisan migrate`: passed.
- `php artisan inventory:backfill`: passed.
- `php artisan test --filter=Phase6CheckoutDeliveryMethodsTest`: `6 passed, 45 assertions`.
- `php artisan test --filter=Phase6ManualFulfillmentTest`: `8 passed, 101 assertions`.
- `php artisan test --filter=Phase5`: `38 passed, 244 assertions`.
- `php artisan test --filter=Phase4`: `20 passed, 166 assertions`.
- `php artisan test --filter=Inventory`: `11 passed, 77 assertions`.
- `php artisan test --filter=DeveloperStorefront`: `8 passed, 42 assertions`.
- `npm.cmd run build`: passed.
- `npm.cmd run build` in `dev-test-storefront`: passed.
- `php artisan test`: `371 passed, 1844 assertions`.

## Remaining Deferrals

Intentionally not implemented in Phase 6B:

- live DHL/UPS/FedEx/USPS APIs
- live carrier rates
- label purchase
- pickup scheduling
- tracking sync jobs
- carrier retry jobs
- automation routing
- returns, refunds, and exchanges
- B2B, Markets, and price lists
- SaaS billing

## External Managed Channel Mode Cross-Reference

Phase 6B platform delivery options apply to **platform checkout** only.

**External checkout** can remain fully external-managed: the external storefront manages payment, shipping, delivery, and fulfillment, then sends snapshots into the SaaS dashboard. The SaaS records and displays that data without requiring internal shipping zones, delivery methods, carrier accounts, or Stripe PaymentIntents.

See `docs/EXTERNAL_MANAGED_CHANNEL_MODE_REPORT.md` for Patch A ownership settings, external fulfillment/shipment sync, UI changes, and **explicit external checkout inventory ownership** (`platform` vs `external`).

## Final Phase 6B Status

Complete.

Checkout delivery methods are store-scoped, customer-facing, priced from Shipping & Delivery settings, snapshotted on checkout/order, compatible with external checkout sync, and verified by focused tests, regression tests, full suite, migration rollback/forward checks, and frontend builds.
