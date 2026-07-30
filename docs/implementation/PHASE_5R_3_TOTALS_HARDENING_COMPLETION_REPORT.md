# Phase 5R-3 — Checkout and Order Totals Hardening Completion Report

**Status:** Complete — 2026-07-23  
**Verification:** `tests/Feature/Phase5R3TotalsHardeningTest.php`, `tests/Feature/Phase5RTaxMigrationRoundTripTest.php`

## Summary

Phase 5R-3 hardens existing money paths for platform checkout, draft/manual conversion, and external order sync. Totals stay on decimal string columns (no Money library, no minor-unit schema migration). Authoritative comparisons use `CurrencyPrecision`, `DecimalString`, and BCMath. Platform checkout conversion rejects inconsistent snapshots; `checkout.totals_mismatch` is audited outside the rolled-back conversion transaction. Explicit external totals remain preserved; platform-coupon line discounts on external sync are decimal-exact and must sum to the platform header discount.

## Files created

- `app/Exceptions/CheckoutTotalsMismatchException.php`
- `app/Services/Checkout/FinancialTotalsInvariantService.php`
- `tests/Feature/Phase5R3TotalsHardeningTest.php`
- `docs/implementation/PHASE_5R_3_TOTALS_HARDENING_COMPLETION_REPORT.md`

## Files modified (core)

- `app/Data/Payments/PaymentIntentResult.php`
- `app/Data/Payments/PaymentWebhookResult.php`
- `app/Http/Controllers/Api/PlatformCheckoutController.php`
- `app/Http/Controllers/Api/StripeConnectWebhookController.php`
- `app/Http/Controllers/Commerce/DraftOrderController.php`
- `app/Services/CheckoutConversionService.php`
- `app/Services/CheckoutService.php`
- `app/Services/Draft/DraftTaxService.php`
- `app/Services/DraftOrderService.php`
- `app/Services/ExternalOrderSyncService.php`
- `app/Services/ManualOrderConversionService.php`
- `app/Services/Payments/StripePlatformPaymentProvider.php`
- `app/Services/Shipping/CheckoutShippingService.php`
- `app/Services/Shipping/DeliveryOptionService.php`
- `database/migrations/2026_07_17_200138_add_product_image_id_to_product_variants_table.php` (SQLite-safe `down()` for Phase 5R migration gate)

Related checkout/payment/coupon tests were updated for string PaymentIntent amounts and decimal-safe assertions.

## What shipped

### Financial invariants

`FinancialTotalsInvariantService` validates checkout and order snapshot consistency:

- line subtotals / discounts / taxes roll up to header totals
- item row totals match exclusive/inclusive tax mode
- tax lines sum to `tax_total`
- grand total matches the deterministic formula for the checkout currency
- order snapshots must match the paid checkout on conversion

Mismatch raises `CheckoutTotalsMismatchException` and records `checkout.totals_mismatch` after conversion rollback so the event survives transaction abort (including under `RefreshDatabase` outer transactions).

### Platform checkout

- Store currency must match checkout currency
- Nested shipping currency mismatches are rejected
- PaymentIntent amount stays synchronized with authoritative major/minor amounts
- Coupon apply/remove and shipping/address recalculation continue through `CheckoutTotalsService` with decimal-safe comparisons

### Draft / manual

- Calculated draft tax applies coupon line discounts before tax
- Coupon changes that invalidate a tax snapshot block conversion cleanly
- Manual tax mode remains unchanged when a coupon is applied

### External sync

- Explicit external totals remain authoritative and do not create PaymentIntents
- Platform coupons (`discount_calculation=platform`) allocate line discounts with BCMath / `CurrencyPrecision`
- Line discount allocations must exactly equal the platform-calculated header discount
- Deterministic fallback grand total when external grand total is omitted
- Nested currency mismatches are rejected

## Correction pass (2026-07-23)

Minimal follow-up hardening (no new phase/architecture/migration):

- `assertPaymentAmountsMatch` independently converts local `paymentIntent.amount` to minor units and compares with checkout expected minor (keeps `amount_minor` + provider checks); audit context includes `local_payment_intent_amount_as_minor`.
- Draft authoritative money ops use draft/store currency with BCMath scale 6 + `CurrencyPrecision::roundMajor` (no hardcoded scale-2 totals).
- Draft coupon conversion requires header discount, snapshot discount, allocation sum, and fresh coupon result to agree.
- `assertOrderMatchesCheckout` also compares `order.total`, `order.shipping_tax`, and persisted tax snapshot.
- External shipping amount presence check uses order currency (not hardcoded USD).

Additional regressions: `test_jpy_draft_create_update_and_conversion_use_zero_decimal_money`, `test_tampered_draft_header_discount_blocks_conversion_without_side_effects`; corrected `test_local_payment_intent_decimal_mismatch_blocks_conversion`.

## Out of scope (by design)

- Money value-object library / integer minor-unit column migration
- FX conversion
- Stackable multi-coupon carts
- Correcting or recalculating explicit external totals
- FedEx carrier behavior (unchanged; failures proven pre-existing on `6bfa366`)

## Acceptance gate

| Gate | Result |
|------|--------|
| Platform checkout totals are server-authoritative | **Met** |
| Stripe amount and checkout amount match | **Met** |
| Order snapshots match the final checkout | **Met** |
| External checkout contracts remain unchanged for explicit totals | **Met** |
| External platform-coupon line discounts are decimal-exact | **Met** |
| `checkout.totals_mismatch` persists after conversion rollback | **Met** |
| Phase 5R tax migration round-trip | **Met** (`Phase5RTaxMigrationRoundTripTest`) |
| Phase 5R marked complete | **Met** (5R-0 through 5R-3) |

## Verification evidence

- Focused: `Phase5R3TotalsHardeningTest` + `Phase5RTaxMigrationRoundTripTest` + related draft/coupon/payment filters — **81 passed**
- Full suite (2026-07-23 local correction pass): **1414 passed**, 2 skipped, **4 failed**
  - Three FedEx UI assertion failures (pre-existing on `6bfa366`; FedEx code not changed)
  - One Delivery UX Stripe DNS connectivity flake
- Builds: root `npm run build` and `dev-test-storefront` `npm run build` — **passed**
- `git diff --check` — **clean**
- `docs/plans/PHASE_9_INTEGRATION_FOUNDATION_PLAN.md` — **unchanged** (5R-3 edits reverted)

## Key regression tests

- `test_external_platform_coupon_line_discounts_are_decimal_exact_and_sum_to_header`
- `test_totals_mismatch_event_persists_after_conversion_transaction_rolls_back`
- `test_tampered_item_subtotal_blocks_conversion_even_when_grand_total_matches_pi`
- `test_successful_order_exactly_matches_checkout_snapshots`
- `test_slice_1a_migrations_round_trip_on_isolated_file_sqlite`

## Next phase

Phase 5R is complete. Per `ENTERPRISE_ROADMAP_2026.md`, next carrier-independent workstreams are Phase 7 (returns/refunds/exchanges) and/or the already-reprioritized Phase 9 Integration Foundation — without reopening 5R-3 scope.
