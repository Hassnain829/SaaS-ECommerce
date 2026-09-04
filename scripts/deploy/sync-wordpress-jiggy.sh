#!/usr/bin/env bash
#
# Sync Eco Portal Connector + Jiggy brand mu-plugins into $DEPLOYPATH/jiggy
# when that WordPress tree already exists. Safe no-op if /jiggy is not installed.
#
# Env:
#   CPANEL_DEPLOY_PATH — Laravel/app docroot (defaults to repo root when run from scripts/)
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${CPANEL_DEPLOY_PATH:-$(cd "${SCRIPT_DIR}/../.." && pwd)}"
JIGGY_ROOT="${APP_DIR}/jiggy"
JIGGY_PLUGINS="${JIGGY_ROOT}/wp-content/plugins"
JIGGY_MU="${JIGGY_ROOT}/wp-content/mu-plugins"
PACK_DIR="${APP_DIR}/deploy/wordpress-jiggy"

PLUGIN_SRC=""
if [[ -f "${PACK_DIR}/eco-portal-connector/eco-portal-connector.php" ]]; then
  PLUGIN_SRC="${PACK_DIR}/eco-portal-connector"
elif [[ -f "${APP_DIR}/dev-test-wordpress/wp-content/plugins/eco-portal-connector/eco-portal-connector.php" ]]; then
  PLUGIN_SRC="${APP_DIR}/dev-test-wordpress/wp-content/plugins/eco-portal-connector"
fi

if [[ ! -d "${JIGGY_PLUGINS}" ]]; then
  echo "==> WordPress /jiggy not present at ${JIGGY_ROOT} — skip plugin sync"
  exit 0
fi

echo "==> Syncing WordPress demo plugins into ${JIGGY_ROOT}"

sync_dir() {
  local src="$1"
  local dest="$2"
  mkdir -p "${dest}"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete "${src}/" "${dest}/"
  else
    # Shared hosts sometimes lack rsync; copy then prune is not perfect — prefer rsync.
    find "${dest}" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
    cp -a "${src}/." "${dest}/"
  fi
}

if [[ -n "${PLUGIN_SRC}" ]]; then
  DEST_PLUGIN="${JIGGY_PLUGINS}/eco-portal-connector"
  sync_dir "${PLUGIN_SRC}" "${DEST_PLUGIN}"
  echo "==> Synced eco-portal-connector from ${PLUGIN_SRC}"
else
  echo "WARN: eco-portal-connector source not found under deploy/wordpress-jiggy or dev-test-wordpress" >&2
fi

if [[ -d "${PACK_DIR}/mu-plugins" ]]; then
  mkdir -p "${JIGGY_MU}"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a "${PACK_DIR}/mu-plugins/" "${JIGGY_MU}/"
  else
    cp -a "${PACK_DIR}/mu-plugins/." "${JIGGY_MU}/"
  fi
  echo "==> Synced Jiggy brand mu-plugins from ${PACK_DIR}/mu-plugins"
else
  echo "WARN: brand pack missing at ${PACK_DIR}/mu-plugins" >&2
fi

echo "==> WordPress /jiggy sync finished"
