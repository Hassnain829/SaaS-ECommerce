#!/usr/bin/env bash
#
# Run ON THE CPANEL SERVER inside the Laravel project root.
# Use when GitHub Actions rsync is unavailable or for first-time go-live.
#
set -euo pipefail

APP_DIR="${CPANEL_DEPLOY_PATH:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
PHP_BIN="${CPANEL_PHP_BIN:-php}"

if ! command -v "${PHP_BIN}" >/dev/null 2>&1 && [[ -x /usr/local/bin/ea-php83 ]]; then
  PHP_BIN="/usr/local/bin/ea-php83"
fi

cd "${APP_DIR}"

if [[ ! -f artisan ]]; then
  echo "ERROR: Run this from the Laravel root (artisan not found)." >&2
  exit 1
fi

echo "==> Live setup in ${APP_DIR}"

if [[ -d .git ]]; then
  git pull origin main || git pull origin master
fi

if command -v composer >/dev/null 2>&1; then
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
elif [[ -x /usr/local/bin/composer ]]; then
  "${PHP_BIN}" /usr/local/bin/composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
else
  echo "ERROR: composer not found on server." >&2
  exit 1
fi

if command -v npm >/dev/null 2>&1; then
  npm ci
  npm run build
else
  echo "WARN: npm not found — public/build must come from CI or another build step." >&2
fi

mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

bash scripts/deploy/cpanel-post-deploy.sh

echo "==> Done. Check https://your-domain/up"
