# Phase 5R-1 — Tax Settings and Tax Calculation Foundation

**Project:** E_COMMERCE_OFFICE (SaaS-Static-Blade)  
**Date:** 2026-06-24 (original plan)  
**Plan lock corrections:** 2026-06-24
**Status:** **In progress — Slices 1A, 1B, 2, 3, 4, and 5 implemented**; slices 6–8 **not implemented**
**Prerequisite:** `docs/audit/PHASE_5R_0_CURRENT_CALCULATION_AUDIT.md` (completed 2026-06-24)

This document is the **corrected and locked** implementation plan for Phase 5R-1. All architectural choices below are final for implementation prompts. Deferrals are explicitly assigned to later phases.

**Planning-only constraint:** Slices 1A–5 are implemented. Batch A completed shipping recalculation, PaymentIntent synchronization, conversion amount invariants, and order tax snapshots. Slices 6–8 remain pending.

---

## Locked architecture decisions (summary)

| # | Decision | Locked rule |
|---|----------|-------------|
| 1 | Tax rate uniqueness | `region_code` is `string(32) NOT NULL DEFAULT ''`; empty string = country-wide; normalize null/blank → `''`; uppercase non-empty regions; unique `(store_id, country_code, region_code)` on SQLite and MySQL |
| 2 | Inclusive tax semantics | `checkout_items.subtotal` and `checkouts.subtotal` remain **gross** catalog amounts; tax is **extracted**; `checkout_tax_lines.taxable_amount` = net base; extracted tax is **not** added to grand total again |
| 3 | Currency precision | Shared `App\Support\Money\CurrencyPrecision` is the single source for exponent, rounding scale, minor-unit conversion, and zero-decimal currencies |
| 4 | Payment amount invariant | **Always fail** in every environment when provider amount, local PI amount, checkout grand total, or currency mismatch before conversion |
| 5 | Open checkout recalculation | Read-only GET/show **never** mutates totals; recalc only on explicit mutation boundaries |
| 6 | Product taxable default | Existing products backfill `true`; all creation paths use `tax_settings.default_product_taxable`; imports must not hardcode `true` when store default is `false` |
| 7 | Settings version | `settings_version` increments atomically in the **same transaction** as every settings/rate mutation |
| 8 | Tax line schema | Full FK/index/timestamp rules; checkout recalc **replaces** tax lines atomically; order tax lines copied at conversion, never recalculated |
| 9 | Draft calculated tax | Item-level allocation via `draft_order_items.tax_amount` + `draft_tax_lines`; `tax_source` distinguishes manual vs calculated |
| 10 | Implementation slices | Eight small slices: **1A, 1B, 2, 3, 4, 5, 6, 7, 8** (see §9.15) |

---

## 9.1 Scope

### In scope

- Store-level tax enable/disable
- Prices entered **inclusive** or **exclusive** of tax (with locked persistence semantics)
- Default taxable behavior for new products (all creation paths)
- Basic country + region/state tax rates (percentage)
- Product `is_taxable` flag
- Shipping taxable/non-taxable at store level (shipping always tax-exclusive in 5R-1)
- Deterministic tax calculation (BCMath, half-up to currency scale)
- Shared `CurrencyPrecision` boundary (eliminate duplicate `amountMinor()` lists in 5R-1)
- Platform checkout integration via unified `CheckoutTotalsService`
- Shipping-method recalculation integration
- PaymentIntent amount synchronization after tax/shipping changes
- Checkout tax snapshot (header + tax lines + metadata)
- Order and order-item tax snapshot on conversion
- **Hard-fail** payment amount invariant before conversion (all environments)
- External checkout **preservation** (no platform recalculation)
- Manual/draft order manual override + optional “Calculate tax from store settings” with item allocation
- Permissions (`settings.view` / `settings.manage`, `catalog.manage` for product flag)
- Merchant tax settings UI at `/settings/taxes`
- Characterization tests before production code
- Documentation updates

### Explicitly excluded

- Coupons (Phase 5R-2)
- Tax-provider APIs, VAT registration, filing, marketplace facilitator
- Tax exemptions, customer tax IDs
- Compound/multi-component tax (single rate per jurisdiction only)
- Postal-code range matching
- Carrier taxes
- Refunds/returns (Phase 7)
- Historical order recalculation
- Admin panel changes
- Repository-wide float removal (Phase 5R-3)
- Per-row spreadsheet taxable column mapping (deferred)

---

## 9.2 Data model

### Table: `tax_settings` (one row per store)

| Column | Type | Nullable | Default | Index | Notes |
|--------|------|----------|---------|-------|-------|
| `id` | bigint PK | no | — | PK | |
| `store_id` | bigint FK → `stores.id` | no | — | **unique** | `ON DELETE CASCADE` |
| `enabled` | boolean | no | false | | Master switch |
| `prices_include_tax` | boolean | no | false | | Inclusive vs exclusive catalog prices |
| `default_product_taxable` | boolean | no | true | | Default for **new** products only |
| `shipping_taxable` | boolean | no | false | | Apply tax to shipping when enabled |
| `calculation_address` | string(32) | no | `shipping` | | Fixed enum: `shipping` only in 5R-1 |
| `settings_version` | unsignedInteger | no | 1 | | Increment atomically on every mutation (§9.12) |
| `created_at` / `updated_at` | timestamps | no | — | | Standard Laravel timestamps |

**Bootstrap:** Migration creates one disabled row per existing store with defaults above.

### Table: `tax_rates`

| Column | Type | Nullable | Default | Index | Notes |
|--------|------|----------|---------|-------|-------|
| `id` | bigint PK | no | — | PK | |
| `store_id` | bigint FK → `stores.id` | no | — | composite | `ON DELETE CASCADE` |
| `country_code` | char(2) | no | — | composite | ISO 3166-1 alpha-2, stored uppercase |
| `region_code` | string(32) | **no** | **`''`** | composite | **Empty string = country-wide**; non-empty = state/province code, stored uppercase |
| `name` | string(120) | no | — | | Merchant label, e.g. “US CA Sales Tax” |
| `rate_percent` | decimal(8,4) | no | — | | e.g. 8.2500 = 8.25% |
| `priority` | unsignedSmallInteger | no | 100 | | Lower = higher priority when multiple rates match (future-proof; 5R-1 uses one rate) |
| `is_active` | boolean | no | true | | |
| `created_at` / `updated_at` | timestamps | no | — | | |

**Unique constraint (SQLite + MySQL safe):**

```sql
UNIQUE (store_id, country_code, region_code)
```

**Normalization rules (application layer, enforced in form requests + model mutator):**

- Accept `null`, blank, or whitespace-only `region_code` from merchants → persist as `''`
- Non-empty `region_code` → `mb_strtoupper(trim($value))`
- Country-wide rate row uses `region_code = ''` (never `NULL`)

**Validation examples:**

| Input region | Persisted | Meaning |
|--------------|-----------|---------|
| (empty) | `''` | Country-wide US rate |
| `ca` | `CA` | California regional rate |
| `null` | `''` | Country-wide |

**Duplicate rejection:** Two rows with `(store_id=1, country_code=US, region_code='')` violates unique constraint.

### Table: `checkout_tax_lines`

| Column | Type | Nullable | Default | FK / delete | Index | Notes |
|--------|------|----------|---------|-------------|-------|-------|
| `id` | bigint PK | no | — | — | PK | |
| `store_id` | bigint | no | — | → `stores.id` **CASCADE** | `(store_id, checkout_id)` | Store ownership for scoping |
| `checkout_id` | bigint | no | — | → `checkouts.id` **CASCADE** | `(checkout_id)` | Parent checkout |
| `tax_rate_id` | bigint | yes | null | → `tax_rates.id` **SET NULL** | | Snapshot link; survives rate deletion |
| `jurisdiction_country_code` | char(2) | no | — | | | Uppercase ISO-2 at calc time |
| `jurisdiction_region_code` | string(32) | no | `''` | | | Empty = country-wide jurisdiction |
| `rate_percent` | decimal(8,4) | no | — | | | Applied rate at calculation time |
| `taxable_amount` | decimal(14,2) | no | 0 | | | **Net taxable base** (see §9.5) |
| `tax_amount` | decimal(14,2) | no | 0 | | | Tax computed on `taxable_amount` |
| `applies_to` | string(32) | no | — | | `(checkout_id, applies_to)` | Allowed: `items`, `shipping` only |
| `settings_version` | unsignedInteger | no | 1 | | | From `tax_settings` at calc time |
| `calculated_at` | timestamp | no | — | | | UTC calculation instant |
| `created_at` / `updated_at` | timestamps | no | — | | | |

**Replacement behavior during checkout recalculation:**

1. Inside the same DB transaction that updates checkout totals:
2. `DELETE FROM checkout_tax_lines WHERE checkout_id = ? AND store_id = ?`
3. `INSERT` fresh rows from calculator output
4. Never append duplicates; never leave stale lines

**Historical immutability:** Rows are copied to `order_tax_lines` at conversion; checkout lines may be deleted with checkout cascade but orders retain snapshots.

### Table: `order_tax_lines`

Same columns as `checkout_tax_lines` except:

| Column | Change |
|--------|--------|
| `checkout_id` | replaced by `order_id` → `orders.id` **CASCADE** |
| Indexes | `(store_id, order_id)`, `(order_id)`, `(order_id, applies_to)` |

**Population:** Copy from `checkout_tax_lines` at conversion (field-for-field). **Never recalculate** after order creation.

### Table: `draft_tax_lines` (new — draft calculated tax)

Same shape as `checkout_tax_lines` except:

| Column | Change |
|--------|--------|
| `checkout_id` | replaced by `draft_order_id` → `draft_orders.id` **CASCADE** |

Used only when `draft_orders.metadata.tax_source = 'calculated'`. Replaced atomically on each calculate-tax action (same delete-then-insert pattern).

### Column: `products.is_taxable`

| Column | Type | Nullable | Default |
|--------|------|----------|---------|
| `is_taxable` | boolean | no | true |

**Backfill migration:** `UPDATE products SET is_taxable = true WHERE is_taxable IS NULL` (or column default on add).

**New products:** Set from `tax_settings.default_product_taxable` at creation time (§9.10).

### Column: `draft_order_items.tax_amount` (new)

| Column | Type | Nullable | Default |
|--------|------|----------|---------|
| `tax_amount` | decimal(14,2) | no | 0 |

Populated only when draft tax is **calculated** (§9.9). Copied to `order_items.tax_amount` on manual draft conversion.

### Checkout / order header columns (existing — no new header columns)

Use existing columns; populate from calculator output:

| Entity | Columns |
|--------|---------|
| `checkouts` | `subtotal`, `tax_total`, `shipping_total`, `discount_total`, `grand_total` |
| `checkout_items` | `subtotal`, `tax_amount` |
| `orders` | `subtotal`, `tax`, `shipping_tax`, `discount`, `total` |
| `order_items` | `subtotal`, `tax_amount` |

### Metadata snapshots (JSON)

**`checkouts.metadata.tax_snapshot`** (written on every recalc):

```json
{
  "enabled": true,
  "prices_include_tax": true,
  "shipping_taxable": true,
  "settings_version": 3,
  "jurisdiction": {"country_code": "US", "region_code": "CA"},
  "calculated_at": "2026-05-24T12:00:00Z",
  "tax_calculation_skipped": false
}
```

**`draft_orders.metadata` tax fields:**

```json
{
  "tax_source": "calculated",
  "tax_snapshot": { "...same shape as checkout..." }
}
```

`tax_source` allowed values:

- `manual` — merchant entered header `tax_total`; item `tax_amount` may be 0
- `calculated` — “Calculate tax from store settings” ran; item allocations + `draft_tax_lines` authoritative

---

## 9.3 Models and relationships

| Model | Table | Relationships | Scopes |
|-------|-------|---------------|--------|
| `TaxSetting` | `tax_settings` | `belongsTo Store` | `forStore($storeId)` |
| `TaxRate` | `tax_rates` | `belongsTo Store` | `forStore`, `active()` |
| `CheckoutTaxLine` | `checkout_tax_lines` | `belongsTo Checkout, Store, TaxRate?` | store-scoped queries |
| `OrderTaxLine` | `order_tax_lines` | `belongsTo Order, Store, TaxRate?` | store-scoped queries |
| `DraftTaxLine` | `draft_tax_lines` | `belongsTo DraftOrder, Store, TaxRate?` | store-scoped queries |

**`TaxRate` mutator:** normalize `region_code` on set (blank → `''`, else uppercase).

**Casts:** money columns `decimal:2`; rates `decimal:4`; booleans for flags.

**Shared resolver (Slice 6):** `App\Services\Catalog\ProductTaxableDefaultResolver::forStore(Store $store): bool` — reads `tax_settings.default_product_taxable`, falls back to `true` if no row.

---

## 9.4 Currency precision boundary

### Class: `App\Support\Money\CurrencyPrecision`

**Responsibility:** Single source of truth for currency exponent, decimal rounding scale, and minor-unit conversion.

**Public API (locked):**

```php
final class CurrencyPrecision
{
    /** ISO 4217 exponent: 0 for zero-decimal currencies, 2 for most others. */
    public static function exponent(string $currencyCode): int;

    /** Decimal places for half-up rounding of major-unit amounts (matches exponent). */
    public static function scale(string $currencyCode): int;

    /** @return list<string> Lowercase ISO codes with exponent 0. */
    public static function zeroDecimalCurrencies(): array;

    public static function isZeroDecimal(string $currencyCode): bool;

    /** Major-unit decimal string → minor units (int). Half-up at scale boundary. */
    public static function toMinorUnits(string $majorAmount, string $currencyCode): int;

    /** Minor units (int) → major-unit decimal string at scale. */
    public static function fromMinorUnits(int $minorAmount, string $currencyCode): string;

    /** Round major-unit decimal string half-up to currency scale. */
    public static function roundMajor(string $amount, string $currencyCode): string;
}
```

**Zero-decimal list (canonical, replaces four duplicates today):**

`bif`, `clp`, `djf`, `gnf`, `jpy`, `kmf`, `krw`, `mga`, `pyg`, `rwf`, `ugx`, `vnd`, `vuv`, `xaf`, `xof`, `xpf`

**Consumers in Phase 5R-1 (must delegate, no local lists):**

| File | Change |
|------|--------|
| `App\Services\Tax\TaxCalculator` | Round persisted amounts via `CurrencyPrecision::roundMajor()` |
| `App\Services\Checkout\CheckoutTotalsService` | All totals rounding |
| `App\Services\CheckoutService` | PI `amount_minor` persistence — implemented |
| `App\Services\Shipping\CheckoutShippingService` | PI refresh `amount_minor` — implemented |
| `App\Services\CheckoutConversionService` | Amount comparison + capture `amount_minor` — implemented |
| `App\Services\Payments\StripePlatformPaymentProvider` | PI create/update amounts — implemented |

**Comparison rule for payment invariant:**

```php
CurrencyPrecision::toMinorUnits((string) $checkout->grand_total, $checkout->currency_code)
    === $paymentIntent->amount_minor
    === $providerSucceededAmountMinor
```

**Deferred:** Removing `float` casts from existing services → Phase 5R-3. Slice 2 may still accept float at boundaries but must convert through `CurrencyPrecision` before comparison/persistence.

---

## 9.5 Tax calculation contract

### Service: `App\Services\Tax\TaxCalculator`

**Input DTO `TaxCalculationRequest`:**

- `Store $store`
- `TaxSetting $settings`
- `string $currencyCode`
- `list<TaxLineItemInput>` — variant id, quantity, unit price (decimal string), `is_taxable`
- `string $shippingAmount` — decimal string (**always treated as tax-exclusive**)
- `AddressInput $destination` — `country_code`, `region_code` (normalized)

**Output DTO `TaxCalculationResult`:**

- `string $itemsSubtotal` — see inclusive/exclusive rules below
- `string $taxableItemsSubtotal` — net base summed across taxable lines
- `string $itemsTax`
- `string $shippingTax`
- `string $totalTax`
- `list<ItemTaxAllocation>` — checkout/draft item id or line key, `tax_amount`, optional net base
- `list<TaxLineOutput>` — jurisdiction, rate, `taxable_amount`, `tax_amount`, `applies_to`
- `int $settingsVersion`
- `?MatchedTaxRate $matchedRate`

**Numeric representation:** BCMath with internal scale ≥ 4; persist via `CurrencyPrecision::roundMajor()`.

**When tax disabled:** all tax fields `"0.00"` (or `"0"` scale for JPY), empty lines, `itemsSubtotal` = sum of line totals per price mode.

### Service: `App\Services\Checkout\CheckoutTotalsService`

**Input:** store, checkout items, shipping amount, destination address, tax settings.

**Output:** subtotal, discount (0 in 5R-1), shipping, tax, grand_total (decimal strings), tax lines, item-level tax allocations.

---

## 9.5.1 Inclusive vs exclusive — locked persistence rules

### Exclusive catalog prices (`prices_include_tax = false`)

| Field | Meaning | Formula |
|-------|---------|---------|
| `checkout_items.subtotal` | Net line total | `qty × unit_price` (net) |
| `checkout_items.tax_amount` | Tax on line | `round_half_up(net_line × rate / 100)` |
| `checkouts.subtotal` | Sum of net item subtotals | Σ item subtotals |
| `checkout_tax_lines.taxable_amount` (items) | Net taxable base | Σ taxable net lines |
| `checkout_tax_lines.tax_amount` (items) | Item tax | Σ item tax |
| `checkouts.tax_total` | Total tax | items tax + shipping tax |
| `checkouts.grand_total` | Amount charged | `subtotal + shipping + tax_total - discount` |

**Example (10% rate, one taxable item $20 net, no shipping):**

| Field | Value |
|-------|-------|
| item subtotal | 20.00 |
| item tax_amount | 2.00 |
| checkout subtotal | 20.00 |
| tax_total | 2.00 |
| grand_total | 22.00 |
| tax_line taxable_amount (items) | 20.00 |
| tax_line tax_amount (items) | 2.00 |

### Inclusive catalog prices (`prices_include_tax = true`)

| Field | Meaning | Formula |
|-------|---------|---------|
| `checkout_items.subtotal` | **Gross** line total (catalog price charged) | `qty × unit_price` (inclusive) |
| `checkout_items.tax_amount` | **Extracted** tax portion | per-line extraction (below) |
| `checkouts.subtotal` | Sum of **gross** item subtotals | Σ gross item subtotals |
| `checkout_tax_lines.taxable_amount` (items) | **Net taxable base** | Σ (gross line − extracted tax) |
| `checkout_tax_lines.tax_amount` (items) | Extracted item tax | Σ extracted tax |
| `checkouts.tax_total` | Total tax (informational + snapshot) | items tax + shipping tax |
| `checkouts.grand_total` | Amount charged | **`subtotal + shipping + shipping_tax - discount`** — item tax **NOT added again** |

**Per-line inclusive extraction:**

```
extracted_tax = round_half_up(gross_line - gross_line / (1 + rate/100))
net_base      = gross_line - extracted_tax
```

**Example (10% rate, one taxable item catalog $22.00 inclusive, no shipping):**

| Field | Value |
|-------|-------|
| item subtotal (gross) | 22.00 |
| item tax_amount (extracted) | 2.00 |
| checkout subtotal (gross) | 22.00 |
| tax_line taxable_amount (net base) | 20.00 |
| tax_line tax_amount | 2.00 |
| tax_total | 2.00 |
| **grand_total before shipping** | **22.00** |

### Shipping tax (both modes)

**Phase 5R-1 rule:** Shipping amounts are **always tax-exclusive**.

When `shipping_taxable = true`:

```
shipping_tax = round_half_up(shipping_amount × rate / 100)
```

Shipping tax is **always added on top** of `shipping_total` in `grand_total`, regardless of inclusive/exclusive product price mode.

**Example (exclusive items $20 + $2 tax, shipping $5 taxable at 10%):**

| Field | Value |
|-------|-------|
| subtotal | 20.00 |
| tax_total | 2.50 (2.00 items + 0.50 shipping) |
| shipping_total | 5.00 |
| grand_total | 27.50 |

**Example (inclusive item $22 gross / $2 extracted tax, shipping $5 taxable at 10%):**

| Field | Value |
|-------|-------|
| subtotal (gross) | 22.00 |
| tax_total | 2.50 (2.00 extracted + 0.50 shipping) |
| shipping_total | 5.00 |
| grand_total | **27.50** (= 22 + 5 + 0.50) |

### Rounding policy

- Per line item first, then sum (line rounding)
- Document in merchant UI help text

### Additional numerical fixtures (calculator tests)

1. Exclusive, non-taxable $20 → subtotal 20.00, tax 0.00, grand 20.00  
2. Exclusive mixed cart: taxable $10 + non-taxable $5 → tax 1.00, grand 16.00  
3. Inclusive non-taxable $22 → subtotal 22.00, tax 0.00, grand 22.00  
4. JPY exclusive ¥1000 × 10% → tax ¥100, grand ¥1100 (zero-decimal)  
5. Half-up: $10.005 net at 10% → line tax $1.00 or $1.01 per line policy (test locks behavior)

---

## 9.6 Jurisdiction matching

**Priority (final):**

1. Active rate where `country_code` matches **and** `region_code` equals normalized destination region (non-empty)
2. Active rate where `country_code` matches **and** `region_code = ''` (country-wide)
3. No tax (0.00) — do not guess

**Normalization:**

- Country: uppercase ISO-2
- Region: normalize blank → `''`; else uppercase trim
- Destination region empty → match country-wide rate only (priority 2)
- Missing `country_code` on address → zero tax; set `tax_calculation_skipped: true` in metadata

**Rate storage vs matching:**

- Merchant creates country-wide US rate with `region_code = ''`
- Merchant creates CA rate with `region_code = 'CA'`
- Destination US + CA → regional rate wins

**Merchant disclaimer:** “Basic configurable tax rates for platform checkout. Not tax advice. Confirm rates with your accountant.”

---

## 9.7 Payment amount invariant (locked — all environments)

### Rule

Immediately **before** checkout-to-order conversion (inside the conversion transaction, after loading checkout + PI, before order insert):

These four values must align when converted through `CurrencyPrecision`:

1. Provider succeeded amount (from webhook/`PaymentWebhookResult`)
2. Local active `payment_intents.amount_minor`
3. Checkout `grand_total` → `CurrencyPrecision::toMinorUnits(...)`
4. Currency code (case-insensitive equality)

### On mismatch

- **Do not** create the order
- **Do not** commit/deduct inventory
- **Do not** mark checkout `converted`
- Record safe structured log/event via `CheckoutEventRecorder` (no PAN/secrets)
- Throw deterministic domain exception
- Retries remain idempotent (second attempt hits same guard, no duplicate order)

### Exception (locked name)

`App\Exceptions\CheckoutPaymentAmountMismatchException`

**Properties exposed:** `checkoutId`, `expectedMinor`, `actualMinor`, `currencyCode`, `providerIntentId`

**HTTP/API behavior:** Platform webhook handler catches and logs; no order side effects; Stripe retry safe.

### Tests (Slice 5)

- `test_conversion_fails_when_provider_amount_minor_mismatch`
- `test_conversion_fails_when_local_pi_amount_minor_mismatch`
- `test_conversion_fails_when_grand_total_mismatch`
- `test_conversion_succeeds_when_all_amounts_match_usd`
- `test_conversion_succeeds_when_all_amounts_match_jpy_zero_decimal`
- `test_mismatch_does_not_deduct_inventory_or_mark_converted`
- `test_repeat_webhook_after_mismatch_remains_idempotent`

---

## 9.8 Open checkout recalculation (locked)

### Read-only endpoints — never mutate totals

| Endpoint | Method | Behavior |
|----------|--------|----------|
| `PlatformCheckoutController::show` | GET | Return persisted checkout JSON only |
| Any checkout GET/show | GET | No tax recalc, no PI refresh |

### Explicit mutation boundaries — recalc allowed

1. Checkout creation (`CheckoutService::create`)
2. Shipping address update (when wired through checkout mutation)
3. Shipping method selection (`CheckoutShippingService::selectShippingMethod`)
4. Explicit recalculate action if added later (none today)
5. Final pre-payment validation hook (before PI create if totals stale vs `settings_version`)
6. Pre-conversion validation (amount invariant + optional stale-totals guard)

### Backward compatibility (pre-tax / pre-enabled checkouts)

| Scenario | Behavior |
|----------|----------|
| Open checkout created before tax deployment | GET show returns unchanged persisted totals |
| Merchant/customer triggers shipping select or address mutation after tax enabled | Full recalc runs; tax applied; PI superseded/refreshed |
| Paid checkout with stale totals vs current tax settings | Conversion **blocked** by payment amount invariant |
| Tax settings change while checkout open | No effect until next mutation boundary; metadata `settings_version` updated on recalc |

**Deploy note for merchants:** “Open checkouts refresh tax on the next shipping or address update, not when merely viewing checkout status.”

---

## 9.9 Manual/draft order compatibility (locked design)

### Two tax sources

| Source | Merchant action | Header `tax_total` | Item `tax_amount` | `draft_tax_lines` |
|--------|-----------------|---------------------|-------------------|-------------------|
| `manual` | Types tax in form | Authoritative | 0 (default) | none |
| `calculated` | Clicks “Calculate tax from store settings” | Sum of allocations + shipping tax | Per-item allocated | Populated |

### Calculate tax action

- **Route:** `POST /draft-orders/{draftOrder}/calculate-tax` → `draft-orders.calculate-tax`
- **Requires:** shipping address on draft, tax enabled, shippable context
- **Does:** runs `TaxCalculator` + allocation; sets `metadata.tax_source = 'calculated'`; replaces `draft_tax_lines`; updates each `draft_order_items.tax_amount`; sets header `tax_total`
- **Does not:** auto-run on every draft save

### Manual entry (backward compatible)

- Default `tax_source = 'manual'` (or absent → treated as manual)
- Merchant-edited `tax_total` preserved on save
- Item `tax_amount` stays 0 unless merchant recalculates

### Conversion to order (`ManualOrderConversionService`)

When `tax_source = 'calculated'`:

- Copy `draft_order_items.tax_amount` → `order_items.tax_amount`
- Copy `draft_tax_lines` → `order_tax_lines`
- Copy `metadata.tax_snapshot` → order metadata
- Set `orders.tax`, `orders.shipping_tax` from draft header breakdown

When `tax_source = 'manual'`:

- Copy header `tax_total` → `orders.tax` (existing behavior)
- Item `tax_amount` = 0 unless manually extended later
- No `order_tax_lines` required (optional empty)

**Shipping tax on drafts:** When calculated, shipping tax remains in header `tax_total` and `draft_tax_lines` row with `applies_to = 'shipping'`, not merged into item rows.

---

## 9.10 Product taxable default (locked)

### Rules

1. **Migration backfill:** all existing products → `is_taxable = true`
2. **New products:** `is_taxable = ProductTaxableDefaultResolver::forStore($store)`
3. **Per-row import taxable column:** deferred; do not map spreadsheet column in 5R-1
4. **Imports must not hardcode `true`** when store default is `false`

### Production creation paths to update (Slice 6)

| Path | File | Method / location |
|------|------|-------------------|
| Onboarding product create | `app/Http/Controllers/OnboardingController.php` | `Product::create` ~L310 (onboarding flow) |
| Catalog normal / quick create | `app/Http/Controllers/OnboardingController.php` | `storeProductForStore()` ~L1905 |
| Product import (simple row) | `app/Services/Catalog/ProductImportProcessor.php` | `Product::query()->create` ~L972 |
| Variant import finalizer (new product) | `app/Services/Catalog/ProductImportVariantFinalizer.php` | `Product::query()->create` ~L390 |
| Order seeder fallback product | `database/seeders/CustomerAndOrderSeeder.php` | `firstOrCreate` ~L33 |

**Not creation paths (no default change):**

- `OnboardingController::updateProductFromManagement` — updates only; preserves `is_taxable`
- `ProductBulkController` — bulk status/brand, not create
- Test fixtures — may set explicitly

**Duplicate/copy flows:** No production duplicate-product route exists today. If added later, must call `ProductTaxableDefaultResolver` (document in Slice 6 acceptance gate).

### UI

- Product workspace edit — checkbox “Charge tax on this product” (`is_taxable`)
- Permission: `catalog.manage` edit; `catalog.view` read-only

### Tests (Slice 6)

- `test_new_product_uses_store_default_taxable_true`
- `test_new_product_uses_store_default_taxable_false`
- `test_import_create_respects_store_default_false`
- `test_onboarding_create_respects_store_default`
- `test_existing_product_backfill_is_taxable_true`

---

## 9.11 Settings version mutations (locked)

`tax_settings.settings_version` increments by 1 **in the same DB transaction** as:

| Mutation | Handler |
|----------|---------|
| Tax settings update | `TaxSettingsController@update` |
| Rate create | `TaxSettingsController@storeRate` |
| Rate update | `TaxSettingsController@updateRate` |
| Rate enable/disable | `TaxSettingsController@updateRate` (is_active toggle) |
| Rate delete | `TaxSettingsController@destroyRate` |

**Implementation pattern:**

```php
DB::transaction(function () {
    // mutate tax_settings and/or tax_rates
    $settings->increment('settings_version');
});
```

**Tests (Slice 1B):** one test per mutation row in table above (5 tests minimum).

---

## 9.12 Tax settings UI

| Item | Value |
|------|-------|
| Routes | `GET /settings/taxes`; `PUT /settings/taxes`; `POST /settings/taxes/rates`; `PATCH /settings/taxes/rates/{taxRate}`; `DELETE /settings/taxes/rates/{taxRate}` |
| Controller | `TaxSettingsController` |
| Form requests | `UpdateTaxSettingsRequest`, `StoreTaxRateRequest`, `UpdateTaxRateRequest` |
| Permission view | `settings.view` |
| Permission edit | `settings.manage` |
| Blade | `resources/views/user_view/settings/taxes.blade.php` |
| Navigation | Settings sidebar: Payments, Locations, **Taxes** |
| Rate form | Country required; region optional (blank = country-wide) |
| Validation | Rate 0–100%; unique `(country_code, region_code)` per store; ISO-2 country; normalize region |

---

## 9.13 Authorization and auditing

| Action | Permission | Cross-store |
|--------|------------|-------------|
| View settings/rates | settings.view | 404 |
| Edit settings/rates | settings.manage | 404 |
| Edit product taxable | catalog.manage | 404 |

**Security log events:** `tax.settings.updated`, `tax.rate.created`, `tax.rate.updated`, `tax.rate.deleted` — include `settings_version`, jurisdiction, no secrets.

---

## 9.14 Checkout integration map

| File | Current | Future |
|------|---------|--------|
| `CheckoutService::totals()` | Hardcoded tax=0 | Delegate to `CheckoutTotalsService` |
| `CheckoutService::create()` | item tax_amount=0 | Set from calculator allocation |
| `CheckoutShippingService::selectShippingMethod()` | Inline grand_total ~L111 | `CheckoutTotalsService` + PI refresh |
| `CheckoutConversionService` | Copies tax; no amount check | Copy tax lines; **hard-fail invariant** |
| `StripePlatformPaymentProvider` | Local `amountMinor()` | `CurrencyPrecision::toMinorUnits()` |
| `PlatformCheckoutController::show` | Read-only | **Remain read-only** |
| `PlatformCheckoutController::checkoutResponse()` | tax_total only | Add `tax_lines`, optional `tax_snapshot` |

**Response contract:** Additive JSON only; do not remove existing keys.

---

## 9.15 External checkout preservation

**Invariant:** External checkout remains externally calculated. Phase 5R-1 **must not** recalculate external totals or replace item pricing.

**Allowed:** Store supplied tax in existing columns; optional debug log when payload internally inconsistent (no rejection in 5R-1).

**Test:** `Phase5ExternalCheckoutSyncTest::test_external_supplied_tax_unchanged_when_store_tax_enabled`

---

## 9.16 Migration order

1. `tax_settings` + seed row per store (disabled, version 1)
2. `tax_rates` with `region_code` NOT NULL default `''` + unique `(store_id, country_code, region_code)`
3. `products.is_taxable` + backfill true
4. `checkout_tax_lines`, `order_tax_lines`
5. `draft_order_items.tax_amount`, `draft_tax_lines` (Slice 7 migration or combined with 4 if preferred — prefer Slice 7 for draft isolation)

**Rollback:** Drop in reverse; orders unaffected.

---

## 9.17 Tests-first inventory

### Tax settings — `tests/Feature/TaxSettingsTest.php`

- Owner/manager/staff/cross-store access
- `test_tax_rate_validation_normalizes_blank_region_to_country_wide`
- `test_tax_rate_rejects_duplicate_country_and_region`
- `test_tax_settings_update_increments_settings_version`
- `test_tax_rate_create_update_toggle_delete_each_increment_settings_version` (5 cases)

### Currency — `tests/Unit/CurrencyPrecisionTest.php`

- `test_usd_two_decimal_exponent_and_minor_units`
- `test_jpy_zero_decimal_minor_units`
- `test_half_up_rounding_at_scale`
- `test_to_minor_and_from_minor_round_trip`

### Calculator — `tests/Unit/TaxCalculatorTest.php`

- Exclusive/inclusive/non-taxable/mixed/shipping/jurisdiction/rounding/JPY fixtures from §9.5.1

### Checkout — `tests/Feature/PlatformCheckoutTaxTest.php`

- Server-side tax ignores client values
- Recalc on shipping method change only (not on show)
- `test_show_checkout_does_not_mutate_totals`
- PI minor amount equals checkout grand_total (USD + JPY)
- Tax lines snapshot + replacement on recalc
- Order conversion copies tax lines

### Conversion — extend checkout/Stripe tests

- All `CheckoutPaymentAmountMismatchException` cases from §9.7

### Draft — extend `Phase4DraftOrderTest.php`

- Manual tax preserved
- Calculate allocates per item + draft_tax_lines
- Conversion copies calculated allocations

### Product — `tests/Feature/ProductTaxableFlagTest.php`

- Defaults per §9.10

---

## 9.18 Implementation slices (locked sequence)

### Slice 1A — Tax schema and models only ✅ Implemented 2026-06-24

**Report:** `docs/implementation/PHASE_5R_1_SLICE_1A_TAX_SCHEMA_REPORT.md`

**Goal:** Database tables and Eloquent models; no calculator, no checkout wiring, no UI.

**Files expected:**

- `database/migrations/*_create_tax_settings_table.php`
- `database/migrations/*_create_tax_rates_table.php`
- `database/migrations/*_add_is_taxable_to_products_table.php`
- `database/migrations/*_create_checkout_tax_lines_table.php`
- `database/migrations/*_create_order_tax_lines_table.php`
- `app/Models/TaxSetting.php`, `TaxRate.php`, `CheckoutTaxLine.php`, `OrderTaxLine.php`
- `tests/Feature/Phase5RTaxSchemaTest.php` (migration smoke, unique constraint, region_code default)

**Characterization tests first:** schema constraints, cascade behavior, `region_code ''` uniqueness.

**Deferred:** UI, calculator, checkout, draft columns.

**Targeted command:** `php artisan test --filter=Phase5RTaxSchemaTest`

**Acceptance gate:** Migrations run on SQLite + MySQL; models load; unique `(store_id, country_code, region_code)` enforced; empty region country-wide row works.

---

### Slice 1B — Tax settings routes, UI, permissions ✅ Implemented 2026-06-24

**Report:** `docs/implementation/PHASE_5R_1_SLICE_1B_TAX_SETTINGS_UI_REPORT.md`

**Goal:** Merchant can manage tax settings and rates; `settings_version` increments on every mutation.

**Files expected:**

- `app/Http/Controllers/TaxSettingsController.php`
- `app/Http/Requests/UpdateTaxSettingsRequest.php`, `StoreTaxRateRequest.php`, `UpdateTaxRateRequest.php`
- `resources/views/user_view/settings/taxes.blade.php`
- Settings nav partial update
- `routes/web.php` (settings tax routes)
- `tests/Feature/TaxSettingsTest.php`

**Characterization tests first:** permissions, validation, version increment per mutation.

**Deferred:** Checkout recalc, product flag UI.

**Targeted command:** `php artisan test --filter=TaxSettingsTest`

**Acceptance gate:** Owner CRUD rates; manager read-only; staff view-only; each mutation increments version atomically.

---

### Slice 2 — CurrencyPrecision + TaxCalculator

**Goal:** Shared money boundary + deterministic tax math with locked inclusive/exclusive semantics.

**Files expected:**

- `app/Support/Money/CurrencyPrecision.php`
- `app/Services/Tax/TaxCalculator.php`
- `app/Services/Tax/DTO/*` (request/result/line DTOs)
- `app/Services/Tax/TaxJurisdictionMatcher.php`
- `tests/Unit/CurrencyPrecisionTest.php`
- `tests/Unit/TaxCalculatorTest.php`

**Characterization tests first:** all §9.5.1 numerical fixtures + JPY + half-up.

**Production changes:** New classes only; **no** checkout wiring yet.

**Deferred:** Replacing all float call sites outside listed consumers (5R-3).

**Targeted command:** `php artisan test --filter='CurrencyPrecisionTest|TaxCalculatorTest'`

**Acceptance gate:** Calculator outputs match locked tables; CurrencyPrecision replaces duplicate zero-decimal lists in new code paths.

---

### Slice 3 — CheckoutTotalsService + checkout creation ✅ Implemented 2026-06-24

**Report:** `docs/implementation/PHASE_5R_1_SLICE_3_CHECKOUT_TOTALS_REPORT.md`

**Goal:** Platform checkout create computes tax; persists item tax + header totals + tax lines; show remains read-only.

**Files expected:**

- `app/Services/Checkout/CheckoutTotalsService.php`
- `app/Services/CheckoutService.php` (delegate totals + create)
- `app/Models/Checkout.php`, `CheckoutItem.php` (relationships)
- `tests/Feature/PlatformCheckoutTaxTest.php` (create + show immutability partial)

**Characterization tests first:** inclusive gross subtotal example; exclusive example; show does not mutate.

**Deferred at original Slice 3 close:** Shipping method recalc (Slice 4), conversion invariant (Slice 5). Both were completed later in Batch A.

**Targeted command:** `php artisan test --filter=PlatformCheckoutTaxTest`

**Acceptance gate:** New checkout has correct tax columns and tax lines; GET show unchanged after tax settings change.

---

### Slice 4 — Shipping recalculation + PaymentIntent synchronization ✅ Implemented 2026-06-25

**Report:** `docs/implementation/PHASE_5R_1_BATCH_A_FINANCIAL_PIPELINE_REPORT.md`

**Goal:** Shipping select recalculates tax; PI amount refreshed via CurrencyPrecision.

**Files expected:**

- `app/Services/Shipping/CheckoutShippingService.php`
- `app/Services/CheckoutService.php` (PI create path)
- `app/Services/Payments/StripePlatformPaymentProvider.php`
- Extend `PlatformCheckoutTaxTest.php`

**Characterization tests first:** shipping taxable adds shipping tax; PI minor = grand_total USD/JPY.

**Targeted command:** `php artisan test --filter=PlatformCheckoutTaxTest`

**Acceptance gate:** selectShippingMethod updates tax + PI; no duplicate grand_total formula remains.

**Batch A result:** shipping/address mutation recalculates persisted checkout item totals, tax lines, checkout header totals, tax snapshot, and active PaymentIntent amount through `CheckoutTotalsService` and `CurrencyPrecision`.

---

### Slice 5 — Conversion invariant + order tax snapshots ✅ Implemented 2026-06-25

**Report:** `docs/implementation/PHASE_5R_1_BATCH_A_FINANCIAL_PIPELINE_REPORT.md`

**Goal:** Hard-fail amount mismatch; copy tax lines to order; never recalc order tax.

**Files expected:**

- `app/Exceptions/CheckoutPaymentAmountMismatchException.php`
- `app/Services/CheckoutConversionService.php`
- `app/Services/CheckoutEventRecorder.php` (mismatch event)
- Extend `Phase5PlatformCheckoutStripeTest.php` or dedicated conversion tests

**Characterization tests first:** all §9.7 mismatch tests.

**Targeted command:** `php artisan test --filter='PlatformCheckout|CheckoutConversion|CheckoutPayment'`

**Acceptance gate:** Matching amounts convert with order_tax_lines; mismatch never creates order.

**Batch A result:** checkout conversion hard-fails on local/provider/checkout amount or currency mismatch before order, payment capture, or inventory mutation. Matching conversions copy checkout tax lines and tax snapshots to orders.

---

### Slice 6 — Product taxable defaults (every creation path)

**Goal:** Single resolver used by all product creation paths.

**Files expected:**

- `app/Services/Catalog/ProductTaxableDefaultResolver.php`
- `app/Http/Controllers/OnboardingController.php`
- `app/Services/Catalog/ProductImportProcessor.php`
- `app/Services/Catalog/ProductImportVariantFinalizer.php`
- `database/seeders/CustomerAndOrderSeeder.php`
- Product workspace edit UI for `is_taxable`
- `tests/Feature/ProductTaxableFlagTest.php`

**Characterization tests first:** store default false → import/onboarding create false.

**Targeted command:** `php artisan test --filter=ProductTaxableFlagTest`

**Acceptance gate:** All listed paths use resolver; backfill true for existing.

---

### Slice 7 — Draft/manual calculated tax compatibility

**Goal:** Calculate-tax action with item allocation; conversion copies to order.

**Files expected:**

- `database/migrations/*_add_tax_amount_to_draft_order_items.php`
- `database/migrations/*_create_draft_tax_lines_table.php`
- `app/Models/DraftTaxLine.php`
- `app/Services/DraftOrderService.php`
- `app/Services/ManualOrderConversionService.php`
- `app/Http/Controllers/DraftOrderController.php` (calculate-tax action)
- Extend `Phase4DraftOrderTest.php`

**Characterization tests first:** manual preserved; calculated allocates items + lines.

**Targeted command:** `php artisan test --filter=Phase4DraftOrderTest`

**Acceptance gate:** Both tax sources coexist; conversion snapshots correct fields.

---

### Slice 8 — External preservation, docs, full regression

**Goal:** Verify external unchanged; update enterprise docs; full suite green.

**Files expected:**

- Extend `Phase5ExternalCheckoutSyncTest.php`
- `ENTERPRISE_PROJECT_CONTEXT.md`, `ENTERPRISE_ROADMAP_2026.md`, `PROJECT_BRAIN.md`, `docs/RELEASE_CHECKLIST.md` (mark 5R-1 implemented only after this slice passes)

**Targeted command:** `php artisan test`

**Acceptance gate:** Full suite baseline; external preservation test passes; no carrier/admin changes.

---

## 9.19 Risks and rollback

| Risk | Prevention | Detection | Rollback |
|------|------------|-----------|----------|
| PI amount mismatch | CurrencyPrecision + recalc on mutation boundaries | Mismatch exception tests | Disable `tax_settings.enabled` |
| Inclusive double-add | Locked grand_total formula | TaxCalculator inclusive fixtures | Disable inclusive mode |
| GET mutates checkout | Explicit show test | PlatformCheckoutTaxTest | Revert Slice 3 |
| Stale paid checkout converts | Hard-fail invariant | Conversion tests | Manual checkout cancel |
| Import ignores default | ProductTaxableFlagTest | Slice 6 gate | Hotfix resolver |
| Duplicate tax lines | Delete-then-insert | Recalc test | Revert checkout wiring |

---

## Architecture diagram

```
Platform checkout:
  Items + shipping + address
    → CheckoutTotalsService
      → TaxCalculator (tax_settings + tax_rates + product.is_taxable)
      → CurrencyPrecision (rounding + minor units)
    → persist checkout totals + checkout_items.tax_amount + checkout_tax_lines (replace)
    → StripePlatformPaymentProvider(CurrencyPrecision → PI amount_minor)
    → [mutation boundaries only — never GET show]
    → CheckoutConversionService
         → verify amounts (hard fail)
         → order + order_items.tax_amount + order_tax_lines (copy)

External checkout: unchanged (ExternalOrderSyncService)

Draft orders:
  manual tax_total (default)
  OR calculate-tax → draft_order_items.tax_amount + draft_tax_lines
    → ManualOrderConversionService → order snapshot
```

**Model A / carrier work:** unchanged — no carrier files in 5R-1 scope.

**No implementation in the planning task that produced this lock revision.**
