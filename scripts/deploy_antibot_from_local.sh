#!/usr/bin/env bash
set -euo pipefail

HOST="${1:-antibot}"
APP_DIR="${2:-/var/www/mirza_pro}"
REF="${3:-HEAD}"
DRY_RUN="${DRY_RUN:-0}"

REPO_ROOT="$(git -C "$(dirname "$0")/.." rev-parse --show-toplevel)"
TMP_DIR="$(mktemp -d)"
LOCK_FILE="/tmp/mirza_deploy.lock"

acquire_lock() {
  if ! mkdir "$LOCK_FILE" 2>/dev/null; then
    echo "ERROR: another deploy is already running (lock: $LOCK_FILE)" >&2
    echo "If no deploy is running, remove the lock manually: rm -rf $LOCK_FILE" >&2
    exit 1
  fi
}

release_lock() {
  rm -rf "$LOCK_FILE"
  rm -rf "$TMP_DIR"
}

trap 'release_lock' EXIT
acquire_lock

# Only deploy committed code — uncommitted changes are never sent to server
git -C "$REPO_ROOT" archive "$REF" | tar -x -C "$TMP_DIR"

RSYNC_EXCLUDES=(
  --exclude '.git/'
  --exclude 'config.php'
  --exclude 'vpnbot/Default/config.php'
  --exclude 'vpnbot/update/config.php'
  --exclude 'error_log'
  --exclude 'cronbot/error_log'
  --exclude 'cronbot/log.txt'
  --exclude 'log.txt'
  --exclude 'cookie.txt'
  --exclude 'api/log.txt'
  --exclude 'storage/'
  --exclude 'sub/cookie.txt'
  --exclude 'config.php.bak'
  --exclude '*.png'
  --exclude '*.jpg'
  --exclude 'vpnbot/[0-9]*/'
  --exclude 'vpnbot/*_bot/'
  --exclude 'docs/'
  --exclude 'PROJECT_UPDATE.md'
)

RSYNC_ARGS=(-az --delete --itemize-changes "${RSYNC_EXCLUDES[@]}")
if [[ "$DRY_RUN" == "1" ]]; then
  RSYNC_ARGS+=(--dry-run)
fi

rsync "${RSYNC_ARGS[@]}" "$TMP_DIR"/ "$HOST:$APP_DIR"/

if [[ "$DRY_RUN" == "1" ]]; then
  echo "dry run complete: no files changed"
  exit 0
fi

ssh "$HOST" "chmod 755 '$APP_DIR' \
  && find '$APP_DIR' -type d -exec chmod 755 {} + \
  && find '$APP_DIR' -type f -exec chmod 644 {} + \
  && chmod 777 '$APP_DIR/vpnbot' \
  && find '$APP_DIR/scripts' -type f -name '*.sh' -exec chmod 755 {} + \
  && cd '$APP_DIR' \
  && php -l index.php >/dev/null \
  && php -l config.php >/dev/null \
  && echo 'main bot deploy ok'"

# Sync reseller bot instances from vpnbot/update template
echo "syncing reseller bot instances..."
ssh "$HOST" "bash '$APP_DIR/scripts/sync_reseller_templates.sh' '$APP_DIR'"

echo "deploy complete: $APP_DIR"
