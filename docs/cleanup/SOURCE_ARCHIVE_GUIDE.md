# Source Archive Guide

## Goal

Create a **clean, export-safe ZIP** of the Laravel project without secrets, dependencies, runtime storage contents, or generated carrier validation artifacts.

## Requirements

- **Git is required.** Archives are created with `git archive HEAD` so `.gitattributes` `export-ignore` rules apply.
- If `.git` is missing, the command fails with an actionable error (no silent manual ZIP fallback).

## Recommended workflow

1. Commit or understand that `git archive HEAD` exports the **committed** tree (working-tree `.gitattributes` rules still apply locally).
2. Run a dry-run plan:

```bash
php artisan project:source-archive --dry-run
```

3. Optionally inspect the file list:

```bash
php artisan project:source-archive --list
```

4. Create the archive:

```bash
php artisan project:source-archive
```

Output filename pattern:

`E_COMMERCE_OFFICE-source-YYYYMMDD-HHMMSS.zip`

Default output directory:

`storage/app/source-archives/`

## What is excluded

- `.env`, `.env.local`, `.env.production`, and other **real** environment files
- `vendor/`
- `node_modules/` (root and `dev-test-storefront/`)
- runtime log/cache/session/view **contents**
- generated files under `storage/app/fedex-validation/` and `storage/app/usps-validation/`
- generated archives under `storage/app/source-archives/`
- local sqlite databases
- IDE/tooling folders marked `export-ignore` in `.gitattributes`

## What is included

Git-tracked source such as:

- `app/`, `config/`, `database/` (migrations/seeders)
- `resources/`, `routes/`, `tests/`, `docs/`
- **Environment templates:** `.env.example`, `.env.testing.example`, `dev-test-storefront/.env.example`
- **Laravel writable-directory placeholders:** tracked `.gitignore` files under `bootstrap/cache/`, `storage/logs/`, `storage/framework/*`, and `storage/app/`
- build manifests (`package.json`, `vite.config.js`, etc.)

### Why broad `.env.* export-ignore` was unsafe

A single `.env.* export-ignore` rule excluded required setup templates such as `.env.example`. CLEAN-1A replaced it with explicit exclusions for real secret-bearing env files only.

### Why placeholders must be preserved

Whole-directory `export-ignore` removed tracked `.gitignore` files that Laravel needs to recreate writable directories after deploy. CLEAN-1A excludes runtime **contents** (`storage/logs/*`) while restoring placeholders (`storage/logs/.gitignore`) via `-export-ignore`.

## Safety rules

- Never manually ZIP the whole folder from Explorer/Finder without exclusions.
- Never include `.env`, real API keys, or validation evidence in client deliveries.
- Use `php artisan project:hygiene-report` before shipping an archive to detect local leak paths.
- Automated tests open the real generated ZIP and verify required/forbidden entries.

## Related commands

```bash
php artisan project:hygiene-report
php artisan project:cleanup
php artisan project:cleanup --force --category=cache
```

See also: `docs/cleanup/PROJECT_CLEANUP_MASTER_PLAN.md`
