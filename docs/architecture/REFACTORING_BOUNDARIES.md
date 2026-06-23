# Refactoring Boundaries (CLEAN-4)

This document records **internal extraction boundaries** introduced during CLEAN-4. Public HTTP contracts, carrier behavior, import outcomes, and merchant UX are unchanged.

## FedEx carrier test presentation

| Layer | Class | Responsibility |
|-------|-------|----------------|
| HTTP / auth / validation | `App\Http\Controllers\Carrier\Operations\FedExCarrierTestController` | Request validation, store/account resolution, service calls, security logging |
| Redirect + flash contract | `App\Services\Carriers\FedEx\Presenters\FedExCarrierTestResponsePresenter` | `fedex_test_result` session shape, success/warning/failure messaging, authorization-blocked support summary |

**Preserved:** route names, middleware, redirects to `shippingAutomation?tab=carriers`, session keys, error flash titles.

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

**Preserved:** all route names, URIs, middleware, controller@action bindings.

## Overlap decisions (unchanged)

CLEAN-4 did **not** merge these pairs — responsibilities and callers differ:

- `FedExCarrierTestController` (merchant validation tools) vs `FedExValidationRunController` (validation workspace runs)
- `FedExAccountRegistrationService` vs connection wizard controllers (HTTP vs API orchestration)

See `docs/architecture/CARRIER_CODE_STRUCTURE.md` for carrier folder layout.

## Deferred targets (future CLEAN or roadmap)

| Target | LOC (approx.) | Reason deferred |
|--------|---------------|-----------------|
| `OnboardingController` | ~2260 | No dedicated characterization suite; high merchant workflow risk |
| `DashboardController` | ~1220 | 34 public actions; weak isolated test coverage |
| `ShippingSettingsController` | ~870 | Touches shipping automation surface |
| `ProductImportVariantFinalizer` | ~925 | Variant matrix rules; needs phase-specific tests before extraction |
| `ProductImportController` | large | HTTP/session patterns; separate from row-mapping seam |

Future extractions must follow the same rules: characterization tests first, one concern per change, no route or behavior drift.
