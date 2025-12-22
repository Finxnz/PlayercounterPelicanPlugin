#!/usr/bin/env bash
set -euo pipefail

clear
echo "                                                                                      "
echo "                                                                                      "
echo "█████▄ ▄▄     ▄▄▄  ▄▄ ▄▄ ▄▄▄▄▄ ▄▄▄▄      ▄█████  ▄▄▄  ▄▄ ▄▄ ▄▄  ▄▄ ▄▄▄▄▄▄ ▄▄▄▄▄ ▄▄▄▄  "
echo "██▄▄█▀ ██    ██▀██ ▀███▀ ██▄▄  ██▄█▄ ▄▄▄ ██     ██▀██ ██ ██ ███▄██   ██   ██▄▄  ██▄█▄ "
echo "██     ██▄▄▄ ██▀██   █   ██▄▄▄ ██ ██     ▀█████ ▀███▀ ▀███▀ ██ ▀██   ██   ██▄▄▄ ██ ██ "
echo "                                                                                      "
echo "                                                                                      "

for cmd in curl unzip php; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "Missing dependency: $cmd"; exit 1; }
done

read -r -p "Panel directory (default: /var/www/pelican): " PANEL_DIR
PANEL_DIR=${PANEL_DIR:-/var/www/pelican}

[ -d "$PANEL_DIR" ] || { echo "Invalid panel directory."; exit 1; }

PLUGINS_DIR="$PANEL_DIR/plugins"
TARGET_DIR="$PLUGINS_DIR/player-counter"
ZIP_URL="https://raw.githubusercontent.com/Finxnz/PlayercounterPelicanPlugin/master/player-counter.zip


TMP_DIR="$(mktemp -d)"
ZIP_PATH="$(mktemp)"

trap 'rm -rf "$TMP_DIR" "$ZIP_PATH"' EXIT

echo
echo "1) Install"
echo "2) Update"
echo "3) Fix (only when error)"
echo
read -r -p "Select [1-3]: " ACTION

if [ "$ACTION" = "3" ]; then
  WEBSERVER_USER="www-data"

  if command -v nginx >/dev/null 2>&1; then
    WEBSERVER_USER=$(ps aux | grep -E 'nginx: worker' | grep -v root | head -1 | awk '{print $1}')
  elif command -v apache2 >/dev/null 2>&1 || command -v httpd >/dev/null 2>&1; then
    WEBSERVER_USER=$(ps aux | grep -E 'apache2|httpd' | grep -v root | head -1 | awk '{print $1}')
  fi

  echo
  echo "Setting permissions for user: $WEBSERVER_USER"
  chown -R "$WEBSERVER_USER":"$WEBSERVER_USER" "$PANEL_DIR"
  chmod -R 755 "$PANEL_DIR"

  echo
  echo "Permissions fixed."
  echo
  exit 0
fi

if [ "$ACTION" = "2" ]; then
  [ -d "$TARGET_DIR" ] || { echo "Plugin not installed."; exit 1; }
  read -r -p "Backup before update? (y/n): " BACKUP
  if [ "$BACKUP" = "y" ]; then
    cp -r "$TARGET_DIR" "${TARGET_DIR}_backup_$(date +%Y%m%d_%H%M%S)"
  fi
  rm -rf "$TARGET_DIR"
elif [ "$ACTION" != "1" ]; then
  echo "Invalid option."
  exit 1
fi

mkdir -p "$PLUGINS_DIR"

echo
echo "Downloading latest release..."
curl -fL "$ZIP_URL" -o "$ZIP_PATH"

echo "Extracting..."
unzip -q "$ZIP_PATH" -d "$TMP_DIR"

INNER_COUNT=$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 | wc -l)
if [ "$INNER_COUNT" -eq 1 ] && [ -d "$(find "$TMP_DIR" -mindepth 1 -maxdepth 1)" ]; then
  SRC_DIR="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1)"
else
  SRC_DIR="$TMP_DIR"
fi

mkdir -p "$TARGET_DIR"
cp -r "$SRC_DIR"/* "$TARGET_DIR"/

echo
echo "Registering plugin with Pelican..."
cd "$PANEL_DIR"
php artisan p:plugin:install player-counter

echo
echo "========================================"
echo "        Installation complete            "
echo "========================================"
echo
