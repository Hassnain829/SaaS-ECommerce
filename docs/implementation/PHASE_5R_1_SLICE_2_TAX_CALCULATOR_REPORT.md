# Phase 5R-1 Slice 2 — CurrencyPrecision and TaxCalculator Report

**Date:** 2026-06-24  
**Status:** Implemented (calculation engine only — not wired to checkout) — closeout corrections applied 2026-06-24  
**Phase 5R-1 overall:** In progress — checkout tax on **create** implemented in Slice 3; shipping recalc and conversion pending

---

## Scope implemented

- `App\Support\Money\CurrencyPrecision` — canonical exponent, half-up rounding, minor-unit conversion
- `App\Support\Money\DecimalString` — shared plain-decimal validation (no scientific notation)
- Immutable tax calculation DTOs under `app/Data/Tax/`
- `App\Services\Tax\TaxCalculator` — read-only, store-scoped jurisdiction matching and tax allocation
- `tests/Unit/CurrencyPrecisionTest.php` (20 tests)
- `tests/Unit/TaxCalculatorTest.php` (47 tests)
- Composer runtime requirement: `ext-bcmath` (lock `platform` synchronized)

## Not implemented (deferred)

- CheckoutTotalsService and checkout create wiring (**Slice 3 — implemented**)
- Shipping method recalculation + PI refresh (**Slice 4**)
- Checkout tax-line replacement on shipping change (**Slice 4**)
- Product taxable UI/resolver (Slice 6)
- Draft tax (Slice 7)
- External preservation sign-off (Slice 8)

---

## CurrencyPrecision API

| Method | Purpose |
|--------|---------|
| `exponent()` / `scale()` | 0 for zero-decimal currencies, else 2 |
| `zeroDecimalCurrencies()` | Canonical 16-currency list |
| `isZeroDecimal()` | Case-insensitive lookup |
| `roundMajor()` | Half-up rounding to currency scale |
| `toMinorUnits()` / `fromMinorUnits()` | Major ↔ minor conversion without floats |

Half-up examples: USD `1.005 → 1.01`, JPY `100.5 → 101`. Unknown currencies default to exponent 2. Empty currency or invalid amounts throw `InvalidArgumentException`. Overflow on minor conversion is rejected.

---

## Tax DTO contracts

| Class | Role |
|-------|------|
| `TaxAddressInput` | Normalized destination country/region |
| `TaxLineItemInput` | Line key, quantity, unit price, taxable flag |
| `TaxCalculationRequest` | Store, settings, currency, items, shipping, destination |
| `MatchedTaxRate` | Immutable matched-rate snapshot |
| `ItemTaxAllocation` | Per-line subtotal, taxable base, tax |
| `TaxLineOutput` | Aggregated items/shipping tax line snapshot |
| `TaxCalculationResult` | Totals, allocations, tax lines, skip metadata |

---

## Jurisdiction matching

Priority: active regional rate → active country-wide (`region_code = ''`) → no match (zero tax).

Inactive regional rates do not block country-wide fallback. Store-scoped queries only. No postal/origin matching. One matched rate per calculation.

---

## Calculation behavior

| Scenario | Behavior |
|----------|----------|
| Tax disabled | Zero tax, allocations with line subtotals, no tax lines |
| Missing country | Zero tax, `taxCalculationSkipped = true`, `skipReason = missing_country` |
| No matching rate | Zero tax, not skipped |
| Exclusive prices | Tax added on taxable line subtotals |
| Inclusive prices | Tax extracted from gross; line subtotal stays gross (e.g. 22.00 @ 10% → 2.00 tax, 20.00 net base) |
| Shipping | Always exclusive; shipping tax line when taxable and rounded shipping amount > 0 (including 0% rate snapshots with `taxAmount = 0`) |

Line-level rounding via `CurrencyPrecision::roundMajor()`; aggregates summed then re-rounded.

---

## Closeout corrections (2026-06-24)

1. **0% shipping snapshot** — shipping tax line emitted when rate matched, shipping taxable, and rounded shipping > 0; `taxAmount` may be zero.
2. **TaxLineItemInput** — trims and stores canonical `lineKey` / `unitPrice`; rejects scientific notation.
3. **DecimalString** — shared plain-decimal validation in `CurrencyPrecision`, `TaxLineItemInput`, and `TaxCalculationRequest` shipping amount.
4. **composer.lock** — `platform.ext-bcmath` synchronized via `composer update --lock --no-install`.

---

## Tests and verification

| Command | Result |
|---------|--------|
| `php artisan test --filter=CurrencyPrecisionTest` | 20 passed |
| `php artisan test --filter=TaxCalculatorTest` | 47 passed |
| `php artisan test --filter=Tax` | Pass (includes Slice 1B feature tests) |
| `php artisan test` | **860 passed**, 2 skipped (4062 assertions) |
| Pint (Slice 2 PHP files) | Pass |

Baseline before Slice 2: **793 passed**, 2 skipped (3874 assertions).

---

## Confirmations

- TaxCalculator **not** wired to checkout, Stripe, or orders
- No checkout/order tax-line writes from calculator
- No carrier/admin changes
- Model A unchanged
- No commit made

**Next:** Slice 4 — shipping selection recalculation + PaymentIntent refresh
