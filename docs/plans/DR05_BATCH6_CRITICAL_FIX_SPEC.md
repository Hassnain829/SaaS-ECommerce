# DR-05 Batch 1–6 Critical Fix Specification

Status: Approved correction specification  
Date: 2026-08-18

Amended 2026-08-19: the webhook-only confirmation restriction is superseded by provider-authoritative reconciliation. An authenticated, store-scoped confirmation request may finalize an order only after the SaaS retrieves the stored PaymentIntent directly from Stripe in the stored mode and connected-account context, validates its identity metadata, amount, and currency, and receives `succeeded`. Browser/WordPress payment claims remain untrusted. Verified webhooks remain the asynchronous retry and recovery path.

Documentation amendment 2026-08-20 (later the same day): the ten Batch 1–6 real-browser scenarios are **complete** by merchant confirmation. Stripe IDs and screenshots were not committed. Batch 7 go-live checklist and Batch 8 acceptance mapping are implemented; DR-05 is signed off for this WordPress-connection workstream. DR-06 follows. See `docs/handoffs/DR05_BATCH6_CRITICAL_FIX_EVIDENCE.md` and `docs/handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md`.

## 1. Purpose

This document defines the required corrections before Batch 7 can begin. It exists because Batches 1–6 contained substantial implementation but did not fully satisfy the locked WordPress/SaaS commerce architecture.

This is an implementation specification, not evidence that the corrections are complete. Completion requires code inspection, migrations, focused tests, the full test suite, and WordPress acceptance testing.

## 2. Locked product architecture

The SaaS replaces WooCommerce as the commerce engine. WordPress remains the merchant's storefront presentation layer and connector host.

### SaaS responsibilities

The SaaS is authoritative for:

- stores and connected-site identity;
- products, categories, variants, images, and publication state;
- locations and inventory;
- price and currency validation;
- coupons, tax, shipping quotations, and totals;
- merchant Stripe connection and PaymentIntent creation;
- customers, orders, payments, returns, and refunds;
- shipping services, carrier rates, labels, fulfillment, and tracking;
- webhook processing, event delivery, reconciliation, and audit state.

### WordPress connector responsibilities

The connector may:

- connect one WordPress site to one SaaS store;
- authenticate server-to-server requests;
- fetch and display published products, variants, images, and categories;
- cache public catalog responses temporarily;
- invalidate public catalog cache after signed SaaS events;
- hold variant IDs and requested quantities in the shopper cart;
- send the cart and addresses to the SaaS;
- request authoritative quotations and available shipping rates;
- start a platform checkout session;
- render Stripe's Payment Element using SaaS-provided public checkout information;
- poll SaaS checkout/order status after Stripe.js completes;
- display confirmation and tracking returned by the SaaS;
- report connection health and conflicts to the merchant.

The connector must not calculate authoritative prices, tax, shipping, inventory, payment state, or order state.

### WooCommerce and WordPress payment plugins

- WooCommerce is used only as a migration source for exported catalog CSV data.
- WooCommerce is not a production runtime dependency of the connector.
- Merchants must deactivate WooCommerce commerce behavior after migration when switching to the SaaS storefront connector.
- Merchants must deactivate WordPress Stripe/payment plugins used by the former WooCommerce checkout.
- Merchants connect their Stripe account through the SaaS portal.
- The WordPress storefront renders Stripe.js/Payment Element, but the SaaS creates and owns the PaymentIntent.

## 3. Authoritative checkout sequence

1. WordPress requests public catalog data from the SaaS.
2. The shopper selects a SaaS product variant and quantity.
3. WordPress stores only identifiers and requested quantities.
4. WordPress sends the cart to the SaaS using a stable idempotency key.
5. The SaaS validates publication, variant ownership, availability, stock, price, and currency.
6. WordPress sends the customer's address to the SaaS.
7. The SaaS calculates discounts, tax, shipping choices, and final totals.
8. The SaaS creates the checkout, inventory reservation, and Stripe PaymentIntent.
9. WordPress renders Stripe's secure Payment Element.
10. After Stripe.js completes, WordPress requests checkout confirmation from the SaaS.
11. The SaaS retrieves the stored PaymentIntent directly from Stripe in the stored mode and connected-account context and validates checkout/store/account metadata, amount, and currency.
12. A provider-verified `succeeded` result finalizes the order and commits/deducts inventory exactly once; the signed webhook remains an idempotent asynchronous recovery path.
13. WordPress displays the SaaS order confirmation and tracking state.

Neither WordPress nor browser-supplied confirmation data may declare an order paid. Payment authority comes only from Stripe: either a server-to-server PaymentIntent retrieval or a verified webhook.

## 4. Critical defects requiring correction

| Area | Defect | Required result |
| --- | --- | --- |
| Direct order API | A connected-site route can create an immediately paid order without Stripe proof | Remove the route and order-creation method; retired endpoints return `404` |
| Payment authority | Client-supplied confirmation could declare payment truth | Confirmation may convert only after server-to-server retrieval of the stored Stripe PaymentIntent and full checkout/account/amount/currency validation; verified webhooks remain supported |
| Idempotency | Check-before-create permits concurrent duplicate checkouts; WordPress creates new retry keys | Database-first atomic claim and one persisted key per logical WordPress checkout |
| Authentication | Legacy store token fallback bypasses connected-site scopes and binding | Active connected-site credentials only; no legacy fallback or mirroring |
| Site ownership | Active normalized URLs are not reliably unique | Database-enforced active URL identity; duplicate migration stops explicitly |
| Catalog delivery | Merchant URLs can target internal infrastructure | Production HTTPS, IP/DNS validation, no redirects, rebinding protection |
| Woo import | Woo IDs are matched without source-site identity; SKU fallback can link unrelated products | Source-site-aware unique identity and explicit SKU-link approval |
| External runtime | Obsolete external sync classes/configuration remain active-looking | Delete runtime stubs and writers; retain only read-only historical interpretation |

## 5. Data invariants

### Tenant scope

Every connected-site, checkout, product, variant, customer, order, inventory, and import lookup must be scoped to the authenticated store. Route model binding alone is not sufficient when the model can belong to another store.

### Payment and order state

- A browser redirect, WordPress POST, Stripe.js success result, PaymentIntent client secret, or client-side status is not proof of payment.
- Successful conversion requires provider-authoritative Stripe evidence: either (1) a cryptographically verified Stripe webhook or (2) authenticated SaaS server-to-server retrieval of the exact stored PaymentIntent in its stored mode and connected-account context.
- Server-to-server retrieval must validate provider intent identity, checkout/store/account metadata, amount, currency, and provider status `succeeded` before conversion.
- Verified webhooks remain an idempotent asynchronous recovery path. Webhook event IDs and payment/order conversion must be idempotent.
- One checkout can convert into at most one order.
- Inventory can be committed and deducted at most once for that conversion.

### Checkout idempotency

- Identity: `store_id + idempotency key`.
- The claim must exist before checkout side effects begin.
- The database unique index is the concurrency authority.
- The stored request hash includes method, path, and validated payload.
- Same key plus different hash is a conflict.
- Same key plus completed response is a replay.
- Same key plus unfinished response is processing/conflict, not a second execution.

### Connected-site identity

- Credentials are stored as hashes.
- Scopes are enforced per request.
- Revoked credentials cannot authenticate.
- Production requests must report the site URL expected by the credential.
- Active normalized site ownership must be unique at database level.
- Revoked/inactive sites release the active unique URL key without erasing historical site records.

### Woo source identity

Use the following identities:

```text
Product: store_id + source_system + source_site + source_product_id
Variant: store_id + source_system + source_site + source_variation_id
```

The exact source site must be captured from the merchant during import confirmation. Do not infer it silently from whichever connected site happens to be active at processing time.

## 6. SSRF security requirements

Catalog event delivery is a server-side request to a merchant-controlled URL and therefore an SSRF boundary.

Production behavior must:

- accept HTTPS only;
- reject embedded user credentials;
- reject localhost and local-development suffixes;
- reject loopback, private, link-local, reserved, multicast, unspecified, and metadata addresses for IPv4 and IPv6;
- resolve both A and AAAA records;
- reject a hostname when any destination used for the request is prohibited;
- revalidate immediately before sending;
- disable redirects;
- pin the request to a validated IP where supported;
- fail closed when safe validation or pinning cannot be performed.

Local/private delivery may be enabled only in a non-production environment using an explicit configuration value. A production environment must ignore that value.

## 7. Migration requirements

### Connected sites

Add an active-only URL identity mechanism, such as a nullable `active_site_url_key`:

- active site: normalized URL value;
- inactive or revoked site: `NULL`;
- unique database index on the active key.

Backfill deterministically. If duplicate active normalized URLs exist, stop with a clear error listing the values requiring manual resolution. Do not silently set duplicates to `NULL`.

### Woo source identities

Add the source-site fields required by product and variant identity. Replace old unique indexes that omit source site with source-site-aware indexes. Backfill from trusted existing source metadata when available; do not fabricate a source site.

Rollback behavior must be explicit. If legitimate multi-site records cannot fit the old uniqueness model, fail with a useful explanation rather than deleting or merging data.

## 8. Historical compatibility boundary

Removing external checkout does not justify destroying historical business data.

Allowed historical compatibility:

- old database columns and migration history;
- reading an old order's source/ownership metadata;
- displaying an old external order accurately;
- deciding how to process a refund for an already existing historical order.

Prohibited runtime behavior:

- creating a new external order;
- accepting external payment truth;
- accepting external shipment truth through retired endpoints;
- enabling an external checkout mode;
- showing an external checkout choice to merchants;
- generating new external-channel settings during catalog or checkout requests.

## 9. Acceptance gates

Batch 1–6 correction is complete only when all applicable gates pass:

- no active direct paid-order route;
- no external order/shipment sync runtime endpoints;
- no conversion from client-supplied payment status or identifiers;
- confirmation conversion requires provider-authoritative Stripe retrieval plus checkout/store/account/amount/currency validation;
- verified webhook conversion remains idempotent and supported;
- atomic idempotency behavior is covered;
- WordPress retry key reuse is covered;
- no legacy-token authentication fallback;
- active site URL uniqueness is enforced in the database;
- production SSRF rejection and no-redirect behavior are covered;
- Woo identities are isolated by source site;
- unsafe SKU linking is rejected by default;
- focused tests pass;
- full PHPUnit passes;
- frontend build passes;
- WordPress test-mode checkout acceptance passes;
- no unrelated runtime, carrier, credential, or environment changes were made.

The ten-scenario browser gate is closed by merchant confirmation dated 2026-08-20. Later batches must not reopen it by inventing Stripe artifacts or adding a replacement browser-test suite unless a production defect requires it.

### 9.1 Required real-browser scenarios

The ten scenarios below are the final Batch 1–6 correction gate. They are not Batch 7.

1. Complete a successful Stripe test payment and prove one checkout, one PaymentIntent, one order, and one inventory commitment/deduction.
2. Delay webhook delivery while authenticated direct retrieval succeeds; confirmation must complete exactly once, and later webhook replay must remain idempotent.
3. Delay webhook delivery while direct retrieval is temporarily unavailable; confirmation must remain truthfully `processing`, tell the shopper not to pay again, create no duplicate order, and complete safely when either provider-authoritative path succeeds.
4. Reload during `processing` and complete a redirect-capable test payment; the same checkout must resume status polling without reconfirming payment.
5. Send duplicate confirmation/status requests and replay the verified webhook; one order and one inventory commitment/deduction may result.
6. Use a declined or failed test payment; `failed` must remain distinct from delayed `processing`, and no order may be claimed.
7. Interrupt initial checkout creation/status networking, including two submissions from one rendered form and a lost first WordPress response; the preallocated logical-attempt key must be reused and no duplicate checkout or PaymentIntent may result.
8. Disconnect or make the current store's Stripe Connect account ineligible; checkout must be blocked before payment and must not fall back to platform credentials or a WordPress gateway.
9. Compare the order, reservation, inventory movement, and stock after retries/replays; each commitment/deduction must occur exactly once.
10. Confirm WooCommerce and WordPress payment/shipping plugins remain non-authoritative and that the connector reports conflicts without deactivating or deleting merchant software.

Required evidence must identify the actual outcome and, where applicable, the SaaS checkout, Stripe PaymentIntent, SaaS order, and inventory records. Screenshots alone are insufficient when an exactly-once database/provider invariant is being proved.

## 10. DR-05 execution order after this correction

Completed 2026-08-20:

1. Close the Batch 1–6 browser-evidence gate — merchant confirmation; all ten scenarios Passed.
2. Implement and verify Batch 7 merchant migration and controlled production cutover.
3. Execute and verify Batch 8 end-to-end release recovery and acceptance mapping.
4. Sign off DR-05 for the WordPress website-connection workstream.

Next:

5. Continue DR-06 owner/manager/staff acceptance across two stores.
6. Continue later development-readiness work in the approved order.

## 11. Required final evidence

The implementer must provide:

- changed, created, and deleted file lists;
- migration names and deployment notes;
- focused test commands and exact results;
- full-suite command and exact result;
- frontend/WordPress checks and exact results;
- search evidence showing retired routes/classes and legacy fallback are absent;
- evidence showing every order-conversion path is provider-authoritative (verified webhook or validated server-to-server Stripe retrieval);
- any unresolved risk or blocked verification.
