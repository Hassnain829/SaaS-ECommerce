# Refactoring roadmap

CLEAN-4 completed the first controlled extractions. Boundaries are documented in `docs/architecture/REFACTORING_BOUNDARIES.md`. Historical report: `docs/archive/cleanup/CLEAN_4_CONTROLLED_REFACTORING_REPORT.md`.

This file is **not** the product roadmap. Current product priority: `docs/current/PROJECT_STATE.md` and `docs/handoffs/DEVELOPMENT_READINESS_MERCHANT_UX_REVIEW.md`.

## Completed in CLEAN-4

| Area | Extraction | Notes |
|------|------------|-------|
| FedEx registration | Registration payload builder | Characterization coverage retained |
| Product import | `ProductImportRowMapper` | Characterization coverage retained |
| Routes | `routes/onboarding.php` | Characterization coverage retained |

Some earlier CLEAN-4 presenter extractions for certification-era FedEx test surfaces were later removed with the certification workspace retirement. Do not restore certification tooling.

## Remaining candidates (deferred)

| Area | File | Suggested direction |
|------|------|---------------------|
| Onboarding | `app/Http/Controllers/Store/OnboardingController.php` | Extract store/product setup steps into action classes or services; keep controller as orchestration only. **Requires characterization tests first.** |
| Dashboard | `app/Http/Controllers/Store/DashboardController.php` | Split profile, store management, and analytics surfaces incrementally. |
| Product import pipeline | `app/Services/Catalog/ProductImportProcessor.php` | Further split persist/images/taxonomy phases (row mapping already extracted). |
| Variant finalization | `app/Services/Catalog/ProductImportVariantFinalizer.php` | Isolate SKU/option matrix rules and image attachment; cover with focused variant/import tests before cutting. |
| Import HTTP | `app/Http/Controllers/Catalog/ProductImportController.php` | Move validation + session flash patterns to form requests / actions. |
| Shipping settings | `app/Http/Controllers/Settings/ShippingSettingsController.php` | Extract carrier tab presenters only after shipping regression coverage. |
| Merchant UI | `resources/views/user_view/products.blade.php` | Incrementally extract Blade partials by concern without changing route contracts in one pass. |

## Rules for future refactors

- Preserve route names, authorization, redirects, view names, JSON shapes, transactions, events, jobs, logs, and database side effects.
- Add or extend characterization tests **before** cutting large methods apart.
- Prefer small changes per extraction (one concern per PR).
- Do not refactor every large file blindly — audit risk and test coverage first.
- Do not reintroduce FedEx certification/validation namespaces.
