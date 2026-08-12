# Phase 5R-1 Slice 1A — Tax Schema and Models Report

**Date:** 2026-06-24
**Status:** Implemented (schema/models only)  
**Phase 5R-1 overall:** In progress — Slices 1A–1B complete; tax calculation and checkout application **not implemented**

---

## Scope implemented

- `tax_settings` table with per-store backfill
- `tax_rates` table with empty-string country-wide `region_code`
- `products.is_taxable` column (default `true`)
- `checkout_tax_lines` and `order_tax_lines` snapshot tables
- Eloquent models: `TaxSetting`, `TaxRate`, `CheckoutTaxLine`, `OrderTaxLine`
- Relationships on `Store`, `Product`, `Checkout`, `Order`
- Store-scoped query scopes and `TaxRate` normalization mutators
- `tests/Feature/Phase5RTaxSchemaTest.php` (20 tests)

## Not implemented (deferred)

| Slice | Work |
|-------|------|
| 1B | Tax settings routes, UI, permissions, `settings_version` mutation tests |
| 2 | `CurrencyPrecision`, `TaxCalculator` |
| 3–5 | Checkout totals, shipping recalc, conversion invariant |
| 6 | Product taxable default resolver + creation paths |
| 7 | Draft calculated tax columns |
| 8 | External preservation regression + docs sign-off |

---

## Migrations created

| File | Purpose |
|------|---------|
| `2026_06_06_010000_create_tax_settings_table.php` | Tax settings + store backfill |
| `2026_06_06_010100_create_tax_rates_table.php` | Tax rates + unique jurisdiction |
| `2026_06_06_010200_add_is_taxable_to_products_table.php` | Product taxable flag |
| `2026_06_06_010300_create_checkout_tax_lines_table.php` | Checkout tax snapshots |
| `2026_06_06_010400_create_order_tax_lines_table.php` | Order tax snapshots |

All migrations include safe `down()` methods.

---

## Tables and columns

### `tax_settings`

One row per store (unique `store_id`). Defaults: tax disabled, exclusive prices, default product taxable, shipping not taxable, `calculation_address = shipping`, `settings_version = 1`.

### `tax_rates`

`region_code` **NOT NULL DEFAULT ''** (empty = country-wide). Rate percent `decimal(8,4)`, priority, active flag.

### `products.is_taxable`

Boolean NOT NULL DEFAULT `true`. Existing rows receive default on column add.

### `checkout_tax_lines` / `order_tax_lines`

Snapshot columns: jurisdiction, rate, taxable base, tax amount, `applies_to` (`items`|`shipping`), `settings_version`, `calculated_at`.

---

## Constraints and indexes

- `tax_settings.store_id` — UNIQUE, FK CASCADE
- `tax_rates` — UNIQUE `(store_id, country_code, region_code)`
- `tax_rates` — INDEX `(store_id, is_active, priority)`
- `tax_rates` — INDEX `(store_id, country_code, region_code)`
- Tax lines — INDEX `(store_id, checkout_id|order_id)`, `(checkout_id|order_id, applies_to)`, `tax_rate_id`
- FK: parent store/checkout/order CASCADE; `tax_rate_id` SET NULL

---

## Backfill behavior

`create_tax_settings_table` migration inserts one disabled row per existing store using `chunkById(100)` and `insertOrIgnore`. Safe on SQLite and MySQL. Does not create tax rates. New stores created after deploy do **not** auto-receive tax settings until Slice 1B.

---

## Models and relationships

| Model | Relationships |
|-------|---------------|
| `TaxSetting` | `belongsTo Store`; `scopeForStore` |
| `TaxRate` | `belongsTo Store`; `hasMany CheckoutTaxLine`, `OrderTaxLine`; `scopeForStore`, `scopeActive`, `scopeForJurisdiction` |
| `CheckoutTaxLine` | `belongsTo Store`, `Checkout`, `TaxRate?`; `scopeForStore` |
| `OrderTaxLine` | `belongsTo Store`, `Order`, `TaxRate?`; `scopeForStore` |
| `Store` | `hasOne taxSetting`, `hasMany taxRates`, `checkoutTaxLines`, `orderTaxLines` |
| `Checkout` | `hasMany taxLines` |
| `Order` | `hasMany taxLines` |
| `Product` | cast `is_taxable` boolean |

---

## Normalization (`TaxRate`)

- `country_code` → uppercase trim on set
- `region_code` → blank/null → `''`; non-empty → uppercase trim
- Static helpers: `normalizeCountryCode()`, `normalizeRegionCode()`

---

## Store scoping

All tax entities are store-owned. Tests prove `TaxRate::forStore()` does not leak across stores. Same jurisdiction allowed in different stores.

---

## Cascade verification

| Action | Result |
|--------|--------|
| Hard-delete checkout (`forceDelete`) | Checkout tax lines removed |
| Delete order | Order tax lines removed |
| Delete tax rate | `tax_rate_id` SET NULL on lines; snapshot fields preserved |
| Delete store | Cascades settings, rates, and tax lines |

Note: `Checkout` uses soft deletes; tax lines remain until checkout is force-deleted or store is deleted.

---

## Tests

**File:** `tests/Feature/Phase5RTaxSchemaTest.php`  
**Count:** 20 tests, 59 assertions

**Targeted result:**

```
Tests: 20 passed (59 assertions)
```

**Full suite after Slice 1A:**

```
Tests: 2 skipped, 743 passed (3654 assertions)
```

(+20 tests vs pre-Slice 1A baseline of 723 passed)

---

## Verification commands

| Command | Result |
|---------|--------|
| `git diff --check` | Pass |
| `composer validate --no-check-publish` | Pass |
| `php artisan test --filter=Phase5RTaxSchemaTest` | 20 passed |
| `php artisan test --filter=Tax` | 24 passed |
| `php artisan test --filter=Product` | 172 passed |
| `php artisan test` | 743 passed, 2 skipped |
| `vendor/bin/pint` (Slice 1A files) | Pass |
| `vendor/bin/pint --test` (repo-wide) | 3 pre-existing unrelated failures (FedEx migrations/test) |

**Migration rollback:** Each migration has `down()`. Rollback of all five tax migrations drops tables/column in reverse order. In-memory SQLite cannot persist state between separate Artisan invocations; use a file-based SQLite DB for interactive rollback verification.

---

## Known limitations

- No tax calculation at runtime
- No merchant tax UI or API
- No checkout/order tax application or conversion copying
- New stores do not auto-create `tax_settings` (Slice 1B)
- Product creation paths still omit explicit `is_taxable` (DB default `true` until Slice 6)
- Checkout soft-delete does not cascade tax lines until hard delete

---

## Confirmations

- No `TaxCalculator`, `CurrencyPrecision`, or checkout wiring added
- No tax routes, controllers, or Blade UI
- No checkout/payment/Stripe behavior changed
- No carrier/admin changes
- Model A / carrier production unchanged
- No commit made in this slice task
