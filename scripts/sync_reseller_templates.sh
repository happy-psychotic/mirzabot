#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${1:-/var/www/mirza_pro}"
VPNBOT_DIR="$APP_DIR/vpnbot"
TEMPLATE_DIR="$VPNBOT_DIR/update"

if [[ ! -d "$VPNBOT_DIR" || ! -d "$TEMPLATE_DIR" ]]; then
  echo "sync skipped: missing vpnbot/update directory"
  exit 0
fi

FILES=(
  "index.php"
  "keyboard.php"
  "admin.php"
  "func.php"
  "botapi.php"
)

shopt -s nullglob
for dir in "$VPNBOT_DIR"/*/; do
  base="$(basename "$dir")"
  case "$base" in
    Default|update)
      continue
      ;;
  esac

  if [[ ! -f "$dir/config.php" ]]; then
    continue
  fi

  for file in "${FILES[@]}"; do
    if [[ -f "$TEMPLATE_DIR/$file" ]]; then
      cp -f "$TEMPLATE_DIR/$file" "$dir/$file"
    fi
  done
done

echo "reseller template sync done"
