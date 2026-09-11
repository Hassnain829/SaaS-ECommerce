# Local setup

## Requirements

- **PHP** 8.2+ with extensions: `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `pdo_sqlite` (for tests) or `pdo_mysql`, `tokenizer`, `xml`, `xmlwriter`.
- **Composer** 2.x  
- **Node.js** 20+ (CI uses 22; match for fewer surprises) and npm.

## 1. Clone and install PHP dependencies

```bash
composer install
```

## 2. Environment file

```bash
copy .env.example .env   # Windows
# cp .env.example .env   # macOS / Linux
php artisan key:generate
```

Edit `.env` for your database (SQLite file or MySQL credentials).

## 3. Database

SQLite (default in `.env.example`):

```bash
type nul > database\database.sqlite   # Windows: create empty file if missing
php artisan migrate
```

## 4. Frontend (merchant UI / Vite)

```bash
npm ci
npm run dev          # development
# npm run build      # production asset build
```

## 5. Developer test storefront (optional)

This is a **local simulator** for catalog + checkout API calls, not the production storefront product.

```bash
cd dev-test-storefront
copy .env.example .env   # set VITE_STOREFRONT_TOKEN from the merchant dashboard
npm ci
npm run dev
```

See root `README.md` for how the Vite proxy targets Laravel.

## 6. Tests

From the Laravel root:

```bash
php artisan config:clear
php artisan route:clear
php artisan test
```

PHPUnit reads `phpunit.xml` (SQLite in-memory, `APP_KEY`, sync queues). You do **not** need a committed `.env.testing` file; use `.env.testing.example` only if you run Artisan with `--env=testing` against a file-based SQLite DB.

`phpunit.xml` blanks `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` so local OAuth keys cannot leak into tests that expect the Google button to stay hidden.

## 7. Google Sign-In (optional)

This is **not** Gmail SMTP (`MAIL_*`). Create a Google Cloud **Web application** OAuth client, then set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env`.

Leave `GOOGLE_REDIRECT_URI` **empty**. The app uses `{APP_URL}/auth/google/callback`. For `php artisan serve`, set `APP_URL=http://127.0.0.1:8000` so local callback is `http://127.0.0.1:8000/auth/google/callback`.

Do not put the local callback URL into a production `.env`. Live uses `APP_URL=https://ecom.resolutedigitalspk.com` with the same empty redirect var.

Google Cloud Console must list **both** origins and **both** redirect URIs on that one client (no trailing slash on origins; no `//auth` on redirects):

- Origins: `http://127.0.0.1:8000`, `https://ecom.resolutedigitalspk.com`
- Redirects: `http://127.0.0.1:8000/auth/google/callback`, `https://ecom.resolutedigitalspk.com/auth/google/callback`

The **Continue with Google** button stays hidden until both client id and secret are filled. After changing Google env vars, run `php artisan config:clear` (and `config:cache` on production). Never commit `.env`. If the client secret was shared, rotate it in Google Cloud.

Details: `docs/current/PROJECT_STATE.md` (Google Sign-In) and `docs/operations/CPANEL_DEPLOYMENT.md`.

## Validation checklist (full)

```bash
composer validate
composer install
npm ci
npm run build
cd dev-test-storefront && npm ci && npm run build && cd ..
php artisan config:clear
php artisan route:clear
php artisan test
```

If `php artisan test` fails with missing extensions, install the PHP extensions listed at the top of this document (Ubuntu packages often named `php-xml`, `php-mbstring`, etc.).
