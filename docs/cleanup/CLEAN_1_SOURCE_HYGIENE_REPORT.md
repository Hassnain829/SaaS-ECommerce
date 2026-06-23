# CLEAN-1 Source Hygiene Report

Checkpoint: `03e49fd` + CLEAN-1A corrections  
Scope: **CLEAN-1 / CLEAN-1A** — repository hygiene only; no business logic changes

## Summary

CLEAN-1 adds repository safety rules, export-safe archive workflow, non-destructive cleanup commands, hygiene reporting, and documentation updates. **CLEAN-1A** corrects safety defects found during review so archives and cleanup are actually safe.

Admin panel, merchant flows, and carrier business logic were not modified.

## CLEAN-1A corrections (2026-06-23)

| Issue | Fix |
|-------|-----|
| Broad `.env.* export-ignore` removed `.env.example` from archives | Replaced with explicit real-env exclusions; templates remain included |
| Whole-directory `export-ignore` removed Laravel `.gitignore` placeholders | Runtime `/*` and `/**` patterns with `-export-ignore` on each placeholder |
| Cleanup could target tracked `.gitignore` dotfiles | Tracked-file protection via `git ls-files`; explicit placeholder allowlist |
| Path guard accepted non-existing traversal paths | Lexical `..` rejection, canonical parent resolution, symlink escape checks |
| Archive test only checked plan, not real ZIP | Integration test opens `git archive` ZIP with `ZipArchive` |
| Dead non-Git ZIP fallback was unreachable | Removed; Git is required with clear error message |
| Model A roadmap filename said “deferred” | Renamed to `docs/FEDEX_MODEL_A_INTEGRATOR_PROVIDER_ROADMAP.md` |

**CLEAN-1 is complete only after CLEAN-1A passes.**

## Repository safety (`.gitignore`)

Hardened ignores for:

- `.env` variants and backups
- `vendor/`, `node_modules/`, `dev-test-storefront/node_modules/`
- runtime `storage/logs`, framework cache/sessions/views
- generated carrier validation trees (`storage/app/fedex-validation/`, `storage/app/usps-validation/`)
- source archive outputs (`storage/app/source-archives/`)
- `bootstrap/cache/*.php`, PHPUnit caches
- local sqlite files, temp/backups, root ZIP outputs

## Export safety (`.gitattributes`)

`git archive` excludes dependencies, runtime storage **contents**, generated validation evidence, and real environment files.

**Included in archives:** `.env.example`, `.env.testing.example`, `dev-test-storefront/.env.example`, and tracked Laravel writable-directory `.gitignore` placeholders.

## New Artisan commands

| Command | Purpose |
|---------|---------|
| `php artisan project:hygiene-report` | Read-only size/leak report (no secret values) |
| `php artisan project:cleanup` | Dry-run cleanup of runtime artifacts |
| `php artisan project:cleanup --force` | Delete approved runtime targets only (never tracked files) |
| `php artisan project:cleanup --category=cache` | Limit to cache category |
| `php artisan project:cleanup --category=logs` | Limit to logs |
| `php artisan project:cleanup --category=carrier-validation` | Remove staging bundle dirs only |
| `php artisan project:source-archive --dry-run` | Plan export-safe archive |
| `php artisan project:source-archive --list` | List included git-tracked files |
| `php artisan project:source-archive` | Create `E_COMMERCE_OFFICE-source-YYYYMMDD-HHMMSS.zip` via **Git required** |

Archive output: `storage/app/source-archives/`

## Cleanup safety

- Dry-run is default; `--force` required for deletion
- **No Git-tracked file may ever be deleted**
- All `.gitignore` placeholders are protected
- Path traversal, symlink escape, and project-root deletion are rejected
- Carrier labels/uploads and canonical evidence are never targets

## Carrier evidence protection

Cleanup category `carrier-validation` removes **only** temporary staging directories. It does **not** delete labels, uploads, final validation ZIP exports, or database API events.

## Documentation updates

- Model A documented as primary integrator strategy
- Model B documented as developer fallback only
- FedEx roadmap renamed (no “deferred” in filename)

## Deferred

- CLEAN-2 through CLEAN-4 not started
- Admin panel migration not started

## Verification

See final CLEAN-1A agent report for exact command output, ZIP inspection, and test results.
