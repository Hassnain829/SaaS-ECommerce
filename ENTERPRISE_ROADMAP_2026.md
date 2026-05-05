# ENTERPRISE ROADMAP 2026 — SaaS E-Commerce Platform

> **Purpose:** This roadmap is the operational build plan for the Laravel Blade multi-store SaaS e-commerce platform. It is designed for Cursor, Codex, Anti-Gravity, and human developers to follow step-by-step without losing the original product direction.
>
> **Target:** Build a merchant-friendly, enterprise-grade commerce operating system that can compete with Shopify/WooCommerce/Amazon Seller Central in capability while being easier for merchants to understand and operate.
>
> **Current audit basis:** Project zip inspection + research document + existing roadmap + ERD + Cursor/Anti-Gravity progress notes.

---

## 0. Strategic North Star

We are not building a simple online store admin panel.

We are building a **multi-tenant SaaS commerce platform** where merchants can:

- create/manage stores;
- manage large product catalogs;
- import messy supplier catalogs;
- manage variants, custom fields, images, and inventory clearly;
- connect external storefronts through APIs;
- receive and manage real orders/customers;
- fulfill shipments;
- process payments/refunds/returns;
- automate operations;
- integrate with external systems;
- manage billing/subscriptions for the SaaS itself;
- operate safely with multi-store permissions, audit logs, and enterprise reliability.

Every roadmap item must make the product more:

1. **Store-scoped**
2. **Merchant-friendly**
3. **Enterprise-safe**
4. **Import-compatible**
5. **API-ready**
6. **Auditable**
7. **Tested**

---

## 1. Current Completion Snapshot

### 1.1 Strongly implemented

The project already has a strong base in these areas:

- Store onboarding
- Current store middleware
- Store membership pivot
- Basic store roles: owner / manager / staff
- Product catalog foundation
- Categories, brands, tags
- Product categories/tags pivots
- Normalized product images
- Product workspace
- Product edit workspace
- Product quick add
- Variant system
- Variant image linkage
- Variant import
- Product import pipeline
- Import preview/history/report/retry
- Custom fields and `import_extra`
- Additional details editing
- Stock movement audit foundation
- Developer storefront API prototype
- Dynamic-ish orders/customers foundation
- Customer/order/address migrations and models
- Basic dev storefront React app

### 1.2 Partially implemented

These exist but require hardening:

- RBAC / permissions
- Product edit UX
- Order management
- Customer CRM
- Storefront checkout
- Inventory logic
- Product API
- Bulk actions
- Dashboard metrics
- Admin/platform UI
- Tests and seeders

### 1.3 Mostly missing

These are required for the final enterprise target:

- Multi-location inventory
- Inventory reservations
- Fulfillment/shipping system
- Carrier accounts
- Shipping rules
- Async carrier jobs
- Payments / payment attempts / refunds
- Tax settings
- Coupons/discount rules
- Returns/exchanges
- B2B companies/company locations
- Markets
- Catalogs/price lists
- API keys with scopes
- Webhooks
- Event outbox
- Idempotency keys
- Notifications
- Security logs
- User sessions
- SaaS billing plans/subscriptions/invoices/payment methods
- Observability/correlation IDs
- Platform admin operations

---

## 2. Mandatory Definition of Done

A feature is **not done** when a Blade page exists.

A feature is done only when all of the following are complete:

1. **Migration exists** where data is needed.
2. **Model relationships exist** and are tested.
3. **Controller/service code is store-scoped**.
4. **Authorization is enforced**.
5. **UI is merchant-friendly** and avoids technical jargon.
6. **Data is shown dynamically**, not static dummy content.
7. **Create/update/delete flows work** where relevant.
8. **Tests cover happy path, validation, permissions, and cross-store safety**.
9. **Seed data exists** if the feature needs realistic demo data.
10. **No broken buttons or fake actions** are visible.
11. **Events/audit records are recorded** for business-critical changes.
12. **`php artisan migrate:fresh --seed` works**.
13. **Targeted tests pass**.

---

# PHASE 0 — Stabilization, CI, and Audit Proof

## Goal

Make the project trustworthy before adding more modules.

## Why this phase is mandatory

The research assessment warns that progress must be traceable to code, migrations, tests, seed data, and working merchant flows. Notes alone are not proof. This phase creates that proof.

---

## 0.1 Fix PHP test environment

### Problem

The test suite could not be fully verified in the audit environment because required PHP extensions were missing:

- `dom`
- `mbstring`
- `xml`
- `xmlwriter`

### Implementation steps

1. Enable/install missing PHP extensions locally.
2. Confirm `php -m` includes:
   - `dom`
   - `mbstring`
   - `xml`
   - `xmlwriter`
3. Create or verify `.env.testing`.
4. Use a separate test database or SQLite for testing.
5. Run:

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan test
```

### Tests / checks

- Full suite executes.
- No environment-related PHPUnit failures.

### Acceptance criteria

- Full test suite runs from clean checkout.
- CI/local developer can prove roadmap completion.

---

## 0.2 Update stale tests after Commerce Core changes

### Problem

Some tests may still represent earlier API contracts. Example: storefront order tests may post only customer name/items while the API now expects customer email and shipping address.

### Implementation steps

1. Inspect `tests/Feature/DeveloperStorefrontApiTest.php`.
2. Match request payload to current `DeveloperStorefrontCatalogController::placeOrder` validation.
3. Add tests for:
   - valid guest checkout;
   - missing shipping address validation;
   - invalid variant;
   - insufficient stock;
   - successful order creates customer/address/order/items/stock movement.

### Acceptance criteria

- Storefront API tests reflect the real current checkout payload.

---

## 0.3 Migration cleanup

### Problem

Commerce migrations evolved quickly. The database must be able to rebuild cleanly without manual fixes.

### Implementation steps

1. Inspect all migrations from:
   - `2026_04_25_120000_developer_storefront_token_and_orders.php`
   - `2026_05_04_203927_create_customers_table.php`
   - `2026_05_04_203928_create_customer_addresses_table.php`
   - `2026_05_04_203929_create_order_addresses_table.php`
   - `2026_05_04_203929_modify_orders_table_for_commerce_core.php`
2. Ensure `orders.customer_name` does not remain after fresh migration.
3. Implement safe `down()` methods.
4. Check foreign keys and column order.
5. Run:

```bash
php artisan migrate:fresh --seed
php artisan migrate:rollback --step=5
php artisan migrate
```

### Acceptance criteria

- No manual DB fixes are needed after `migrate:fresh --seed`.
- Seeders populate expected customers/orders.

---

## 0.4 Root directory cleanup

### Implementation steps

1. Remove temporary files:
   - `patch.php`
   - `fix_*.php`
   - `drop_*.php`
   - scratch files
2. Add `.gitignore` rules if needed.
3. Run:

```bash
git status
```

### Acceptance criteria

- Project root contains only proper project files.

---

## 0.5 Deterministic order number generation

### Problem

Random 5-digit order numbers can collide and are not reliable enough.

### Implementation options

Preferred:

- Add `store_order_sequences` table:
  - `id`
  - `store_id`
  - `next_number`
  - timestamps
- Generate order numbers transactionally:
  - `#1001`, `#1002`, etc.
  - or store-prefixed: `DF-1001`

Alternative:

- Query last order number per store with lock.

### Implementation steps

1. Create migration for `store_order_sequences`.
2. Create `OrderNumberGenerator` service.
3. Use DB transaction and row lock.
4. Replace random generation in:
   - `DeveloperStorefrontCatalogController`
   - `CustomerAndOrderSeeder`
   - future manual/draft order creation
5. Add unique index:
   - `unique(store_id, order_number)`

### Tests

- same store gets sequential unique numbers;
- different stores can use same numeric sequence;
- order number is short and human-readable.

### Acceptance criteria

- No random order-number collisions.
- Merchant sees manageable order numbers.

---

# PHASE 1 — SaaS Foundation Hardening

## Goal

Move from basic multi-store support to safe multi-store SaaS operations.

---

## 1.1 Store-scoped permissions

### Current state

The app has:

- global roles table;
- `store_user` pivot;
- store member roles: owner, manager, staff;
- current store middleware.

### Missing

Granular permissions are not implemented.

### Implementation steps

1. Keep current `store_user.role` for now.
2. Add a permission mapping layer:

```php
owner: all store permissions
manager: catalog.manage, orders.manage, customers.manage
staff: catalog.view, orders.view, customers.view
```

3. Add helper/policy methods:
   - `canManageCatalog($store)`
   - `canManageOrders($store)`
   - `canManageCustomers($store)`
   - `canManageSettings($store)`
4. Replace role-name checks in controllers with permission checks.
5. Later upgrade to Spatie Permission with teams only if needed.

### Tests

- owner can manage everything;
- manager can manage allowed modules;
- staff cannot mutate catalog/orders/customers;
- cross-store access rejected.

### Acceptance criteria

- Permissions are explicit and test-covered.

---

## 1.2 Security logs

### Create tables

`security_logs`:

- `id`
- `store_id` nullable
- `user_id` nullable
- `target_user_id` nullable
- `event_type`
- `severity`
- `ip_address`
- `user_agent`
- `metadata`
- `created_at`

### Events to log

- login
- logout
- failed login
- password change
- store switch
- team member invited
- role changed
- API key created/revoked
- order status changed
- product bulk action
- import confirmed

### Implementation steps

1. Create migration/model.
2. Create `SecurityLogRecorder` service.
3. Call service from auth/team/API/order/product areas.
4. Add dynamic security page.

### Acceptance criteria

- Sensitive actions are auditable.

---

## 1.3 User sessions

### Create table

`user_sessions`:

- `user_id`
- `session_id`
- `ip_address`
- `user_agent`
- `browser`
- `os`
- `device_type`
- `last_activity`
- `revoked_at`
- `is_current`

### UI

- Security settings page shows active sessions.
- User can revoke other sessions.

### Acceptance criteria

- Account security is visible and manageable.

---

## 1.4 Auth completion

### Implement

- password reset;
- email verification;
- optional 2FA later;
- throttled login;
- profile update;
- avatar upload;
- account deactivation rules.

### Acceptance criteria

- Authentication is no longer demo-level.

---

# PHASE 2 — Catalog Completion and Data Cleanup

## Goal

Finish catalog foundations before deeper commerce/fulfillment work.

---

## 2.1 SKU uniqueness refactor

### Problem

`product_variants.sku` appears globally unique in the project. This can block two stores from using the same supplier SKU.

### Implementation steps

1. Confirm current DB index name.
2. Add migration to drop global unique index on `product_variants.sku`.
3. Preferred: add `store_id` to `product_variants` and backfill from products.
4. Add store-scoped unique index:
   - `unique(store_id, sku)` where DB supports it;
   - otherwise enforce in validation and service layer.
5. Update import/edit validation to be store-aware.

### Tests

- same SKU in two different stores allowed;
- same SKU twice in same store rejected with validation error;
- duplicate SKU never causes 500.

### Acceptance criteria

- SKU behavior is SaaS-safe.

---

## 2.2 Attribute system

### Why

Custom fields are flexible, but attributes are needed for structured filtering and storefront discovery.

### Tables

- `attributes`
- `attribute_terms`
- `product_attributes`
- `product_attribute_terms`

### Implementation steps

1. Add migrations and models.
2. Build attribute manager UI under Catalog Tools.
3. Product edit page:
   - add attribute section;
   - allow selecting terms;
   - mark attribute as visible/filterable.
4. Import mapping:
   - support mapping columns to attributes.
5. Storefront API:
   - expose filterable attributes.

### Acceptance criteria

- Attributes are structured product data.
- Custom fields remain flexible extra data.

---

## 2.3 Product type behavior

### Product types

- physical
- digital
- service
- subscription
- virtual

### Implementation steps

1. Add clear behavior service:
   - `ProductBehaviorResolver`
2. Rules:
   - physical: stock + shipping required;
   - digital: no shipping, no physical fulfillment;
   - service: booking/resource later;
   - subscription: recurring billing later;
   - virtual: no shipping.
3. Update product edit UI to show relevant sections only.
4. Update storefront API to expose behavior.

### Acceptance criteria

- Product type affects checkout/fulfillment behavior.

---

## 2.4 Product API v1

### Current

Developer storefront endpoint is proof-of-concept.

### Build

- `GET /api/v1/products`
- `GET /api/v1/products/{id}`
- `GET /api/v1/categories`
- `GET /api/v1/brands`

### Requirements

- API key auth;
- scopes;
- pagination;
- filters;
- stable response shape;
- versioned namespace.

### Acceptance criteria

- Dev storefront uses production-style API v1.

---

# PHASE 3 — Enterprise Inventory Model

## Goal

Replace single-stock logic with location-aware inventory before building serious fulfillment.

---

## 3.1 Locations

### Tables

`locations`:

- `id`
- `store_id`
- `name`
- `type` warehouse/store/third_party
- `address_line1`
- `city`
- `state`
- `postal_code`
- `country_code`
- `is_default`
- `is_active`

### Implementation steps

1. Create migration/model.
2. Create default location for every store.
3. Add Settings → Locations UI.
4. Enforce one default location per store.

### Acceptance criteria

- Every store has at least one location.

---

## 3.2 Inventory items and levels

### Tables

`inventory_items`:

- store
- product
- variant
- sku
- tracked

`inventory_levels`:

- inventory item
- location
- available
- reserved
- committed
- incoming

### Migration strategy

1. For each product variant, create one inventory item.
2. Create inventory level at store default location.
3. Move current `product_variants.stock` into `inventory_levels.available`.
4. Keep `product_variants.stock` temporarily as cached total, or deprecate it carefully.

### Acceptance criteria

- Product stock total equals sum of inventory levels.
- Existing product UI still works.

---

## 3.3 Inventory reservations

### Table

`inventory_reservations`:

- store
- checkout/order reference
- inventory item
- location
- quantity
- status
- expires_at

### Implementation steps

1. During checkout, reserve stock.
2. On payment/order confirmation, convert reservation to committed/deducted.
3. On failure/expiry, release reservation.

### Acceptance criteria

- Concurrent checkout cannot oversell.

---

## 3.4 Stock movement upgrade

### Expand movement types

- manual_adjustment
- import
- order_reserved
- order_committed
- order_deducted
- reservation_released
- return_restock
- transfer_out
- transfer_in
- correction

### Acceptance criteria

- Every inventory change is auditable by location.

---

# PHASE 4 — Commerce Core Completion

## Goal

Turn current orders/customers into a production commerce backbone.

---

## 4.1 Order lifecycle cleanup

### Correct separation

Order status:

- pending
- confirmed
- processing
- completed
- cancelled
- refunded

Payment status:

- pending
- authorized
- paid
- failed
- refunded
- partially_refunded

Fulfillment status:

- unfulfilled
- partial
- fulfilled
- returned

Shipment status:

- pending
- label_created
- picked_up
- in_transit
- delivered
- failed
- returned

### Implementation steps

1. Audit current status usage.
2. Remove `shipped` and `delivered` from order status UI.
3. Move shipping state to shipment/fulfillment layer.
4. Add status transition service.

### Acceptance criteria

- Order, payment, fulfillment, and shipment statuses are not mixed.

---

## 4.2 Order events

### Table

`order_events`:

- order_id
- store_id
- event_type
- title
- description
- actor_id
- data
- created_at

### Events

- order placed
- payment marked paid
- status changed
- inventory reserved
- inventory deducted
- shipment created
- tracking added
- cancelled
- refunded

### Acceptance criteria

- Order detail timeline is real and event-backed.

---

## 4.3 Dynamic order detail completion

### Requirements

Order detail page must show only real data:

- customer info;
- shipping/billing addresses;
- line items;
- variant details;
- payment status;
- fulfillment status;
- totals;
- timeline;
- notes;
- shipments if any;
- refunds/returns if any.

### Acceptance criteria

- No hardcoded placeholders except honest empty states.

---

## 4.4 Manual order and draft order

### Build

- Create draft order.
- Add customer.
- Add products/variants.
- Adjust quantities.
- Add discount/shipping/tax.
- Convert draft to confirmed order.

### Acceptance criteria

- Merchant can create phone/manual orders.

---

## 4.5 Customer CRM completion

### Add

- customer notes;
- tags;
- default address management;
- customer order history;
- lifetime spend recalculation;
- status block/unblock;
- marketing consent tracking;
- customer merge later.

### Acceptance criteria

- Customer profile is operational, not just informational.

---

# PHASE 5 — Checkout, Payments, Tax, Discounts

## Goal

Replace direct test order creation with production checkout lifecycle.

---

## 5.1 Checkout sessions

### Tables

- `checkouts`
- `checkout_items`
- `checkout_addresses`
- `checkout_events`

### Flow

1. Create checkout.
2. Add cart items.
3. Validate stock.
4. Reserve inventory.
5. Capture address.
6. Calculate shipping/tax/discounts.
7. Create payment intent.
8. Confirm order.

### Acceptance criteria

- Storefront does not directly create final paid order without lifecycle.

---

## 5.2 Payment gateway

### Build

- Stripe integration or equivalent.
- Hosted payment page/fields.
- Payment intents.
- Payment attempts.
- Captures.
- Webhook signature verification.
- Idempotency keys.

### Tables

- `payment_intents`
- `payment_attempts`
- `payment_captures`
- `refunds`

### Acceptance criteria

- Payment success/failure updates order safely.
- No raw card data touches the app.

---

## 5.3 Tax settings

### Build

- `tax_settings`
- tax regions/rates
- prices include/exclude tax
- tax snapshots on order and line items

### Acceptance criteria

- Tax is calculated and snapshotted, not hardcoded.

---

## 5.4 Coupons and discounts

### Tables

- `coupons`
- `order_coupons`

### Features

- fixed amount;
- percentage;
- minimum order;
- max discount;
- usage limits;
- customer eligibility;
- product/category eligibility.

### Acceptance criteria

- Coupons work in checkout and are snapshotted on order.

---

# PHASE 6 — Fulfillment and Shipping

## Goal

Implement roadmap Sprint 4: carriers, carrier accounts, shipping rules, shipments, async jobs.

---

## 6.1 Carriers and carrier accounts

### Tables

- `carriers`
- `carrier_accounts`

### Features

- DHL/UPS/FedEx/manual carrier entries;
- credentials encrypted;
- sandbox/production environment;
- default service;
- warehouse origin.

### Acceptance criteria

- Shipping Automation page becomes dynamic.

---

## 6.2 Shipping rules

### Table

- `shipping_rules`

### Rule examples

- if destination country = US, use UPS Ground;
- if weight > X, use DHL;
- if order total > X, free shipping;
- cheapest/fastest/balanced strategy.

### Acceptance criteria

- Merchant can configure shipping behavior without code.

---

## 6.3 Shipments

### Tables

- `shipments`
- `shipment_items`
- optional `shipment_packages`

### Features

- create shipment from order;
- assign carrier;
- generate tracking number;
- store tracking URL;
- update fulfillment status;
- show shipment timeline.

### Acceptance criteria

- Order detail shows real shipment data.

---

## 6.4 Async carrier jobs

### Jobs

- quote shipping rates;
- buy label;
- sync tracking;
- retry failed shipment;
- void label.

### Acceptance criteria

- Carrier API failures are visible and retryable.

---

# PHASE 7 — Returns, Refunds, Exchanges

## Goal

Make post-purchase operations first-class.

---

## 7.1 Returns

### Tables

- `returns`
- `return_items`
- `return_reasons`

### Flow

1. Merchant/customer requests return.
2. Merchant approves/rejects.
3. Items received.
4. Refund/restock decision.
5. Close return.

### Acceptance criteria

- Returns are managed from order detail/customer profile.

---

## 7.2 Refunds

### Tables

- `refunds`
- `refund_items`
- `refund_adjustments`

### Features

- full refund;
- partial refund;
- shipping refund;
- tax refund;
- restock yes/no;
- payment gateway refund.

### Acceptance criteria

- Refund changes payment and inventory correctly.

---

## 7.3 Exchanges

### Tables

- `exchanges`
- `exchange_items`

### Features

- exchange one variant for another;
- calculate price difference;
- collect/refund difference;
- update inventory.

### Acceptance criteria

- Common apparel exchange workflow works.

---

# PHASE 8 — B2B, Markets, Catalogs, Price Lists

## Goal

Expand from simple B2C stores to enterprise selling models.

---

## 8.1 Markets

### Tables

- `markets`
- `market_countries`
- `market_currencies`
- `market_languages`

### Features

- region-specific availability;
- currencies;
- language/locale;
- storefront behavior.

### Acceptance criteria

- Store can define different selling markets.

---

## 8.2 Catalogs and price lists

### Tables

- `catalogs`
- `catalog_products`
- `price_lists`
- `price_list_prices`

### Features

- retail catalog;
- wholesale catalog;
- market-specific catalog;
- company-specific catalog;
- volume pricing later.

### Acceptance criteria

- Same product can have different price/availability per audience.

---

## 8.3 B2B companies

### Tables

- `companies`
- `company_locations`
- `company_customers`
- `payment_terms`

### Features

- company account;
- multiple buyers;
- buyer permissions;
- assigned catalog;
- payment terms;
- draft order approval.

### Acceptance criteria

- Platform can support wholesale/B2B merchants.

---

# PHASE 9 — API Keys, Webhooks, Event Outbox, Automation

## Goal

Make the platform integration-ready and automation-friendly.

---

## 9.1 API keys

### Table

- `api_keys`

### Fields

- store_id
- name
- key hash
- secret hash if needed
- type: development/production
- scopes
- active
- last used
- expires/revoked

### Replace

Replace current developer token approach with real API keys.

### Acceptance criteria

- External storefronts use scoped API keys.

---

## 9.2 Idempotency keys

### Table

- `idempotency_keys`

### Use cases

- order creation;
- payment confirmation;
- refund creation;
- webhook/event processing.

### Acceptance criteria

- Retried POST requests do not duplicate orders/payments/refunds.

---

## 9.3 Event outbox

### Table

- `event_outbox`

### Fields

- store_id
- event_type
- aggregate_type
- aggregate_id
- payload
- status
- attempts
- available_at
- processed_at

### Events

- product.created
- product.updated
- inventory.low
- order.created
- order.paid
- shipment.created
- refund.created
- customer.created

### Acceptance criteria

- Events are persisted and processed reliably.

---

## 9.4 Webhooks

### Tables

- `webhooks`
- `webhook_deliveries`

### Features

- URL;
- secret;
- selected events;
- retry count;
- response status;
- disable after failures.

### Acceptance criteria

- Developers can integrate external systems safely.

---

## 9.5 Automation builder

### Concepts

- Trigger
- Condition
- Action

### Examples

- When stock is low → notify manager.
- When order is paid → create shipment draft.
- When customer total spend > X → tag VIP.
- When webhook fails repeatedly → notify owner.

### Acceptance criteria

- Merchant can automate workflows without code.

---

# PHASE 10 — SaaS Billing

## Goal

Charge merchants for using the SaaS.

---

## 10.1 Plans

### Table

- `plans`

### Fields

- name
- slug/code
- monthly/yearly price
- trial days
- product limit
- staff limit
- API limits
- features
- active/public

### UI

- Platform admin plan management.
- Merchant plan selection.

### Acceptance criteria

- Plans are dynamic.

---

## 10.2 Subscriptions

### Table

- `subscriptions`

### Gateway

Prefer Stripe Cashier or a clean Stripe integration.

### Features

- trial;
- active;
- past due;
- cancelled;
- cancel at period end;
- plan change.

### Acceptance criteria

- Store access can depend on subscription status.

---

## 10.3 Invoices and payment methods

### Tables

- `invoices`
- `payment_methods`

### UI

- billing page shows real invoices;
- update payment method;
- download invoice;
- change/cancel plan.

### Acceptance criteria

- Billing UI is no longer static.

---

# PHASE 11 — Notifications and Communication

## Goal

Notify merchants and customers about important events.

---

## 11.1 Notifications

### Tables

- `notifications`
- `notification_preferences`

### Channels

- in-app;
- email;
- webhook;
- later SMS/WhatsApp.

### Events

- new order;
- payment failed;
- shipment delivered;
- low stock;
- import completed;
- webhook failed;
- billing issue.

### Acceptance criteria

- Notifications page is dynamic.

---

# PHASE 12 — Platform Admin

## Goal

Make landlord/admin side operational.

---

## 12.1 Tenant management

### Features

- list stores;
- view store health;
- suspend/reactivate store;
- impersonate owner with audit log;
- see plan/subscription.

### Acceptance criteria

- Platform owner can operate tenants safely.

---

## 12.2 System operations

### Features

- failed jobs;
- import queue status;
- webhook delivery status;
- API usage;
- billing overview;
- security logs.

### Acceptance criteria

- Admin panel is not static.

---

# PHASE 13 — Performance and Scale

## Goal

Prepare for 50k+ product catalogs and large imports.

---

## 13.1 Database indexes

Audit all high-traffic queries and add indexes:

- `store_id`
- `product_id`
- `variant_id`
- `customer_id`
- `order_number`
- `status`
- `created_at`
- API lookup columns

### Acceptance criteria

- Product/order/customer pages remain fast.

---

## 13.2 Large import benchmark

Test:

- 10k rows
- 50k rows
- 100k rows

Metrics:

- memory usage;
- processing time;
- queue behavior;
- failure recovery;
- DB query count.

### Acceptance criteria

- Large supplier catalogs are realistic.

---

## 13.3 Search strategy

Options:

- database fulltext;
- Meilisearch;
- Scout;
- Algolia later.

### Acceptance criteria

- Search remains fast with large catalogs.

---

# PHASE 14 — Observability and Reliability

## Goal

Make failures diagnosable.

---

## 14.1 Correlation IDs

Add request/job correlation IDs across:

- HTTP requests;
- API requests;
- queue jobs;
- webhooks;
- payments;
- shipments.

### Acceptance criteria

- One order failure can be traced through API, job, payment, webhook.

---

## 14.2 Structured logs

Log:

- order creation;
- payment events;
- shipment jobs;
- webhook deliveries;
- imports;
- security-sensitive actions.

### Acceptance criteria

- Production issues are debuggable.

---

# PHASE 15 — Public Beta Launch Readiness

## Checklist

Before public beta:

- Full test suite green.
- `migrate:fresh --seed` green.
- No static dummy merchant-core pages.
- Store scoping tested.
- Product import tested with messy files.
- Dev storefront uses production-style API.
- Order lifecycle tested.
- Inventory reservation tested.
- Payment sandbox tested.
- Fulfillment manual workflow tested.
- Webhook delivery tested.
- SaaS billing sandbox tested.
- Security logs enabled.
- Error monitoring configured.
- Backups configured.
- Queue workers configured.
- Storage symlink configured.
- Documentation written.

---

# Recommended Immediate Next Sprint

Do **not** jump directly to carriers/shipping yet.

Do this first:

1. Fix test environment.
2. Fix stale storefront/order tests.
3. Clean migrations and seeders.
4. Implement deterministic order numbers.
5. Clean order/payment/fulfillment status separation.
6. Add `order_events`.
7. Make order detail/customer profile fully dynamic and event-backed.
8. Add idempotency to storefront order creation.
9. Add basic API key scopes/rate limiting.
10. Then start multi-location inventory.

Only after inventory locations/reservations exist should you build fulfillment/shipping.

---

# AI Coding Task Template

Use this prompt template when asking Cursor/Codex/Anti-Gravity to implement any roadmap item:

```md
I am implementing: [Roadmap Phase + Task]

Read first:
- ENTERPRISE_PROJECT_CONTEXT.md
- ENTERPRISE_ROADMAP_2026.md
- PROJECT-CONTEXT.txt
- ROADMAP.txt
- Updated ERD similar to Shopify.txt

Rules:
- Preserve current-store scoping.
- Do not weaken permissions.
- Do not create fake UI controls.
- Do not hardcode demo data in dynamic pages.
- Add migrations/models/services/tests where needed.
- Add seed data only if useful for demo/testing.
- Use merchant-friendly wording.

Inspect before coding:
- routes/web.php
- routes/api.php
- relevant controller
- relevant model
- relevant migration
- relevant Blade view
- existing tests

Return:
1. files inspected
2. root cause/current gap
3. implementation plan
4. files changed
5. tests added/updated
6. commands run
7. what remains deferred
8. final sign-off status
```
