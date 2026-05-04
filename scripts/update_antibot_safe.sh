#!/usr/bin/env bash
set -euo pipefail

HOST="${1:-antibot}"
APP_DIR="/var/www/mirza_pro"

ssh "$HOST" 'bash -s' <<'REMOTE'
set -euo pipefail
APP_DIR="/var/www/mirza_pro"
TS=$(date +%F_%H-%M-%S)
BACK="/root/mirza_backup_${TS}"

if [ ! -d "$APP_DIR/.git" ]; then
  echo "ERROR: $APP_DIR is not a git repository"
  exit 1
fi

mkdir -p "$BACK"
cp -a "$APP_DIR" "$BACK/"
cp -a "$APP_DIR/config.php" "$BACK/config.php.local"

git config --global --add safe.directory "$APP_DIR" >/dev/null 2>&1 || true
cd "$APP_DIR"

git remote set-url origin https://github.com/happy-psychotic/mirzabot.git
git fetch origin
git checkout -- config.php
git pull --ff-only origin main

cp -f "$BACK/config.php.local" "$APP_DIR/config.php"
chown www-data:www-data "$APP_DIR/config.php" || true

echo "Backup created at: $BACK"
git log --oneline -n 3
git status --short --branch | sed -n "1,80p"
REMOTE
