# Order Lifecycle Hardening Report

## Summary

This pass hardened the existing Commerce Core order lifecycle before Phase 2 work. It separates order, payment, fulfillment, and future shipment responsibilities; adds event-backed order activity; removes shipping-like order statuses from dashboard flows; and keeps the developer storefront order creation path working.

No API key/scopes/idempotency work, real payment gateway, carrier/shipment system, returns/refunds module, inventory locations/reservations, billing, webhooks, automation, or Phase 2 catalog work was implemented.

## Status Separation

Order status now describes the merchant-facing order lifecycle only. Payment status remains payment-specific. Fulfillment status remains fulfillment-specific. Shipment statuses are documented centrally for future shipment work but are not exposed as order statuses.

The order dashboard and order detail page now render order state, payment, and fulfillment as separate badges.

## Order Statuses

- `pending`
- `confirmed`
- `processing`
- `completed`
- `cancelled`
- `refunded`

Rejected as order statuses:

- `shipped`
- `delivered`

## Payment Statuses

- `pending`
- `authorized`
- `paid`
- `failed`
- `refunded`
- `partially_refunded`

## Fulfillment Statuses

- `unfulfilled`
- `partial`
- `fulfilled`
- `returned`

## Future Shipment Statuses

Shipment statuses are centralized for future use only:

- `pending`
- `label_created`
- `picked_up`
- `in_transit`
- `delivered`
- `failed`
- `returned`

## Event Types

Supported order event types:

- `order.created`
- `order.status_changed`
- `payment.status_changed`
- `fulfillment.status_changed`
- `inventory.deducted`
- `order.note_added`
- `order.cancelled`
- `order.completed`
- `order.refunded`

## Files Changed

- `app/Support/OrderLifecycle.php`
- `app/Models/Order.php`
- `app/Models/OrderEvent.php`
- `app/Services/OrderEventRecorder.php`
- `app/Console/Commands/BackfillOrderEvents.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/Api/DeveloperStorefrontCatalogController.php`
- `bootstrap/app.php`
- `database/seeders/CustomerAndOrderSeeder.php`
- `resources/views/user_view/orders.blade.php`
- `resources/views/user_view/orderViewDetails.blade.php`
- `resources/views/user_view/customersProfileTab.blade.php`
- `tests/Feature/OrderLifecycleTest.php`
- `tests/Feature/OrderEventsTimelineTest.php`
- `tests/Feature/DeveloperStorefrontOrderEventsTest.php`
- `docs/ORDER_LIFECYCLE_HARDENING_REPORT.md`

## Migrations Added

- `database/migrations/2026_05_06_020000_create_order_events_table.php`

The `order_events` table contains:

- `id`
- `store_id`
- `order_id`
- `actor_user_id`
- `event_type`
- `title`
- `description`
- `data`
- `created_at`

Order events cascade when an order is deleted and remain store-scoped through `store_id`.

## Services Added

- `OrderEventRecorder`: central service for writing order events from an `Order`.
- `OrderLifecycle`: central constants, labels, badge classes, shipment status documentation, and simple order transition rules.

## UI Changes

Orders list:

- Uses central order status filters.
- No longer shows `shipped` as an order filter.
- Shows separate order, payment, and fulfillment badges.

Order detail:

- Status dropdown uses central lifecycle statuses.
- Invalid transition options are not offered.
- Timeline is loaded from `$order->events`.
- Empty timeline state is honest when no order events exist.
- Fake courier automation, tracking number, tracking URL, print-label, and inferred timeline rows were removed.
- Shipment area now shows a clean empty state until real shipping exists.

Customer profile:

- Purchase history order status labels use `OrderLifecycle`.
- The fake purchase-history CSV control was removed.

## Tests Added/Updated

- `tests/Feature/OrderLifecycleTest.php`
- `tests/Feature/OrderEventsTimelineTest.php`
- `tests/Feature/DeveloperStorefrontOrderEventsTest.php`

Coverage includes:

- status separation
- `shipped` and `delivered` rejected as order statuses
- simple order transition rules
- order event relationships
- storefront order event creation
- dashboard order status event creation
- security log creation on order status change
- invalid status rejection
- invalid transition rejection
- staff permission denial for order status updates
- orders list separate status badges
- order detail real timeline rendering
- empty order timeline state
- cross-store order detail protection
- idempotent `orders:backfill-events` command

## Commands Run

- `composer dump-autoload`
- `php artisan optimize:clear`
- `php -l app\Support\OrderLifecycle.php`
- `php -l app\Models\Order.php`
- `php -l app\Models\OrderEvent.php`
- `php -l app\Services\OrderEventRecorder.php`
- `php -l app\Console\Commands\BackfillOrderEvents.php`
- `php -l app\Http\Controllers\DashboardController.php`
- `php -l app\Http\Controllers\Api\DeveloperStorefrontCatalogController.php`
- `php -l database\seeders\CustomerAndOrderSeeder.php`
- `php -l database\migrations\2026_05_06_020000_create_order_events_table.php`
- `php -l bootstrap\app.php`
- `php artisan test --filter=OrderLifecycle`
- `php artisan test --filter=OrderEventsTimeline`
- `php artisan test --filter=DeveloperStorefront`
- `php artisan test --filter=Order`
- `php artisan test --filter=Customer`
- `php artisan test --filter=SecurityLogAndSessionTest`
- `php artisan test --filter=StorePermissionLayerTest`
- `php artisan migrate:fresh --seed`
- `php artisan orders:backfill-events`
- `php artisan migrate:rollback --step=1`
- `php artisan migrate`
- `php artisan orders:backfill-events`
- `php artisan test`

## Verification Results

- `OrderLifecycle`: 3 passed, 14 assertions.
- `OrderEventsTimeline`: 10 passed, 55 assertions.
- `DeveloperStorefront`: 8 passed, 40 assertions.
- `Order`: 25 passed, 120 assertions.
- `Customer`: 1 passed, 9 assertions.
- `SecurityLogAndSessionTest`: 6 passed, 28 assertions.
- `StorePermissionLayerTest`: 3 passed, 49 assertions.
- `php artisan migrate:fresh --seed`: passed on the configured local database.
- First `php artisan orders:backfill-events` after seeding: backfilled 0 order events because seeded orders already had event history.
- `php artisan migrate:rollback --step=1`: rolled back the order events migration successfully.
- `php artisan migrate`: reapplied the order events migration successfully.
- Second `php artisan orders:backfill-events`: backfilled 13 order events for existing orders after the table was recreated.
- Full suite: 252 passed, 1046 assertions.

## Remaining Deferrals

Intentionally deferred:

- API keys, scopes, idempotency keys, and rate limiting.
- Production API v1.
- Real checkout sessions.
- Real payment gateway and payment attempts.
- Fulfillment carriers, shipments, labels, tracking sync, and carrier jobs.
- Returns, refunds, and exchanges.
- Multi-location inventory and reservations.
- Order notes write flow.
- Manual/draft order creation.
- Customer CRM completion beyond order-history status labeling.

## Final Status

Complete.

Order lifecycle status separation, order events, storefront order event recording, dashboard status-change events, event-backed order detail timeline, event backfill, store scoping, Phase 1 permissions, migration safety, and full test verification are in place.
