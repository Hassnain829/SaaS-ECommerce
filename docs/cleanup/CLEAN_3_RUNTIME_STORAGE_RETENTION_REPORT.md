# CLEAN-3 Runtime Storage Retention Report

Date: 2026-06-23  
Scope: **CLEAN-3 only** — storage lifecycle and maintenance; no business logic changes

## Summary

CLEAN-3 adds configurable, conservative runtime storage retention via `php artisan project:retention`, protection markers for canonical carrier evidence, optional scheduler integration (disabled by default), and extended hygiene reporting. **No FedEx, USPS, merchant, checkout, order, inventory, billing, admin, or validation behavior was changed.**

Model A / Integrator Provider remains the locked primary architecture.

## Before / after storage inventory

| Area | Before CLEAN-3 | After CLEAN-3 |
|------|----------------|---------------|
| Cache | Manual `project:cleanup --category=cache` only | Age-based cache retention category |
| Logs | All `.log` files targeted by cleanup | Active log preserved; age-based log retention |
| FedEx validation | Staging dirs only in cleanup | Temp vs protected classification; final ZIPs protected |
| Source archives | No retention policy | Latest-N + max-age under `storage/app/source-archives/` |
| PHPUnit cache | Included in cleanup cache | Dedicated test-artifacts category |
| Scheduler | None | Optional retention schedule (disabled by default) |
| Protection | Path substrings (`/labels/`, `/uploads/`) | Markers + manifest + final ZIP pattern + tracked files |

## Files created

| File | Purpose |
|------|---------|
| `config/project_retention.php` | Retention configuration |
| `app/Support/ProjectHygiene/ProjectStorageClassification.php` | Category catalog |
| `app/Support/ProjectHygiene/ProjectStorageProtection.php` | Protection markers and rules |
| `app/Support/ProjectHygiene/ProjectRetentionService.php` | Scan/prune engine |
| `app/Support/ProjectHygiene/ProjectRetentionReporter.php` | Retention reporting |
| `app/Console/Commands/ProjectRetentionCommand.php` | `project:retention` command |
| `app/Console/Scheduling/ProjectRetentionScheduleConfigurator.php` | Optional scheduler registration |
| `tests/Feature/ProjectRetentionCommandsTest.php` | Focused retention tests (isolated temp fixtures) |
| `docs/operations/RUNTIME_STORAGE_RETENTION.md` | Operations guide |

## Files modified

| File | Change |
|------|--------|
| `routes/console.php` | Register optional retention schedule |
| `app/Support/ProjectHygiene/ProjectHygieneReporter.php` | Retention preview section |
| `app/Console/Commands/ProjectHygieneReportCommand.php` | Display retention preview |
| `tests/Feature/ProjectHygieneCommandsTest.php` | Register `project:retention` |
| `.env.example` | Retention env placeholders |
| Documentation files listed in CLEAN-3 prompt | Phase status and references |

## Retention defaults

| Setting | Default |
|---------|---------|
| Enabled | `false` |
| Dry-run | `true` |
| Require force | `true` |
| Log days | 30 |
| Cache hours | 24 |
| Source archive count | 5 |
| Source archive days | 30 |
| Validation temp days | 14 |
| Test artifact hours | 24 |
| Session cleanup | `false` |
| Schedule | `false` |

## Scheduler state

- **Disabled by default** (`PROJECT_RETENTION_SCHEDULE_ENABLED=false`)
- When enabled: daily or weekly per config, `withoutOverlapping()`, dry-run unless `PROJECT_RETENTION_SCHEDULE_FORCE=true`

## Protection mechanism

- `.protected` marker file in a directory
- `evidence-manifest.json` in a directory
- Path substrings: `/labels/`, `/uploads/`, `/00_documents/`, `/printed_scans/`
- Filename pattern: `fedex-validation-final-*.zip`
- Git-tracked files and Laravel `.gitignore` placeholders

No `--include-protected` flag exists.

## Tests

See test run output in final verification section. Destructive tests use **isolated temporary project roots** under the system temp directory — not the real worktree.

## Known limitations

- File-session cleanup is opt-in and file-driver only
- Laravel logging channel is not changed (single vs daily); old rotated logs are pruned by age
- `project:retention --force` against the real worktree was **not** used during implementation verification
- Historical phase report docs may still reference pre-CLEAN-2 paths

## Acceptance

- **CLEAN-3 is complete only after CLEAN-3A passes** (see `docs/cleanup/CLEAN_3A_RETENTION_TEST_ISOLATION_REPORT.md`)

- Admin panel: unchanged
- Carrier API / OAuth / validation logic: unchanged
- Merchant UI / checkout / orders / inventory: unchanged
- Database schema and API events: unchanged
- CLEAN-4: not started
