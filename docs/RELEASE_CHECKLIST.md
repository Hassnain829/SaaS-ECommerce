# Release checklist

Use this before tagging a release or merging to production branches.

## Repository hygiene

- [ ] No tracked `.env`, real API keys, or tokens (only `*.example` templates).
- [ ] No tracked `vendor/`, `node_modules/`, `database/*.sqlite`, `storage/logs/*.log`, `bootstrap/cache/*.php`, or `.phpunit.cache/`.
- [ ] `composer validate --strict` passes.
- [ ] `php artisan test` passes locally (same PHP extensions as CI: `dom`, `mbstring`, `xml`, `xmlwriter`, plus `pdo_sqlite` for the default test suite).

## Builds

- [ ] `npm ci` and `npm run build` at repository root.
- [ ] `cd dev-test-storefront && npm ci && npm run build`.

## Laravel caches

- [ ] Clear stale caches before packaging or deploying: `php artisan config:clear`, `route:clear`, `view:clear`.

## Secrets

- [ ] If anything sensitive was ever committed or shared, follow `SECURITY_ROTATION_REQUIRED.md`.

## CI

- [ ] GitHub Actions workflow `.github/workflows/ci.yml` is green on the release commit.
