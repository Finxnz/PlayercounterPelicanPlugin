read -r -p "Enter your panel directory (default: /var/www/pelican): " PANEL_DIR
PANEL_DIR=${PANEL_DIR:-/var/www/pelican}
PLUGINS_DIR="$PANEL_DIR/plugins"
TARGET_DIR="$PLUGINS_DIR/player-counter"
ZIP_URL="https://github.com/Finxnz/PlayercounterPelicanPlugin/raw/refs/heads/master/player-counter.zip"
ZIP_PATH="$PLUGINS_DIR/player-counter.zip"
TMP_DIR=$(mktemp -d)

mkdir -p "$PLUGINS_DIR"
curl -L -o "$ZIP_PATH" "$ZIP_URL"
unzip -q "$ZIP_PATH" -d "$TMP_DIR"

INNER_COUNT=$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 | wc -l)
if [ "$INNER_COUNT" -eq 1 ] && [ -d "$(find "$TMP_DIR" -mindepth 1 -maxdepth 1)" ]; then
  SRC_DIR="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1)"
else
  SRC_DIR="$TMP_DIR"
fi

mkdir -p "$TARGET_DIR"
cp -r "$SRC_DIR"/* "$TARGET_DIR"/
rm -rf "$TMP_DIR" "$ZIP_PATH"

echo
echo -e "\033[1;32mInstallation complete\033[0m"
echo
