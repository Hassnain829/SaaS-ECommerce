# Batch 1 — Delivery UX (Presentation & Information Architecture)

**Status:** Completed  
**Plan reference:** [MERCHANT_SETUP_AND_DELIVERY_UX_SIMPLIFICATION_PLAN.md](./MERCHANT_SETUP_AND_DELIVERY_UX_SIMPLIFICATION_PLAN.md) (Revised 2026-07-02)  
**Roadmap alignment:** Pre–Batch 2 simplification; no write-path or matcher changes

---

## Goal

Make merchant delivery setup **understandable** without changing backend models, checkout resolution, tax logic, or fulfillment routing. Batch 1 is **read-only presentation**: terminology, navigation, setup health, summaries, and links into existing editors.

---

## What shipped

### Merchant-facing terminology

| Old / technical | New merchant language |
|-----------------|----------------------|
| Shipping & automation | **Delivery** |
| Shipping zones | **Delivery areas** |
| Shipping methods | **Delivery options** |
| Tax settings (sidebar) | **Checkout & tax** |

### Navigation

- Sidebar: **Delivery** hub + **Checkout & tax** (tax stays separate).
- Top-level **Locations** removed from sidebar; ship-from reached via Delivery hub / General settings links.
- Delivery page tabs: **Setup overview** + **Advanced settings** (legacy `?tab=zones` etc. map to Advanced via JS).

### Delivery setup overview (read-only)

New deterministic setup health via `DeliverySetupStatusService`:

- Ship-from status (active location, address completeness, online fulfillment)
- Delivery areas summary
- Delivery options summary
- Delivery providers summary
- **Tax read-only** block with link to Checkout & tax (no tax writes from Delivery)

Health cards use **configuration checks only** — no address simulation, no checkout API calls.

### Advanced settings entry

Existing locations, zones, methods, and carrier panels remain reachable under **Advanced settings** with updated copy.

---

## Key files

| File | Role |
|------|------|
| `app/Services/Delivery/DeliverySetupStatusService.php` | Read-only setup health + plain-language summaries |
| `app/Http/Controllers/ShippingSettingsController.php` | Injects setup status + tax setting into Delivery view |
| `resources/views/user_view/shippingAutomation.blade.php` | Delivery hub shell + tabs |
| `resources/views/user_view/shipping/tabs/overview.blade.php` | Merchant questions + health cards |
| `resources/views/user_view/shipping/tabs/advanced.blade.php` | Embeds legacy advanced panels |
| `resources/views/layouts/user/user-sidebar.blade.php` | Sidebar grouping |
| `resources/views/user_view/generalSettings.blade.php` | Delivery CTA copy |
| `resources/views/user_view/settings/taxes.blade.php` | Checkout & tax title |

---

## Explicit non-goals (Batch 1)

- No new write endpoints
- No structured country/state/postal inputs
- No delivery wizard
- No `DeliveryAreaInputNormalizer` / manual provider auto-creation
- No changes to `ShippingZoneMatcher`, `DeliveryOptionService`, `CheckoutShippingService`, `TaxCalculator`

---

## Tests

- `tests/Unit/DeliverySetupStatusServiceTest.php`
- `tests/Feature/Phase6ShippingDeliveryUxTest.php` (updated hub strings)
- Regression: `Phase6CheckoutDeliveryMethodsTest`

---

## Sign-off notes

Batch 1 is **functionally complete** for presentation and IA. Residual UX polish (mobile drawer a11y, wizard) belongs to Batch 3.

**Next:** [Batch 2 — Structured inputs & simple editors](./BATCH_2_IMPLEMENTATION.md)
