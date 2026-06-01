# Phase 6C-0A Nearest Eligible Origin Routing Report

## Summary

Phase 6C-0A adds service-area and stock-aware fulfillment origin routing before live carrier APIs. The implementation selects a best eligible origin from active store locations using configured countries, regions, postal patterns, pickup eligibility, stock availability, and store-owner routing priority.

This phase does not implement latitude/longitude, geocoding, miles, kilometers, live carrier rates, labels, pickup scheduling, tracking sync jobs, or carrier retries.

## Files Inspected

- `AGENTS.md`
- `ENTERPRISE_PROJECT_CONTEXT.md`
- `ENTERPRISE_ROADMAP_2026.md`
- `PROJECT_BRAIN.md`
- `.agents/rules/PROJECT-CONTEXT.txt`
- `.agents/rules/ROADMAP.txt`
- `.agents/rules/Updated ERD similar to Shopify.txt`
- `docs/PHASE_3_ENTERPRISE_INVENTORY_REPORT.md`
- `docs/PHASE_6A_MANUAL_FULFILLMENT_REPORT.md`
- `docs/PHASE_6B_CHECKOUT_DELIVERY_METHODS_REPORT.md`
- `docs/EXTERNAL_MANAGED_CHANNEL_MODE_REPORT.md`
- checkout, inventory, shipping, fulfillment, location, external sync, and dev storefront code paths

`PHASE_6C_CODE_AUDIT_BUNDLE.md` and `PHASE_6C_CURRENT_STATE_SUMMARY.md` were requested as read-first files but were not present in the project root during this implementation pass.

## Files Changed

- `database/migrations/2026_05_30_010000_add_fulfillment_routing_to_locations_and_checkouts.php`
- `app/Models/Location.php`
- `app/Models/Checkout.php`
- `app/Data/Fulfillment/FulfillmentOriginResult.php`
- `app/Services/Fulfillment/LocationServiceAreaMatcher.php`
- `app/Services/Fulfillment/FulfillmentOriginRouter.php`
- `app/Services/Inventory/DefaultLocationService.php`
- `app/Services/CheckoutService.php`
- `app/Services/Shipping/CheckoutShippingService.php`
- `app/Services/CheckoutConversionService.php`
- `app/Services/ExternalOrderSyncService.php`
- `app/Services/Fulfillment/ShipmentService.php`
- `app/Http/Controllers/LocationController.php`
- `app/Http/Controllers/Api/PlatformCheckoutController.php`
- `app/Support/OrderLifecycle.php`
- `resources/views/user_view/locations.blade.php`
- `resources/views/user_view/orderViewDetails.blade.php`
- `dev-test-storefront/src/App.jsx`
- `tests/Feature/Phase6NearestEligibleOriginRoutingTest.php`
- `ENTERPRISE_PROJECT_CONTEXT.md`
- `ENTERPRISE_ROADMAP_2026.md`

## Data Model

Added location routing fields:

- `fulfills_online_orders`
- `pickup_enabled`
- `routing_priority`
- `service_countries`
- `service_regions`
- `service_postal_patterns`

Added checkout routing fields:

- `fulfillment_origin_location_id`
- `pickup_location_id`
- `fulfillment_routing_snapshot`

Orders keep the final routing snapshot in `orders.meta.fulfillment_routing`.

## Routing Behavior

- Delivery orders choose one active location that can fulfill online orders, matches the customer destination service area, and has enough stock for all tracked items.
- Pickup orders require an active pickup-enabled location with enough stock.
- If exactly one pickup location is eligible, it can be auto-selected.
- If multiple pickup locations are eligible, checkout requires a selected `pickup_location_id`.
- Routing uses postal exact/prefix matches, region matches, country matches, default-location tie help, routing priority, and deterministic location ID ordering.
- The routing strategy is stored as `nearest_eligible_0a` with basis `service_area_stock_priority`.

## Checkout Integration

- Platform checkout routes before inventory reservation.
- Inventory reservations are created at the selected origin instead of always using the default location.
- Selecting or changing a shipping/pickup method can reroute reservations safely inside a database transaction.
- Delivery options expose safe fulfillment origin details and pickup location choices without stock counts.
- Checkout events record `fulfillment.origin_selected`.

## Order And Shipment Integration

- Platform checkout conversion copies checkout routing into `orders.meta.fulfillment_routing`.
- External checkout sync routes only when dashboard inventory owns inventory for external orders.
- External inventory-owned orders do not fake routing.
- Order events record `fulfillment.origin_selected`.
- Order detail preselects the routed origin in the shipment form and shows pickup-origin context when present.
- Shipment metadata records routed origin, selected origin, and whether the store owner used the routed origin or manually overrode it.

## Onboarding And Locations UI

- Default locations now initialize routing-safe defaults.
- Store defaults can fill blank default location address/country/service-country fields without overwriting edited values.
- Settings -> Locations now includes online fulfillment, pickup, routing priority, service countries, service regions, and postal patterns.

## Tests Added

- `tests/Feature/Phase6NearestEligibleOriginRoutingTest.php`

Coverage includes service-area origin selection, pickup location selection, reservation rerouting, exact-stock delivery options that count the checkout's own reservation, order routing snapshots, shipment origin prefill behavior, external checkout platform-inventory routing, and external-inventory non-routing.

## Commands Run

- `php -l` on new/changed PHP classes and controllers: passed.
- `php artisan test --filter=Phase6NearestEligibleOriginRoutingTest`: passed, `5 passed, 41 assertions`.
- `php artisan test --filter=Phase6CheckoutDeliveryMethodsTest`: passed, `6 passed, 45 assertions`.
- `php artisan test --filter=Phase6ManualFulfillmentTest`: passed, `8 passed, 101 assertions`.
- `php artisan test --filter=ExternalManagedChannelModeTest`: passed, `22 passed, 83 assertions`.
- `php artisan test --filter=Phase5PlatformCheckoutStripeTest`: passed, `9 passed, 72 assertions`.
- `php artisan test --filter=Phase5ExternalCheckoutSyncTest`: passed, `8 passed, 59 assertions`.
- `php artisan test --filter=Inventory`: passed, `24 passed, 117 assertions`.
- `php artisan migrate`: passed.
- `php artisan migrate:rollback --step=2`: passed.
- `php artisan migrate`: passed.
- `composer dump-autoload`: passed.
- `php artisan optimize:clear`: passed.
- `npm.cmd run build`: passed.
- `npm.cmd run build` in `dev-test-storefront`: passed.
- `php artisan test`: passed, `426 passed, 2077 assertions`.

## Deferred

- optional coordinate/geocoding-based routing
- split-origin fulfillment
- live carrier APIs
- live rates
- label purchase
- carrier pickup scheduling
- tracking sync jobs
- carrier retry jobs
- carrier automation rules

## Final Status

Complete.
