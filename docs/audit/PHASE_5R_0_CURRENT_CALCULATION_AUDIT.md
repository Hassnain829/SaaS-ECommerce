# Phase 5R-0 — Current Calculation Audit

**Project:** E_COMMERCE_OFFICE (SaaS-Static-Blade)  
**Date:** 2026-06-24  
**Status:** Completed (audit and planning only — no production tax code)  
**Scope:** Financial calculation paths for platform checkout, external checkout, draft/manual orders, shipping, payments, and schema readiness for Phase 5R-1.

---

## 1. Executive summary

**Verdict:** The repository has **three independent totals paths** (platform checkout, external sync, draft/manual) with **schema-ready tax/discount columns** but **no tax engine**. Platform checkout hardcodes `tax_total = 0` and `discount_total = 0`; grand total is `subtotal + shipping + tax - discount` using **float `round(..., 2)`** in multiple services. **Duplicate grand-total formulas** exist in `CheckoutService` and `CheckoutShippingService`. PaymentIntent amounts derive from `checkout.grand_total` via duplicated `amountMinor()` helpers. External checkout treats the authenticated external payload as financial source of truth. Draft orders use **merchant-entered** tax/discount/shipping with **BCMath** for the draft total only.

**Authoritative path recommendation (Phase 5R-1):** Introduce a single **`CheckoutTotalsService`** (name per implementation plan) for platform checkout only. `CheckoutService` and `CheckoutShippingService` delegate all persisted checkout totals to it. External and manual paths remain exceptions with documented invariants.

**Money recommendation for Phase 5R-1:** Keep existing `decimal(14,2)` columns; calculate with **BCMath string decimals** inside the new tax/totals layer; persist rounded two-decimal strings. Defer repository-wide float removal to **Phase 5R-3**.

**Phase 5R-1 must not change:** external checkout supplied totals, carrier/shipping configuration behavior, admin panel, or historical orders.

---

## 2. Current financial-flow architecture

```
Platform checkout (server-authoritative)
  PlatformCheckoutController → CheckoutService::create()
    → variant prices from DB
    → CheckoutService::totals() [tax=0, discount=0]
    → Checkout + CheckoutItem rows
    → StripePlatformPaymentProvider::createPaymentIntent(checkout.grand_total)
  Later: CheckoutShippingService::selectShippingMethod()
    → inline grand_total recalc
    → refreshPaymentIntent() [supersedes old PI, creates new]

External checkout (external-authoritative)
  ExternalOrderSyncController → ExternalOrderSyncService::sync()
    → ExternalOrderSyncService::totals() [payload wins]
    → Order + OrderItem rows [item tax/discount = 0]

Manual draft (merchant-authoritative)
  DraftOrderController → DraftOrderService [manual tax/discount/shipping]
    → ManualOrderConversionService::convert() [snapshot copy, no recalc]
```

---

## 3. Platform checkout sequence

| Step | File | Class / method | Input | Output / DB | Money | Tests |
|------|------|----------------|-------|-------------|-------|-------|
| 1 | `routes/api.php` | Route → `PlatformCheckoutController@store` | HTTP POST | — | — | Phase5 |
| 2 | `PlatformCheckoutController.php` | `validatedPayload()` L154–216 | Request body | Validated array; **no** client `tax_total`/`grand_total` rules | — | Partial |
| 3 | `CheckoutService.php` | `create()` L39–295 | Store + payload | Transaction | float | Phase5 |
| 4 | `CheckoutService.php` | `prepareItems()` L352–410 | `items[].variant_id`, qty | Unit price from `ProductVariant::price` | `money()` float | Phase5 |
| 5 | `CheckoutService.php` | `subtotal()` L435–438 | Prepared items | Sum of line subtotals | float | Phase5 |
| 6 | `CheckoutService.php` | `totals()` L416–430 | Items + shipping | `tax=0`, `discount=0`, `grand_total` formula | float | **Missing explicit tax=0 assert** |
| 7 | `CheckoutService.php` | `create()` L68–91 | Optional `shipping_method_id` | `shipping_total`, snapshot | float | Phase6 |
| 8 | `CheckoutService.php` | `create()` L105–137 | Totals | `checkouts.*` monetary columns | decimal cast | Phase5 |
| 9 | `CheckoutService.php` | `create()` L178–198 | Items | `checkout_items`; `tax_amount=0`, `discount_amount=0` | decimal | Partial |
| 10 | `CheckoutService.php` | L153–172 | Inventory | Reservations | — | Phase5 |
| 11 | `PaymentProviderManager` | → `StripePlatformPaymentProvider` | `checkout.grand_total` | Stripe PI | minor int | Phase5, Phase6 |
| 12 | `PlatformCheckoutController.php` | `deliveryOptions()` L67–80 | Checkout + address | JSON options | — | Phase6 |
| 13 | `CheckoutShippingService.php` | `selectShippingMethod()` L53–149 | Method id | Updates shipping + grand_total | float | Phase6 |
| 14 | `CheckoutShippingService.php` | `refreshPaymentIntent()` L283–364 | Updated checkout | Supersedes PI, new PI | minor int | Phase6 |
| 15 | Webhook / confirm | `CheckoutConversionService::handleSucceededPayment()` | Stripe result | Order snapshot | copy decimals | Phase5 |
| 16 | `CheckoutConversionService.php` | L112–156 | Checkout fields | `orders.*` totals | **no recalc** | Partial |

**Key code — tax hardcoded zero:**

```416:429:app/Services/CheckoutService.php
    private function totals(array $items, float $shippingTotal): array
    {
        $subtotal = $this->subtotal($items);
        $shipping = $this->money($shippingTotal);
        $tax = 0.0;
        $discount = 0.0;

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tax' => $tax,
            'discount' => $discount,
            'grand_total' => $this->money(max(0, $subtotal + $shipping + $tax - $discount)),
        ];
    }
```

**Client totals ignored:** `Phase5PlatformCheckoutStripeTest::test_platform_checkout_creates_checkout_payment_intent_and_reserves_inventory_with_server_totals` sends inflated client totals; DB asserts `grand_total=24.00` from server variant prices.

---

## 4. Shipping recalculation sequence

| Location | Method | Behavior |
|----------|--------|----------|
| `CheckoutService::create()` L68–91 | Initial shipping if method sent at create | Sets `shipping_total` via `DeliveryOptionService` |
| `CheckoutShippingService::selectShippingMethod()` L110–121 | Post-create method change | **`grand_total = subtotal + shipping + tax_total - discount_total`** (inline, not shared helper) |
| `DeliveryOptionService` | Zone/method pricing | Flat/free/threshold rules; returns `amount` float |
| `CheckoutShippingService::refreshPaymentIntent()` | After shipping change | Marks old PI `superseded`, creates new PI at new `grand_total` |

**Duplicate source of truth:** `CheckoutService::totals()` vs `CheckoutShippingService` L111 inline formula. Today equivalent when tax/discount are zero; will diverge once tax exists unless unified.

---

## 5. PaymentIntent amount sequence

| Step | File | Method | Source amount |
|------|------|--------|---------------|
| Create | `StripePlatformPaymentProvider.php` | `createPaymentIntent()` L35–36 | `(float) $checkout->grand_total` → `amountMinor()` |
| Store local | `CheckoutService.php` | L244–266 | `PaymentIntent.amount`, `amount_minor` |
| Refresh | `CheckoutShippingService.php` | `refreshPaymentIntent()` L303–308 | New PI from updated `checkout.grand_total` |
| Webhook | `StripePlatformPaymentProvider.php` | `verifyWebhook()` L85 | `fromMinor()` back to float |
| Conversion | `CheckoutConversionService.php` | L65–79 | Capture uses webhook `amount` or PI amount — **no compare to checkout.grand_total** |

**Zero-decimal currencies:** Identical hardcoded list in four places: `CheckoutService::amountMinor()` L527–529, `CheckoutShippingService::amountMinor()` L373–375, `CheckoutConversionService::amountMinor()` L399–403, `StripePlatformPaymentProvider::amountMinor()` L155–159.

**Connect mode:** `requestOptionsForAccount()` L146–152 passes `stripe_account` for connected accounts.

**Retry/idempotency:** Conversion idempotent via `converted_order_id` check L94–101; duplicate webhook returns existing order. PI refresh supersedes prior intents L296–299 rather than updating in place.

---

## 6. Checkout-to-order conversion sequence

`CheckoutConversionService::handleSucceededPayment()` (L30–321):

- Copies checkout header: `subtotal`, `discount_total` → `discount`, `shipping_total` → `shipping`, `tax_total` → `tax`, `grand_total` → `total`/`grand_total` (L125–132).
- Sets `discount_tax=0`, `shipping_tax=0` (L127–129).
- Copies item: `discount_amount`, `tax_amount`, `total` from checkout items (L187–190).
- **Does not** validate `$result->amount === $checkout->grand_total`.
- **Does not** recalculate totals.

---

## 7. External checkout sequence

| Step | File | Method | Notes |
|------|------|--------|-------|
| Auth | `ExternalOrderSyncController` | `store()` | Storefront token |
| Validate | `validatedPayload()` L96–207 | Accepts `tax_total`, `discount_total`, nested `totals.*` |
| Idempotency | `store()` L39–64 | `Idempotency-Key` header + DB record |
| Service idempotency | `ExternalOrderSyncService::resolveExistingExternalOrder()` L329–366 | Request hash replay |
| Totals | `totals()` L473–493 | Priority: `payload.totals.*` > flat fields > computed fallback |
| Items | `sync()` L219–224 | `tax_amount=0`, `discount_amount=0` on lines |
| Order | `sync()` L113–119 | Persists external tax/discount/shipping |

**External is source of truth:** Yes — supplied tax/discount/grand totals stored without platform recalculation.

**Inconsistency accepted:** No validation that `subtotal + shipping + tax - discount ≈ grand_total`. Grand total `0` with missing keys triggers fallback recomputation (`totals()` L482–484).

**Phase 5R-1 change to external:** **None** — preserve supplied snapshots only; optional non-blocking consistency logging deferred.

---

## 8. Draft/manual order sequence

| Step | File | Method | Notes |
|------|------|--------|-------|
| Form | `DraftOrderController::validatedDraftPayload()` L207–248 | `tax_total`, `discount_total`, `shipping_total` nullable numeric |
| Create/update | `DraftOrderService::create/update()` L27–54 | Manual fields via `money()` string formatter |
| Lines | `replaceItems()` L141 | `bcmul` line totals |
| Total | `recalculate()` L147–164 | BCMath: `subtotal + shipping + tax - discount` |
| Convert | `ManualOrderConversionService::convert()` L104–110 | Direct field copy to order |
| Items | L174–189 | `line_total` → subtotal/total; **no** item tax/discount |

**Tax is manual:** Yes — merchant enters `tax_total`; no tax engine.

**Conversion validation:** Store ownership, draft status, customer, address, variants — **no monetary arithmetic checks**.

---

## 9. Database financial-column inventory

| Table | Monetary columns | Type | Migration |
|-------|------------------|------|-----------|
| `checkouts` | subtotal, discount_total, shipping_total, tax_total, grand_total | decimal(14,2) default 0 | `2026_05_12_020000_create_platform_checkout_and_payment_tables.php` |
| `checkout_items` | unit_price, subtotal, discount_amount, tax_amount, total | decimal(14,2) | same |
| `orders` | subtotal, discount, discount_tax, shipping, shipping_tax, tax, total, grand_total, refunded_total, outstanding_total, exchange_rate | decimal(14,2) / rate decimal(10,6) | `2026_05_04_203929_modify_orders_table_for_commerce_core.php` |
| `order_items` | unit_price decimal(12,2), subtotal, discount_amount, tax_amount, total | decimal(14,2) | commerce core migration |
| `draft_orders` | subtotal, discount_total, tax_total, shipping_total, total | decimal(14,2) | `2026_05_09_040000_create_draft_orders_tables.php` |
| `payment_intents` | amount decimal(14,2), amount_minor bigint | platform checkout migration |

No tax-specific tables exist.

---

## 10. Tax/coupon domain inventory

Repository-wide grep (`tax_class`, `taxable`, `is_taxable`, `tax_settings`, `tax_rates`, `prices_include_tax`):

- **Runtime PHP / migrations:** zero implementations (roadmap docs only).
- **No** `TaxService`, tax settings UI, product taxable flag, or tax line tables.

Coupons: no coupon tables or services (Phase 5R-2).

---

## 11. Money and rounding inventory

| Helper | Location | Type | Rounding |
|--------|----------|------|----------|
| `money()` | `CheckoutService` L516–523 | float | `round(..., 2)`, max 0 |
| `money()` | `CheckoutShippingService` L366–369 | float | same |
| `money()` | `ExternalOrderSyncService` L674–681 | float | same |
| `money()` | `DraftOrderService` L225–228 | string | via `(float)` then `number_format` |
| BCMath | `DraftOrderService::recalculate()` L147–164 | string decimal | scale 2 |
| `amountMinor()` | 4 duplicate implementations | int | `round(amount * 100)` or *1 for zero-decimal |

**Duplicate grand-total formulas:** `CheckoutService::totals()`; `CheckoutShippingService` L111; `ExternalOrderSyncService::totals()` fallback; `DraftOrderService::recalculate()`.

**Phase 5R-1 recommendation:** **Option A** — keep decimal columns; new tax/totals code uses BCMath internally; do not migrate to minor-unit integers in 5R-1. **Option D boundary:** full consolidation into one money library → **Phase 5R-3**.

---

## 12. Currency behavior

| Surface | Source | Validation |
|---------|--------|------------|
| Store | `stores.currency` | Used as checkout default L59 |
| Platform checkout | `payload.currency_code` or store currency | Uppercased string |
| Order | Copied from checkout | `exchange_rate = 1` on conversion L122 |
| External | Payload | Accepted per sync validation |
| Draft | Store currency | Implicit |
| Stripe PI | `checkout.currency_code` lowercase | `amountMinor()` zero-decimal list (17 currencies) |

**Phase 5R-1 scope:** Support **store checkout currency** with **two-decimal** and **documented zero-decimal** currencies using the existing Stripe list. Reject unsupported precision in tax calculator input validation. **No FX conversion** in 5R-1.

---

## 13. Address/jurisdiction data

Available at platform checkout create:

- **Required:** `shipping_address.address_line1`, `city`, `country` (`PlatformCheckoutController` L168–178).
- **Optional but present in schema:** `state`, `province_code`, `postal_code`, `country_code`.
- Billing address optional (same-as-shipping default).

**Phase 5R-1 default:** **Destination-based tax using shipping address** (`country_code` + `state`/`province_code`). If `country_code` missing, derive from `country` name mapping or treat as non-calculable (zero tax with merchant-visible warning in API). Tax recalculates when shipping address changes on delivery-options/shipping-method endpoints.

---

## 14. Product taxability status

- **No** `is_taxable`, tax class, or tax code on `products` / `product_variants`.
- Product types (physical/service/digital) exist in product model but **do not** affect tax today.

**Phase 5R-1 minimum:** Add `products.is_taxable` boolean (default true), store-level default taxable behavior, shipping taxable flag on tax settings.

---

## 15. Permissions and store scoping

From `app/Support/StorePermission.php`:

| Role | settings.view | settings.manage |
|------|---------------|-----------------|
| owner | yes | yes |
| manager | yes | **no** |
| staff | yes | **no** |

Existing settings routes use `settings.view` / `settings.manage` (e.g. `routes/web.php` L224–272).

**Phase 5R-1 matrix:**

| Action | owner | manager | staff |
|--------|-------|---------|-------|
| View tax settings | yes | yes | yes (read-only) |
| Edit tax settings / rates | yes | no | no |
| Edit product taxable flag | yes (catalog.manage) | yes | no |

Cross-store: all queries must filter by `store_id` (existing pattern).

---

## 16. UI/settings placement

Current merchant settings cluster:

- `/settings/payments` — Payments & channels
- `/settings/locations` — Locations
- `/shippingAutomation` — Shipping & delivery (zones/methods)

**Recommendation:** **`/settings/taxes`** named route `settings.taxes.index` / `settings.taxes.update` / `settings.taxes.rates.*`, linked from the same settings navigation as Payments and Locations. Section title: **Taxes** — merchant copy: “Configure how tax is calculated for platform checkout.” Disclaimer: basic configurable calculation, not tax advice.

---

## 17. Existing test-coverage matrix

| Concern | Status | Test reference |
|---------|--------|----------------|
| Server platform totals | **Covered** | `Phase5PlatformCheckoutStripeTest` — client inflated totals ignored |
| Client tax ignored | **Partial** | Same test; no explicit `tax_total=0` assert |
| Checkout subtotal | **Covered** | Phase5 create test |
| Shipping change + grand total | **Covered** | `Phase6CheckoutDeliveryMethodsTest::test_selecting_shipping_method_updates...` |
| PaymentIntent update on shipping | **Covered** | Phase6 — supersede + new amount |
| Stripe amount match | **Covered** | Phase5 — amount 24.00 / minor 2400 |
| Checkout conversion snapshots | **Partial** | Phase5 webhook — grand_total only |
| Item tax snapshots | **Missing** | — |
| External supplied tax | **Covered** | `Phase5ExternalCheckoutSyncTest` — `orders.tax=1.50` |
| External discount | **Covered** | Same test |
| External totals preservation | **Covered** | Phase6 external shipping snapshot test |
| Manual draft tax | **Partial** | `Phase4DraftOrderTest` — implied via grand_total 31.00 |
| Money rounding edge cases | **Missing** | — |
| Zero-decimal currency tax | **Missing** | — |
| PI amount vs checkout invariant | **Missing** | No explicit mismatch rejection test |
| Historical immutability | **Missing** | — |
| Cross-store financial isolation | **Covered** | Multiple store isolation tests |

---

## 18. Duplicate sources of truth

1. **Grand total formula** — `CheckoutService` vs `CheckoutShippingService` (critical for 5R-1).
2. **`amountMinor()`** — four copies (maintenance risk for zero-decimal currencies).
3. **`money()` float helpers** — three services + Stripe provider.
4. **Three totals engines** — platform / external / draft (intentional; external/manual remain exceptions).

---

## 19. Risks

| Risk | Severity | Phase 5R-1 mitigation |
|------|----------|------------------------|
| Float drift in platform checkout | Medium | BCMath in new calculator; persist decimal(14,2) |
| Shipping change without tax recalc | High once tax live | Unified totals service invoked from shipping selection |
| PI amount ≠ checkout after tax change | High | Recalculate tax before every PI create/refresh; add invariant test |
| External regression | Medium | Zero behavior change; dedicated preservation tests |
| Manual draft merchant override lost | Low | Keep manual fields; optional calculate action |
| Open checkouts at deploy | Medium | Tax applies on next recalc; document behavior |
| No conversion amount check | Medium | Add explicit compare in 5R-1 (5R-3 hardens further) |

---

## 20. Authoritative path recommendation

**Future single authority (platform checkout):** `CheckoutTotalsService` composing:

1. Line subtotals (from variant prices × qty)
2. Shipping (from `DeliveryOptionService`)
3. Tax (from new `TaxCalculator`)
4. Discount (= 0 in 5R-1)

**Delegates:** `CheckoutService`, `CheckoutShippingService`.

**Stops independent grand total:** `CheckoutShippingService` L111.

**Exceptions:** `ExternalOrderSyncService` (external totals); `DraftOrderService` (manual override + optional calculate).

**5R-1 vs 5R-3 boundary:** 5R-1 adds tax + unified platform formula + snapshots; 5R-3 consolidates float removal, coupon interaction, and strict PI/order invariant enforcement.

---

## 21. Phase 5R-1 prerequisites

- [x] Phase 5R-0 audit complete (this document)
- [x] Baseline test suite green
- [ ] Implementation prompt approved
- [ ] No carrier production work in same slice

---

## 22. Explicit deferred items

- Coupons (5R-2)
- Central money value object / full float purge (5R-3)
- Tax provider APIs, VAT validation, marketplace facilitator
- Customer tax IDs, exemptions, compound tax
- Postal-code range engines
- Historical order recalculation
- Carrier tax
- Refunds/returns (Phase 7)

---

## 23. Exact command/test results (2026-06-24)

| Command | Result |
|---------|--------|
| `git diff --check` | PASS |
| `composer validate --no-check-publish` | PASS |
| `composer dump-autoload` | PASS |
| `php artisan about` | Laravel 12.53.0, PHP 8.2.12, local |
| `php artisan migrate:status` | All migrations Ran |
| `Phase5PlatformCheckoutStripeTest` | **9 passed** (72 assertions) |
| `Phase5StripeConnectFoundationTest` | **12 passed** (60 assertions) |
| `Phase5ExternalCheckoutSyncTest` | **8 passed** (59 assertions) |
| `Phase6CheckoutDeliveryMethodsTest` | **6 passed** (45 assertions) |
| `Phase4DraftOrderTest` | **13 passed** (102 assertions) |
| `ExternalManagedChannelModeTest` | **22 passed** (82 assertions) |
| `EnterpriseQaExternalOrderDedupHardeningTest` | **10 passed** (60 assertions) |
| `--filter=Order` | **80 passed** (517 assertions) |
| Full `php artisan test` | **723 passed, 2 skipped** (3595 assertions) |
| `vendor/bin/pint --test` | **FAIL** — 3 pre-existing style issues (not introduced by this audit): `database/migrations/2026_06_05_010000_extend_carrier_api_events_for_fedex_validation_evidence.php`, `database/migrations/2026_06_05_010100_extend_fedex_validation_artifacts_for_evidence.php`, `tests/Feature/Phase6FedExValidationWorkspaceTest.php` |

**No production PHP, migrations, routes, or tests were modified for this audit.**
