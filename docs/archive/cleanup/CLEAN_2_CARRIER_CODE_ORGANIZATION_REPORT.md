# CLEAN-2 Carrier Code Organization Report

Date: 2026-06-23  
Scope: **CLEAN-2 only** — file/folder organization; no business logic changes

## Baseline (pre-CLEAN-2)

- Full suite: **671 passed, 1 skipped** (1 pre-existing failure in `Phase6FedExMerchantApiChecksTest` during early run; resolved after namespace fixes)
- Carrier services: 55 files in flat `app/Services/Carriers/` and `FedEx/` root
- Controllers: 8 carrier-related controllers at `app/Http/Controllers/` root
- Routes: all carrier routes inline in `routes/web.php`

## Summary

CLEAN-2 reorganizes carrier code into **Core / FedEx / USPS** responsibility folders and **Carrier/Connection|Operations|Validation** controller namespaces. All imports, tests, and route names were updated. **No merchant, admin, FedEx, USPS, shipping, checkout, order, or validation behavior was changed.**

Model A / Integrator Provider remains the locked primary architecture.

## Files moved (59 `git mv` operations)

### Core (`app/Services/Carriers/Core/`)

| From | To |
|------|-----|
| `CarrierProviderInterface.php` | `Core/Contracts/CarrierProviderInterface.php` |
| `DTO/*.php` (3 files) | `Core/DTO/` |
| `CarrierApiEventLogger.php` | `Core/CarrierApiEventLogger.php` |
| `CarrierConnectionWizardService.php` | `Core/CarrierConnectionWizardService.php` |
| `CarrierOriginReadinessService.php` | `Core/CarrierOriginReadinessService.php` |
| `CarrierProviderManager.php` | `Core/CarrierProviderManager.php` |

### FedEx (namespace `App\Services\Carriers\FedEx\{Auth|Connection|Operations|Validation|Presenters|Support}`)

| Folder | Count | Examples |
|--------|-------|----------|
| Auth | 5 | Parent/child OAuth, Model B OAuth, token service |
| Connection | 8 | Registration, EULA, orchestrator, validators |
| Operations | 9 | Address, rates, ship, tracking, trade docs |
| Validation | 10 | Evidence export, preflight, fixtures, MFA evidence |
| Presenters | 3 | Status, workspace cards, merchant check |
| Support | 3 | Config, HTTP client, CarrierProvider |
| DTO | 3 | Unchanged path `FedEx/DTO/` |

### USPS

| Folder | Files |
|--------|-------|
| Auth | `USPSOAuthTokenService.php` |
| Operations | `USPSAddressValidationService.php`, `USPSDomesticRateQuoteService.php` |
| Support | `USPSConfig.php`, `USPSHttpClient.php`, `USPSCarrierProvider.php` |

### Controllers

| From | To |
|------|-----|
| `CarrierConnectionWizardController.php` | `Carrier/Connection/` |
| `FedExIntegratorConnectionController.php` | `Carrier/Connection/` |
| `FedExCarrierTestController.php` | `Carrier/Operations/` |
| `FedExValidation*Controller.php` (4) | `Carrier/Validation/` |
| `Concerns/ResolvesFedExValidationAccount.php` | `Carrier/Validation/Concerns/` |

## Files created

| File | Purpose |
|------|---------|
| `routes/carriers.php` | Extracted carrier route definitions |
| `docs/architecture/CARRIER_CODE_STRUCTURE.md` | Canonical carrier layout reference |
| `tests/Feature/CarrierRouteRegressionTest.php` | Route registration regression tests |

## Files removed

- One-time migration helpers `scripts/clean2_update_namespaces.php` and `scripts/clean2_add_imports.php` (used during CLEAN-2 only; deleted at close-out)

No dead duplicate classes were found safe to delete.

## Duplicate / overlap decisions

| Pair | Decision |
|------|----------|
| `FedExCarrierTestController` vs `FedExValidationRunController` | **Keep both** — shipping-page sandbox checks vs validation workspace locked scenarios |
| `FedExTestCaseFixtureService` vs `FedExShipTestCaseFixtureService` | **Keep both** — general workbook/MFA fixtures vs ship label fixtures |
| `FedExValidationStatusPresenter` vs `FedExValidationWorkspaceCardPresenter` | **Keep both** — summary vs per-card layout |
| `FedExIntegratorConnectionController::exportValidation` vs `FedExValidationExportController` | **Keep both** — legacy simple export vs diagnostic/final preflight exports |

Documented for **CLEAN-4** review in `docs/architecture/CARRIER_CODE_STRUCTURE.md`.

## Compatibility shims

**None retained.** All references updated to new FQCNs.

## Route organization

- Carrier routes extracted to `routes/carriers.php`
- Loaded via `require __DIR__.'/carriers.php'` inside the authenticated store group in `routes/web.php`
- URLs, names, middleware, HTTP methods, and controller actions **unchanged**

## Minor hygiene alignment (archive test stability)

- `ProjectSourceArchiveService` now uses `git archive --worktree-attributes` so current `.gitattributes` placeholder rules apply
- `.gitattributes` cache rule adjusted (`data/*` instead of `/**`)
- Archive integration test uses HEAD commit file list for placeholder expectations

## Line endings / Pint

- **65 carrier/controller files** formatted with Pint (LF, import order)
- Resolved pre-existing line-ending issues in touched FedEx validation files

## Test results (exact)

| Command | Result |
|---------|--------|
| `php artisan test --filter=FedEx` | **162 passed** |
| `php artisan test --filter=USPS` | **26 passed** |
| `php artisan test --filter=Carrier` | **77 passed** |
| `php artisan test --filter=Shipping` | **29 passed** |
| `php artisan test` | **673 passed, 1 skipped, 3327 assertions** (66.13s) |
| `vendor/bin/pint` (carrier paths) | **PASS** on 65 touched files |

Skipped: symlink rejection test in `ProjectHygieneCommandsTest` (Windows environment).

## Behavior preservation

- Admin panel: **unchanged**
- Merchant UI / Blade views: **unchanged** (view names unchanged)
- FedEx/USPS API payloads, OAuth, registration, validation: **unchanged**
- Database schema: **unchanged**
- Model A primary architecture: **confirmed**

## Deferred

- **CLEAN-3** — scheduled storage pruning
- **CLEAN-4** — large controller/service decomposition; overlap review above
- Admin validation tools migration — still deferred
