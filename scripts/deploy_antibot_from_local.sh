#!/usr/bin/env bash
set -euo pipefail

HOST="${1:-antibot}"
APP_DIR="${2:-/var/www/mirza_pro}"
REF="${3:-HEAD}"

REPO_ROOT="$(git -C "$(dirname "$0")/.." rev-parse --show-toplevel)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

git -C "$REPO_ROOT" archive "$REF" | tar -x -C "$TMP_DIR"

rsync -az --delete \
  --exclude '.git/' \
  --exclude 'config.php' \
  --exclude 'error_log' \
  --exclude 'log.txt' \
  --exclude 'cookie.txt' \
  --exclude 'api/log.txt' \
  --exclude 'storage/' \
  --exclude 'sub/cookie.txt' \
  --exclude 'config.php.bak' \
  --exclude 'vpnbot/[0-9]*/' \
  --exclude 'vpnbot/*_bot/' \
  "$TMP_DIR"/ "$HOST:$APP_DIR"/

ssh "$HOST" "cd '$APP_DIR' && php -l index.php >/dev/null && php -l config.php >/dev/null && echo 'deploy ok: $APP_DIR'"
ssh "$HOST" "bash '$APP_DIR/scripts/sync_reseller_templates.sh' '$APP_DIR'"
