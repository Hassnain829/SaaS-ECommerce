# Project Cleanup Master Plan

Project: **E_COMMERCE_OFFICE** (repository: SaaS-Static-Blade)

## Purpose

This plan documents repository hygiene work through CLEAN-4. **CLEAN-1 through CLEAN-4 are implemented.** Later items (admin-side tool migration, additional decomposition) remain deferred.

CLEAN-1A corrects archive/cleanup safety defects (`.gitattributes` env templates, Laravel placeholder preservation, tracked-file deletion protection, path traversal hardening, real ZIP verification). **CLEAN-1 is accepted only after CLEAN-1A passes.**

## Locked carrier architecture (unchanged by cleanup)

- **Model A / Official Integrator Provider** is the primary courier connectivity strategy for FedEx and future couriers where supported.
- Merchants connect merchant-owned courier accounts through platform onboarding; they should not paste developer API keys in normal flows.
- **Model B / merchant developer credentials** remain an explicitly documented **temporary developer fallback** only (`FEDEX_MODEL_B_DEVELOPER_FALLBACK_ENABLED`).
- CLEAN phases do **not** change FedEx, USPS, shipping, checkout, orders, inventory, billing, or admin behavior.

## Phase overview

| Phase | Name | Status | Scope |
|-------|------|--------|-------|
| CLEAN-1 | Source, archive, and repository hygiene | **Implemented** | `.gitignore`, `.gitattributes`, hygiene commands, docs, safe runtime cleanup |
| CLEAN-1A | Archive and cleanup safety corrections | **Implemented** | Env template export fix, placeholder preservation, tracked-file protection, path guard, ZIP integration tests |
| CLEAN-2 | Logical file/code organization (carriers) | **Implemented** | Core/FedEx/USPS folders, Carrier controllers, `routes/carriers.php`, docs |
| CLEAN-3 | Runtime storage retention and scheduled maintenance | **Implemented** | `project:retention`, protection markers, config, scheduler (disabled by default) |
| CLEAN-3A | Retention test isolation and real-worktree protection | **Implemented** | Marked sandboxes, destructive guard in testing, regression tests |
| CLEAN-4 | Controlled refactoring of giant controllers/services/routes | **Implemented** | Presenter, payload builder, import row mapper, `routes/onboarding.php`; see `docs/cleanup/CLEAN_4_CONTROLLED_REFACTORING_REPORT.md` |
| Later | Admin-side validation/dev tools migration | Deferred | Admin panel migration remains deferred |
| Later | Carrier-neutral product work while approvals pending | Ongoing | Resume normal roadmap after hygiene |

## CLEAN-1 deliverables

- Hardened ignore/export rules
- `php artisan project:hygiene-report`
- `php artisan project:cleanup` (dry-run default)
- `php artisan project:source-archive` (dry-run/list/create)
- `php artisan project:retention` (dry-run default; age-based retention — see `docs/operations/RUNTIME_STORAGE_RETENTION.md`)
- Cleanup documentation under `docs/cleanup/`
- Documentation alignment: Model A primary, Model B fallback only

## Non-goals (all phases until explicitly scheduled)

- Admin panel changes
- Merchant UI or business logic changes
- Carrier API behavior changes
- Git history rewrite
- Automatic deletion of canonical validation evidence
- Deleting migrations, tests, source, or merchant uploads

## Decision authority

When uncertain whether a file is junk or evidence, **leave it** and record the item in `CLEANUP_DECISION_LOG.md`.
