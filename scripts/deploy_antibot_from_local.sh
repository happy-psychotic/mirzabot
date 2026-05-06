#!/usr/bin/env bash
set -euo pipefail

HOST="${1:-antibot}"
APP_DIR="${2:-/var/www/mirza_pro}"
REF="${3:-HEAD}"
DRY_RUN="${DRY_RUN:-0}"

REPO_ROOT="$(git -C "$(dirname "$0")/.." rev-parse --show-toplevel)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

git -C "$REPO_ROOT" archive "$REF" | tar -x -C "$TMP_DIR"

RSYNC_EXCLUDES=(
  --exclude '.git/'
  --exclude 'config.php'
  --exclude 'error_log'
  --exclude 'log.txt'
  --exclude 'cookie.txt'
  --exclude 'api/log.txt'
  --exclude 'storage/'
  --exclude 'sub/cookie.txt'
  --exclude 'config.php.bak'
  --exclude 'vpnbot/[0-9]*/'
  --exclude 'vpnbot/*_bot/'
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
  && echo 'deploy ok: $APP_DIR'"
echo "reseller bot templates were not auto-synced; run scripts/sync_reseller_templates.sh manually only after review"
