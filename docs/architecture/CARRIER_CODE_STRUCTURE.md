# Carrier Code Structure

Project: **E_COMMERCE_OFFICE** (SaaS-Static-Blade)  
Updated: CLEAN-2 carrier code organization

## Architecture (locked)

**Model A / Official Integrator Provider** is the primary courier connectivity strategy for FedEx and future couriers where supported. The platform owns integrator/parent credentials; merchants connect merchant-owned courier accounts through onboarding. **Model B** (merchant-entered FedEx Developer credentials) remains a documented developer fallback only.

Carrier billing stays merchant-owned. Platform SaaS billing is separate.

## Top-level layout

```
app/Services/Carriers/
├── Core/                    # Shared carrier infrastructure
│   ├── Contracts/           # CarrierProviderInterface
│   ├── DTO/                 # CarrierApiResult, connection/readiness DTOs
│   ├── CarrierApiEventLogger.php
│   ├── CarrierConnectionWizardService.php
│   ├── CarrierOriginReadinessService.php
│   └── CarrierProviderManager.php
├── FedEx/
│   ├── Auth/                # OAuth, token acquisition, credential-mode classification
│   ├── Connection/          # Registration, EULA, MFA orchestration, merchant account connect
│   ├── Operations/          # Address, rates, ship, tracking, trade docs (production API calls)
│   ├── Validation/          # Integrator validation evidence, fixtures, export, preflight
│   ├── Presenters/          # Merchant-facing status/workspace formatting
│   ├── DTO/                 # FedEx-specific value objects
│   └── Support/             # Config, HTTP client, CarrierProvider implementation
└── USPS/
    ├── Auth/
    ├── Operations/
    └── Support/

app/Http/Controllers/Carrier/
├── Connection/              # Wizard + FedEx Model A integrator onboarding
├── Operations/              # FedEx sandbox API checks from shipping settings
└── Validation/              # FedEx validation workspace, runs, exports, artifacts
    └── Concerns/

routes/carriers.php          # Carrier route definitions (required from web.php)
```

## Responsibility boundaries

| Layer | Purpose | Examples |
|-------|---------|----------|
| **Core** | Provider-neutral shared services | Event logging, wizard orchestration, origin readiness |
| **Auth** | Token/credential acquisition | Parent/child OAuth, Model B fallback OAuth |
| **Connection** | Account onboarding state | EULA, registration API, MFA, child credentials |
| **Operations** | Live/sandbox API operations | Rates, labels, tracking, address validation |
| **Validation** | FedEx integrator certification | Locked scenarios, evidence export, preflight |
| **Presenters** | UI mapping only | Workspace cards, connection status copy |
| **Support** | Config + HTTP + provider adapter | `FedExConfig`, `FedExHttpClient`, `FedExCarrierProvider` |

`ShippingSettingsController` remains in `app/Http/Controllers/` because it owns the full shipping settings page (zones, methods, manual delivery) — only carrier-specific routes were extracted.

## Routes

Carrier HTTP routes live in `routes/carriers.php` and are loaded inside the authenticated store middleware group via `require __DIR__.'/carriers.php'` in `routes/web.php`.

URLs, route names, middleware, and controller actions are unchanged from pre-CLEAN-2.

## Tests

| Area | Location |
|------|----------|
| FedEx foundation / Model A | `tests/Feature/Phase6FedEx*.php`, `tests/Unit/FedEx*.php` |
| USPS foundation | `tests/Feature/Phase6USPSPublicApiFoundationTest.php` |
| Carrier origin readiness | `tests/Feature/Phase6CarrierOriginReadinessTest.php` |
| Route regression (CLEAN-2) | `tests/Feature/CarrierRouteRegressionTest.php` |
| Retention (CLEAN-3) | `tests/Feature/ProjectRetentionCommandsTest.php` |
| Shipping UX | `tests/Feature/Phase6ShippingDeliveryUxTest.php` |

## Validation storage paths (CLEAN-3 classification)

| Path | Classification |
|------|----------------|
| `storage/app/fedex-validation/{store}/{timestamp}/FedEx_Integrator_Validation_*` | Temporary staging (retention eligible) |
| `storage/app/fedex-validation/{store}/{timestamp}/fedex-validation-diagnostic-*.zip` | Temporary export (retention eligible) |
| `storage/app/fedex-validation/{store}/{timestamp}/fedex-validation-final-*.zip` | Protected canonical submission |
| `storage/app/fedex-validation/{store}/labels/` | Protected production labels |
| `storage/app/fedex-validation/{store}/uploads/` | Protected scans and merchant uploads |
| `storage/app/usps-validation/**/staging` | Temporary staging (retention eligible) |

Mark any directory with `.protected` or `evidence-manifest.json` to exclude it from automated retention. See `docs/operations/RUNTIME_STORAGE_RETENTION.md`.

## Adding a future courier

1. Create `app/Services/Carriers/{Courier}/` with `Auth`, `Connection`, `Operations`, `Support` as needed.
2. Implement `{Courier}CarrierProvider` against `Core\Contracts\CarrierProviderInterface`.
3. Register in `Core\CarrierProviderManager`.
4. Add connection/operations controllers under `app/Http/Controllers/Carrier/` if merchant flows are needed.
5. Add routes to `routes/carriers.php`.
6. Do **not** copy FedEx validation folders unless that courier requires integrator certification tooling.

## Overlaps reviewed in CLEAN-4 (unchanged — keep both sides)

| Pair | Decision |
|------|----------|
| `FedExCarrierTestController` vs `FedExValidationRunController` | **Distinct** — shipping-page sandbox checks vs validation workspace locked scenarios; presentation extracted to `FedExCarrierTestResponsePresenter` in CLEAN-4 |
| `FedExTestCaseFixtureService` vs `FedExShipTestCaseFixtureService` | **Distinct** — general MFA/workbook fixtures vs ship label test cases |
| `FedExValidationStatusPresenter` vs `FedExValidationWorkspaceCardPresenter` | **Distinct** — summary status vs per-card workspace layout |
| `FedExIntegratorConnectionController::exportValidation` vs `FedExValidationExportController` | **Distinct** — legacy simple ZIP download vs diagnostic/final preflight exports |
| `FedExAccountRegistrationService` vs `FedExRegistrationPayloadBuilder` | **Layered** — orchestration vs payload construction (CLEAN-4 extraction; not a duplicate) |

No compatibility shims were retained — all references updated to new namespaces.

## Related docs

- `docs/fedex/MODEL_A_INTEGRATOR_PROVIDER.md` — FedEx Model A implementation
- `docs/FEDEX_MODEL_A_INTEGRATOR_PROVIDER_ROADMAP.md` — validation/production roadmap
- `docs/cleanup/CLEAN_2_CARRIER_CODE_ORGANIZATION_REPORT.md` — move inventory and test results
- `docs/cleanup/CLEAN_4_CONTROLLED_REFACTORING_REPORT.md` — controlled refactoring results
- `docs/architecture/REFACTORING_BOUNDARIES.md` — extraction boundaries
