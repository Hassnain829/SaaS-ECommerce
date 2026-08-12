# Phase 5R-1 Slice 1B — Tax Settings UI and Configuration Report

**Date:** 2026-06-24  
**Status:** Implemented (settings UI, permissions, bootstrap, versioning) — closeout corrections applied 2026-06-24
**Phase 5R-1 overall:** In progress — tax calculation and checkout application **not implemented**

---

## Scope implemented

- Merchant tax settings page at `/settings/taxes`
- Five store-scoped routes with permission middleware
- `TaxSettingsController` (thin)
- Form requests: `UpdateTaxSettingsRequest`, `StoreTaxRateRequest`, `UpdateTaxRateRequest`
- `TaxConfigurationService` with atomic `settings_version` increments and row locking
- New-store `TaxSetting` bootstrap via `Store::created` observer
- Security log events for settings and rate mutations
- Settings sidebar navigation link
- `tests/Feature/TaxSettingsTest.php` (49 tests)
- `tests/Feature/Phase5RTaxMigrationRoundTripTest.php` (Slice 1A migration proof)
- Slice 1A date/plan documentation corrections

## Not implemented (deferred)

- `TaxCalculator`, `CurrencyPrecision`, checkout tax application (Slices 2–5)
- Product taxable UI and creation-path resolver (Slice 6)
- Draft calculated tax (Slice 7)
- External preservation regression sign-off (Slice 8)

---

## Routes

| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/settings/taxes` | `settings.taxes.index` | `settings.view` |
| PUT | `/settings/taxes` | `settings.taxes.update` | `settings.manage` |
| POST | `/settings/taxes/rates` | `settings.taxes.rates.store` | `settings.manage` |
| PATCH | `/settings/taxes/rates/{taxRate}` | `settings.taxes.rates.update` | `settings.manage` |
| DELETE | `/settings/taxes/rates/{taxRate}` | `settings.taxes.rates.destroy` | `settings.manage` |

Rates are resolved through `$currentStore->taxRates()` — never global implicit binding.

---

## Permission matrix

| Role | View | Mutate settings/rates |
|------|------|------------------------|
| Owner | Yes | Yes |
| Manager | Yes | No (403) |
| Staff | Yes | No (403) |
| Guest | No (redirect sign-in) | No |
| Non-member | No (404 on mutate) | No |

Cross-store rate IDs return **404**.

---

## New-store bootstrap

`Store::booted()` `created` callback calls `TaxConfigurationService::ensureSettingsForStore()` when `tax_settings` table exists.

Defaults: tax disabled, exclusive prices, default product taxable, shipping not taxable, `calculation_address = shipping`, `settings_version = 1`.

Idempotent via `firstOrCreate`. Does not create tax rates.

`GET /settings/taxes` is read-only: loads existing `TaxSetting` or returns **503** with a merchant-safe message when missing. No `ensureSettingsForStore()` on index.

---

## Closeout corrections (2026-06-24)

1. **calculation_address** — defaults to `shipping` only when absent; submitted values such as `billing` fail HTTP validation without mutating settings or incrementing version.
2. **Inactive rate creation** — create form uses hidden `is_active=0` plus checkbox `is_active=1`; `StoreTaxRateRequest` no longer defaults omitted checkbox to active.
3. **Decimal no-op detection** — `rate_percent` compared with `bccomp` at scale 4 (`8.25` ≡ `8.2500`).
4. **GET read-only** — index never provisions or repairs tax settings.
5. **SQLite round-trip test** — temp file under `sys_get_temp_dir()`, explicit `DB::purge`/`reconnect` on switch and restore.

---

## settings_version behavior

Inside `DB::transaction()`:

1. `lockForUpdate()` on store's `TaxSetting` row
2. Mutate settings or rates
3. Increment `settings_version` exactly once on real persisted changes
4. Commit
5. Record security log

No increment on: validation failure, authorization failure, cross-store failure, true no-op updates.

---

## Security events

| Event | When |
|-------|------|
| `tax.settings.updated` | Settings saved with changes |
| `tax.rate.created` | Rate added |
| `tax.rate.updated` | Rate changed or toggled |
| `tax.rate.deleted` | Rate removed |

Metadata: `store_id`, actor, `settings_version`, jurisdiction, `tax_rate_id` — no secrets or full payloads.

---

## UI behavior

- Owner: full settings form + add/edit/delete rate controls (create/edit rate forms include hidden `is_active=0`)
- Manager/staff: read-only values, no mutation forms; message “Only the store owner can change tax settings.”
- Disclaimer: basic tax rates, not legal advice
- Notice: checkout calculation not live yet
- Empty rates state: merchant-friendly copy

---

## Slice 1A migration round-trip

`Phase5RTaxMigrationRoundTripTest` on isolated file SQLite in `sys_get_temp_dir()`:

1. `DB::purge` / `DB::reconnect` after pointing sqlite at temp file
2. `migrate:fresh`
3. Assert Slice 1A tables/column exist
4. `migrate:rollback --step=5`
5. Assert tax tables/column removed
6. `migrate` forward again
7. In `finally`: restore `:memory:`, purge/reconnect, delete temp file

---

## Tests and verification

| Command | Result |
|---------|--------|
| `php artisan test --filter=TaxSettingsTest` | 49 passed |
| `php artisan test --filter=Phase5RTaxMigrationRoundTripTest` | 1 passed |
| `php artisan test --filter=Phase5RTaxSchemaTest` | 20 passed |
| `php artisan test` | **793 passed**, 2 skipped (3874 assertions) |
| Pint (Slice 1B PHP files) | Pass |

Baseline before Slice 1B: 743 passed.

---

## Confirmations

- No `TaxCalculator` or `CurrencyPrecision`
- No checkout, Stripe, or PaymentIntent changes
- No carrier/admin changes
- Model A unchanged
- No commit made
