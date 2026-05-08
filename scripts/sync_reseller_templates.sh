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

  # Skip bots with no config.php (incomplete/orphaned installs)
  if [[ ! -f "$dir/config.php" ]]; then
    echo "  skip $base: no config.php"
    continue
  fi

  # Skip bots that have opted out of auto-sync
  if [[ -f "$dir/.no-sync" ]]; then
    echo "  skip $base: .no-sync marker present"
    continue
  fi

  for file in "${FILES[@]}"; do
    src="$TEMPLATE_DIR/$file"
    dst="$dir/$file"
    if [[ ! -f "$src" ]]; then
      continue
    fi
    if [[ -f "$dst" ]] && ! diff -q "$src" "$dst" > /dev/null 2>&1; then
      echo "  WARNING: $base/$file differs from template — overwriting"
    fi
    cp -f "$src" "$dst"
  done
  echo "  synced $base"
done

echo "reseller template sync done"
