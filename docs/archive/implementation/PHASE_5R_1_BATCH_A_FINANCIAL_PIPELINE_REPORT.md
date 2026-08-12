# Phase 5R-1 Batch A Financial Pipeline Report

**Date:** 2026-06-25
**Status:** Implemented
**Phase 5R-1 overall:** Complete after Batch B. Batch A completed the former Slice 4 and Slice 5 work; Batch B completed product taxable defaults, draft/manual calculated tax, external checkout preservation, and final regression. Final report: `docs/implementation/PHASE_5R_1_BATCH_B_FINAL_COMPLETION_REPORT.md`.

---

## Summary

Batch A hardened the platform checkout financial pipeline without changing external checkout, manual draft orders, products, carriers, or admin behavior.

Implemented:

- shipping selection/address mutation now recalculates checkout item tax, checkout tax lines, checkout header totals, and `metadata.tax_snapshot`;
- active platform PaymentIntents are synchronized to the recalculated grand total and currency;
- local platform checkout minor-unit helpers were replaced with `CurrencyPrecision`;
- checkout-to-order conversion now hard-fails on amount or currency mismatch before mutating order, payment, capture, or inventory state;
- order tax snapshots are copied from checkout snapshots and are never recalculated at conversion.

---

## Shipping Recalculation Architecture

`CheckoutShippingService::selectShippingMethod()` is now the explicit mutation boundary for delivery/address changes. It runs inside one database transaction and locks the checkout row.

The transaction sequence is:

1. lock the payment-pending checkout;
2. merge submitted shipping address fields with the current persisted shipping address;
3. resolve the selected delivery method and fulfillment origin;
4. retarget inventory reservations to the routed origin;
5. persist submitted shipping and same-as-shipping billing address data;
6. lock the store `TaxSetting`;
7. call `CheckoutTotalsService::calculateForCheckout()`;
8. update each checkout item tax/subtotal/total snapshot;
9. replace `checkout_tax_lines`;
10. update checkout header totals, shipping snapshot, tax snapshot, and routing snapshot;
11. record checkout events;
12. synchronize the active PaymentIntent.

Any exception in this sequence rolls back address, tax-line, item, checkout total, and payment-intent local changes.

---

## Persisted Item Pricing Decision

Recalculation intentionally uses persisted checkout item snapshots rather than live catalog prices.

`CheckoutTotalsService::calculateForCheckout()` reads:

- `checkout_items.product_variant_id` for the stable line key;
- `checkout_items.quantity`;
- `checkout_items.unit_price`;
- `checkout_items.metadata.tax.is_taxable`.

It does not reload live product prices or live product taxability. This keeps open checkout financial snapshots stable even if a store owner edits catalog pricing or product taxability after the checkout was created.

---

## Tax-Line Replacement

`CheckoutTotalsService::replaceTaxLines()` remains the tax-line persistence boundary. During shipping/address mutation, stale `checkout_tax_lines` are deleted and replaced in the same transaction as checkout/item total updates.

The checkout metadata tax snapshot is also replaced with the recalculated result.

GET/show endpoints remain read-only and do not recalculate or replace tax lines.

---

## Address And Rate Recalculation

When a shipping-selection request includes address fields, the submitted address is first merged with the persisted checkout shipping address. The merged destination is used for:

- delivery method eligibility;
- fulfillment origin routing;
- tax jurisdiction normalization;
- persisted checkout address rows.

Changing the destination from one jurisdiction to another recalculates tax using the current store tax settings at that mutation boundary.

---

## PaymentIntent Synchronization

`CheckoutShippingService::refreshPaymentIntent()` loads the latest checkout PaymentIntent (any status), applies an explicit mutation policy, and uses `CurrencyPrecision::toMinorUnits()` for comparisons.

### Mutable statuses (amount update allowed)

- `requires_payment_method`
- `requires_confirmation`

No-op when local amount and currency already match the checkout total.

### Blocked statuses (shipping/address financial mutation rejected)

- `requires_action`
- `processing`
- `requires_capture`
- `succeeded`
- any unknown/unrecognized provider status

### Terminal statuses (no replacement intent created)

- `canceled`
- `failed`
- `superseded`

Terminal and blocked states return a deterministic validation error and require checkout/payment restart rather than creating ambiguous duplicate payment state.

### Update-in-place behavior

- if amount changed but currency matches, the existing remote Stripe PaymentIntent is updated through `PaymentProviderInterface::updatePaymentIntentAmount()`;
- the same local `payment_intents` row, `provider_intent_id`, and `client_secret` are preserved;
- `cancelPaymentIntent()` is not used for normal amount-only shipping recalculation;
- if local PaymentIntent currency differs from checkout currency, the mutation is rejected;
- if no PaymentIntent has ever been created, the initial create path is used.

### Provider response validation

`StripePlatformPaymentProvider::updatePaymentIntentAmount()` reads actual Stripe response fields (`id`, `amount`, `currency`, `status`, `client_secret`) into `PaymentIntentUpdateResult` — request arguments are not echoed back as proof.

Before local persistence, `CheckoutShippingService` verifies:

1. returned provider intent ID equals the existing local `provider_intent_id`;
2. returned amount minor equals expected checkout amount minor;
3. returned currency equals checkout/local currency;
4. returned status remains mutable;
5. returned client secret, when present, matches the existing local secret.

Any mismatch throws `CheckoutPaymentSynchronizationException`, records `payment.sync_failed`, and rolls back the enclosing shipping/tax transaction.

---

## CurrencyPrecision Replacements

Removed local platform checkout minor-unit helpers and zero-decimal currency lists from:

- `CheckoutService`;
- `CheckoutShippingService`;
- `CheckoutConversionService`;
- `StripePlatformPaymentProvider`;
- `StripeConnectWebhookController`.

Those paths now delegate to `App\Support\Money\CurrencyPrecision`.

---

## Conversion Invariant

`CheckoutConversionService::handleSucceededPayment()` now verifies amounts before it mutates order, payment capture, checkout, or inventory state.

It compares:

- checkout `grand_total` converted to minor units;
- local `payment_intents.amount_minor`;
- provider-confirmed amount minor units;
- checkout currency;
- local payment intent currency;
- provider-confirmed currency.

Any mismatch throws `CheckoutPaymentAmountMismatchException`.

---

## Mismatch Event And Exception

When the invariant fails, the transaction rolls back and a safe checkout event is recorded outside the transaction:

- event type: `payment.amount_mismatch`;
- title: `Payment total mismatch`;
- metadata: checkout id, expected/provider/local minor amounts, currencies, and provider intent id.

No order is created, no inventory is deducted, no payment capture is written, and the checkout remains unconverted.

---

## Order Snapshot Mappings

On successful conversion, order tax data is copied from checkout state:

- `orders.tax` from `checkouts.tax_total`;
- `orders.shipping_tax` from checkout tax lines where `applies_to = shipping`;
- `orders.meta.tax_snapshot` from `checkouts.metadata.tax_snapshot`;
- `order_items.tax_amount` from `checkout_items.tax_amount`;
- `order_tax_lines` copied field-for-field from `checkout_tax_lines`.

Tax is not recalculated during conversion.

---

## Tests Added

Added `tests/Feature/PlatformCheckoutShippingTaxRecalculationTest.php`:

- taxable shipping recalculates checkout tax lines and PaymentIntent amount;
- switching to free shipping removes stale shipping tax and can no-op PaymentIntent sync;
- shipping mutation uses persisted checkout item pricing and taxability;
- submitted address changes persist and recalculate jurisdiction;
- provider sync failure rolls back local checkout/tax changes;
- JPY shipping recalculation uses zero-decimal minor units.

Added `tests/Feature/CheckoutPaymentInvariantTest.php`:

- provider amount mismatch fails before order creation or inventory deduction;
- local PaymentIntent amount mismatch fails;
- provider currency mismatch fails;
- successful conversion copies item tax and tax-line snapshots;
- order tax snapshots remain immutable after tax settings change;
- JPY conversion uses zero-decimal minor units;
- repeated mismatch callbacks remain state-idempotent.

---

## Verification Results

Final verification after PaymentIntent status-policy and provider-response hardening (2026-06-25):

| Command | Result |
| --- | --- |
| `php artisan test --filter=PlatformCheckoutShippingTaxRecalculationTest` | 18 passed, 178 assertions |
| `php artisan test --filter=StripePlatformPaymentProviderTest` | 2 passed, 9 assertions |
| `php artisan test --filter=Phase6CheckoutDeliveryMethodsTest` | 6 passed, 44 assertions |
| `php artisan test --filter=CheckoutPaymentInvariantTest` | 9 passed, 61 assertions |
| `php artisan test --filter=Phase5PlatformCheckoutStripeTest` | 9 passed, 72 assertions |
| `php artisan test --filter=Tax` | 182 passed, 944 assertions |
| `php artisan test` | 932 passed, 2 skipped, 4618 assertions |
| `vendor/bin/pint --test` on touched PaymentIntent hardening PHP files | Passed, 10 files |
| `git diff --check` | Passed |
| `composer validate --strict --no-check-publish` | Passed |

Historical Batch A implementation verification (superseded counts — retained for audit trail only):

| Command | Result |
| --- | --- |
| `php artisan test` during initial Batch A delivery | 900 passed, 2 skipped, 4343 assertions |

Repository-wide `vendor/bin/pint --test` may still fail on three pre-existing carrier-validation files outside Batch A scope:

- `database/migrations/2026_06_05_010000_extend_carrier_api_events_for_fedex_validation_evidence.php`
- `database/migrations/2026_06_05_010100_extend_fedex_validation_artifacts_for_evidence.php`
- `tests/Feature/Phase6FedExValidationWorkspaceTest.php`

---

## Files Changed

Production:

- `app/Services/Checkout/CheckoutTotalsService.php`
- `app/Services/Shipping/CheckoutShippingService.php`
- `app/Services/CheckoutConversionService.php`
- `app/Services/CheckoutService.php`
- `app/Data/Payments/PaymentIntentUpdateResult.php`
- `app/Exceptions/CheckoutPaymentSynchronizationException.php`
- `app/Contracts/Payments/PaymentProviderInterface.php`
- `app/Services/Payments/StripePlatformPaymentProvider.php`
- `app/Http/Controllers/Api/StripeConnectWebhookController.php`
- `app/Exceptions/CheckoutPaymentAmountMismatchException.php`

Tests:

- `tests/Feature/PlatformCheckoutShippingTaxRecalculationTest.php`
- `tests/Unit/StripePlatformPaymentProviderTest.php`
- `tests/Feature/CheckoutPaymentInvariantTest.php`

Docs:

- `docs/implementation/PHASE_5R_1_BATCH_A_FINANCIAL_PIPELINE_REPORT.md`
- `docs/implementation/PHASE_5R_1_SLICE_3_CHECKOUT_TOTALS_REPORT.md`
- `docs/plans/PHASE_5R_1_TAX_FOUNDATION_IMPLEMENTATION_PLAN.md`
- `ENTERPRISE_PROJECT_CONTEXT.md`
- `ENTERPRISE_ROADMAP_2026.md`
- `PROJECT_BRAIN.md`
- `README.md`
- `docs/RELEASE_CHECKLIST.md`

---

## Unchanged Areas

- External checkout sync remains externally calculated and was not recalculated.
- Draft/manual order tax behavior remains unchanged in Batch A.
- Product creation/import taxable defaults remain unchanged in Batch A.
- Carrier, shipping API, FedEx, USPS, admin, billing, returns, refunds, coupons, and marketplace work were not changed.

---

## Remaining Deferrals

- Phase 5R-2: coupons and discount rules.
- Phase 5R-3: broader checkout/order totals hardening and remaining float boundary cleanup.

---

## Final Batch A Status

Complete. The platform checkout mutation and conversion pipeline now recalculates, synchronizes, verifies, and snapshots tax/payment totals at the critical financial boundaries.
