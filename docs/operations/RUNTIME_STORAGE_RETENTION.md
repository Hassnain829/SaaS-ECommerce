# Runtime Storage Retention

Operations guide for **CLEAN-3** runtime storage lifecycle management.

Model A / Integrator Provider carrier architecture is unchanged. This system manages **runtime files only** — not merchant business data, database rows, or admin features.

## Categories

| Category | Allowlisted paths | Default retention | Auto-delete |
|----------|-------------------|-------------------|-------------|
| **cache** | `bootstrap/cache/*.php`, `storage/framework/cache/data/*`, compiled views, PHPUnit cache | 24 hours | Yes (with `--force`) |
| **logs** | `storage/logs/*.log` (not active log) | 30 days | Yes |
| **validation-temp** | FedEx staging dirs, diagnostic ZIPs, USPS `staging/` | 14 days | Yes |
| **source-archives** | `storage/app/source-archives/E_COMMERCE_OFFICE-source-*.zip` | Keep latest 5 + max 30 days | Yes |
| **test-artifacts** | `.phpunit.cache/*`, `.phpunit.result.cache` | 24 hours | Yes |

### Never auto-deleted (protected)

- Git-tracked files and `.gitignore` placeholders
- `fedex-validation-final-*.zip` (canonical submissions)
- Paths under `/labels/`, `/uploads/`, `/00_documents/`, `/printed_scans/`
- Directories/files marked with `.protected` or `evidence-manifest.json`
- Merchant uploads (`storage/app/public/`, `storage/app/private/`)
- Unrelated ZIPs outside the source-archive naming pattern
- Database `carrier_api_events` and all business tables

## Configuration

See `config/project_retention.php` and `.env.example`.

| Variable | Default | Meaning |
|----------|---------|---------|
| `PROJECT_RETENTION_ENABLED` | `false` | Must be `true` before `--force` deletes |
| `PROJECT_RETENTION_DRY_RUN` | `true` | Default non-destructive mode |
| `PROJECT_RETENTION_LOG_DAYS` | `30` | Log file age threshold |
| `PROJECT_RETENTION_CACHE_HOURS` | `24` | Cache file age threshold |
| `PROJECT_RETENTION_SOURCE_ARCHIVE_COUNT` | `5` | Always retain newest N archives |
| `PROJECT_RETENTION_SOURCE_ARCHIVE_DAYS` | `30` | Delete extras older than N days |
| `PROJECT_RETENTION_VALIDATION_TEMP_DAYS` | `14` | Temp validation artifact age |
| `PROJECT_RETENTION_TEST_ARTIFACT_HOURS` | `24` | PHPUnit cache age |
| `PROJECT_RETENTION_SESSION_CLEANUP_ENABLED` | `false` | Opt-in file session cleanup |
| `PROJECT_RETENTION_SCHEDULE_ENABLED` | `false` | Scheduler integration |

## Testing destructive runs

In `APP_ENV=testing`, `--force` requires a marked sandbox outside the repository (`.retention-test-sandbox`). Forced retention/cleanup against `base_path()` or `storage_path()` is rejected before any file scan or deletion. See `docs/cleanup/CLEAN_3A_RETENTION_TEST_ISOLATION_REPORT.md`.

## Commands

```bash
# Default dry-run across all categories
php artisan project:retention

# Category-specific dry-run
php artisan project:retention --category=logs --dry-run

# Destructive run (requires PROJECT_RETENTION_ENABLED=true)
php artisan project:retention --force --category=validation-temp

# Override age threshold (days) for applicable categories
php artisan project:retention --older-than=14 --dry-run

# JSON report (no secret contents)
php artisan project:retention --report=json --category=cache
```

`project:cleanup` remains for immediate runtime sweeps. `project:retention` adds **age-based**, **category-aware**, and **protected-artifact** rules.

## Marking evidence protected

Place either file in the directory to protect:

- `.protected` — simple marker (empty file is fine)
- `evidence-manifest.json` — structured manifest (also protects tree)

Final FedEx submission ZIPs matching `fedex-validation-final-*.zip` are protected by default without a marker.

## Scheduler

Disabled by default. When `PROJECT_RETENTION_SCHEDULE_ENABLED=true`:

- Registers one scheduled event per configured category
- Uses `--dry-run` unless `PROJECT_RETENTION_SCHEDULE_FORCE=true`
- Applies `withoutOverlapping()` mutex
- Does not require Redis (file cache mutex)

Configure categories via comma-separated `PROJECT_RETENTION_SCHEDULE_CATEGORIES`.

## Inspecting reports

`php artisan project:hygiene-report` includes a retention preview (eligible totals).

`project:retention` lists each entry with status:

- `eligible` — would be deleted with `--force`
- `protected` — never deleted
- `skipped_active` — current log file
- `skipped_recent` — within retention window or latest-N source archive
- `skipped_unsafe` — outside allowlist

## Failure handling

- Continues after individual file failures
- Returns non-zero exit code if any deletion fails
- Does not partially strip protected directories (skips whole directory if any protected child)
- Handles missing files between scan/delete (TOCTOU)
- Does not print log or evidence file contents

## Recovery

Retention is **not** a backup system. Canonical evidence and merchant uploads must be backed up separately. Dry-run first. For accidental deletion, restore from Git (tracked files), backup, or re-export validation packages.

## Related docs

- `docs/cleanup/CLEAN_3_RUNTIME_STORAGE_RETENTION_REPORT.md`
- `docs/cleanup/PROJECT_CLEANUP_MASTER_PLAN.md`
- `docs/cleanup/SOURCE_ARCHIVE_GUIDE.md`
