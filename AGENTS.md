# AGENTS.md — Enterprise SaaS E-Commerce Project Instructions


## Developer Storefront Clarification

`dev-test-storefront` is not the final storefront product. It is a local testing simulator used to prove that external websites can fetch products from this SaaS platform and send orders/customers back into the merchant dashboard.

The final product goal is to support merchants who already have Shopify, WooCommerce, WordPress, custom websites, mobile apps, or other selling channels. The SaaS platform acts as the central commerce backend for catalog, inventory, orders, customers, and future fulfillment.

Do not overbuild the dev storefront during catalog phases. Keep it functional for testing catalog API and order creation only. Full production API keys, scopes, webhooks, idempotency, and external integration management belong to the later API/integrations roadmap phase.

## Project Identity

This project is a Laravel Blade multi-store SaaS e-commerce platform.

It is not a simple webshop.

The long-term goal is to build a merchant-friendly enterprise commerce operating system that can compete with Shopify, WooCommerce, and Amazon Seller Central in capability, while being easier and less frustrating for merchants to use.

The platform must support:

- multi-store SaaS tenancy
- merchant storefront/API connection
- large catalog management
- product imports
- variants and option groups
- custom fields and imported data preservation
- inventory tracking
- orders and customers
- fulfillment and shipping
- payments and refunds
- returns and exchanges
- SaaS billing
- integrations, API keys, webhooks, automation
- security logs, permissions, and enterprise auditability

---

## Canonical Project Files

Before implementing any task, always read these files first:

1. `ENTERPRISE_PROJECT_CONTEXT.md`
2. `ENTERPRISE_ROADMAP_2026.md`
3. `PROJECT_BRAIN.md`
4. `.agents/rules/PROJECT-CONTEXT.txt`
5. `.agents/rules/ROADMAP.txt`
6. `.agents/rules/Updated ERD similar to Shopify.txt`
7. `.agents/rules/cursor_project_context_and_development-day-17last.md`
8. `.agents/rules/OVERVIEW FOR SAAS.txt`
9. `.cursor/rules/*` if present

The canonical source of truth is:

- `ENTERPRISE_PROJECT_CONTEXT.md` for product vision, architecture rules, and development principles.
- `ENTERPRISE_ROADMAP_2026.md` for build order, implementation phases, tests, and acceptance criteria.

Older files such as `ROADMAP.txt` and `PROJECT-CONTEXT.txt` are retained for history and compatibility, but the enterprise files override them when there is conflict.

---

## Core Product Philosophy

Every feature must answer this question:

> Does this make merchant life easier?

If the answer is no, the implementation is wrong.

The product must:

- simplify complexity
- reduce merchant effort
- make imports understandable
- make variants easier to manage
- make inventory clear
- make orders/customers actionable
- avoid technical wording in merchant UI
- expose important data clearly
- preserve unknown imported data safely
- remain flexible for many store types

Avoid merchant-facing terms such as:

- JSON
- payload
- schema
- pivot
- raw meta
- internal object

Use merchant-friendly terms such as:

- Additional details
- Imported data
- Product options
- Variants
- Inventory
- Store settings
- Customer information
- Order activity

---

## Non-Negotiable Architecture Rules

### 1. Store Scoping Is Mandatory

Every tenant-owned entity must be scoped to the current store.

Never allow cross-store leakage.

Store-scoped examples:

- products
- variants
- images
- categories
- brands
- tags
- imports
- customers
- customer addresses
- orders
- order items
- order addresses
- stock movements
- API keys
- webhooks
- notifications
- settings

Every query must be checked for store safety.

Do not use unscoped model queries in merchant-facing controllers.

Bad:

```php
Product::find($id);
Order::find($id);
Customer::find($id);