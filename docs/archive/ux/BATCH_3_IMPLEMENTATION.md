# Batch 3 — Delivery UX (Guided Flow & Advanced Polish)

**Status:** Sign-off complete (Delivery UX scope)
**Final report:** [DELIVERY_UX_FINAL_ACCEPTANCE_REPORT.md](./DELIVERY_UX_FINAL_ACCEPTANCE_REPORT.md)
**Plan reference:** [MERCHANT_SETUP_AND_DELIVERY_UX_SIMPLIFICATION_PLAN.md](./MERCHANT_SETUP_AND_DELIVERY_UX_SIMPLIFICATION_PLAN.md) §13, §17  
**Depends on:** [Batch 1](./BATCH_1_IMPLEMENTATION.md), [Batch 2](./BATCH_2_IMPLEMENTATION.md)

---

## Sign-off checklist

| Criterion | Status | Evidence |
|-----------|--------|----------|
| Four-step wizard saves/manages real data | ✅ | `DeliveryWizardPersistenceService` + full-flow feature test |
| No duplicate delivery-option business logic | ✅ | Shared `DeliveryOptionInputNormalizer` (wizard + advanced editor) |
| Legacy advanced configurations preserved | ✅ | `isLegacyZone()` blocks wizard edit; advanced tab unchanged |
| Review shows persisted wizard selections | ✅ | Review overrides summaries via `summarizeShipFromLocation` / area / option |
| Tax read-only & separate | ✅ | Review link only; `test_wizard_finish_does_not_mutate_tax_settings` |
| Diagnostic reuses production matchers | ✅ | `ShippingZoneMatcher` + `DeliveryOptionService` in `DeliveryAddressDiagnosticService` |
| Diagnostic has no writes/API/payment | ✅ | Read-only controller action + feature test |
| Batch 1/2 carryover risks test-covered | ✅ | Batch 2 + Phase 6 checkout/delivery UX tests updated & passing |
| Mobile / accessibility pass | ✅ | Wizard progress `aria-*`, drawer `role="dialog"`, Escape close, focus on open |
| Permissions / store scoping | ✅ | Staff view-only test-address; wizard POST 403; cross-store zone blocked |
| FedEx/USPS behavior untouched | ✅ | No carrier service changes; carrier regression tests pass |
| Coupons not started | ✅ | Out of scope per plan §14 |
| Full repo regression suite | ✅ | **1182 passed**, 2 skipped (see final acceptance report) |

### Sign-off correction pass (2026-05-24)

- Hub GET read-only; checkout-usable readiness; wizard finish validation
- Selector hydration on all wizard steps; preserved advanced fields
- Storefront E2E: wizard option appears in `/api/v1/checkout/{id}/delivery-options`
- Regression fixes: tax migration rollback step, FedEx test expectations, `DeliverySetupStatusServiceTest` DI

---

## What shipped

### Four-step Delivery setup wizard

Routes under `settings/delivery/setup/*`:

| Step | Heading | Persists to |
|------|---------|-------------|
| 1 | Where do you ship from? | `locations` |
| 2 | Where do you deliver? | `shipping_zones` (simple one-country) |
| 3 | What should customers see at checkout? | `shipping_methods` |
| 4 | Review your delivery setup | Read-only summary + finish |

**Rules preserved:**

- Each step saves **real records** immediately.
- Session stores navigation hints only: `delivery_wizard.location_id`, `zone_id`, `method_id`.
- Tax is **read-only on review** with link to Checkout & tax.
- Legacy multi-country areas are **not editable** in wizard.
- Existing non-manual `carrier_account_id` preserved on wizard method update.
- Flag mismatch resolution explicit (same as Batch 2).

### Test a customer address (read-only)

Route: `settings/delivery/test-address`

Uses `ShippingZoneMatcher`, `DeliveryOptionService`, and `DeliveryAddressDiagnosticService`.

**Does not:** write to DB, create checkout, calculate tax, call carriers, or mutate inventory.

### Shared normalizer (dedupe)

`DeliveryOptionInputNormalizer` — pricing mode, simple/advanced availability, unique method code — used by:

- `DeliveryWizardPersistenceService`
- `ShippingSettingsController` (advanced drawers)

Zone/area logic remains in `DeliveryAreaInputNormalizer` (Batch 2).

### Delivery hub updates

- Primary **Set up delivery** / **Review delivery setup**
- Quick action: **Test a customer address**

### Accessibility / mobile polish

- Wizard step nav: `aria-label`, `aria-current`, keyboard-focusable completed-step links
- Shipping drawers: `role="dialog"`, `aria-modal`, Escape to close, focus first field on open
- Removed merchant-visible `payload` wording from zone editor JS (`data-zone-form`)

---

## Key files

```
app/Http/Controllers/DeliverySetupWizardController.php
app/Services/Delivery/DeliveryWizardPersistenceService.php
app/Services/Delivery/DeliveryAddressDiagnosticService.php
app/Services/Delivery/DeliveryOptionInputNormalizer.php
app/Services/Delivery/DeliverySetupStatusService.php (wizard summarize helpers)
resources/views/user_view/delivery/*
resources/views/user_view/shipping/tabs/overview.blade.php
routes/web.php (settings/delivery/*)
```

---

## Tests (Delivery UX bundle)

| Test | Coverage |
|------|----------|
| `tests/Feature/DeliveryUxBatch3Test.php` | Full wizard, legacy block, carrier preserve, staff, tax, diagnostic, a11y, hub read-only, storefront E2E |
| `tests/Unit/DeliveryAddressDiagnosticServiceTest.php` | Available/unavailable + reason codes |
| `tests/Unit/DeliveryOptionInputNormalizerTest.php` | Shared pricing/availability |
| `tests/Feature/DeliveryUxBatch2Test.php` | Structured inputs regression |
| `tests/Feature/Phase6CheckoutDeliveryMethodsTest.php` | Checkout resolution unchanged |
| `tests/Feature/Phase6ShippingDeliveryUxTest.php` | Hub + FedEx/USPS sections |
| `tests/Feature/CarrierRouteRegressionTest.php` | Carrier routes intact |

Run bundle:

```bash
php artisan test tests/Feature/DeliveryUxBatch3Test.php tests/Unit/DeliveryAddressDiagnosticServiceTest.php tests/Unit/DeliveryOptionInputNormalizerTest.php tests/Feature/DeliveryUxBatch2Test.php tests/Feature/Phase6CheckoutDeliveryMethodsTest.php tests/Feature/Phase6ShippingDeliveryUxTest.php tests/Feature/CarrierRouteRegressionTest.php
```

---

## Explicit non-goals (unchanged)

- No changes to `ShippingZoneMatcher`, `DeliveryOptionService`, `CheckoutShippingService`, `TaxCalculator`
- No new migrations or setup-completion database flag
- No dev-test-storefront changes
- No Coupons (Phase 5R-2)

**Previous batches:** [Batch 1](./BATCH_1_IMPLEMENTATION.md) · [Batch 2](./BATCH_2_IMPLEMENTATION.md)
