# SaaS E-Commerce (Laravel merchant platform)

Multi-store merchant dashboard and APIs for catalog, imports, inventory, orders, and developer-channel checkout. A separate `dev-test-storefront` Vite + React app exercises public catalog and order APIs locally.

## Tech stack

- **Backend:** PHP 8.2+, Laravel 12  
- **Merchant UI:** Blade, Tailwind CSS, Vite 7  
- **Developer simulator:** React 19 + Vite (`dev-test-storefront`)  
- **Payments:** Stripe (PHP SDK + webhooks)

## PHP extensions

Required for day-to-day development and CI:

- `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `xmlwriter`  
- SQLite (`pdo_sqlite`) for the default test suite, **or** MySQL client extensions if you use MySQL locally.

## Node.js

Use **Node 20+** locally; CI uses **Node 22**. Match CI when debugging asset or storefront build issues.

## Quick start

See **[docs/LOCAL_SETUP.md](docs/LOCAL_SETUP.md)** for step-by-step instructions.

Minimal path:

```bash
composer install
copy .env.example .env   # or cp on Unix
php artisan key:generate
php artisan migrate
npm ci && npm run build
php artisan serve
```

## Environment variables

Copy **`.env.example`** to `.env` and fill values. Never commit real secrets. Payment and webhook variables are documented inline in `.env.example`.

Developer storefront client env: **`dev-test-storefront/.env.example`**.

## Running tests

```bash
php artisan config:clear
php artisan route:clear
php artisan test
```

`phpunit.xml` pins testing env (`APP_KEY`, in-memory SQLite, sync queues). See `docs/LOCAL_SETUP.md` if you use `php artisan migrate:fresh --env=testing` with a file-based DB.

## Build commands

| Location | Command |
|----------|---------|
| Laravel root | `npm ci` / `npm run build` |
| `dev-test-storefront/` | `npm ci` / `npm run build` |

## Security & release hygiene

- **`SECURITY_ROTATION_REQUIRED.md`** — what to rotate after a leak.  
- **`docs/SECURITY_HARDENING.md`** — image download SSRF controls and API throttle / webhook notes.  
- **`docs/RELEASE_CHECKLIST.md`** — pre-release checks (no `vendor/`, no `.env`, CI green).

Do not commit: `.env`, `vendor/`, `node_modules/`, `database/*.sqlite`, `storage/logs/*.log`, `bootstrap/cache/*.php`, `.phpunit.cache/`, or generated carrier validation trees under `storage/app/fedex-validation/` and `storage/app/usps-validation/`.

## Project hygiene commands (CLEAN-1 / CLEAN-1A)

Non-destructive repository maintenance — does not change merchant or carrier business logic:

```bash
php artisan project:hygiene-report
php artisan project:cleanup                  # dry-run (default); never deletes Git-tracked files
php artisan project:cleanup --force          # delete approved runtime targets only
php artisan project:cleanup --category=cache
php artisan project:source-archive --dry-run # Git required
php artisan project:source-archive           # creates real ZIP via git archive
```

See `docs/cleanup/SOURCE_ARCHIVE_GUIDE.md` and `docs/cleanup/PROJECT_CLEANUP_MASTER_PLAN.md`.

## Troubleshooting

| Symptom | Likely cause |
|---------|----------------|
| `php artisan test` fails loading XML | Install `dom`, `xml`, `xmlwriter`. |
| Import jobs stuck “queued” in tests | Delete `bootstrap/cache/config.php` if present; tests remove common cache files in `Tests\TestCase`. |
| Vite / API 419 or session issues | Session driver and `APP_URL` must match how you open the app (host + port). |
| Developer storefront 401 | `VITE_STOREFRONT_TOKEN` must match the token issued in the merchant dashboard; restart Vite after changing `.env`. |

## Further reading

- **[docs/cleanup/PROJECT_CLEANUP_MASTER_PLAN.md](docs/cleanup/PROJECT_CLEANUP_MASTER_PLAN.md)** — CLEAN-1 hygiene and future cleanup phases.
- **[docs/REFACTORING_ROADMAP.md](docs/REFACTORING_ROADMAP.md)** — large-file refactors deferred intentionally.  
- **`ENTERPRISE_PROJECT_CONTEXT.md`** / **`ENTERPRISE_ROADMAP_2026.md`** — product scope and build order.
