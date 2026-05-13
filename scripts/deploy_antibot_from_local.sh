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
chmod 755 "$TMP_DIR"

RSYNC_EXCLUDES=(
  # git metadata
  --exclude '.git/'
  # server-local config — never overwrite
  --exclude 'config.php'
  --exclude 'config.php.bak'
  --exclude 'vpnbot/Default/config.php'
  --exclude 'vpnbot/update/config.php'
  # runtime logs and cookies
  --exclude 'error_log'
  --exclude 'log.txt'
  --exclude 'cookie.txt'
  --exclude 'cronbot/error_log'
  --exclude 'cronbot/log.txt'
  --exclude 'api/log.txt'
  --exclude 'sub/cookie.txt'
  # runtime storage and temp files
  --exclude 'storage/'
  --exclude '.cache/'
  --exclude '*.png'
  --exclude '*.jpg'
  # gitignored server-side dirs — exist on server but not in git, --delete would wipe them
  --exclude 'vendor/'
  --exclude 'payment/'
  --exclude 'ibsng/'
  --exclude 'ibsng.php'
  --exclude 'agent_panel.php'
  --exclude 'panels.php'
  --exclude 'docs/'
  --exclude 'PROJECT_UPDATE.md'
  # live reseller bot instances — managed by sync_reseller_templates.sh
  --exclude 'vpnbot/[0-9]*/'
  --exclude 'vpnbot/*_bot/'
)

RSYNC_ARGS=(-az --delete --itemize-changes --chmod=D755,F644 "${RSYNC_EXCLUDES[@]}")
if [[ "$DRY_RUN" == "1" ]]; then
  RSYNC_ARGS+=(--dry-run)
fi

# Ensure root dir and vpnbot are writable by www-data before and after rsync
ssh "$HOST" "chmod 755 '$APP_DIR' && chmod 777 '$APP_DIR/vpnbot' 2>/dev/null || true"

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
