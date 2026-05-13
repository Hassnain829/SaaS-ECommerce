# Refactoring roadmap (deferred)

The QA report flagged several very large classes and views. These are **not** candidates for a single risky rewrite without dedicated time, tests around extracted units, and product sign-off.

## Priority candidates (later)

| Area | File | Suggested direction |
|------|------|---------------------|
| Onboarding | `app/Http/Controllers/OnboardingController.php` | Extract store/product setup steps into action classes or services; keep controller as orchestration only. |
| Product import | `app/Services/Catalog/ProductImportProcessor.php` | Split pipeline phases (normalize, persist, images, variants) behind small collaborators; preserve import idempotency and progress reporting. |
| Variant finalization | `app/Services/Catalog/ProductImportVariantFinalizer.php` | Isolate SKU/option matrix rules and image attachment into dedicated services covered by `ProductImportVariantDay16Test` and siblings. |
| Import HTTP | `app/Http/Controllers/ProductImportController.php` | Move validation + session flash patterns to form requests / actions. |
| Merchant UI | `resources/views/user_view/products.blade.php` | Incrementally extract Livewire/Blade partials by concern (filters, bulk actions, row cells) without changing route contracts in one pass. |

## Rules for future refactors

- Preserve route names, authorization, and store scoping.
- Add or extend tests **before** cutting large methods apart.
- Prefer small PRs per extraction (one concern per PR).
