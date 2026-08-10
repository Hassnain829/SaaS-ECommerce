# Carrier Code Structure

Project: **E_COMMERCE_OFFICE** (SaaS-Static-Blade)  
Updated: post FedEx Integrator certification removal (production-only FedEx)

## Architecture (locked)

**Model A / Official Integrator Provider** is the primary courier connectivity strategy for FedEx and future couriers where supported. The platform owns integrator/parent credentials; merchants connect merchant-owned courier accounts through onboarding. **Model B** (merchant-entered FedEx Developer credentials) remains a documented developer fallback only (`local`/`testing` + config flag).

Carrier billing stays merchant-owned. Platform SaaS billing is separate.

FedEx Integrator certification / validation workspace code is **removed**. Do not reintroduce `Validation/` namespaces, validation routes, or `storage/app/fedex-validation/`.

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
│   ├── Operations/          # Address, rates, ship, tracking, ETD (production API calls)
│   ├── Presenters/          # Merchant-facing status formatting
│   ├── DTO/                 # FedEx-specific value objects (e.g. FedExApiEventContext)
│   └── Support/             # Config, HTTP client, branding, security, CarrierProvider
└── USPS/
    ├── Auth/
    ├── Operations/
    └── Support/

app/Http/Controllers/Carrier/
├── Connection/              # Wizard + FedEx Model A integrator onboarding
└── Operations/              # FedEx merchant production ops (order ship/rates/ETD/tracking)

app/Http/Controllers/Admin/
└── FedExAdminDiagnosticsController.php   # Admin FedEx ops console (/admin-fedex)

routes/carriers.php          # Requires routes/fedex.php + routes/usps.php
```

## Responsibility boundaries

| Layer | Purpose | Examples |
|-------|---------|----------|
| **Core** | Provider-neutral shared services | Event logging, wizard orchestration, origin readiness |
| **Auth** | Token/credential acquisition | Parent/child OAuth, Model B fallback OAuth |
| **Connection** | Account onboarding state | EULA, registration API, MFA, child credentials |
| **Operations** | Production API operations | Address Validation, rates, labels, tracking, ETD |
| **Presenters** | UI mapping only | Connection status / check result formatting |
| **Support** | Config + HTTP + branding/security + provider adapter | `FedExConfig`, `FedExHttpClient`, `FedExBrandComplianceService`, `FedExCarrierProvider` |

`ShippingSettingsController` remains in `app/Http/Controllers/Settings/` because it owns the full shipping settings page (zones, methods, manual delivery) — only carrier-specific routes were extracted.

## Routes

Carrier HTTP routes live in `routes/carriers.php` (loads `fedex.php` / `usps.php`) inside the authenticated store middleware group via `require __DIR__.'/carriers.php'` in `routes/web.php`.

There is **no** `routes/fedex-validation.php`.

## Tests

| Area | Location |
|------|----------|
| FedEx production / Model A | `tests/Feature/Phase6FedEx*.php`, `tests/Feature/FedEx*.php`, `tests/Unit/FedEx*.php` |
| Certification-gone guard | `tests/Feature/FedExCertificationSystemRemovedTest.php` |
| USPS foundation | `tests/Feature/Phase6USPSPublicApiFoundationTest.php` |
| Carrier origin readiness | `tests/Feature/Phase6CarrierOriginReadinessTest.php` |
| Route regression | `tests/Feature/CarrierRouteRegressionTest.php` |
| Retention / hygiene | `tests/Feature/ProjectRetentionCommandsTest.php`, `tests/Feature/ProjectHygieneCommandsTest.php` |
| Shipping UX | `tests/Feature/Phase6ShippingDeliveryUxTest.php` |

## FedEx / USPS runtime storage paths

| Path | Classification |
|------|----------------|
| `storage/app/private/fedex/labels/` | Protected production FedEx labels |
| `storage/app/private/fedex/` (ETD / related) | Protected production FedEx artifacts |
| `storage/app/usps-validation/**/staging` | Temporary USPS staging (retention eligible) |

Mark any directory with `.protected` or `evidence-manifest.json` to exclude it from automated retention. See `docs/operations/RUNTIME_STORAGE_RETENTION.md`.

## Adding a future courier

1. Create `app/Services/Carriers/{Courier}/` with `Auth`, `Connection`, `Operations`, `Support` as needed.
2. Implement `{Courier}CarrierProvider` against `Core\Contracts\CarrierProviderInterface`.
3. Register in `Core\CarrierProviderManager`.
4. Add connection/operations controllers under `app/Http/Controllers/Carrier/` if merchant flows are needed.
5. Add routes to `routes/carriers.php` (or a dedicated `routes/{courier}.php` required from it).
6. Do **not** introduce Integrator certification/validation folders.

## Production seams

| Pair | Decision |
|------|----------|
| `FedExAccountRegistrationService` vs `FedExRegistrationPayloadBuilder` | **Layered** — orchestration vs payload construction |

## Related docs

- `docs/fedex/MODEL_A_INTEGRATOR_PROVIDER.md` — FedEx Model A production implementation
- `docs/operations/RUNTIME_STORAGE_RETENTION.md` — runtime storage retention
- `docs/architecture/REFACTORING_BOUNDARIES.md` — extraction boundaries
- Historical CLEAN reports under `docs/cleanup/` (not current architecture)
