# CLEAN-4 Controlled Refactoring Report

**Project:** E_COMMERCE_OFFICE (SaaS-Static-Blade)  
**Date:** 2026-06-24  
**Status:** Implemented  
**Constraint:** No merchant, carrier, checkout, order, inventory, billing, or admin behavior changes.

## Summary

CLEAN-4 audited oversized controllers, services, processors, and route files. Four high-value, test-backed extractions were implemented with characterization tests added **before** each refactor. Documentation contradictions from CLEAN-2/CLEAN-3 close-out were corrected in the master plan and decision log.

## Audit (pre-refactor)

Physical line counts at audit time (`@(Get-Content path).Count`).

| File | Physical lines | Public methods | Test coverage | Risk | Decision |
|------|----------------|----------------|---------------|------|----------|
| `OnboardingController.php` | 2625 | 17 | None dedicated | High | **Deferred** |
| `DashboardController.php` | 1433 | 34 | None dedicated | High | **Deferred** |
| `ProductImportProcessor.php` | 1424 | 3 | `ProductImportTest`, Day13/16 | Medium | **Selected** (row mapper only) |
| `ProductImportVariantFinalizer.php` | 1017 | 2 | Day16 variant tests | Medium | **Deferred** |
| `FedExAccountRegistrationService.php` | 796 | 10 | Phase6 FedEx tests | Medium | **Selected** (payload builder) |
| `ShippingSettingsController.php` | 1006 | 19 | Indirect shipping tests | High | **Deferred** |
| `FedExCarrierTestController.php` | 684 | 7 | Phase6 + route regression | Low–medium | **Selected** (presenter) |
| `routes/web.php` | 406 | — | Route registration | Low | **Selected** (onboarding extract) |
| `routes/carriers.php` | 181 | — | `CarrierRouteRegressionTest` | Low | No change (already extracted in CLEAN-2) |

## Selected targets and extractions

### 1. `FedExCarrierTestResponsePresenter`

- **From:** `FedExCarrierTestController` redirect/flash helpers
- **To:** `app/Services/Carriers/FedEx/Presenters/FedExCarrierTestResponsePresenter.php`
- **Tests:** `tests/Unit/FedExCarrierTestResponsePresenterTest.php` (4 tests)

### 2. `FedExRegistrationPayloadBuilder`

- **From:** `FedExAccountRegistrationService` payload/summary helpers
- **To:** `app/Services/Carriers/FedEx/Connection/FedExRegistrationPayloadBuilder.php`
- **Tests:** `tests/Unit/FedExRegistrationPayloadBuilderTest.php` (5 tests)

### 3. `ProductImportRowMapper`

- **From:** `ProductImportProcessor` row mapping helpers
- **To:** `app/Services/Catalog/ProductImportRowMapper.php`
- **Tests:** `tests/Unit/ProductImportRowMapperTest.php` (5 tests)

### 4. `routes/onboarding.php`

- **From:** onboarding + store-product onboarding routes in `routes/web.php`
- **To:** `routes/onboarding.php` (required inside authenticated store middleware group)
- **Tests:** `tests/Feature/OnboardingRouteRegressionTest.php`

## Before / after metrics

Physical line counts in the current repository after CLEAN-4 closeout. Counting method: total lines returned by reading each file (`@(Get-Content path).Count`). Extracted-class “before” contribution is the line delta removed from the source file during extraction.

| Artifact | Before (physical lines) | After (physical lines) | Extracted to (physical lines) |
|----------|-------------------------|------------------------|-------------------------------|
| `FedExCarrierTestController.php` | 684 | 492 | `FedExCarrierTestResponsePresenter.php`: 192 |
| `FedExAccountRegistrationService.php` | 796 | 656 | `FedExRegistrationPayloadBuilder.php`: 140 |
| `ProductImportProcessor.php` | 1424 | 1279 | `ProductImportRowMapper.php`: 154 |
| `routes/web.php` + onboarding block | 406 | 379 (+ `require`) | `routes/onboarding.php`: 28 |

## Route contract verification

- **Carrier routes:** unchanged; `CarrierRouteRegressionTest` retained.
- **Onboarding routes:** 17 named routes verified in `OnboardingRouteRegressionTest` — name, URI, HTTP method, middleware, controller, and action.
- **No admin routes touched.**

## Overlap decisions

No consolidation of FedEx test vs validation controllers or registration vs wizard HTTP layers. Rationale documented in `docs/architecture/REFACTORING_BOUNDARIES.md`.

## Documentation updates

- `docs/cleanup/PROJECT_CLEANUP_MASTER_PLAN.md` — CLEAN-4 marked implemented; intro aligned with phase table
- `docs/cleanup/CLEANUP_DECISION_LOG.md` — open items closed; CLEAN-4 entry added
- `docs/architecture/REFACTORING_BOUNDARIES.md` — new boundary reference
- `docs/REFACTORING_ROADMAP.md` — completed vs remaining candidates
- `PROJECT_BRAIN.md`, `README.md`, `ENTERPRISE_PROJECT_CONTEXT.md`, `ENTERPRISE_ROADMAP_2026.md` — current-state alignment

## Verification commands

```bash
git diff --check
composer dump-autoload
php artisan test --filter=OnboardingRouteRegressionTest
php artisan test --filter=CarrierRouteRegressionTest
php artisan test --filter=FedExCarrierTestResponsePresenterTest
php artisan test --filter=FedExRegistrationPayloadBuilderTest
php artisan test --filter=ProductImportRowMapperTest
php artisan test
vendor/bin/pint --test
```

## Non-goals (honored)

- No admin panel changes
- No new functionality
- No FedEx/USPS/shipping/checkout/order/inventory/billing behavior changes
- No database business migrations
- No forced retention/cleanup against real project data
- No git commit (per task instruction)

## Post-CLEAN-4

Cleanup is complete. Resume carrier-neutral platform roadmap work while courier production approvals are pending. Further decomposition of deferred targets requires dedicated characterization tests per target.
