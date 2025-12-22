@@ -1,13 +1,50 @@
clear
echo
echo -e "\033[1;36m========================================\033[0m"
echo -e "\033[1;36m   Player Counter Plugin Installer\033[0m"
echo -e "\033[1;36m========================================\033[0m"
echo

read -r -p "Enter your panel directory (default: /var/www/pelican): " PANEL_DIR
PANEL_DIR=${PANEL_DIR:-/var/www/pelican}

PLUGINS_DIR="$PANEL_DIR/plugins"
TARGET_DIR="$PLUGINS_DIR/player-counter"
ZIP_URL="https://github.com/Finxnz/PlayercounterPelicanPlugin/raw/refs/heads/master/player-counter.zip"
ZIP_PATH="$PLUGINS_DIR/player-counter.zip"

echo
echo "What do you want to do?"
echo
echo "  1) Install"
echo "  2) Update"
echo
read -r -p "Select an option [1-2]: " ACTION

if [ "$ACTION" = "2" ]; then
  echo
  echo -e "\033[1;33mAre you sure you want to update?\033[0m"
  echo -e "\033[1;33mThis may cause issues if something goes wrong.\033[0m"
  echo
  read -r -p "Continue? (y/n): " CONFIRM
  if [ "$CONFIRM" != "y" ]; then
    echo
    echo "Update cancelled."
    echo
    exit 0
  fi
  rm -rf "$TARGET_DIR"
elif [ "$ACTION" != "1" ]; then
  echo
  echo "Invalid option."
  echo
  exit 1
fi

TMP_DIR=$(mktemp -d)

mkdir -p "$PLUGINS_DIR"
curl -L -o "$ZIP_PATH" "$ZIP_URL"
curl -fsSL -o "$ZIP_PATH" "$ZIP_URL"
unzip -q "$ZIP_PATH" -d "$TMP_DIR"

INNER_COUNT=$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 | wc -l)
@@ -22,5 +59,7 @@ cp -r "$SRC_DIR"/* "$TARGET_DIR"/
rm -rf "$TMP_DIR" "$ZIP_PATH"

echo
echo -e "\033[1;32mInstallation complete\033[0m"
echo -e "\033[1;32m========================================\033[0m"
echo -e "\033[1;32m        Installation complete\033[0m"
echo -e "\033[1;32m========================================\033[0m"
echo
