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



# PHASE 3.5 — Store Settings Alignment: Locations, Markets, Currency, and Timezone

## Goal

Clarify and align store onboarding/settings with the new Phase 3 inventory model before moving deeper into commerce, fulfillment, and markets.

This is not a full Markets implementation.

This phase exists to prevent confusion between:

- inventory locations
- selling markets
- store currency
- market currencies
- store timezone
- future location/market timezones

## Why this phase matters

Phase 3 introduced Locations for inventory and fulfillment origin.

But onboarding already collects store-level settings such as:

- primary market
- store currency
- timezone
- country/address

These fields are defaults. They are not enough for a full international selling system.

Without clarification, the UI may accidentally make merchants think Locations control markets, currencies, or timezones. That is wrong.

## Core definitions

### Location

A physical or operational place where stock exists.

Examples:

- warehouse
- shop
- stock room
- restaurant branch
- third-party storage

Used for:

- inventory levels
- reservations
- stock movements
- future fulfillment origin
- future courier pickup

### Market

A selling region/customer-facing commercial context.

Examples:

- USA
- Middle East
- Asia
- EU
- wholesale
- retail

Used later for:

- countries
- currencies
- language/locale
- product availability
- catalogs
- price lists
- tax behavior
- storefront behavior

### Currency

Store currency is the default/base currency.

Market currency will later define what shoppers see/pay in each market.

### Timezone

Store timezone is the default dashboard/reporting timezone.

Future location timezone may be needed for warehouse cutoff times and courier pickup operations.

Future market/customer timezone may be needed for localized storefront and delivery promises.

## Implementation tasks

### 3.5.1 Onboarding/default location alignment

Verify store creation flow:

1. Store onboarding saves:
   - primary market
   - currency
   - timezone
   - store address/country if available
2. Default inventory location is created for the store.
3. Default location uses store address/country/city where available.
4. If address is incomplete, default location is created as `Main location`.
5. Merchant can edit the default location later from Settings → Locations.

### 3.5.2 Locations UI clarification

Update Settings → Locations copy.

Use this merchant-facing explanation:


Locations are places where you store or fulfill inventory, such as a warehouse, shop, stock room, restaurant branch, or third-party storage.

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

# PHASE 5 — Checkout, Payments, Tax, Discounts, and Channel Payment Modes

## Goal

Replace direct test order creation with a production checkout lifecycle while supporting multiple merchant payment models.

The platform must not force every merchant into one payment gateway.

Merchants should be able to use one of these modes:

1. **External checkout sync**
   - Merchant already collects payment on Shopify, WooCommerce, WordPress, custom website, PayPal, COD, bank transfer, or another external checkout.
   - Our platform receives the paid/pending order and manages catalog, customers, orders, inventory, fulfillment, and reporting.
   - Our platform does not process the customer payment in this mode.

2. **Platform checkout**
   - Merchant uses our checkout lifecycle.
   - Our platform creates checkout sessions, validates/reserves stock, calculates discounts/tax, creates payment intents, receives webhook confirmation, and converts checkout into an order.

3. **Merchant-connected payments**
   - Merchant connects their own Stripe account through Stripe Connect later.
   - Our platform creates payments for that connected merchant account.
   - Platform fees/payout management can be added later.

Phase 5 must build the neutral foundation first, with Stripe sandbox as the first real payment provider and external checkout sync as the first channel-friendly mode.

Do not hardcode the whole payment system around Stripe only.

---

## 5.0 Payment and Channel Mode Foundation

### Purpose

Create a flexible architecture that supports external checkouts, platform checkout, and future connected merchant accounts without rewriting orders later.

### Concepts

A store can support one or more payment/channel modes:

- `external_checkout`
- `platform_checkout`
- `stripe_platform`
- `stripe_connect`
- `manual`
- future: `paypal`, `square`, `authorize_net`, `shopify`, `woocommerce`

### Tables

Create or update:

- `payment_provider_accounts`
- `payment_intents`
- `payment_attempts`
- `payment_captures`
- `refunds`
- `idempotency_keys` if not already present
- optional: `store_payment_settings`

### `payment_provider_accounts`

Fields:

- `id`
- `store_id`
- `provider`  
  Examples: `stripe`, `external`, `manual`, `paypal`, `shopify`, `woocommerce`
- `mode`  
  Examples: `test`, `live`
- `connection_type`  
  Examples: `platform`, `connect`, `external_reference`, `manual`
- `display_name`
- `status`  
  Examples: `active`, `inactive`, `pending`, `restricted`, `revoked`
- `is_default`
- `provider_account_id` nullable  
  Example: Stripe connected account ID
- `credentials_encrypted` nullable
- `capabilities` json nullable
- `settings` json nullable
- `last_verified_at` nullable
- `created_by`
- timestamps
- soft deletes if consistent with project style

### Rules

- Do not store raw card data.
- Do not ask merchants to paste Stripe secret keys for production connected payments.
- Use Stripe Connect later for merchant-owned Stripe accounts.
- Credentials must be encrypted if credentials are ever stored.
- All provider accounts must be store-scoped.
- One store may have multiple provider accounts.
- One provider account may be marked default for platform checkout.

### Services

Create:

- `PaymentProviderInterface`
- `PaymentProviderManager`
- `StripePlatformPaymentProvider`
- `ExternalPaymentProvider`
- `ManualPaymentProvider`
- future: `StripeConnectPaymentProvider`

### Internal payment result

All providers must return a normalized internal result:

- `provider`
- `provider_account_id`
- `provider_intent_id`
- `provider_charge_id`
- `status`
- `amount`
- `currency`
- `failure_code`
- `failure_message`
- `raw_response` stored safely in metadata where appropriate

### Acceptance criteria

- Payment logic is provider-neutral.
- Stripe is the first implementation, not the only architecture.
- External payment references can be recorded without processing payment.
- Store scoping is enforced.
- Tests prove Store A cannot use Store B payment provider account.

---

## 5.1 Checkout Sessions

### Purpose

Create a production checkout lifecycle so storefronts do not directly create final paid orders.

### Tables

- `checkouts`
- `checkout_items`
- `checkout_addresses`
- `checkout_events`

### `checkouts`

Suggested fields:

- `id`
- `store_id`
- `customer_id` nullable
- `checkout_number`
- `source_channel`
  - `dev_storefront`
  - `api`
  - `external_storefront`
  - `manual`
  - future: `shopify`, `woocommerce`
- `mode`
  - `external_checkout`
  - `platform_checkout`
- `status`
  - `open`
  - `address_pending`
  - `payment_pending`
  - `paid`
  - `confirmed`
  - `expired`
  - `cancelled`
  - `failed`
- `currency_code`
- `subtotal`
- `discount_total`
- `shipping_total`
- `tax_total`
- `grand_total`
- `payment_provider`
- `payment_provider_account_id` nullable
- `external_checkout_reference` nullable
- `external_order_reference` nullable
- `metadata` json nullable
- timestamps
- `expires_at`
- `completed_at`
- `converted_order_id` nullable

### Flow

1. Create checkout.
2. Add cart items.
3. Validate stock.
4. Reserve inventory where available.
5. Capture address.
6. Calculate discounts/tax/shipping placeholders.
7. Create payment intent if platform checkout.
8. Receive payment success/failure.
9. Convert checkout into confirmed order.
10. Release reservation on cancellation/expiry/failure.

### Acceptance criteria

- Storefront does not directly create final paid order for platform checkout.
- Checkout can safely convert to order once.
- Checkout conversion snapshots customer, address, item, tax, discount, and payment data.
- Checkout events show real lifecycle activity.
- Idempotency prevents duplicate checkout/order creation.

---

## 5.2 External Checkout Sync

### Purpose

Support merchants who already collect payment on Shopify, WooCommerce, WordPress, custom websites, PayPal, COD, bank transfer, or another external checkout.

In this mode, the external storefront is the payment source of truth.

Our platform records the order and payment reference but does not process the payment.

### API behavior

Create or update endpoint:

- `POST /api/v1/external/orders`
- or version current dev storefront order endpoint safely

Payload should support:

- `external_order_number`
- `external_checkout_reference`
- `payment_status`
- `payment_gateway`
- `payment_reference`
- `payment_method`
- `customer`
- `shipping_address`
- `billing_address`
- `items`
- `discounts`
- `taxes`
- `shipping`
- `totals`
- `placed_at`

### Rules

- External order payload must be authenticated.
- Store scoping is mandatory.
- External order number should be unique per store/source.
- Payment status must map into internal payment statuses:
  - `pending`
  - `authorized`
  - `paid`
  - `failed`
  - `refunded`
  - `partially_refunded`
- Do not create Stripe PaymentIntent for external-paid orders.
- Do not collect raw card data.
- Create order events:
  - `external_order.received`
  - `payment.status_recorded`
  - `inventory.deducted` or `inventory.reserved` depending inventory mode
- Create security/audit log where appropriate.

### Dev storefront simulator

Update `dev-test-storefront` to support two modes:

1. `External paid order`
   - Simulates Shopify/WooCommerce/custom website checkout.
   - Sends `payment_status=paid`, `payment_gateway=external_test`, and `payment_reference`.

2. `Platform checkout`
   - Starts your checkout session flow and Stripe sandbox payment when implemented.

### Acceptance criteria

- Merchants can connect existing storefronts without using our payment gateway.
- External paid orders appear in Orders dashboard.
- Customers, addresses, order items, payment status, and order events are created.
- Duplicate external order numbers are rejected or idempotently returned.
- Tests prove external checkout mode does not create Stripe payment intents.

---

## 5.3 Platform Checkout with Stripe Sandbox

### Purpose

Implement the first real payment provider for platform checkout using Stripe test mode.

### Provider

- `StripePlatformPaymentProvider`

### Build

- Stripe SDK integration
- PaymentIntent creation
- Hosted payment page or Stripe Elements/Payment Element later
- Payment attempt records
- Webhook signature verification
- Payment success/failure handling
- Checkout-to-order conversion after confirmed payment

### Environment

Add to `.env.example`:
PAYMENTS_DEFAULT_PROVIDER=stripe
STRIPE_MODE=test
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

---

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

# PHASE 6 — Fulfillment, Shipping, Delivery Methods, and Carrier Foundation

## Goal

Build the fulfillment and shipping foundation after checkout/payment is complete.

Phase 6 must answer:

> How does the store owner deliver confirmed orders to the customer?

The platform must support simple local stores and future global stores with multiple locations, multiple shipping zones, multiple carriers, and different delivery methods.

Do not start with courier automation or live DHL/UPS/FedEx APIs.

Start with manual fulfillment and clean shipping settings.

---

## Business Scenarios Phase 6 Must Support

### Scenario 1 — One store, one location

A simple store owner has one shop or warehouse and ships orders manually or with one local courier.

The system should be easy and not require advanced global setup.

### Scenario 2 — One store, multiple locations

A global store owner may have multiple warehouses/shops.

Examples:

- New York warehouse;
- Dubai warehouse;
- London warehouse;
- Karachi warehouse.

The platform must allow shipments to originate from the correct fulfillment location.

Future routing can select the best location based on stock, destination, carrier availability, and delivery strategy.

### Scenario 3 — Multiple separate stores

A user may own multiple stores.

Each store has separate:

- locations;
- shipping zones;
- carrier accounts;
- delivery methods;
- shipping rules;
- shipments.

No cross-store leakage is allowed.

### Scenario 4 — Regional carrier availability

A store may use:

- DHL for international express;
- UPS for USA orders;
- FedEx for express delivery;
- local courier for city delivery;
- store pickup for shop locations.

The platform must support multiple carrier services and only offer services that are valid for the customer destination.

---

## Phase 6 Concepts

### Location

Where stock exists or orders ship from.

Examples:

- warehouse;
- shop;
- stock room;
- restaurant branch;
- third-party fulfillment warehouse.

### Shipping Zone

Where the store delivers.

Examples:

- United States;
- Canada;
- Europe;
- Middle East;
- South Asia;
- local city zone;
- international zone.

### Carrier / Courier

Who delivers the package.

Examples:

- DHL;
- UPS;
- FedEx;
- USPS;
- local courier;
- manual delivery;
- store pickup.

### Carrier Service

A delivery service under a carrier.

Examples:

- DHL Express;
- UPS Ground;
- FedEx 2Day;
- Local same-day delivery;
- Store pickup.

### Delivery Method

What the customer sees at checkout.

Examples:

- Economy delivery;
- Standard delivery;
- Express delivery;
- Local delivery;
- Store pickup.

The customer should not see internal routing strategy names like:

- cheapest reliable;
- balanced optimizer;
- carrier automation score.

Those are internal store-owner/admin concepts for future automation.

---

## 6A — Manual Fulfillment Foundation

### Purpose

Create the real fulfillment foundation before live carrier automation.

### Tables

Create:

- `carriers`
- `carrier_accounts`
- `shipping_zones`
- `shipping_methods`
- `shipments`
- `shipment_items`

Optional later:

- `shipment_events`
- `shipment_packages`

### `carriers`

Purpose:

Global or system-defined carrier catalog.

Fields:

- `id`
- `name`
- `code`
- `type`
  - manual
  - courier
  - pickup
  - local_delivery
  - third_party
- `website_url` nullable
- `tracking_url_template` nullable
- `is_system` boolean
- `is_active` boolean
- timestamps

Seed basic carriers:

- Manual delivery
- Store pickup
- DHL
- UPS
- FedEx
- USPS
- Local courier

Live carrier APIs are not required yet.

### `carrier_accounts`

Purpose:

Store-scoped carrier setup.

Fields:

- `id`
- `store_id`
- `carrier_id`
- `display_name`
- `connection_type`
  - manual
  - api
  - external
- `status`
  - setup_required
  - enabled
  - disabled
  - internal_only
- `credentials_encrypted` nullable
- `settings` json nullable
- `supported_countries` json nullable
- `enabled_for_checkout` boolean
- `created_by`
- timestamps
- soft deletes if consistent

Rules:

- Store-scoped.
- Store A cannot use Store B carrier account.
- Do not store real courier API credentials unless encrypted.
- API carrier integrations are deferred.

### `shipping_zones`

Purpose:

Define where the store delivers.

Fields:

- `id`
- `store_id`
- `name`
- `countries` json nullable
- `regions` json nullable
- `postal_patterns` json nullable
- `is_active` boolean
- `sort_order`
- timestamps

Examples:

- United States
- Local delivery area
- Europe
- International

### `shipping_methods`

Purpose:

Define customer-facing delivery options.

Fields:

- `id`
- `store_id`
- `shipping_zone_id`
- `carrier_account_id` nullable
- `name`
- `code`
- `description` nullable
- `delivery_speed_label` nullable
  - Economy
  - Standard
  - Express
  - Pickup
- `rate_type`
  - flat
  - free
  - manual
  - carrier_calculated_later
- `flat_rate`
- `free_over_amount` nullable
- `min_order_amount` nullable
- `max_order_amount` nullable
- `estimated_min_days` nullable
- `estimated_max_days` nullable
- `enabled_for_checkout` boolean
- `is_active` boolean
- `sort_order`
- timestamps

Rules:

- Customer sees shipping method name, price, and estimated delivery.
- Do not show internal courier routing strategy to customer.
- If no method matches destination, checkout should return a clear no-method-available message.

### `shipments`

Purpose:

Represent actual fulfillment of an order.

Fields:

- `id`
- `store_id`
- `order_id`
- `shipment_number`
- `origin_location_id` nullable
- `carrier_account_id` nullable
- `shipping_method_id` nullable
- `status`
  - pending
  - label_created
  - shipped
  - in_transit
  - delivered
  - failed
  - returned
  - cancelled
- `tracking_number` nullable
- `tracking_url` nullable
- `carrier_service` nullable
- `package_count` default 1
- `package_weight` nullable
- `shipping_cost` nullable
- `label_url` nullable
- `shipped_at` nullable
- `delivered_at` nullable
- `shipped_by` nullable
- `metadata` json nullable
- timestamps
- soft deletes if consistent

Rules:

- No fake label generation.
- Tracking number can be manually entered.
- Tracking URL can be generated from carrier template or manually entered.
- Shipment status is separate from order status.

### `shipment_items`

Purpose:

Track which order items and quantities are included in a shipment.

Fields:

- `id`
- `store_id`
- `shipment_id`
- `order_item_id`
- `quantity`
- timestamps

Rules:

- Quantity must not exceed remaining unshipped quantity.
- Supports partial shipments.
- Supports split shipments across locations/carriers later.

---

## 6A Flow

### Create shipment from order

From order detail, store owner can:

1. Open Fulfillment panel.
2. Select items and quantities to ship.
3. Select origin location.
4. Select carrier account or manual delivery.
5. Enter tracking number/tracking URL if available.
6. Create shipment.

### Shipment actions

Allowed manual actions:

- create shipment;
- add/update tracking number;
- mark as shipped;
- mark as delivered;
- mark as failed;
- cancel pending shipment if safe.

Do not add:

- buy label;
- schedule pickup;
- live tracking sync;
- carrier API purchase;
- refund/return controls.

Those are future phases.

### Fulfillment status calculation

Order fulfillment status should be calculated from shipment items:

- no shipped items: `unfulfilled`
- some quantity shipped: `partial`
- all quantity shipped: `fulfilled`

Returned status belongs to returns phase.

Do not set order status to `shipped`.

### Order events

Create order events for:

- `shipment.created`
- `shipment.tracking_added`
- `shipment.status_changed`
- `fulfillment.status_changed`

Timeline should show real shipment activity.

---

## 6A UI Requirements

### Shipping & Delivery settings

Replace static Shipping Automation preview with real setup areas:

1. Shipping zones
   - Where does this store deliver?

2. Delivery methods
   - What choices can customers select at checkout?

3. Carriers & accounts
   - Which courier services can this store use?

4. Fulfillment locations
   - Where do orders ship from?

5. Automation
   - Coming later after manual fulfillment is stable.

Do not show fake save/export/toggle controls.

### Order detail Fulfillment panel

Add a real fulfillment panel to order detail:

- fulfillment status;
- shipment list;
- remaining items to ship;
- create shipment action;
- tracking number;
- carrier;
- shipment status;
- shipped/delivered timestamps.

Empty state:

> No shipments have been created yet. Create a shipment when this order is ready to fulfill.

---

## 6A Tests

Add tests for:

1. owner can create carrier account;
2. staff cannot manage carrier accounts;
3. shipping zones are store-scoped;
4. shipping methods are store-scoped;
5. same carrier can be used by multiple stores with separate accounts;
6. Store A cannot use Store B carrier account;
7. shipment can be created from order;
8. shipment items cannot exceed ordered quantity;
9. partial shipment sets fulfillment status to partial;
10. full shipment sets fulfillment status to fulfilled;
11. tracking number can be added;
12. shipment can be marked shipped;
13. shipment can be marked delivered;
14. order events are created;
15. order detail shows fulfillment panel;
16. no fake label/pickup buttons are shown;
17. `migrate:fresh --seed` passes;
18. full suite passes.

---

## 6B — Shipping Settings and Checkout Delivery Methods

### Purpose

Allow checkout to offer customer-facing delivery methods.

Build after 6A.

### Features

- destination country/region matching;
- active shipping zones;
- active shipping methods;
- flat rate shipping;
- free shipping threshold;
- delivery estimates;
- checkout shipping selection;
- shipping snapshots on checkout and order.

### Customer checkout display

Customer sees:

- Economy delivery;
- Standard delivery;
- Express delivery;
- Local delivery;
- Store pickup.

Each option should show:

- name;
- price;
- estimated delivery;
- short description.

### Rules

- Hide unsupported delivery methods.
- Hide methods that do not match destination.
- If no method is available, return clear checkout error.
- Do not show internal carrier automation strategy to customer.

### Tests

Add tests for:

1. checkout returns delivery methods for destination;
2. unsupported country has no methods;
3. free shipping threshold works;
4. flat rate shipping is added to checkout totals;
5. selected shipping method is snapshotted on order;
6. external checkout sync can still pass external shipping values;
7. platform checkout still works.

---

## 6C — Carrier API Integrations and Automation

### Purpose

Add real courier integrations after manual fulfillment and shipping methods are stable.

Do not start 6C until 6A and 6B are complete.

### Future features

- DHL sandbox;
- UPS sandbox;
- FedEx sandbox;
- live rate quotes;
- label purchase;
- tracking sync;
- pickup scheduling;
- carrier API retry jobs;
- shipment background jobs;
- routing preference:
  - cheapest available;
  - fastest available;
  - balanced;
  - manual selection;
- automation insights.

### Rules

- No fake live carrier buttons.
- API credentials must be encrypted.
- Carrier failures must not corrupt shipment/order state.
- Shipment jobs must be retryable.
- Every carrier action should create a shipment/order event.

---

## Phase 6 Acceptance Criteria

Phase 6 is complete only if:

1. Manual fulfillment works.
2. Shipments and shipment items exist.
3. Fulfillment status is separate from order status.
4. Partial fulfillment works.
5. Split-shipment-ready schema exists.
6. Carrier accounts are store-scoped.
7. Shipping zones are store-scoped.
8. Shipping methods are customer-friendly.
9. Unsupported regions do not show unavailable courier services.
10. Order detail shows real fulfillment/shipment data.
11. Static shipping automation preview is removed or converted into real setup UI.
12. No fake label/carrier automation buttons exist.
13. Store scoping is tested.
14. Staff permissions are tested.
15. `migrate:fresh --seed` passes.
16. Full suite passes.
17. Carrier API integrations remain deferred until foundation is stable.

---

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

---

# Phase 3.5 Roadmap Addendum - Store Settings Alignment

This addendum completes the Phase 3.5 scope above and keeps the work bounded before Phase 4.

## 3.5.1 Onboarding/default location alignment

- Store onboarding saves primary market, default store currency, default store timezone, and store address where available.
- Every store must have one active default inventory location.
- Missing default locations are created as `Main location`.
- Blank default-location address fields may be filled from store defaults where those fields exist.
- Merchant-edited default location fields must not be overwritten.
- The alignment must be idempotent and must not create duplicate default locations on retry.

## 3.5.2 Locations UI clarification

Settings -> Locations must explain that locations are places where stock is stored or fulfilled from.

Locations are used for:

- inventory levels
- reservations
- stock movements
- future fulfillment origin

Locations do not control:

- customer markets
- selling currencies
- language
- regional pricing
- storefront availability

Only real actions should be visible: add location, edit location, make default, and activate/deactivate. Do not show fake controls for courier pickup, carrier setup, fulfillment routing, stock transfer, market assignment, or currency assignment.

## 3.5.3 Store settings copy clarification

Use these labels and helpers:

- `Primary market`: This is your default selling region. Full multi-market selling, regional currencies, and price lists will be added later.
- `Default store currency`: This is your store's base currency for dashboard totals and default pricing. Market-specific currencies will be added later.
- `Default store timezone`: This timezone is used for dashboard dates, reports, and store operations. Location-specific cutoff times can be added later when fulfillment is enabled.

## 3.5.4 Documentation

Document the distinction between Locations, Markets, Currency, and Timezone in:

- `ENTERPRISE_PROJECT_CONTEXT.md`
- `ENTERPRISE_ROADMAP_2026.md`
- `docs/PHASE_3_ENTERPRISE_INVENTORY_REPORT.md`

## 3.5.5 Future guardrails

Future Markets and fulfillment work belongs to later phases:

- `markets`
- `market_countries`
- `market_currencies`
- `market_languages`
- `catalogs`
- `catalog_products`
- `price_lists`
- `price_list_prices`
- regional availability/tax/shipping
- location timezone/cutoff rules
- fulfillment routing by region

Do not add `locations.timezone` until fulfillment cutoff times, carrier pickup windows, or multi-region warehouse operations require it.
