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

# When the subdomain document root is the Laravel project root (common on cPanel),
# install front-controller files that are not part of the normal public/ tree.
# Without these, rsync --delete leaves the site at 403 and /build assets 404.
if [[ -f scripts/deploy/cpanel-docroot-index.php && -f scripts/deploy/cpanel-docroot.htaccess ]]; then
  cp -f scripts/deploy/cpanel-docroot-index.php "${APP_DIR}/index.php"
  cp -f scripts/deploy/cpanel-docroot.htaccess "${APP_DIR}/.htaccess"
  echo "==> Installed cPanel docroot index.php and .htaccess"
fi

# Writable Laravel directories (tracked .gitignore placeholders may exist; ensure dirs are present)
mkdir -p \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

# Clear stale package/config caches BEFORE any artisan call. CI rsync excludes
# bootstrap/cache/*.php, so an old packages.php can still reference --dev providers
# (e.g. Laravel\Pail) that are absent from production vendor/.
rm -f bootstrap/cache/*.php
echo "==> Cleared bootstrap/cache/*.php"

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
    local current
    current="$(readlink "${link_path}" || true)"
    # Repair broken/local-machine symlinks left by bad deploys.
    if [[ -n "${current}" && -d "${current}" ]]; then
      return 0
    fi
    rm -f "${link_path}"
  fi

  if [[ -e "${link_path}" && ! -L "${link_path}" ]]; then
    rm -rf "${link_path}"
  fi

  ln -sfn "${target}" "${link_path}"
  echo "==> Linked public/storage -> storage/app/public"
}

if [[ ! -L public/storage ]] || [[ ! -d "$(readlink public/storage 2>/dev/null || true)" ]]; then
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

# Optional: GitHub Actions can pass OAuth/API secrets so merchant Google and
# live FedEx stay enabled without copying .env from the repo.
if [[ -f scripts/deploy/upsert-env.php ]]; then
  $PHP_BIN scripts/deploy/upsert-env.php GOOGLE_CLIENT_ID || true
  $PHP_BIN scripts/deploy/upsert-env.php GOOGLE_CLIENT_SECRET || true
  $PHP_BIN scripts/deploy/upsert-env.php FEDEX_LIVE_CLIENT_ID || true
  $PHP_BIN scripts/deploy/upsert-env.php FEDEX_LIVE_CLIENT_SECRET || true

  if [[ -n "${FEDEX_LIVE_CLIENT_ID:-}" && -n "${FEDEX_LIVE_CLIENT_SECRET:-}" ]]; then
    export APP_NAME="${APP_NAME:-Retailo}"
    export FEDEX_ENABLED=true
    export FEDEX_ENVIRONMENT=live
    export FEDEX_DEFAULT_CONNECTION_MODEL=integrator_provider
    export FEDEX_INTEGRATOR_MODEL_A_ENABLED=true
    export FEDEX_INTEGRATOR_PRODUCTION_ENABLED=true
    export FEDEX_DEVELOPER_MODE_ENABLED=false
    export FEDEX_MODEL_B_DEVELOPER_FALLBACK_ENABLED=false
    export FEDEX_SANDBOX_ALLOW_PLATFORM_FALLBACK=false
    export FEDEX_LIVE_ALLOWED_COUNTRIES=US,CA
    export FEDEX_LIVE_BASE_URL=https://apis.fedex.com
    export FEDEX_LIVE_ACCOUNT_REGISTRATION_PATH=/registration/v2/address/keysgeneration
    export FEDEX_OPS_ADDRESS_VALIDATION_ENABLED=true
    export FEDEX_OPS_SERVICE_AVAILABILITY_ENABLED=true
    export FEDEX_OPS_NEGOTIATED_RATES_ENABLED=true
    export FEDEX_OPS_SHIP_LABELS_ENABLED=true
    export FEDEX_OPS_TRACKING_ENABLED=true
    export FEDEX_CHECKOUT_RATES_ENABLED=true
    export FEDEX_MFA_PIN_GENERATION_PATH=/registration/v2/customerkeys/pingeneration
    export FEDEX_MFA_PIN_VALIDATION_PATH=/registration/v2/pin/keysgeneration
    export FEDEX_MFA_INVOICE_VALIDATION_PATH=/registration/v2/invoice/keysgeneration
    export FEDEX_ADDRESS_VALIDATION_PATH=/address/v1/addresses/resolve
    export FEDEX_SERVICE_AVAILABILITY_PATH=/availability/v1/packageandserviceoptions
    export FEDEX_RATE_QUOTE_PATH=/rate/v1/rates/quotes
    export FEDEX_COMPREHENSIVE_RATE_PATH=/rate/v1/comprehensiverates/quotes
    export FEDEX_SHIP_CREATE_PATH=/ship/v1/shipments
    export FEDEX_SHIP_VALIDATE_PATH=/ship/v1/shipments/packages/validate
    export FEDEX_SHIP_CANCEL_PATH=/ship/v1/shipments/cancel
    export FEDEX_BASIC_INTEGRATED_VISIBILITY_PATH=/track/v1/trackingnumbers

    for key in \
      APP_NAME \
      FEDEX_ENABLED \
      FEDEX_ENVIRONMENT \
      FEDEX_DEFAULT_CONNECTION_MODEL \
      FEDEX_INTEGRATOR_MODEL_A_ENABLED \
      FEDEX_INTEGRATOR_PRODUCTION_ENABLED \
      FEDEX_DEVELOPER_MODE_ENABLED \
      FEDEX_MODEL_B_DEVELOPER_FALLBACK_ENABLED \
      FEDEX_SANDBOX_ALLOW_PLATFORM_FALLBACK \
      FEDEX_LIVE_ALLOWED_COUNTRIES \
      FEDEX_LIVE_BASE_URL \
      FEDEX_LIVE_ACCOUNT_REGISTRATION_PATH \
      FEDEX_OPS_ADDRESS_VALIDATION_ENABLED \
      FEDEX_OPS_SERVICE_AVAILABILITY_ENABLED \
      FEDEX_OPS_NEGOTIATED_RATES_ENABLED \
      FEDEX_OPS_SHIP_LABELS_ENABLED \
      FEDEX_OPS_TRACKING_ENABLED \
      FEDEX_CHECKOUT_RATES_ENABLED \
      FEDEX_MFA_PIN_GENERATION_PATH \
      FEDEX_MFA_PIN_VALIDATION_PATH \
      FEDEX_MFA_INVOICE_VALIDATION_PATH \
      FEDEX_ADDRESS_VALIDATION_PATH \
      FEDEX_SERVICE_AVAILABILITY_PATH \
      FEDEX_RATE_QUOTE_PATH \
      FEDEX_COMPREHENSIVE_RATE_PATH \
      FEDEX_SHIP_CREATE_PATH \
      FEDEX_SHIP_VALIDATE_PATH \
      FEDEX_SHIP_CANCEL_PATH \
      FEDEX_BASIC_INTEGRATED_VISIBILITY_PATH
    do
      $PHP_BIN scripts/deploy/upsert-env.php "$key" || true
    done
  fi
fi

# Avoid route:cache / optimize / event:cache on hosts where proc_open is disabled.
$PHP_BIN artisan config:cache || true

$PHP_BIN artisan google:status || true
$PHP_BIN artisan fedex:production-preflight || true

# Refresh /jiggy WordPress connector + brand pack when that install exists (no-op otherwise).
bash "${SCRIPT_DIR}/sync-wordpress-jiggy.sh"

echo "==> Post-deploy finished at $(date -u +%Y-%m-%dT%H:%M:%SZ)"
