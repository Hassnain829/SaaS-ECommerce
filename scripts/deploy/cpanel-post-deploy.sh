#!/usr/bin/env bash
#
# Post-deploy hook for cPanel / shared hosting.
# Run from the Laravel application root after code is synced.
#
# Optional environment overrides (set in cPanel cron or SSH session):
#   CPANEL_PHP_BIN   — PHP binary (e.g. /usr/local/bin/ea-php83)
#   CPANEL_DEPLOY_PATH — app root if not inferred from script location
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${CPANEL_DEPLOY_PATH:-$(cd "${SCRIPT_DIR}/../.." && pwd)}"
PHP_BIN="${CPANEL_PHP_BIN:-php}"

if ! command -v "${PHP_BIN}" >/dev/null 2>&1 && [[ -x /usr/local/bin/ea-php83 ]]; then
  PHP_BIN="/usr/local/bin/ea-php83"
fi

cd "${APP_DIR}"

if [[ ! -f artisan ]]; then
  echo "ERROR: artisan not found in ${APP_DIR}. Set CPANEL_DEPLOY_PATH to your Laravel root." >&2
  exit 1
fi

echo "==> Post-deploy: ${APP_DIR}"
echo "==> PHP: $($PHP_BIN -v | head -n 1)"

# Writable Laravel directories (tracked .gitignore placeholders may exist; ensure dirs are present)
mkdir -p \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

# Production dependencies should already be present from CI (vendor/ + public/build/).
# If you deploy via cPanel Git pull instead of GitHub Actions, uncomment:
# composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
# npm ci && npm run build

$PHP_BIN artisan migrate --force --no-interaction

link_public_storage() {
  local target="${APP_DIR}/storage/app/public"
  local link_path="${APP_DIR}/public/storage"

  mkdir -p "${target}"

  if [[ -L "${link_path}" ]]; then
    return 0
  fi

  if [[ -e "${link_path}" && ! -L "${link_path}" ]]; then
    rm -rf "${link_path}"
  fi

  ln -sfn "${target}" "${link_path}"
  echo "==> Linked public/storage -> storage/app/public"
}

if [[ ! -L public/storage ]]; then
  if $PHP_BIN artisan storage:link --force 2>/dev/null; then
    echo "==> storage:link via artisan"
  else
    echo "==> artisan storage:link unavailable (exec disabled?) — linking manually"
    link_public_storage
  fi
fi

$PHP_BIN artisan config:clear || true
$PHP_BIN artisan route:clear || true
$PHP_BIN artisan view:clear || true
rm -f bootstrap/cache/*.php

# Avoid route:cache / optimize / event:cache on hosts where proc_open is disabled.
$PHP_BIN artisan config:cache || true

echo "==> Post-deploy finished at $(date -u +%Y-%m-%dT%H:%M:%SZ)"
