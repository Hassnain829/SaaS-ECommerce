# CLEAN-3A Retention Test Isolation Report

Date: 2026-06-23  
Scope: **CLEAN-3A only** — destructive test isolation and real-worktree protection

## Why CLEAN-3A was required

During CLEAN-3 development, an early destructive test invoked forced retention against `base_path()` and deleted local FedEx diagnostic ZIPs under `storage/app/fedex-validation/1/`. CLEAN-3 infrastructure was functionally complete but not safe to accept until destructive tests and `--force` runs could never target the real worktree in the testing environment.

## Incident summary

| Item | Detail |
|------|--------|
| Affected paths | Local generated `fedex-validation-diagnostic-*.zip` files under `storage/app/fedex-validation/1/` |
| Nature | Local diagnostic exports only — not canonical final submission packages |
| Git-tracked files | **Not deleted** (verified via `git ls-files` integrity) |
| Canonical final ZIPs | **Not targeted** by the mistaken test pattern |
| Production labels / merchant uploads | **Not targeted** |
| Database `carrier_api_events` | **Unaffected** (no DB mutation in retention commands) |
| Recovery | Re-export diagnostics from the FedEx validation workspace if still needed; no automatic reconstruction performed |

## Root cause

Destructive tests called `ProjectRetentionService::run(force: true)` and `project:retention --force` with `ProjectPathGuard::forProject(base_path())`, writing fixtures into real `storage/logs` and `storage/app/fedex-validation`. No guard prevented forced deletion against the application worktree when `APP_ENV=testing`.

## Solution

### 1. Marked sandbox (`RetentionTestSandbox`)

- Root: `<system-temp>/ecommerce-office-retention-tests/<unique-id>/`
- Required marker: `.retention-test-sandbox` (JSON with `uuid`, `created_at`, `environment=testing`)
- Standard layout mirrors project storage directories needed for retention tests
- **Outside** repository root and real `storage_path()`

### 2. Real-worktree guard (`DestructiveHygieneRootGuard`)

When `APP_ENV=testing` and `--force` (non-dry-run):

1. Rejects roots matching `base_path()`, `storage_path()`, or subdirectories thereof
2. Requires valid `.retention-test-sandbox` marker
3. Throws `UnsafeRetentionTestRootException` **before** scanning or deleting
4. Commands return exit code `1` with message confirming **no files were changed**

Production behavior unchanged: guard only applies in testing.

### 3. Test helper (`CreatesRetentionTestSandbox` trait)

Centralizes sandbox creation, validation, and teardown for all destructive retention/cleanup tests.

## Files created

| File | Purpose |
|------|---------|
| `app/Support/ProjectHygiene/UnsafeRetentionTestRootException.php` | Dedicated safety exception |
| `app/Support/ProjectHygiene/RetentionTestSandbox.php` | Sandbox marker and layout |
| `app/Support/ProjectHygiene/DestructiveHygieneRootGuard.php` | Testing destructive root guard |
| `tests/Support/CreatesRetentionTestSandbox.php` | Reusable test trait |
| `tests/Feature/RetentionTestIsolationTest.php` | Regression suite |

## Files modified

| File | Change |
|------|--------|
| `ProjectRetentionService.php` | Guard before destructive run |
| `ProjectCleanupService.php` | Guard before destructive cleanup |
| `ProjectRetentionCommand.php` | Catch unsafe root exception |
| `ProjectCleanupCommand.php` | Catch unsafe root exception |
| `config/project_retention.php` | `test_sandbox_required` key |
| `ProjectRetentionCommandsTest.php` | Uses sandbox trait |
| `ProjectHygieneCommandsTest.php` | Destructive tests moved to sandbox |
| Documentation files | CLEAN-3A status and ops notes |

## Regression tests

`RetentionTestIsolationTest` covers:

- Artisan `--force` blocked against real `base_path()` and `storage_path()`
- Unmarked temp directories rejected
- Marked sandbox accepts force and deletes only sandbox files
- Guard runs before scan/delete (probe file survives)
- Cleanup command parity
- Misconfiguration rejection
- Dry-run on real worktree still allowed
- Architectural checks for sandbox trait usage

## Acceptance

- **CLEAN-3 is complete only after CLEAN-3A passes**
- **CLEAN-4 not started**
- **Model A remains primary**
- **No admin or carrier business behavior changes**
- **No real forced retention run during verification**

## Known limitations

- Symlink escape tests may skip on Windows when symlink creation is unavailable
- Dry-run against the real worktree remains permitted (non-destructive)
