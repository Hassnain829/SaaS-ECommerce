# Project State Snapshot — After Phase Q Step 3C

## Project

Laravel Blade multi-store SaaS e-commerce platform.

Goal: enterprise-grade Shopify-like SaaS with multi-store tenancy, catalog, inventory, checkout, payments, external order sync, fulfillment, shipping/delivery, and future carrier automation.

---

## Completed phases

### Phase 1 — SaaS foundation

Implemented:

- multi-store SaaS foundation
- authentication
- store ownership/team access
- permissions/middleware-based authorization
- security hardening
- audit/security logs
- session/profile/account hardening

Note:

- no `app/Policies` directory currently;
- authorization is middleware/service/controller based.

---

### Order Lifecycle Hardening

Implemented:

- separated `order_status`
- separated `payment_status`
- separated `fulfillment_status`
- order events/timeline
- safer lifecycle transitions

---

### Phase 2 — Catalog Completion

Implemented:

- product management
- variants
- attributes/options
- SKU/variant behavior
- catalog APIs
- catalog UI cleanup

---

### Phase 3 — Enterprise Inventory

Implemented:

- locations
- inventory items
- inventory levels
- reservations
- stock movements
- location-aware stock tracking
- default location foundation

---

### Phase 3.5 — Store Settings / Onboarding Alignment

Implemented:

- onboarding/store settings alignment
- default fulfillment location provisioning
- primary market/business address/country used for default location
- timezone remains store-level

---

### Phase 4 — Commerce Core

Implemented:

- orders
- draft/manual orders
- customer CRM
- customer notes/tags
- manual order conversion
- order events/timeline

---

### Phase 5 — Payments / External Checkout / Stripe

Implemented:

- platform checkout
- Stripe PaymentIntent sandbox flow
- Stripe Connect foundation
- hosted Connect onboarding
- test/live provider account separation
- no merchant secret-key input
- external checkout sync
- external managed channel mode

Important decision:

- store owners do not paste Stripe secret keys;
- platform owns Stripe platform keys;
- store owners connect Stripe via hosted onboarding.

---

### Patch A — External Managed Channel Mode

Implemented:

- external storefront/channel can own checkout/payment/shipping/fulfillment
- dashboard syncs orders/shipments
- platform inventory only touched when `inventory_owner = platform`

---

### Patch B — Stripe Sandbox/Live Connect Support

Implemented:

- test/live mode distinction
- sandbox account support
- hosted onboarding
- payment settings UX cleanup

---

### Phase 6A — Manual Fulfillment

Implemented:

- shipments
- shipment items
- shipment statuses
- carrier/carrier account foundation
- manual tracking
- fulfillment recalculation

---

### Phase 6B — Checkout Delivery Methods

Implemented:

- shipping zones
- shipping methods
- postal/country/region matching
- checkout delivery option selection
- shipping snapshot
- payment intent refresh when shipping changes
- external checkout shipping preservation

Deferred:

- carrier APIs
- live rates
- label purchase
- pickup scheduling
- tracking sync jobs

---

### Phase 6C-0A — Nearest Eligible Origin Routing

Implemented:

- nearest eligible fulfillment origin routing by:
  - service area
  - stock availability
  - routing priority
  - pickup eligibility
- fulfillment origin snapshot on checkout
- fulfillment routing copied to order meta
- pickup location selection foundation
- shipment origin prefill
- external platform-owned inventory routing
- external-owned inventory no-routing/no-deduction behavior
- onboarding/default location alignment with routing fields

Important boundary:

```txt
Phase 6C-0A = nearest eligible by service area + stock + priority
Phase 6C-0B = true physical nearest by lat/lng/geocoding later