# PROJECT_STRUCTURE.md — Codebase Map for Cursor Agents

> **Read this first** when navigating the repository. Volatile status: `docs/current/PROJECT_STATE.md`. Product rules: `ENTERPRISE_PROJECT_CONTEXT.md`. Build order: `ENTERPRISE_ROADMAP_2026.md`.

Last updated: 2026-08-12 (documentation cleanup; inspected inventory).

---

## 1. Five-Layer Mental Model

```
routes/ + Middleware
    → Http/Controllers/{Domain}/
        → Services/{Domain}/
            → Models/ + database/migrations/
        → resources/views/
tests/ validate Services + HTTP flows
docs/ = active plans + ops + current state (archive = history only)
dev-test-storefront/ = local API/checkout simulator (not production storefront)
```

Every tenant operation must pass **`EnsureCurrentStore`** middleware and scope queries by `store_id`.

---

## 2. Route Entry Points

| File | Purpose |
|------|---------|
| `routes/web.php` | Merchant dashboard (catalog, commerce, settings, delivery) |
| `routes/onboarding.php` | Onboarding steps + product save pipeline — included from `web.php` |
| `routes/carriers.php` | Carrier connect wizard shell — included from `web.php` |
| `routes/fedex.php` | FedEx Model A integrator + production merchant ops |
| `routes/usps.php` | USPS merchant connection / foundation routes |
| `routes/api.php` | Connected-site catalog/platform checkout APIs + Stripe webhooks; retired order/shipment sync routes are absent |
| `routes/console.php` | Scheduled Artisan commands |

There is **no** `routes/fedex-validation.php`. FedEx certification/validation routes are removed.

---

## 3. Controllers by Domain

Base class: `app/Http/Controllers/Controller.php`

### `Catalog/` — Products, taxonomy, import

| Controller | Primary routes / views |
|------------|------------------------|
| `ProductWorkspaceController` | `GET products/{id}`, `GET products/{id}/edit` → product workspace |
| `ProductWorkspaceDataController` | AJAX/data for workspace |
| `ProductInlineController` | List inline price/stock (`products.inline.*`) |
| `ProductBulkController` | Bulk catalog actions including soft-delete restore/force-delete |
| `ProductImportController` | Import upload/mapping/preview |
| `BrandController`, `CategoryController`, `TagController`, `AttributeController` | Taxonomy CRUD |

**Canonical product edit path:** `products.edit` (product workspace). Product list Edit must route to that workspace — not a list-page Edit modal as the primary workflow.

**Product save bridge (known debt):** save still goes through `Store\OnboardingController::updateProductFromManagement` via `routes/onboarding.php` (`PUT /product/{id}`). Soft delete / restore / permanent delete also live on onboarding routes.

### `Commerce/` — Orders, customers, fulfillment

| Controller | Views / flows |
|------------|---------------|
| `OrderController` | orders, order detail |
| `DraftOrderController` | Draft order create/edit |
| `CustomerController` | customers, customer profile |
| `ShipmentController` | Manual shipments |

### `Settings/` — Store configuration

Tax, payments, locations, shipping/delivery, developer storefront token, team members.

### `Store/` — Shell, auth, onboarding

Dashboard, current-store switch, onboarding + product CRUD save pipeline.

### `Admin/` — Platform admin

Admin shells and FedEx admin diagnostics (`Admin\FedExAdminDiagnosticsController`).

### `Api/` — Connected-site integrations

Catalog API, developer storefront catalog compatibility path, platform checkout polling, site health/events, and Stripe webhooks. Direct paid-order and external order/shipment sync controllers are removed.

### `Carrier/` — Connection + operations only

```
Carrier/
├── Connection/   # Connect wizard, FedEx integrator Model A
└── Operations/   # Merchant carrier production ops
```

There is **no** `Carrier/Validation` namespace. FedEx certification controllers are removed.

---

## 4. Services (Business Logic)

**159 service classes** under `app/Services/` (inspected 2026-08-12).

| Folder | Responsibility |
|--------|----------------|
| `Catalog/` | Product import pipeline, variant finalizer, image download |
| `Checkout/` | Checkout totals, conversion, shipping selection |
| `Delivery/` | Setup wizard persistence, readiness assessment |
| `Shipping/` | Zones, delivery options, checkout shipping |
| `Tax/` | Tax configuration, calculator |
| `Payments/` | Stripe Connect, payment provider manager |
| `Inventory/` | Locations sync, reservations, availability |
| `Fulfillment/` | Shipments, origin routing |
| `Carriers/Core/` | Provider interface, connection wizard |
| `Carriers/FedEx/` | Connection, operations, presenters, support (no `Validation/`) |
| `Carriers/USPS/` | OAuth, rate quotes foundation |

Support helpers: `app/Support/` (permissions, product payloads, stock recorder, project hygiene).

FedEx certification code under `Services/Carriers/FedEx/Validation` is **removed**. Keep old create/drop migrations in migration history.

---

## 5. Models & Database

**73 Eloquent models** in `app/Models/`. **93 migrations** in `database/migrations/` (inspected 2026-08-12).

Old FedEx validation/certification **create** migrations remain for history; a later drop migration removes those tables when present. Do not delete historical migrations merely because filenames contain `validation`.

All tenant reads/writes must verify `store_id` ownership.

---

## 6. Views (`resources/views/`)

**151 Blade views** (inspected 2026-08-12).

Primary merchant UI under `resources/views/user_view/`. FedEx Model A under `user_view/fedex_integrator/`. Admin FedEx under `admin/fedex/`.

---

## 7. Tests

| Folder | Count (inspected) | Role |
|--------|------------------:|------|
| `tests/Feature/` | 122 | HTTP/integration |
| `tests/Unit/` | 32 | Isolated logic |
| `tests/Support/` | helpers | Shared utilities |

Keep `tests/Feature/FedExCertificationSystemRemovedTest.php` — isolation guard for removed certification system.

Do not claim the full suite is green without a successful run.

---

## 8. Documentation Layout (`docs/`)

| Folder | Contents | Authority |
|--------|----------|-----------|
| `docs/current/` | `PROJECT_STATE.md` | **Volatile current state** |
| `docs/handoffs/` | Active readiness review | Release-readiness scope |
| `docs/canonical/` | Pointers to root enterprise docs | Index |
| `docs/architecture/` | Carrier structure, refactoring boundaries/roadmap | Structure reference |
| `docs/cleanup/` | Decision log + source archive guide | Active ops hygiene |
| `docs/fedex/` | `MODEL_A_INTEGRATOR_PROVIDER.md` | Active FedEx architecture |
| `docs/operations/` | Setup, security, release, retention | Ops reference |
| `docs/plans/` | Active DR-05 correction/cutover plans and Phase 9 plan (not complete) | Planning |
| `docs/archive/` | Historical phases/audits/reports | **Historical only** |

**Root docs:** `ENTERPRISE_PROJECT_CONTEXT.md`, `ENTERPRISE_ROADMAP_2026.md`, `PROJECT_BRAIN.md`, `AGENTS.md`, `PROJECT_STRUCTURE.md`, `README.md`, `SECURITY_ROTATION_REQUIRED.md`.

---

## 9. Non-Production & Ignore

| Path | Action |
|------|--------|
| `dev-test-storefront/` | Keep — API/checkout test simulator |
| `storage/` | Runtime only |
| `vendor/`, `node_modules/` | Dependencies |
| `docs/archive/` | Historical; ignored by Cursor; export-ignored from source archives |

---

## 10. Typical Request Flows

### Product edit
`GET /products/{id}/edit` (`products.edit`) → `Catalog\ProductWorkspaceController@edit` → workspace Blade
`PUT /product/{id}` → `Store\OnboardingController@updateProductFromManagement` → DB

### Delivery setup
`GET /settings/delivery/setup` → `Settings\DeliverySetupWizardController` → `Services/Delivery/*`

### Carrier connect
`GET /settings/shipping/carriers/connect` → `Carrier\Connection\*` → FedEx/USPS services → `carrier_accounts`

### Platform checkout (external)
`POST /api/v1/checkout` → `Api\PlatformCheckoutController` → checkout + shipping services

---

## 11. Known Structural Debt

Documented in `docs/architecture/REFACTORING_BOUNDARIES.md`:

- Fat controllers: `Store\OnboardingController`, `Store\DashboardController`, `Settings\ShippingSettingsController`
- Dual product save pipeline (Catalog edit + Store save)
- Settings spread across multiple controllers/views

Future extractions must keep route names and behavior unchanged; add characterization tests first.

---

## 12. When Adding New Code

1. Pick the domain folder (Catalog, Commerce, Settings, Store, Api, Carrier).
2. Put business logic in `app/Services/{Domain}/`, not in controllers.
3. Scope every query to the current store.
4. Add Feature tests for HTTP flows; Unit tests for services.
5. Update this file only when **folder layout** changes.

---

## 13. Phase 9 (Integration Foundation)

Approved plan: [`docs/plans/PHASE_9_INTEGRATION_FOUNDATION_PLAN.md`](docs/plans/PHASE_9_INTEGRATION_FOUNDATION_PLAN.md)

Status: **not complete**. Merchant readiness P0 and suite recovery precede Phase 9 unless explicitly reprioritized. See `docs/current/PROJECT_STATE.md`.

---

## 14. DR-05 WordPress cutover

- Correction contract: [`docs/plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md`](docs/plans/DR05_BATCH6_CRITICAL_FIX_SPEC.md)
- Batch 7 plan: [`docs/plans/DR05_BATCH7_MERCHANT_CUTOVER_PLAN.md`](docs/plans/DR05_BATCH7_MERCHANT_CUTOVER_PLAN.md)
- Batch 8 evidence: [`docs/handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md`](docs/handoffs/DR05_BATCH8_RELEASE_EVIDENCE.md)
- Architecture: [`docs/WORDPRESS_SAAS_COMMERCE_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md`](docs/WORDPRESS_SAAS_COMMERCE_ARCHITECTURE_AND_IMPLEMENTATION_PLAN.md)

DR-05 Batches 1–8 are complete. Runtime cutover lives in `app/Services/MerchantCutoverService.php` and the Website go-live checklist. DR-06 merchant acceptance is in progress; Phase 9 remains separate.
