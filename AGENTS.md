# BaaS Core — Global Project Instructions

## What this product is

BaaS Core is an enterprise-ready multi-tenant SaaS ecommerce platform.

It is being built to:
- compete with Amazon Seller Central, Shopify, and WooCommerce
- support many store types:
  - physical products
  - clothing
  - electronics
  - grocery
  - restaurant/menu
  - services
  - subscriptions
  - digital products
- be easier, faster, and less frustrating for merchants

This is NOT:
- a demo
- a CRUD panel
- an MVP shortcut build

---

## Core product promise

Every feature must reduce merchant friction.

The platform must:
- simplify complex tasks
- preserve imported data safely
- make product and variant management easier
- make inventory understandable
- make operations feel direct and predictable

If a change makes the merchant think too much, navigate too much, or hunt for data, it is the wrong solution.

---

## Architecture rules

- Every tenant-facing query must be scoped by current store.
- Never allow cross-store access or mutation.
- Keep core catalog data normalized.
- Use JSON/meta only for flexible imported or custom fields.
- Do not store images in the database.
- Imports must remain resumable, store-safe, and scalable.
- Inventory changes must remain auditable.

---

## Current roadmap focus

Current critical phase:
- Day 14: Product Detail Workspace
- Day 15: Variant System Upgrade
- Day 16: Variant Import
- Day 17: Custom Fields Usability
- Day 18: Product UX Completion

Then:
- Day 19–24: Commerce Core
- Day 25–29: Fulfillment
- Day 30–34: Billing
- Day 35–40: Integrations and Security

Follow roadmap order unless explicitly told otherwise.

---

## UX rules

Prefer:
- direct workspace-based flows
- clear section hierarchy
- readable merchant-facing copy
- strong progress feedback
- visible and understandable imported data

Avoid:
- modal/list bridge hacks for core workflows
- debug-style pages
- technical jargon in merchant UI
- redirect loops
- hiding important data behind internal structures

---

## Development standard

Every meaningful feature should follow this order where applicable:
1. migration
2. model
3. relationships
4. validation
5. authorization
6. controller/service logic
7. Blade UI
8. seed/demo data if needed
9. feature tests

Do not stop at UI-only implementation.