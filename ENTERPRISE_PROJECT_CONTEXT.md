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

## 13. Payments Rules

Do not store or process raw card data.

Use:

- hosted checkout;
- hosted fields;
- Stripe/Cashier or equivalent;
- signed webhooks;
- idempotency keys;
- payment attempt records.

Payment records should be separate from orders.

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
