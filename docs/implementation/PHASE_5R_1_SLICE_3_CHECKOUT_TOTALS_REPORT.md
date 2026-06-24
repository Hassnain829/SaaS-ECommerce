# Phase 5R-1 Slice 3 — CheckoutTotalsService and Platform Checkout Creation Report

**Date:** 2026-06-24  
**Status:** Implemented — platform checkout **create** path only  
**Phase 5R-1 overall:** In progress — Slices 1A, 1B, 2, and **3** complete; Slices 4–8 pending

---

## Scope implemented

- `App\Services\Checkout\CheckoutTotalsService` — authoritative totals boundary for platform checkout creation
- Typed DTOs: `CheckoutTotalsResult`, `CheckoutItemTotals`
- `CheckoutService` platform-create path delegates to `CheckoutTotalsService` + `TaxCalculator`
- Checkout header totals, item tax allocations, `checkout_tax_lines`, and `metadata.tax_snapshot` persisted atomically on create
- Initial `PaymentIntent` receives taxed `checkout.grand_total` (provider code unchanged)
- GET/show remains read-only (no recalculation)
- Initial shipping method on create includes shipping tax when configured
- `tests/Feature/PlatformCheckoutTaxTest.php` (21 tests)
- `tests/Unit/CheckoutTotalsServiceTest.php` (2 tests)

## Not implemented (deferred)

- Shipping method **change** recalculation (Slice 4)
- PaymentIntent refresh after address/shipping mutation (Slice 4)
- `CurrencyPrecision` in `StripePlatformPaymentProvider` / `CheckoutShippingService` (Slice 4/5)
- Conversion amount invariant + order tax snapshots (Slice 5)
- Product taxable default resolver / UI (Slice 6)
- Draft calculated tax (Slice 7)
- External preservation sign-off (Slice 8)

---

## Authoritative totals architecture

```
Platform checkout CREATE:
  prepare items (merge variants, DB prices, product.is_taxable)
    → load TaxSetting (fail if missing — no auto-provision)
    → itemsSubtotal() for shipping option lookup
    → resolve optional initial shipping method
    → CheckoutTotalsService::calculate()
         → TaxCalculator (BCMath + CurrencyPrecision)
    → persist checkout header + metadata.tax_snapshot
    → persist checkout items (subtotal, tax_amount, total, item metadata.tax)
    → replaceTaxLines() → checkout_tax_lines
    → create PaymentIntent (existing provider, taxed grand_total)
```

GET/show loads persisted snapshots only — never calls `CheckoutTotalsService` or `TaxCalculator`.

---

## Files created

| File | Purpose |
|------|---------|
| `app/Services/Checkout/CheckoutTotalsService.php` | Totals authority |
| `app/Data/Checkout/CheckoutTotalsResult.php` | Header + allocations result |
| `app/Data/Checkout/CheckoutItemTotals.php` | Per-line totals |
| `tests/Feature/PlatformCheckoutTaxTest.php` | Slice 3 feature coverage |
| `tests/Unit/CheckoutTotalsServiceTest.php` | USD/JPY service fixtures |

## Files modified

| File | Change |
|------|--------|
| `app/Services/CheckoutService.php` | Inject `CheckoutTotalsService`; create path uses authoritative totals; removed legacy `totals()`/`subtotal()` |
| `app/Http/Controllers/Api/PlatformCheckoutController.php` | Response items include `subtotal`, `discount_amount`, `tax_amount`, `total` (additive, backward-compatible) |

---

## CheckoutTotalsService API

| Method | Role |
|--------|------|
| `lineKeyForVariant(int $variantId): string` | Deterministic key `variant:{id}` |
| `itemsSubtotal(string $currencyCode, array $preparedItems): string` | Preliminary subtotal for shipping eligibility (BCMath) |
| `calculate(Store, TaxSetting, currency, items, shippingTotal, shippingAddress, ?calculatedAt): CheckoutTotalsResult` | Pure calculation — no DB writes |
| `replaceTaxLines(Checkout, CheckoutTotalsResult): void` | Delete-then-insert scoped tax lines |
| `normalizeDestinationFromAddress(array $shippingAddress): TaxAddressInput` | Country/region normalization |

Dependencies: `TaxCalculator`, `CurrencyPrecision`, `DecimalString` only — no HTTP, Stripe, or carrier services.

---

## Typed totals DTOs

**`CheckoutItemTotals`:** `lineKey`, `subtotal`, `discountAmount`, `taxAmount`, `total`, `isTaxable` — all money as decimal strings.

**`CheckoutTotalsResult`:** `storeId`, `subtotal`, `discountTotal`, `shippingTotal`, `itemsTax`, `shippingTax`, `taxTotal`, `grandTotal`, `pricesIncludeTax`, `itemTotals` (keyed by line key), `taxLines`, `taxSnapshot`, `calculatedAt`.

---

## Destination normalization

Country priority:

1. `shipping_address.country_code` (normalized uppercase ISO-2)
2. `shipping_address.country` only when exactly two alphabetic characters
3. Otherwise blank → `tax_calculation_skipped: true`, `skip_reason: missing_country`

Region priority:

1. `shipping_address.province_code`
2. `shipping_address.state`
3. Empty string

---

## TaxSetting missing behavior

Checkout creation loads the store's existing `TaxSetting`. Does **not** call `ensureSettingsForStore()`. Missing settings throw a validation/domain failure before checkout, reservations, tax lines, or PaymentIntent are created.

---

## Locked grand-total formulas

**Discount (Slice 3):** always zero at currency precision.

**Tax-exclusive:** `grandTotal = subtotal + shippingTotal + taxTotal - discountTotal`

**Tax-inclusive:** `grandTotal = subtotal + shippingTotal + shippingTax - discountTotal` (extracted item tax not added again)

---

## Item persistence semantics

| Mode | `subtotal` | `tax_amount` | `total` |
|------|------------|--------------|---------|
| Exclusive | net line | calculated tax | subtotal + tax_amount |
| Inclusive | gross line | extracted tax | subtotal (gross) |

Item `metadata.tax`: `{ is_taxable, prices_include_tax, settings_version }` — preserves `reservation_id`.

---

## Checkout header semantics

Persisted from `CheckoutTotalsResult`: `subtotal`, `discount_total`, `shipping_total`, `tax_total`, `grand_total`. Client-supplied totals ignored. `tax_total` includes item tax + shipping tax.

---

## Metadata tax snapshot

Stored at `checkouts.metadata.tax_snapshot`:

```json
{
  "enabled": true,
  "prices_include_tax": false,
  "shipping_taxable": true,
  "settings_version": 4,
  "destination": { "country_code": "US", "region_code": "CA" },
  "matched_rate": { "tax_rate_id": 15, "country_code": "US", "region_code": "CA", "rate_percent": "8.2500", "priority": 100 },
  "tax_calculation_skipped": false,
  "skip_reason": null,
  "calculated_at": "2026-06-24T12:00:00+00:00"
}
```

Existing metadata keys are preserved (merge, not overwrite root metadata).

---

## Tax-line replacement

`replaceTaxLines()` deletes `checkout_tax_lines` for `(store_id, checkout_id)` then inserts fresh rows from calculator output with shared `calculated_at`. Zero-percent shipping lines preserved when applicable. No `order_tax_lines` writes.

---

## Initial shipping tax (create only)

When `shipping_method_id` is supplied at create: authoritative shipping amount included in calculation; shipping tax line persisted when `shipping_taxable` is true (including 0% rate snapshots). Grand total and PaymentIntent reflect shipping + shipping tax.

Shipping **selection change** after create remains Slice 4 (`CheckoutShippingService` untouched).

---

## Initial PaymentIntent behavior

`StripePlatformPaymentProvider` unchanged. `CheckoutService` creates PaymentIntent **after** authoritative totals are persisted; provider receives `checkout.grand_total` including tax.

---

## Read-only GET behavior

`PlatformCheckoutController::show` returns persisted totals only. Tax settings/rate changes do not alter open checkout snapshots. Tests verify no DB writes on GET.

---

## Remaining float boundaries (Phase 5R-3)

| Location | Temporary float use |
|----------|---------------------|
| `CheckoutService::money()` | Cast to float for Eloquent decimal columns |
| `CheckoutService::amountMinor()` | PI persistence helper |
| `DeliveryOptionService` boundary | `itemsSubtotal()` cast to float for shipping eligibility lookup |
| `CheckoutShippingService` | Unchanged — still uses inline grand_total on select (Slice 4) |

Legacy `CheckoutService::totals()` and `subtotal()` removed; create path uses `CheckoutTotalsService` only.

---

## Verification results

**Baseline (pre-Slice 3):** 860 passed, 2 skipped, 4062 assertions

**After Slice 3:**

| Command | Result |
|---------|--------|
| `php artisan test --filter=PlatformCheckoutTaxTest` | 21 passed (152 assertions) |
| `php artisan test --filter=CheckoutTotalsServiceTest` | 2 passed (9 assertions) |
| `php artisan test --filter=Phase5PlatformCheckoutStripeTest` | 9 passed |
| `php artisan test --filter=Phase6CheckoutDeliveryMethodsTest` | 6 passed |
| `php artisan test --filter=TaxCalculatorTest` | (included in full suite) |
| `php artisan test --filter=CurrencyPrecisionTest` | (included in full suite) |
| `php artisan test` | **883 passed**, 2 skipped, **4223 assertions** |
| `composer validate --strict --no-check-publish` | Pass |
| `vendor/bin/pint --test` (Slice 3 PHP files) | Pass |
| `php -l` (all Slice 3 PHP files) | Pass |

**Net change:** +23 tests, +161 assertions vs pre-Slice 3 baseline.

---

## Closeout corrections (2026-06-24)

- Removed dead `CheckoutService::totals()` and `subtotal()` methods
- Removed unused `unit_price` / `subtotal` from `prepareItems()` prepared-item shape
- Hardened `shipping_address.country_code` validation to ISO-2 alpha only on create, delivery-options, and shipping-selection endpoints
- Hardened `CheckoutTotalsService::normalizeDestinationFromAddress()` to reject invalid `country_code` values (e.g. `USA`) before jurisdiction matching

---

## Known limitations

- Tax applies only during **new** platform checkout creation
- Later shipping/address changes do not recalculate tax yet
- Conversion amount invariant not implemented
- Order tax snapshots not implemented
- External checkout and draft/manual orders unchanged
- Coupons/discounts remain zero
- No carrier or admin changes

---

## Next

**Slice 4** — Shipping selection recalculation + PaymentIntent refresh via `CheckoutTotalsService` and `CurrencyPrecision` in payment consumers.

**Model A** remains the primary carrier architecture; carrier production work frozen.
