# ENTERPRISE PROJECT CONTEXT — SaaS E-Commerce Platform Brain

> **Purpose:** This file is the long-term project brain for AI coding agents and developers. Put it in the project root so Cursor/Codex/Anti-Gravity can understand what the platform is, what must never be broken, and how every feature should be implemented.

---

## 1. Product Identity

This project is a **multi-tenant SaaS e-commerce platform** built with Laravel + Blade, with a developer test storefront for headless/API commerce.

The goal is not to build a small webshop.

The goal is to build a platform that gives merchants:

- Amazon-level operational power;
- Shopify-level usability;
- WooCommerce-level flexibility;
- easier catalog, variant, import, inventory, order, and fulfillment management than existing tools.

The product must support many store types:

- physical products;
- grocery;
- electronics;
- clothing/apparel;
- restaurant/menu products;
- services;
- subscriptions;
- virtual products;
- digital products/courses/downloads;
- future B2B/wholesale flows.

---

## 2. Core Philosophy

Every feature must answer this question:

> **Does this make merchant life easier?**

If the feature creates confusion, hidden complexity, fake UI, or technical language, it is wrong.

### Platform principles

1. Simplify complexity.
2. Reduce merchant effort.
3. Make imports understandable.
4. Make data visible and actionable.
5. Make variants easy.
6. Make inventory accurate.
7. Make orders auditable.
8. Make integrations reliable.
9. Make every action store-safe.
10. Build with future enterprise expansion in mind.

---

## 3. Current Technical Stack

- Backend: Laravel
- Frontend: Blade templates + JavaScript
- Storefront test app: React/Vite under `dev-test-storefront`
- Database: MySQL-style schema
- Queue system: Laravel jobs available
- Testing: PHPUnit/Pest style Laravel feature/unit tests

---

## 4. Current Project Structure Highlights

Important folders/files:

```txt
app/Models
app/Http/Controllers
app/Http/Controllers/Api
app/Http/Middleware
app/Services
app/Support
database/migrations
database/seeders
resources/views/user_view
resources/views/user_view/product_import
resources/views/user_view/partials
routes/web.php
routes/api.php
dev-test-storefront/src/App.jsx
tests/Feature
tests/Unit
```

Important existing controllers:

- `DashboardController`
- `OnboardingController`
- `ProductWorkspaceController`
- `ProductImportController`
- `ProductBulkController`
- `DeveloperStorefrontSettingsController`
- `Api/DeveloperStorefrontCatalogController`
- `TeamMemberController`
- `CurrentStoreController`
- `BrandController`
- `CategoryController`
- `TagController`

Important existing models:

- `Store`
- `User`
- `Role`
- `Product`
- `ProductVariant`
- `ProductImage`
- `ProductVariationType`
- `ProductVariationOption`
- `Category`
- `Brand`
- `Tag`
- `ProductImport`
- `ProductImportRow`
- `StockMovement`
- `Customer`
- `CustomerAddress`
- `Order`
- `OrderItem`
- `OrderAddress`

---

## 5. Current Implemented Reality

### Strong areas

- Store onboarding
- Current store middleware
- Store-user pivot
- Basic store roles
- Product catalog foundation
- Product workspace
- Product edit workspace
- Normalized product images
- Variants and options
- Variant images
- Stock movements
- Product import system
- Variant import
- Custom fields / additional details
- `import_extra` preservation
- Product list UX polish
- Basic developer storefront API
- Basic dynamic customers/orders

### Partial areas

- RBAC/permissions
- Commerce Core
- Customers CRM
- Orders detail/timeline
- Inventory accuracy beyond one stock value
- Dev storefront checkout
- Storefront API security
- Dashboard analytics
- Admin platform

### Missing areas

- Multi-location inventory
- Inventory reservations
- Fulfillment/shipping
- Payments/refunds
- Tax/coupons
- Returns/exchanges
- B2B/markets/catalogs/price lists
- API keys/scopes
- Webhooks/outbox/idempotency
- Notifications
- Security logs/sessions
- SaaS billing
- Observability
- Platform admin operations

---

## 6. Non-Negotiable Architecture Rules

### 6.1 Store scoping is mandatory

Every tenant-owned entity must be scoped by store.

Examples:

- products
- variants
- categories
- brands
- tags
- product images
- imports
- stock movements
- customers
- orders
- addresses
- shipments
- API keys
- webhooks
- billing records
- security logs

Do not query tenant data without current store checks.

Bad:

```php
Product::find($id);
```

Good:

```php
$currentStore->products()->whereKey($id)->firstOrFail();
```

or:

```php
Product::where('store_id', $currentStore->id)->findOrFail($id);
```

### 6.2 Never trust IDs from the request

If a request submits:

- product_id
- variant_id
- image_id
- category_id
- order_id
- customer_id

always verify the record belongs to the current store.

### 6.3 No cross-store leakage

Tests must cover:

- Store A cannot access Store B product/order/customer.
- Store A cannot assign Store B image/category/variant.
- Staff cannot mutate restricted data.

### 6.4 No fake UI

Do not show buttons or controls unless they work.

If a feature is deferred:

- hide the control, or
- show clear disabled/coming-later copy only if needed.

### 6.5 No technical jargon in merchant UI

Avoid:

- meta
- JSON
- payload
- schema
- pivot
- raw object
- foreign key

Use:

- Additional details
- Imported data
- Store fields
- Product options
- Variants
- Inventory rows
- Catalog category
- Automation

### 6.6 Do not store image binaries in DB

Use `product_images` with paths/metadata.

### 6.7 Do not bypass stock movements

Any stock change must create an auditable stock movement.

### 6.8 Do not hardcode demo data in dynamic pages

Static UI is acceptable only for unreleased admin mockups.
Merchant-core pages must use real models/queries.

---

## 7. Data Model Philosophy

The platform uses a hybrid model:

| Data kind | Storage |
|---|---|
| Core product fields | product columns |
| Variants | product_variants |
| Images | product_images |
| Merchant-chosen extra fields | products.meta.custom_fields |
| Variant extra fields | product_variants.meta.custom_fields |
| Preserved unmapped supplier data | products.meta.import_extra |
| Stock audit | stock_movements |
| Customer/order snapshots | orders, order_items, order_addresses |

### Important distinction

**Additional details**

- editable extra product information;
- chosen by merchant;
- examples: supplier, material, origin, ingredients, care note, warranty, internal reference.

**Advanced imported data**

- read-only preserved spreadsheet columns;
- not mapped during import;
- can be promoted to Additional details;
- can be used to recover catalog categories.

---

## 8. Catalog Rules

### 8.1 Product types

Product types must affect behavior:

- Physical: inventory + shipping
- Digital: no shipping, digital delivery later
- Service: booking/resource flow later
- Subscription: recurring billing later
- Virtual: no shipping

### 8.2 Categories vs product type

Never confuse product type with category.

- Category = merchant taxonomy, e.g. Shoes, Electronics, Drinks.
- Product type = behavior, e.g. physical, digital, service.

### 8.3 Variants

Use consistent language:

- Option groups = choices shoppers pick, e.g. Color, Size.
- Options = values, e.g. Red, Blue, Small, Large.
- Variants = sellable combinations, e.g. Red / Small.

Each variant should consistently expose:

- image;
- option combination;
- SKU;
- price;
- compare-at price;
- stock;
- stock alert;
- additional variant details.

### 8.4 Simple products

Simple product:

- one default variant;
- stock belongs to default variant;
- do not force merchant through variant complexity.

### 8.5 Variant products

Variant product:

- stock belongs to each variant;
- total stock = sum of variant stock;
- base stock must not be a second source of truth.

---

## 9. Product Import Rules

Import is a core differentiator.

It must support:

- CSV/XLSX;
- messy supplier data;
- column mapping;
- validation preview;
- custom fields;
- `import_extra` preservation;
- variant grouping;
- variant custom fields;
- variant images;
- taxonomy creation;
- stock movement recording;
- row-level debugging;
- retry failed rows;
- large catalogs.

### Import design rules

1. Never lose unmapped columns silently.
2. Preserve unknown data in `import_extra`.
3. Let merchant later promote/recover useful fields.
4. Do not create dynamic DB columns.
5. Store custom mapped fields in `meta.custom_fields`.
6. Validate duplicate variant combinations.
7. Keep import UI merchant-friendly.

---

## 10. Inventory Rules

### Current state

Stock currently lives on `product_variants.stock`, with stock movements.

### Enterprise target

Move to:

- locations;
- inventory items;
- inventory levels;
- reservations;
- stock movements by location.

### Rules

- Never silently duplicate stock.
- Variant product total = sum of variant rows.
- Multi-location total = sum of inventory levels.
- Checkout must reserve stock before final deduction.
- Returns/refunds must affect stock through explicit rules.
---

## 10.1 Locations vs Markets vs Currency vs Timezone

Do not confuse these concepts.

### Location

A location is a physical or operational place where inventory exists or can be fulfilled from.

Examples:

- warehouse
- physical shop
- stock room
- restaurant branch
- third-party storage
- fulfillment partner warehouse

Locations are part of the inventory and fulfillment foundation.

A location answers:

> Where is the stock?

Locations are used for:

- inventory levels
- stock availability
- reservations
- stock movements
- future fulfillment origin
- future courier pickup origin
- future warehouse routing

A location is **not** a selling market.
A location is **not** a currency setting.
A location is **not** the same as a store timezone.
A location should not manage regional pricing or storefront availability.

### Market

A market is a selling region or customer-facing commercial context.

Examples:

- USA market
- Middle East market
- Asia market
- EU market
- wholesale market
- retail market

A market answers:

> Where and how do we sell?

Markets will later control:

- countries/regions served
- selling currency
- language/locale
- regional product availability
- regional pricing
- market-specific catalogs
- tax behavior
- shipping rules
- storefront behavior

A market can be served by one location or many locations.
One location can serve one market or many markets.

Never enforce this incorrect rule:

1 market = 1 location

 Store
  -> Markets define selling rules
  -> Locations define where stock exists
  -> Fulfillment routing later connects markets/orders to locations

### Currency

Store currency is the default/base currency for dashboard totals, current catalog pricing, and order totals until Markets introduce regional pricing.

Currency answers:

> What is the store's default money unit?

Store currency is not the same as a location. A warehouse or shop should not control selling currency.

Future Markets work will add:

- market-specific currencies
- exchange-rate strategy
- price lists
- regional catalog prices
- shopper-facing currency display

### Timezone

Store timezone is the default timezone for dashboard dates, reports, order activity, and store operations.

Timezone answers:

> What timezone should the merchant dashboard use by default?

Store timezone is not the same as an inventory location. Do not add `locations.timezone` until fulfillment cutoff times, carrier pickup windows, or multi-region warehouse operations need it.

Future fulfillment and Markets work may add:

- location cutoff times
- courier pickup windows
- region-specific delivery promises
- customer-facing localized storefront dates


## 11. Order Rules

### Orders must be auditable snapshots

Do not rely on live product/customer data for historical order display.

Snapshot:

- product name;
- SKU;
- variant details;
- product image;
- unit price;
- tax;
- discount;
- shipping;
- billing/shipping address;
- customer email/phone.

### Separate statuses

Do not mix these:

- order status;
- payment status;
- fulfillment status;
- shipment status.

### Order events

Every important order change should create an event:

- order placed;
- payment status changed;
- fulfillment status changed;
- shipment created;
- refunded;
- cancelled;
- note added.

---

## 12. Storefront/API Rules

The dev storefront is a testbed, not the final production API.
### Developer Storefront Payment Clarification

`dev-test-storefront` is a local simulator used to test how an external website can connect to this SaaS platform.

It is not the final storefront builder.

It should eventually support two testing modes:

1. **External paid order mode**
   - Simulates Shopify, WooCommerce, WordPress, custom websites, PayPal, cash on delivery, bank transfer, or another existing checkout.
   - Payment is collected outside this SaaS.
   - The external site sends the order, customer, address, payment status, payment gateway, and payment reference into our SaaS.

2. **Platform checkout mode**
   - Simulates a storefront using our checkout lifecycle.
   - Our SaaS creates checkout sessions, validates/reserves stock, calculates tax/discounts, creates payment intents, receives webhook confirmation, and converts checkout into an order.

Do not overbuild `dev-test-storefront` as the final storefront product.

Production API keys, scopes, rate limits, webhook delivery, event outbox, and full external integration management belong to the later integration roadmap.

Production API must have:

- versioning: `/api/v1/...`;
- API keys;
- scopes;
- rate limits;
- idempotency keys;
- stable response shapes;
- clear error responses;
- webhook/event support.

### Storefront order creation must eventually flow through checkout

Do not directly create final paid orders forever.

Final target:

1. create checkout;
2. reserve inventory;
3. calculate shipping/tax/discount;
4. authorize/capture payment;
5. create order;
6. emit order event;
7. notify merchant/customer.

---

## 13. Payments and Checkout Strategy

This platform must support multiple merchant payment models.

The SaaS is not limited to one payment gateway.

The platform supports three payment/channel modes:

### 13.1 External checkout sync

Use this mode for merchants who already accept payments through:

- Shopify;
- WooCommerce;
- WordPress;
- custom websites;
- PayPal;
- bank transfer;
- cash on delivery;
- any other existing checkout or gateway.

In this mode:

- the external storefront collects payment;
- our SaaS receives the order through API/integration;
- our SaaS stores the order, customer, address, items, payment status, payment gateway, and payment reference;
- our SaaS does not process the payment again;
- no Stripe PaymentIntent is created for externally paid orders.

This is the closest match to the current `dev-test-storefront` concept.

External orders should store:

- `external_order_id` or `external_order_number` (at least one required on create);
- `external_checkout_reference`;
- `payment_status`;
- `payment_gateway`;
- `payment_reference`;
- `payment_method`;
- customer snapshot;
- address snapshots;
- item snapshots;
- tax/discount/shipping/totals snapshots.

### 13.2 Platform checkout

Use this mode for merchants who want this SaaS platform to manage checkout.

In this mode:

- our SaaS creates checkout sessions;
- validates/reserves inventory;
- captures address;
- applies discounts/tax;
- creates payment intents;
- receives payment webhook confirmation;
- converts checkout into a confirmed order.

Stripe sandbox is the first supported provider for platform checkout.

Do not directly create final paid orders for platform checkout.

Correct lifecycle:

1. Create checkout.
2. Add checkout items.
3. Validate stock.
4. Reserve stock when reservation support exists.
5. Capture shipping/billing address.
6. Calculate shipping, tax, and discounts.
7. Create payment intent.
8. Receive payment success/failure webhook.
9. Convert checkout to confirmed order.
10. Record order/payment events.

### 13.3 Merchant-connected payments

Use this mode for merchants who want to connect their own payment account to platform checkout.

Stripe Connect is the preferred future implementation for merchant-owned Stripe accounts.

Rules:

- Do not ask merchants to paste production Stripe secret keys into the dashboard.
- Do not store raw merchant secret keys for connected payments.
- Store connected account IDs and provider account status.
- Use hosted Stripe onboarding when Stripe Connect is implemented.

**Stripe Connect no-key UX (Patch B cleanup):** Platform Stripe keys (`STRIPE_TEST_*`, `STRIPE_LIVE_*`) are configured by the SaaS/platform owner in server environment only. Store owners connect test/live Stripe accounts through Stripe hosted onboarding/account links. The dashboard stores connected account IDs and status only — never merchant secret keys. Normal Payments UI must not mention `.env`, `STRIPE_*` variable names, or key-paste instructions; technical config visibility belongs in local/testing Developer diagnostics only.

Future Stripe Connect support should store data in `payment_provider_accounts`, such as:

- provider: `stripe`;
- connection_type: `connect`;
- provider_account_id: Stripe connected account ID;
- status;
- capabilities;
- last verified time.

### 13.4 Payment architecture rules

Payment logic must be provider-neutral.

Use provider services instead of putting Stripe logic directly inside controllers.

Required provider architecture:

- `PaymentProviderInterface`
- `PaymentProviderManager`
- `StripePlatformPaymentProvider`
- `ExternalPaymentProvider`
- `ManualPaymentProvider`
- future: `StripeConnectPaymentProvider`
- future: `PayPalPaymentProvider`
- future: `SquarePaymentProvider`

All providers should return a normalized internal payment result:

- provider;
- provider account;
- provider intent/reference;
- status;
- amount;
- currency;
- failure code;
- failure message;
- safe metadata.

### 13.5 Security and compliance rules

Never store or process raw card data.

Use:

- hosted checkout;
- hosted payment fields;
- Stripe PaymentIntents or equivalent;
- signed webhooks;
- idempotency keys;
- payment attempt records;
- provider-neutral payment records.

Never expose secret keys to frontend code.

Payment records must be separate from orders.

Every payment record must be store-scoped.

Every important payment state change should create an order event.

Payment provider failures must not corrupt order state.

External paid orders must not create Stripe PaymentIntents.

### 13.6 Tables expected in Phase 5

Phase 5 should introduce or prepare:

- `checkouts`
- `checkout_items`
- `checkout_addresses`
- `checkout_events`
- `payment_provider_accounts`
- `payment_intents`
- `payment_attempts`
- `payment_captures`
- `refunds`
- `idempotency_keys`
- `tax_settings`
- `coupons`
- `order_coupons`

### 13.7 Dashboard UX rule

Merchants should eventually see a clear settings area:

`Settings → Payments & Channels`

It should explain:

1. **External checkout**
   - For merchants already using Shopify, WooCommerce, WordPress, PayPal, COD, bank transfer, or another checkout.

2. **Platform checkout**
   - For merchants who want this SaaS to manage checkout and payment.

3. **Connected Stripe account**
   - For merchants who want to connect their own Stripe account later.

Do not show fake working buttons.

If a mode is not implemented, show a clear coming-later state.

---

## 14. Fulfillment Rules

Fulfillment must be separate from orders.

Do not treat “shipped” as a generic order status.

Use:

- shipments;
- shipment items;
- carrier accounts;
- tracking numbers;
- labels;
- async jobs;
- shipment timeline.

---

## 14.1 Fulfillment, Shipping, Locations, and Delivery Strategy

Phase 6 must build fulfillment and shipping in a way that supports simple local stores and future global store owners.

Do not confuse these concepts:

### Store

A store is the user/store owner’s sales workspace in the SaaS platform.

A user/store owner may have:

- one store with one location;
- one store with multiple locations/warehouses;
- multiple separate stores;
- a global store selling into different regions.

Each store must keep its own shipping setup, carrier accounts, delivery methods, fulfillment locations, shipments, and rules.

### Location

A location is where stock exists or where orders can ship from.

Examples:

- warehouse;
- physical shop;
- stock room;
- restaurant branch;
- third-party fulfillment warehouse;
- local pickup location.

A location answers:

> Where is the stock or fulfillment operation?

Locations are used for:

- inventory levels;
- inventory reservations;
- stock movements;
- shipment origin;
- local pickup;
- future carrier pickup scheduling;
- future fulfillment routing.

A location is not a selling market, currency, or customer-facing region.

### Market / Shipping Zone

A market or shipping zone is where the store sells or delivers.

Examples:

- United States;
- Canada;
- United Kingdom;
- Europe;
- Middle East;
- South Asia;
- local delivery area;
- international delivery zone.

A shipping zone answers:

> Where can this store deliver?

Shipping zones are used for:

- destination country/region matching;
- available delivery methods;
- available carrier services;
- shipping rates;
- free shipping thresholds;
- future region-specific taxes, prices, and delivery promises.

A shipping zone is not the same as an inventory location.

### Carrier / Courier

A carrier or courier is the service that delivers the shipment.

Examples:

- DHL;
- UPS;
- FedEx;
- USPS;
- Canada Post;
- local courier;
- manual delivery;
- store pickup;
- third-party fulfillment provider.

A carrier answers:

> Who delivers the package?

A store owner may connect or configure multiple carriers, but a carrier must only be offered where it actually supports delivery.

### Delivery Method

A delivery method is what the customer sees at checkout.

Examples:

- Economy delivery;
- Standard delivery;
- Express delivery;
- Local delivery;
- Store pickup.

The customer should not see complex courier-routing language such as:

- cheapest reliable;
- balanced optimizer;
- carrier automation score;
- fulfillment routing strategy.

Those are internal store-owner settings, not customer checkout labels.

The customer should see clear choices:

- Economy delivery — lower cost, slower delivery;
- Standard delivery — balanced delivery;
- Express delivery — faster delivery;
- Store pickup — no shipping.

Behind the scenes, delivery methods can map to carrier services and shipping rules.

---

## 14.2 Global Shipping Scenarios

The platform must support these scenarios:

### Scenario 1 — One store, one location

Example:

- Store: Local clothing shop
- Location: Main shop
- Carrier: Manual delivery or local courier

This should be simple and not require advanced setup.

### Scenario 2 — One store, multiple warehouses/shops

Example:

- Store: Global fashion brand
- Locations:
  - New York warehouse
  - Dubai warehouse
  - London warehouse
  - Karachi warehouse

The platform should eventually decide which location can fulfill the order based on:

- stock availability;
- customer destination;
- shipping zone;
- carrier availability;
- store-owner routing preference.

Phase 6C-0A implements the first routing layer: nearest eligible fulfillment origin routing based on configured service areas, stock availability, pickup eligibility, and store-owner priority. It is not physical distance routing. Do not describe it as geocoded or mile/km based; optional coordinate/geocoding-based routing belongs to a later phase.

**Phase Q Step 3 (2026-05-24):** Must-fix QA hardening completed — external order sync dedup and 6C-0A routing negative/edge tests expanded. See `docs/audit/PHASE_Q_STEP_3_MUST_FIX_QA_HARDENING_REPORT.md`.

**Phase Q Step 3C (2026-05-24):** Strict external order identity — `external_order_id` or `external_order_number` is **required** for external order creation; `Idempotency-Key` is optional replay protection only and cannot be the sole identity. QA audit artifacts live under `docs/audit/`.

**Phase 6C-1A (2026-06-04):** FedEx sandbox carrier connection foundation — provider-neutral carrier interface, encrypted merchant credentials, Account Registration + OAuth test connection, carrier API event logs, Shipping & Delivery UI. **Not implemented:** labels, checkout live rates, tracking sync, live/production FedEx. See `docs/PHASE_6C_1A_FEDEX_SANDBOX_CARRIER_FOUNDATION_REPORT.md`.

**Phase 6C-1B-USPS:** USPS public API OAuth, address validation, and domestic test rate quotes using platform USPS credentials. Does not buy labels, authorize EPS payments, schedule pickups, or enable production live labels. Merchant-owned label purchase remains deferred. See `docs/PHASE_6C_1B_USPS_PUBLIC_API_FOUNDATION_REPORT.md`.

### Scenario 3 — Multiple separate stores

Example:

- Store 1: USA store
- Store 2: UAE store
- Store 3: Pakistan store

Each store has separate:

- locations;
- carrier accounts;
- shipping zones;
- delivery methods;
- shipping rules;
- shipments.

Store A must never use Store B carrier account, location, shipping rule, or shipment.

### Scenario 4 — Global delivery with regional carrier availability

Example:

- DHL enabled for international express;
- UPS enabled only for USA;
- local courier enabled only for one city;
- store pickup enabled only for physical shop locations.

The platform must hide unsupported carrier services for destinations they cannot serve.

If no delivery method is available for a customer address, the checkout should show a clear message:

> No delivery options are available for this address.

The dashboard should guide the store owner:

> No active shipping method covers this destination. Add a shipping zone or enable a carrier service for this region.

---

## 14.3 Carrier and Courier Strategy

The store owner should be able to add multiple courier/carrier services.

The correct structure is:

- Carrier: DHL, UPS, FedEx, Manual Courier, Store Pickup
- Carrier Account: the store owner’s account/configuration for that carrier
- Carrier Service: DHL Express, UPS Ground, FedEx 2Day, Manual Local Delivery
- Shipping Zone Availability: where the carrier service is allowed
- Checkout Visibility: whether the customer can choose it
- Status: connected, enabled, setup required, internal only, disabled

Do not use only one global active/inactive toggle.

A carrier can be connected but only used for specific zones or delivery methods.

Examples:

- DHL connected but only available for international express.
- UPS active only for United States deliveries.
- FedEx active only for express shipping.
- Manual courier active only for local delivery.
- Store pickup active only for selected shop locations.

Carrier statuses should be user-friendly:

- Not configured
- Setup required
- Enabled
- Internal only
- Disabled

Do not show fake carrier API buttons before real integrations exist.

---

## 14.4 Shipping Rules Strategy

Shipping rules decide which delivery methods and rates are available.

Start simple before building automation.

Phase 6 should support simple store-owner rules first:

- destination country/region;
- origin location;
- delivery method;
- carrier account/service;
- flat shipping rate;
- free shipping above order total;
- active/inactive;
- checkout visibility.

Future rules can add:

- product weight;
- package dimensions;
- product category;
- product type;
- customer group;
- market-specific pricing;
- live carrier rates;
- cutoff times;
- carrier pickup windows;
- delivery promises.

Shipping rules must stay store-scoped.

Do not build a complex automation/routing engine before manual fulfillment and basic shipping setup are stable.

---

## 14.5 Fulfillment and Shipment Strategy

Fulfillment must be separate from orders.

Do not use `shipped` or `delivered` as generic order statuses.

Order statuses, payment statuses, fulfillment statuses, and shipment statuses are different.

### Order status

Examples:

- pending;
- confirmed;
- processing;
- completed;
- cancelled;
- refunded.

### Payment status

Examples:

- pending;
- authorized;
- paid;
- failed;
- refunded;
- partially_refunded.

### Fulfillment status

Examples:

- unfulfilled;
- partial;
- fulfilled;
- returned.

### Shipment status

Examples:

- pending;
- label_created;
- shipped;
- in_transit;
- delivered;
- failed;
- returned;
- cancelled.

A shipment should have:

- store_id;
- order_id;
- shipment_number;
- origin_location_id;
- carrier_account_id;
- shipping_method_id;
- tracking_number;
- tracking_url;
- status;
- package count;
- package weight;
- shipped_at;
- delivered_at;
- shipped_by;
- metadata.

A shipment item should have:

- shipment_id;
- order_item_id;
- quantity.

This is required for partial and split shipments.

Examples:

- one order ships from one warehouse;
- one order ships in multiple packages;
- item A ships from New York;
- item B ships from Dubai;
- part of an order ships now and the rest ships later.

The system must prevent shipping more quantity than ordered.

---

## 14.6 Customer Checkout Delivery Choice

At checkout, the customer should choose a simple delivery option.

Customer-facing examples:

- Economy delivery;
- Standard delivery;
- Express delivery;
- Store pickup;
- Local delivery.

The customer should not choose internal courier strategy names like:

- cheapest reliable;
- fastest carrier automation;
- balanced optimizer.

Internal routing preferences can exist later for the store owner:

- cheapest available;
- fastest available;
- balanced;
- manual selection.

But checkout must remain simple.

---

## 14.7 Phase 6 UI/UX Rule

The current static Shipping Automation preview page must not become the first real Phase 6 page.

It is too advanced and mixes future automation, courier integrations, region settings, currency, timezone, and notification preferences before the foundation exists.

Replace or evolve it into real setup areas:

1. Shipping zones  
   Where does this store deliver?

2. Delivery methods  
   What choices can customers select at checkout?

3. Carriers & accounts  
   Which courier services can this store use?

4. Fulfillment locations  
   Where do orders ship from?

5. Fulfillment workflow  
   Create shipment, add tracking, mark shipped/delivered.

6. Automation  
   Coming later after manual fulfillment, shipping rules, and carrier services are stable.

Do not show fake save/export/toggle controls.

If a feature is not implemented, hide it or clearly mark it as coming later.

---

## 14.8 Phase 6 Implementation Priority

Build Phase 6 in this order:

### Phase 6A — Manual Fulfillment Foundation

Build first:

- carriers;
- carrier accounts;
- shipping zones;
- delivery methods;
- shipments;
- shipment items;
- manual tracking number;
- manual tracking URL;
- create shipment from order;
- mark shipment as shipped;
- mark shipment as delivered;
- fulfillment status recalculation;
- order events;
- shipment events if needed;
- order detail fulfillment panel.

Do not integrate live DHL/UPS/FedEx APIs in Phase 6A.

### Phase 6B — Shipping Settings and Checkout Delivery Methods

Build second:

- flat rate shipping;
- free shipping threshold;
- country/region-based shipping zones;
- delivery methods visible in checkout;
- checkout shipping selection;
- shipping snapshots on checkout/order;
- simple fallback if no method is available.

### Phase 6C — Carrier API Integrations and Automation

Build first:

- service-area and stock-aware fulfillment origin routing;
- pickup location selection for checkout pickup methods;
- checkout/order routing snapshots;
- shipment origin prefill from the routed order origin.

Build later:

- DHL/UPS/FedEx sandbox integrations;
- live carrier rates;
- label purchase;
- tracking sync;
- pickup scheduling;
- carrier API retries;
- async jobs;
- routing preferences;
- automation insights.

Do not start Phase 6C before Phase 6A and 6B are stable.

---

## 14.9 Non-Negotiable Phase 6 Rules

- Store scoping is mandatory.
- Store A cannot access Store B carriers, zones, delivery methods, locations, shipments, or shipment items.
- Shipment must not mutate order status incorrectly.
- Fulfillment status must be calculated from shipment items.
- Do not ship more quantity than ordered.
- Do not show carrier services that do not support the customer destination.
- Do not show fake label purchase buttons.
- Do not show fake automation toggles.
- Manual fulfillment must work before live carrier APIs.
- Shipping data must be snapshotted on orders.
- Important shipment changes must create order events.
---

## 15. Returns and Refunds Rules

Returns/refunds are core commerce, not optional polish.

Implement:

- returns;
- return items;
- refund records;
- refund items;
- exchange flow;
- restock decisions;
- payment refund integration;
- order events.

---

## 16. B2B and Markets Vision

Future enterprise support requires:

- companies;
- company locations;
- company buyers;
- payment terms;
- assigned catalogs;
- price lists;
- markets;
- regional product availability;
- regional currency/locale behavior.

Do not hardcode assumptions that every customer is a simple retail consumer.

---

## 17. Automation and Integration Vision

The platform should reduce manual merchant work.

Build toward:

- event outbox;
- webhook subscriptions;
- webhook delivery logs;
- API keys/scopes;
- automation builder:
  - triggers;
  - conditions;
  - actions.

Example automations:

- order paid → notify fulfillment team;
- stock low → create notification;
- import completed → email owner;
- customer spend > threshold → tag VIP;
- shipment delivered → email customer.

---

## 18. UI/UX Rules

### 18.1 Workspace-first

Core workflows should be full pages/workspaces, not cramped modals.

Use modals only for quick/simple actions.

### 18.2 Quick Add vs Full Builder

Quick Add should remain simple:

- product name;
- SKU;
- price;
- stock;
- category;
- images;
- product type.

Complex work belongs in full product editor:

- variants;
- variant images;
- additional details;
- import recovery;
- SEO;
- advanced inventory.

### 18.3 Empty states

Every empty state should guide the merchant.

Bad:

> No records.

Good:

> No additional details yet. Add supplier, material, origin, or care notes from Edit product.

### 18.4 Keep default pages calm

Product list default should show:

- search;
- category;
- product type;
- brand if needed;
- quick chips.

Advanced filters/settings should be hidden unless needed.

---

## 19. Testing Standards

For every feature, add tests for:

1. owner happy path;
2. manager/staff authorization;
3. validation errors;
4. cross-store access rejection;
5. persistence;
6. UI route render;
7. important side effects/events/stock movements.

### Commands

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan test
```

Targeted examples:

```bash
php artisan test --filter=ProductImportTest
php artisan test --filter=ProductWorkspaceViewTest
php artisan test --filter=DeveloperStorefrontApiTest
php artisan test --filter=ProductBulkActionsTest
php artisan test --filter=StockMovementTest
```

---

## 20. Coding Style Rules for AI Agents

When implementing tasks:

1. Inspect existing code first.
2. Do not rewrite working systems unnecessarily.
3. Prefer services for business logic.
4. Keep controllers thin where possible.
5. Keep Blade merchant-friendly.
6. Add migrations only when data model requires it.
7. Add tests with every real feature.
8. Never weaken store scoping.
9. Never bypass permissions.
10. Never leave broken buttons.
11. Never make invented claims about tests passing unless commands were run.
12. Report exact files changed and commands run.

---

## 21. Recommended Implementation Response Format

Every AI coding completion should return:

1. Files inspected.
2. Root cause/current gap.
3. Implementation summary.
4. Files changed.
5. Migrations added.
6. Tests added/updated.
7. Commands run.
8. Manual verification notes.
9. Deferred items, if any.
10. Final sign-off status.

---

## 22. Current Priority Queue

Do these next, in order:

1. Fix test environment.
2. Fix stale storefront/order tests.
3. Clean migrations and seeders.
4. Implement deterministic order numbers.
5. Add order events/timeline.
6. Clean order/payment/fulfillment status separation.
7. Harden developer storefront order creation with idempotency.
8. Add API key/scopes/rate limit foundation.
9. Add locations/inventory items/inventory levels.
10. Then begin fulfillment/shipping.

---

## 23. Final Product Bar

The product is not enterprise-ready until it supports:

- tenant isolation;
- store-scoped permissions;
- catalog/import/variant/custom data;
- location-aware inventory;
- checkout lifecycle;
- payments/refunds;
- orders/events;
- fulfillment/shipping;
- returns/exchanges;
- B2B/markets/catalogs;
- API keys/webhooks/outbox;
- automation;
- SaaS billing;
- observability/security logs;
- platform admin operations.

Build every next feature toward that final bar.
