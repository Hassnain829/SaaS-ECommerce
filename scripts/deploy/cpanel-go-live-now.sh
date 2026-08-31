#!/usr/bin/env bash
#
# One-shot go-live for cPanel Terminal.
# Usage:
#   cd ~/public_html/ecom.resolutedigitalspk.com
#   bash scripts/deploy/cpanel-go-live-now.sh
#
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${CPANEL_PHP_BIN:-}"

if [[ -z "${PHP_BIN}" ]]; then
  for candidate in /usr/local/bin/ea-php83 /usr/local/bin/ea-php82 php; do
    if command -v "${candidate}" >/dev/null 2>&1; then
      PHP_BIN="${candidate}"
      break
    fi
  done
fi

echo "==> App: $(pwd)"
echo "==> PHP: $("${PHP_BIN}" -v | head -n 1)"

if [[ -d .git ]]; then
  git fetch origin main
  git reset --hard origin/main
fi

cp -f scripts/deploy/cpanel-docroot-index.php index.php
cp -f scripts/deploy/cpanel-docroot.htaccess .htaccess

if [[ ! -f composer.phar ]]; then
  curl -sS https://getcomposer.org/download/latest-stable/composer.phar -o composer.phar
fi

# cPanel often disables proc_open. Composer post-scripts (package:discover) then fail.
# Install without scripts, then discover packages best-effort.
"${PHP_BIN}" composer.phar install \
  --no-dev \
  --no-interaction \
  --prefer-dist \
  --optimize-autoloader \
  --no-scripts

"${PHP_BIN}" artisan package:discover --ansi 2>/dev/null || \
  echo "WARN: package:discover skipped (proc_open disabled). Using existing bootstrap cache if present."

if [[ ! -f vendor/autoload.php ]]; then
  echo "ERROR: vendor/autoload.php missing after composer install." >&2
  exit 1
fi

if [[ ! -f public/build/manifest.json ]]; then
  echo "ERROR: public/build/manifest.json missing after git pull." >&2
  exit 1
fi

mkdir -p \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

ln -sfn ../storage/app/public public/storage
chmod -R ug+rwx storage bootstrap/cache

# Do NOT call artisan storage:link (exec/proc_open often disabled on cPanel).
"${PHP_BIN}" artisan migrate --force --no-interaction
"${PHP_BIN}" artisan config:clear
"${PHP_BIN}" artisan route:clear
"${PHP_BIN}" artisan view:clear
rm -f bootstrap/cache/*.php

# Avoid optimize/route:cache if Process/proc_open breaks console helpers.
"${PHP_BIN}" artisan config:cache || true

echo "==> Checking /up"
curl -sS -D - -o /tmp/laravel-up-body.txt \
  "http://127.0.0.1/up" \
  -H "Host: ecom.resolutedigitalspk.com" | head -n 20
echo "==> Body:"
head -c 2000 /tmp/laravel-up-body.txt || true
echo ""
echo "==> Fatal log (if any):"
tail -n 40 storage/logs/php-fatal.log 2>/dev/null || echo "(none)"
echo "==> Laravel log tail:"
tail -n 40 storage/logs/laravel.log 2>/dev/null || echo "(none)"
echo "==> Done"
