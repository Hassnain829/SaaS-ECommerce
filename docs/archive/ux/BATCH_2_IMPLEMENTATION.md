# Batch 2 — Delivery UX (Structured Inputs & Simple Editors)

**Status:** Implemented  
**Plan reference:** [MERCHANT_SETUP_AND_DELIVERY_UX_SIMPLIFICATION_PLAN.md](./MERCHANT_SETUP_AND_DELIVERY_UX_SIMPLIFICATION_PLAN.md) §8, §14, §17  
**Depends on:** [Batch 1](./BATCH_1_IMPLEMENTATION.md)

---

## Goal

Replace raw comma/wildcard merchant inputs with **structured, country-aware editors** while preserving existing database shapes and checkout behavior. Batch 2 adds normalization on save and simple UI — **no migrations**, no matcher rewrites.

---

## What shipped

### Services

| Service | Purpose |
|---------|---------|
| `DeliveryAreaInputNormalizer` | Simple + legacy zone input → `countries[]`, `regions[]`, `postal_patterns[]` |
| `ManualDeliveryProviderResolver` | Reuse or safely create store-scoped manual `CarrierAccount` for flat-rate options |

**Normalizer behavior:**

- **Simple mode:** one `country_code`, `region_codes[]`, `postal_rules_json` (Exact / Starts with → internal `606*`)
- **Legacy mode:** `zone_editor_mode=legacy` + comma fields (multi-country advanced areas)
- **Backward compat:** direct `countries` / `regions` / `postal_patterns` POST fields still accepted

**Manual provider behavior:**

- Reuse enabled manual account for store
- Re-enable disabled manual account if present
- Create one manual account via `manual-delivery` carrier when missing
- Never cross-store

### Controller wiring

`ShippingSettingsController`:

- `storeZone` / `updateZone` → normalizer
- `storeMethod` / `updateMethod` → pricing mode mapping, availability rules, manual provider resolution
- Index passes `countries` (`TaxCountryCatalog`) to views

`LocationController`:

- Passes `countries` to locations view
- Normalizes `state` to region code when country has a catalog

### Geo Blade components

| Component | Use |
|-----------|-----|
| `components/geo/country-select.blade.php` | Searchable country select (ISO-2 stored) |
| `components/geo/region-select.blade.php` | Single state/province for locations |
| `components/geo/region-multi-select.blade.php` | Multi region for delivery areas |
| `components/geo/postal-rule-builder.blade.php` | Exact / Starts-with rule rows + JSON hidden field |

### Delivery drawers (Advanced → Areas / Options)

**Delivery area drawer:**

- Simple: one country, region checkboxes, postal rule builder
- Collapsed **Advanced delivery area** for legacy multi-country comma fields
- Edit buttons pass `data-payload` JSON from `presentationFromZone()`

**Delivery option drawer:**

- Delivery price radios: Fixed | Free | Free over amount
- **Available to customers** on create (sets both `is_active` + `enabled_for_checkout`)
- Advanced panel: separate Active / Show at checkout, carrier select, min/max order, full rate type
- Mismatched legacy flags: warning on edit; flags not silently reconciled

### Ship-from locations form

`resources/views/user_view/locations.blade.php`:

- Country + state/province structured selects
- Pickup, routing priority, service areas moved under **Advanced routing (optional)** `<details>`

### Client-side behavior

`shippingAutomation.blade.php` script:

- Region catalog JSON for dynamic region lists
- Postal rule add/remove + JSON sync
- Zone legacy/simple editor mode toggle
- Method price mode visibility + flat-rate mirror for free-over

---

## Locked rules preserved

| Rule | Implementation |
|------|----------------|
| One country per simple delivery area | Normalizer enforces single `country_code` in simple mode |
| Merchants never type `*` | Prefix rules stored as `606*` from “Starts with” UI |
| Legacy multi-country zones editable | Legacy panel + `editor_mode=legacy` |
| Mismatched method flags not overwritten | Update uses explicit advanced toggles; warning UI |
| No checkout/matcher/tax changes | Services untouched |
| No migrations | Array columns unchanged |

---

## Key files

```
app/Services/Delivery/DeliveryAreaInputNormalizer.php
app/Services/Delivery/ManualDeliveryProviderResolver.php
app/Http/Controllers/ShippingSettingsController.php
app/Http/Controllers/LocationController.php
resources/views/components/geo/*
resources/views/user_view/shipping/partials/drawers.blade.php
resources/views/user_view/shipping/tabs/zones.blade.php
resources/views/user_view/shipping/tabs/methods.blade.php
resources/views/user_view/locations.blade.php
resources/views/user_view/shippingAutomation.blade.php (JS)
```

---

## Tests

| Test | Coverage |
|------|----------|
| `tests/Unit/DeliveryAreaInputNormalizerTest.php` | Simple/legacy/postal prefix/dedup |
| `tests/Unit/ManualDeliveryProviderResolverTest.php` | Reuse, create, store scope |
| `tests/Feature/DeliveryUxBatch2Test.php` | Zone/method HTTP + structured UI strings |
| Regression | `Phase6CheckoutDeliveryMethodsTest`, `Phase6ShippingDeliveryUxTest`, `Phase6ManualFulfillmentTest` (legacy zone fields) |

---

## Not in Batch 2 (Batch 3)

- Four-step Delivery wizard
- **Test a customer address** diagnostic tool
- Full mobile/a11y drawer pass
- Carrier wizard redesign

---

## Merchant workflow after Batch 2

1. Open **Delivery** → Setup overview for health/status.
2. **Add delivery area:** pick country → optional states → optional postal rules (no wildcards typed).
3. **Add delivery option:** fixed/free pricing + “Available to customers”; manual provider attached automatically.
4. **Ship-from:** Locations page with country/state selects; routing hidden unless needed.
5. **Tax:** still only under **Checkout & tax**.

**Previous:** [Batch 1](./BATCH_1_IMPLEMENTATION.md)
