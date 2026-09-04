# cPanel deployment and GitHub CI/CD

This guide covers taking the Laravel merchant portal live on cPanel with automated deploys from GitHub.

**Primary path:** GitHub Actions builds production assets and syncs to your server after CI passes (`.github/workflows/deploy-cpanel.yml`).

**Alternative path:** cPanel Git Version Control + `.cpanel.yml` (requires Composer, Node, and PHP CLI on the host).

---

## What the pipeline does

On every successful CI run on `main` or `master`, the deploy workflow:

1. Checks out the release commit
2. Runs `composer install --no-dev --optimize-autoloader`
3. Runs `npm ci` and `npm run build` (Vite → `public/build/`)
4. Rsyncs the application to your cPanel server (excluding dev/test paths and secrets)
5. Runs `scripts/deploy/cpanel-post-deploy.sh` over SSH:
   - `php artisan migrate --force`
   - `php artisan storage:link` (or manual symlink when `exec` is disabled)
   - `php artisan config:cache` (route/optimize caches avoided when `proc_open` is disabled)
   - `scripts/deploy/sync-wordpress-jiggy.sh` — refreshes `/jiggy` connector + brand mu-plugins **only if** that WordPress tree already exists

Manual deploy: GitHub → **Actions** → **Deploy to cPanel** → **Run workflow**.

---

## WordPress demo under `/jiggy` (manager storefront)

The live manager demo WordPress site is a **subfolder** on the same cPanel docroot as the Laravel portal — not a separate domain and not part of the full app rsync.

| Surface | URL |
|---------|-----|
| Laravel portal | `https://ecom.resolutedigitalspk.com` |
| WordPress demo | `https://ecom.resolutedigitalspk.com/jiggy/` |

Server path (this host): `/home/resolutedigita2/public_html/ecom.resolutedigitalspk.com/jiggy`

### What stays out of every git push

Full WordPress (core, uploads, Elementor data, DB) is **not** deployed with Laravel. `scripts/deploy/rsync-exclude.txt` excludes `dev-test-wordpress/`. That is intentional for a large local WP tree.

### What the pipeline syncs every deploy

1. GitHub Actions **stages** `dev-test-wordpress/wp-content/plugins/eco-portal-connector/` into `deploy/wordpress-jiggy/eco-portal-connector/` before rsync (that staged tree is gitignored).
2. Brand helpers ship from the repo: `deploy/wordpress-jiggy/mu-plugins/` (`eco-portal-only.php`, `jiggy-eco-brand.css`). See `deploy/wordpress-jiggy/README.md`.
3. After Laravel post-deploy, `sync-wordpress-jiggy.sh`:
   - If `$CPANEL_DEPLOY_PATH/jiggy/wp-content/plugins` is missing → **no-op** (safe before the one-time WP install).
   - If present → rsync connector → `jiggy/wp-content/plugins/eco-portal-connector/` and brand files → `jiggy/wp-content/mu-plugins/`.

cPanel Git / `cpanel-go-live-now.sh` runs the same sync. If the staged pack is absent, the script falls back to `dev-test-wordpress/wp-content/plugins/eco-portal-connector/` when that path exists on the server.

Laravel docroot `.htaccess` (`scripts/deploy/cpanel-docroot.htaccess`) already skips existing directories/files before front-controller routing, so `/jiggy` is not swallowed once the folder exists.

### One-time install + Eco Portal connection

Step-by-step DB, upload, URL replace, permalinks, Elementor CSS, and connection key wiring:

→ **`docs/operations/JIGGY_CPANEL_DEMO_CHECKLIST.md`**

---

## Server requirements

| Requirement | Notes |
|-------------|--------|
| PHP | **8.2+** (8.3 recommended; match CI) |
| PHP extensions | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `xmlwriter`, `zip` |
| Database | **MySQL/MariaDB** for production (not SQLite) |
| Web server | Apache with `mod_rewrite` (default on cPanel) |
| SSH | Enabled for deploy user |
| Document root | Must point to Laravel **`public/`** directory |

Enable PHP extensions in cPanel → **Select PHP Version** → **Extensions**.

Find your PHP CLI binary (often `/usr/local/bin/ea-php83`) under **MultiPHP Manager** or `which php` in SSH.

---

## Recommended directory layout

Do **not** put the full Laravel app inside `public_html` if you can avoid it.

```
/home/USERNAME/
├── laravel-app/          ← CPANEL_DEPLOY_PATH (app root; not web-accessible)
│   ├── app/
│   ├── public/           ← document root should target THIS folder
│   ├── storage/
│   └── ...
└── public_html/          ← optional symlink or cPanel subdomain docroot → ../laravel-app/public
```

In cPanel → **Domains** → your domain → set **Document Root** to `/home/USERNAME/laravel-app/public`.

---

## One-time server setup

### 1. Create the app directory

```bash
mkdir -p ~/laravel-app
chmod 755 ~/laravel-app
```

### 2. Create production `.env`

Create `~/laravel-app/.env` on the server (never commit this file). Start from `.env.example`:

```bash
cd ~/laravel-app
cp .env.example .env
```

Minimum production changes:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_cpanel_db_name
DB_USERNAME=your_cpanel_db_user
DB_PASSWORD=your_db_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@your-domain.com
```

Generate the app key once:

```bash
/usr/local/bin/ea-php83 artisan key:generate
```

Configure Stripe, FedEx, mail, and other integrations per `.env.example` comments. Follow `SECURITY_ROTATION_REQUIRED.md` for secrets handling.

### 3. Database

Create a MySQL database and user in cPanel → **MySQL Databases**, then run migrations on first deploy (the pipeline runs `migrate --force` automatically).

### 4. Writable permissions

```bash
cd ~/laravel-app
chmod -R ug+rwx storage bootstrap/cache
```

Your cPanel user must own these directories.

### 5. SSH key for GitHub Actions

On your **local machine**:

```bash
ssh-keygen -t ed25519 -C "github-deploy-saas-ecommerce" -f ./cpanel_deploy_key -N ""
```

Add the **public** key (`cpanel_deploy_key.pub`) in cPanel → **SSH Access** → **Manage SSH Keys** → **Import** → **Authorize**.

Keep the **private** key for GitHub secrets (next section).

---

## GitHub repository secrets

Repository → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**:

| Secret | Example | Purpose |
|--------|---------|---------|
| `CPANEL_SSH_HOST` | `your-domain.com` | SSH hostname |
| `CPANEL_SSH_PORT` | `22` | SSH port |
| `CPANEL_SSH_USER` | `cpanel_username` | cPanel account username |
| `CPANEL_SSH_KEY` | contents of private key | Deploy authentication |
| `CPANEL_DEPLOY_PATH` | `/home/cpanel_username/laravel-app` | Absolute Laravel root on server |
| `CPANEL_PHP_BIN` | `/usr/local/bin/ea-php83` | PHP CLI for artisan (optional) |

### GitHub environment (recommended)

Create a **production** environment under **Settings** → **Environments** → **production**:

- Required reviewers (optional)
- Environment secrets (same keys as above) for stricter access control

The deploy workflow uses `environment: production`.

---

## Cron jobs (required)

Laravel needs a scheduler and queue worker in production.

cPanel → **Cron Jobs**:

**Scheduler** (every minute):

```cron
* * * * * /usr/local/bin/ea-php83 /home/USERNAME/laravel-app/artisan schedule:run >> /dev/null 2>&1
```

**Queue worker** (every minute; processes jobs then exits — suitable for shared hosting):

```cron
* * * * * /usr/local/bin/ea-php83 /home/USERNAME/laravel-app/artisan queue:work database --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

Replace paths with your `CPANEL_PHP_BIN` and `CPANEL_DEPLOY_PATH`.

Scheduled tasks in this app include catalog event delivery, checkout expiry, and optional FedEx tracking refresh (`routes/console.php`).

---

## First deploy checklist

- [ ] MySQL database created and credentials in server `.env`
- [ ] `APP_KEY` generated on server
- [ ] Document root → `public/`
- [ ] GitHub secrets configured
- [ ] SSH key authorized on cPanel
- [ ] Cron jobs added
- [ ] CI green on `main` / `master`
- [ ] Deploy workflow completes (Actions → Deploy to cPanel)
- [ ] Hit `https://your-domain.com/up` (Laravel health route)
- [ ] Sign in and smoke-test merchant flows

---

## What is excluded from deploy

See `scripts/deploy/rsync-exclude.txt`. Notable exclusions:

- `.env` (server keeps its own)
- `node_modules/`, `tests/`, `dev-test-storefront/`, `dev-test-wordpress/` (full local WP tree)
- Local SQLite databases and log/cache files

The Eco Portal Connector still reaches production for `/jiggy` via the **staged** copy under `deploy/wordpress-jiggy/eco-portal-connector/` (Actions only) plus post-deploy sync — not via deploying the whole `dev-test-wordpress/` tree.

Production ships with **pre-built** `vendor/` and `public/build/` from GitHub Actions — you do not need Node or Composer on the server for the primary pipeline.

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| 500 after deploy | Check `storage/logs/laravel.log`; verify `storage/` and `bootstrap/cache/` permissions |
| Vite assets 404 | Confirm `public/build/` exists on server; re-run deploy workflow |
| `php` not found in SSH action | Set `CPANEL_PHP_BIN` secret to full path (`ea-php83`) |
| Migration errors | Backup DB; run `php artisan migrate:status` on server |
| Mixed content / wrong URLs | Set `APP_URL` to exact HTTPS origin |
| Queue jobs stuck | Confirm cron queue worker; `QUEUE_CONNECTION=database` and `jobs` table migrated |

---

## Related docs

- `docs/operations/RELEASE_CHECKLIST.md` — pre-release verification
- `docs/operations/JIGGY_CPANEL_DEMO_CHECKLIST.md` — one-time `/jiggy` WordPress + Eco Portal connection
- `deploy/wordpress-jiggy/README.md` — brand pack + pipeline sync overview
- `docs/LOCAL_SETUP.md` — local PHP/Node extension parity
- `.github/workflows/ci.yml` — test gate before deploy
- `SECURITY_ROTATION_REQUIRED.md` — secret rotation if leaked
