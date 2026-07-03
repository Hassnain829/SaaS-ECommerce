# PROJECT_STRUCTURE.md — Codebase Map for Cursor Agents

> **Read this first** when navigating the repository. Canonical product rules remain in `ENTERPRISE_PROJECT_CONTEXT.md` and `ENTERPRISE_ROADMAP_2026.md`. This file explains **where code lives** and **how layers connect**.

Last reorganized: 2026-07-03 (controller domain folders + docs/phases).

---

## 1. Five-Layer Mental Model

```
routes/ + Middleware
    → Http/Controllers/{Domain}/
        → Services/{Domain}/
            → Models/ + database/migrations/
        → resources/views/
tests/ validate Services + HTTP flows
docs/ = reports & plans (not runtime code)
dev-test-storefront/ = local API/checkout simulator (not production storefront)
```

Every tenant operation must pass **`EnsureCurrentStore`** middleware and scope queries by `store_id`.

---

## 2. Route Entry Points

| File | Purpose |
|------|---------|
| `routes/web.php` | Merchant dashboard (catalog, commerce, settings, delivery) |
| `routes/onboarding.php` | Onboarding steps + **product save** (`PUT /product/{id}`) — included from `web.php` |
| `routes/carriers.php` | Carrier connect wizard, FedEx integrator, validation workspace — included from `web.php` |
| `routes/api.php` | Developer/catalog/checkout/external sync APIs + Stripe webhooks |
| `routes/console.php` | Scheduled Artisan commands |

---

## 3. Controllers by Domain

Base class: `app/Http/Controllers/Controller.php`

### `Catalog/` — Products, taxonomy, import

| Controller | Primary routes / views |
|------------|------------------------|
| `ProductWorkspaceController` | `GET products/{id}`, `GET products/{id}/edit` → `product_workspace*.blade.php` |
| `ProductWorkspaceDataController` | AJAX/data for workspace |
| `ProductBulkController` | Bulk catalog actions |
| `ProductImportController` | Import upload/mapping/preview → `product_import/*` |
| `BrandController`, `CategoryController`, `TagController`, `AttributeController` | Taxonomy CRUD modals |

**Important:** Product **save** still goes through `Store/OnboardingController::updateProductFromManagement` via `routes/onboarding.php` (`PUT /product/{id}`). Edit UI is Catalog; save pipeline is Store (known bridge — future extraction target).

### `Commerce/` — Orders, customers, fulfillment

| Controller | Views / flows |
|------------|---------------|
| `OrderController` | `orders`, `orderViewDetails` |
| `DraftOrderController` | Draft order create/edit |
| `CustomerController` | `customers`, customer profile |
| `ShipmentController` | Manual shipments |

### `Settings/` — Store configuration

| Controller | Surface |
|------------|---------|
| `TaxSettingsController` | `settings/taxes.blade.php` |
| `PaymentSettingsController` | `payment_settings.blade.php` |
| `LocationController` | `locations.blade.php` |
| `ShippingSettingsController` | `shippingAutomation.blade.php` + shipping tabs |
| `DeliverySetupWizardController` | `delivery/setup/*` wizard |
| `DeveloperStorefrontSettingsController` | Dev API token management |
| `TeamMemberController` | Team invites & roles |

### `Store/` — Shell, auth, onboarding

| Controller | Surface |
|------------|---------|
| `DashboardController` | Dashboard, product list, sign-in/register, many list shells |
| `CurrentStoreController` | Switch active store |
| `OnboardingController` | Onboarding steps + product CRUD save pipeline |

### `Admin/` — Platform admin (mostly view shells)

| Controller | Surface |
|------------|---------|
| `AdminController` | `admin_view/*` |

### `Api/` — External integrations (Bearer dev token unless webhook)

| Controller | Prefix |
|------------|--------|
| `CatalogApiV1Controller` | `/api/v1/catalog/*` |
| `DeveloperStorefrontCatalogController` | `/api/developer-storefront/*` (legacy) |
| `PlatformCheckoutController` | `/api/v1/checkout/*` |
| `ExternalOrderSyncController`, `ExternalShipmentSyncController` | `/api/v1/external/*` |
| `StripeWebhookController`, `StripeConnectWebhookController` | `/api/webhooks/stripe/*` |

### `Carrier/` — Already organized (CLEAN-2)

```
Carrier/
├── Connection/   # Connect wizard, FedEx integrator Model A
├── Operations/   # Merchant carrier tests
└── Validation/   # FedEx validation workspace, runs, exports
```

---

## 4. Services (Business Logic)

Controllers should delegate here. **~149 service classes** under `app/Services/`:

| Folder | Responsibility |
|--------|----------------|
| `Catalog/` | Product import pipeline, variant finalizer, image download |
| `Checkout/` | Checkout totals, conversion, shipping selection |
| `Delivery/` | Setup wizard persistence, readiness assessment, input normalizers |
| `Shipping/` | Zones, delivery options, checkout shipping |
| `Tax/` | Tax configuration, calculator |
| `Payments/` | Stripe Connect, payment provider manager |
| `Inventory/` | Locations sync, reservations, availability |
| `Fulfillment/` | Shipments, origin routing |
| `Carriers/Core/` | Provider interface, connection wizard |
| `Carriers/FedEx/` | Largest subtree — connection, validation, operations |
| `Carriers/USPS/` | OAuth, rate quotes |

Support helpers: `app/Support/` (permissions, product payloads, stock recorder, project hygiene).

---

## 5. Models & Database

**63 Eloquent models** in `app/Models/`. **73 migrations** in `database/migrations/`.

Core relationships:

```
Store
 ├── products → product_variants → product_images
 ├── customers → orders → order_items → shipments
 ├── checkouts → (converts to) orders
 ├── locations → inventory_levels → stock_movements
 ├── shipping_zones → shipping_methods
 ├── carrier_accounts → carrier
 └── tax_settings → tax_rates
```

All tenant reads/writes must verify `store_id` ownership.

---

## 6. Views (`resources/views/`)

| Path | Purpose |
|------|---------|
| `layouts/user/` | Sidebar, main merchant layout |
| `components/geo/` | Country/region/postal selects |
| `user_view/` | Merchant UI (primary) |
| `user_view/product_import/` | Import wizard |
| `user_view/delivery/setup/` | Delivery setup wizard |
| `user_view/shipping/` | Delivery hub tabs |
| `user_view/fedex_integrator/` | FedEx Model A connect flow |
| `user_view/carrier_connection_wizard/` | Carrier selection cards |
| `user_view/onboarding-Step*.blade.php` | Onboarding (legacy naming) |
| `admin_view/` | Platform admin placeholders |

View names are **not** renamed yet (cosmetic deferred). Controllers above map to these paths.

---

## 7. Tests

| Folder | Count | Role |
|--------|-------|------|
| `tests/Feature/` | Majority | HTTP/integration (Phase*, Delivery*, Product*) |
| `tests/Unit/` | Services, normalizers | Isolated logic |
| `tests/Support/` | Helpers | Shared test utilities |

Run full suite: `php artisan test` (expect ~1191+ passing).

---

## 8. Documentation Layout (`docs/`)

| Folder | Contents | Canonical? |
|--------|----------|------------|
| `docs/canonical/` | Pointers to root enterprise docs | Index only |
| `docs/phases/` | `PHASE_*_REPORT.md` completion reports | Historical proof |
| `docs/architecture/` | `REFACTORING_BOUNDARIES`, `CARRIER_CODE_STRUCTURE` | **Yes — structure reference** |
| `docs/ux/` | Delivery UX batches, acceptance reports | Active UX reference |
| `docs/cleanup/` | CLEAN-1 through CLEAN-4 reports | Hygiene history |
| `docs/implementation/` | Slice/batch implementation reports | Historical |
| `docs/audit/` | QA gap/risk/command outputs | Audit snapshots |
| `docs/fedex/` | FedEx integrator docs, baselines | Carrier reference |
| `docs/operations/` | Local setup, retention, release checklist | Ops reference |
| `docs/reports/` | Standalone hardening/support reports | Historical |
| `docs/plans/` | Implementation plans | Planning |
| `docs/archive/` | Notes on deprecated `.agents/rules/` | Archive index |

**Root canonical docs (always win on conflict):**

- `ENTERPRISE_PROJECT_CONTEXT.md`
- `ENTERPRISE_ROADMAP_2026.md`
- `PROJECT_BRAIN.md`
- `AGENTS.md`
- `PROJECT_STRUCTURE.md` (this file)

**Deprecated / historical only:** `.agents/rules/*.txt` — do not treat as source of truth.

---

## 9. Non-Production & Ignore

| Path | Action |
|------|--------|
| `dev-test-storefront/` | Keep — API/checkout test simulator |
| `storage/` | Runtime only — never commit compiled views/logs |
| `vendor/`, `node_modules/` | Dependencies |
| `tmp_*.php` | **Do not commit** — gitignored |
| `docs/QA_REMEDIATION_REPORT.docx` | Generated artifact — optional delete |

---

## 10. Typical Request Flows (Quick Reference)

### Product edit
`GET /products/{id}/edit` → `Catalog\ProductWorkspaceController@edit` → Blade  
`PUT /product/{id}` → `Store\OnboardingController@updateProductFromManagement` → DB

### Delivery setup
`GET /settings/delivery/setup` → `Settings\DeliverySetupWizardController` → `Services/Delivery/*` → zones/methods/locations

### Carrier connect
`GET /settings/shipping/carriers/connect` → `Carrier\Connection\CarrierConnectionWizardController` → FedEx/USPS services → `carrier_accounts`

### Platform checkout (external)
`POST /api/v1/checkout` → `Api\PlatformCheckoutController` → `CheckoutService` → Stripe + `CheckoutShippingService` → order conversion

---

## 11. Known Structural Debt (Not Bugs)

Documented in `docs/architecture/REFACTORING_BOUNDARIES.md`:

- Fat controllers: `Store\OnboardingController`, `Store\DashboardController`, `Settings\ShippingSettingsController`
- Dual product save pipeline (Catalog edit + Store save)
- Settings spread across multiple controllers/views
- Onboarding routes mixed into production `web.php` group

Future extractions must keep route names and behavior unchanged; add characterization tests first.

---

## 12. When Adding New Code

1. Pick the **domain folder** (Catalog, Commerce, Settings, Store, Api, Carrier).
2. Put business logic in `app/Services/{Domain}/`, not in controllers.
3. Scope every query to the current store.
4. Add Feature tests for HTTP flows; Unit tests for services.
5. Update this file only when **folder layout** changes — not for every new class.

---

## 13. Phase 9 (Integration Foundation)

Approved Phase 9 execution plan (goals, baseline, batch order, do-not-do list). **Not** a root canonical document — root enterprise docs remain authoritative on conflict:

**[`docs/plans/PHASE_9_INTEGRATION_FOUNDATION_PLAN.md`](docs/plans/PHASE_9_INTEGRATION_FOUNDATION_PLAN.md)**

Status: not started in code. First batch when explicitly instructed: **9-0** (contract tests + architecture docs only).
