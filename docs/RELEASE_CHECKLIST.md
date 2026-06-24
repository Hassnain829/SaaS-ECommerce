# Release checklist

Use this before tagging a release or merging to production branches.

## Repository hygiene

- [ ] No tracked `.env`, real API keys, or tokens (only `*.example` templates).
- [ ] No tracked `vendor/`, `node_modules/`, `database/*.sqlite`, `storage/logs/*.log`, `bootstrap/cache/*.php`, or `.phpunit.cache/`.
- [ ] No tracked carrier validation artifacts under `storage/app/fedex-validation/` or `storage/app/usps-validation/`.
- [ ] Run `php artisan project:hygiene-report` and review potential leak paths before packaging.
- [ ] For source ZIP deliveries, use `php artisan project:source-archive --dry-run` then `php artisan project:source-archive` (Git required; see `docs/cleanup/SOURCE_ARCHIVE_GUIDE.md`).
- [ ] Verify generated ZIP excludes `.env` but includes `.env.example` and Laravel writable-directory `.gitignore` placeholders.
- [ ] `project:cleanup --force` must never be run against canonical validation evidence; it cannot delete Git-tracked files.
- [ ] Review `php artisan project:retention --dry-run` before enabling scheduled or forced retention (`docs/operations/RUNTIME_STORAGE_RETENTION.md`).
- [ ] Destructive retention/cleanup tests must use marked sandboxes only (`docs/cleanup/CLEAN_3A_RETENTION_TEST_ISOLATION_REPORT.md`).
- [ ] `PROJECT_RETENTION_SCHEDULE_FORCE` remains `false` unless operations explicitly approves destructive scheduled pruning.
- [ ] Carrier routes remain registered after `routes/carriers.php` extraction (`tests/Feature/CarrierRouteRegressionTest.php`).
- [ ] `composer validate --strict` passes.
- [ ] `php artisan test` passes locally (same PHP extensions as CI: `dom`, `mbstring`, `xml`, `xmlwriter`, plus `pdo_sqlite` for the default test suite).
- [ ] Phase 5R checkout/tax regression filters pass when tax is implemented (`Phase5PlatformCheckoutStripeTest`, `Phase5ExternalCheckoutSyncTest`, `Phase4DraftOrderTest`, `Phase6CheckoutDeliveryMethodsTest`).
- [ ] After Phase 5R-0: calculation audit current (`docs/audit/PHASE_5R_0_CURRENT_CALCULATION_AUDIT.md`). Slices 1A–3 complete (schema, settings UI, calculator, create-path checkout tax); shipping recalc and conversion invariant must not ship until Slices 4–5 pass (`docs/implementation/PHASE_5R_1_SLICE_3_CHECKOUT_TOTALS_REPORT.md`).

## Builds

- [ ] `npm ci` and `npm run build` at repository root.
- [ ] `cd dev-test-storefront && npm ci && npm run build`.

## Laravel caches

- [ ] Clear stale caches before packaging or deploying: `php artisan config:clear`, `route:clear`, `view:clear`.

## Secrets

- [ ] If anything sensitive was ever committed or shared, follow `SECURITY_ROTATION_REQUIRED.md`.

## CI

- [ ] GitHub Actions workflow `.github/workflows/ci.yml` is green on the release commit.
