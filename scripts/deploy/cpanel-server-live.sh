#!/usr/bin/env bash
#
# Run ON THE CPANEL SERVER inside the Laravel project root.
# Use when GitHub Actions rsync is unavailable or for first-time go-live.
#
set -euo pipefail

APP_DIR="${CPANEL_DEPLOY_PATH:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
PHP_BIN="${CPANEL_PHP_BIN:-}"

resolve_php_bin() {
  if [[ -n "${PHP_BIN}" ]] && command -v "${PHP_BIN}" >/dev/null 2>&1; then
    return 0
  fi
  for candidate in php /usr/local/bin/ea-php83 /usr/local/bin/ea-php82 /opt/cpanel/ea-php83/root/usr/bin/php; do
    if command -v "${candidate}" >/dev/null 2>&1; then
      PHP_BIN="${candidate}"
      return 0
    fi
  done
  echo "ERROR: PHP CLI not found. Set CPANEL_PHP_BIN." >&2
  exit 1
}

run_composer() {
  if command -v composer >/dev/null 2>&1; then
    composer "$@"
    return 0
  fi
  if [[ -x /usr/local/bin/composer ]]; then
    "${PHP_BIN}" /usr/local/bin/composer "$@"
    return 0
  fi
  if [[ -f "${APP_DIR}/composer.phar" ]]; then
    "${PHP_BIN}" "${APP_DIR}/composer.phar" "$@"
    return 0
  fi

  echo "==> Bootstrapping composer.phar"
  curl -sS https://getcomposer.org/download/latest-stable/composer.phar -o "${APP_DIR}/composer.phar"
  "${PHP_BIN}" "${APP_DIR}/composer.phar" "$@"
}

run_npm() {
  if command -v npm >/dev/null 2>&1; then
    npm "$@"
    return 0
  fi
  if [[ -x /usr/local/bin/npm ]]; then
    /usr/local/bin/npm "$@"
    return 0
  fi
  if [[ -f "${HOME}/.nvm/nvm.sh" ]]; then
    # shellcheck disable=SC1091
    source "${HOME}/.nvm/nvm.sh"
    npm "$@"
    return 0
  fi
  return 1
}

resolve_php_bin
cd "${APP_DIR}"

if [[ ! -f artisan ]]; then
  echo "ERROR: Run this from the Laravel root (artisan not found)." >&2
  exit 1
fi

echo "==> Live setup in ${APP_DIR}"
echo "==> PHP: $("${PHP_BIN}" -v | head -n 1)"

if [[ -d .git ]]; then
  git pull origin main || git pull origin master
fi

run_composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if run_npm ci && run_npm run build; then
  echo "==> Frontend build complete"
else
  echo "WARN: npm not available — ensure public/build exists (GitHub Actions deploy can supply it)." >&2
  if [[ ! -d public/build ]] || [[ "$(find public/build -type f 2>/dev/null | wc -l)" -eq 0 ]]; then
    echo "ERROR: public/build is missing. Enable Node.js in cPanel or fix GitHub SSH deploy." >&2
    exit 1
  fi
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

export CPANEL_PHP_BIN="${PHP_BIN}"
export CPANEL_DEPLOY_PATH="${APP_DIR}"
bash scripts/deploy/cpanel-post-deploy.sh

echo "==> Done. Check ${APP_URL:-https://ecom.resolutedigitalspk.com}/up"
