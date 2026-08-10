# Refactoring Boundaries (CLEAN-4)

This document records **internal extraction boundaries** introduced during CLEAN-4. Public HTTP contracts, carrier behavior, import outcomes, and merchant UX are unchanged.

FedEx Integrator certification / validation workspace controllers and presenters have been **removed** and are not part of the current architecture. See `docs/architecture/CARRIER_CODE_STRUCTURE.md` and `docs/fedex/MODEL_A_INTEGRATOR_PROVIDER.md`.

## FedEx account registration payload

| Layer | Class | Responsibility |
|-------|-------|----------------|
| Orchestration | `App\Services\Carriers\FedEx\Connection\FedExAccountRegistrationService` | OAuth, HTTP calls, MFA steps, event logging, credential persistence |
| Payload construction | `App\Services\Carriers\FedEx\Connection\FedExRegistrationPayloadBuilder` | Account number normalization, v2 registration payload, request summary diagnostics |
| Response analysis | `App\Services\Carriers\FedEx\Connection\FedExRegistrationResponseAnalyzer` | *(existing)* credential extraction, MFA detection |

**Preserved:** registration API payload shape, residential mode behavior, debug/redacted export helpers.

## Product import row mapping

| Layer | Class | Responsibility |
|-------|-------|----------------|
| Pipeline orchestration | `App\Services\Catalog\ProductImportProcessor` | Import session lifecycle, DB transactions, product/variant persistence, images, taxonomy |
| Row normalization | `App\Services\Catalog\ProductImportRowMapper` | Header alignment, field mapping, unmapped extras, custom fields, attributes, delimited values |

**Preserved:** import row outcomes, meta layering, variant finalization delegation.

## Onboarding and store-product routes

| Layer | Location | Responsibility |
|-------|----------|----------------|
| Main web routes | `routes/web.php` | Core merchant routes; includes `require routes/onboarding.php` inside authenticated store group |
| Onboarding routes | `routes/onboarding.php` | Onboarding steps, store management mutations, store-scoped product CRUD from onboarding flows |
| Onboarding controller | `App\Http\Controllers\Store\OnboardingController` | Onboarding + product save pipeline |
| Dashboard controller | `App\Http\Controllers\Store\DashboardController` | Merchant shell, auth, product list |
| Shipping settings | `App\Http\Controllers\Settings\ShippingSettingsController` | Delivery hub |
| Product workspace | `App\Http\Controllers\Catalog\ProductWorkspaceController` | Product workspace read/edit |
| Import HTTP | `App\Http\Controllers\Catalog\ProductImportController` | Import upload/mapping |

**Preserved:** all route names, URIs, middleware, controller@action bindings.

## Overlap decisions

Remaining FedEx layered seam:

- `FedExAccountRegistrationService` vs connection wizard controllers (HTTP vs API orchestration)

See `docs/architecture/CARRIER_CODE_STRUCTURE.md` for carrier folder layout.

## Deferred targets (future CLEAN or roadmap)

| Target | LOC (approx.) | Reason deferred |
|--------|---------------|-----------------|
| `OnboardingController` | ~2260 | `App\Http\Controllers\Store\OnboardingController` — no dedicated characterization suite; high merchant workflow risk |
| `DashboardController` | ~1220 | `App\Http\Controllers\Store\DashboardController` — 34 public actions; weak isolated test coverage |
| `ShippingSettingsController` | ~870 | `App\Http\Controllers\Settings\ShippingSettingsController` — touches shipping automation surface |
| `ProductImportVariantFinalizer` | ~925 | Variant matrix rules; needs phase-specific tests before extraction |
| `ProductImportController` | large | `App\Http\Controllers\Catalog\ProductImportController` — HTTP/session patterns; separate from row-mapping seam |

Future extractions must follow the same rules: characterization tests first, one concern per change, no route or behavior drift.
