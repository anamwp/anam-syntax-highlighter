#!/usr/bin/env bash
#
# Build a production-ready zip of the plugin, suitable for uploading via
# Plugins > Add New > Upload Plugin or dropping into wp-content/plugins/.
#
# Usage: bin/build.sh
# Output: build/anam-syntax-highlighter-<version>.zip

set -euo pipefail

PLUGIN_SLUG="anam-syntax-highlighter"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MAIN_FILE="$PLUGIN_DIR/$PLUGIN_SLUG.php"

if [ ! -f "$MAIN_FILE" ]; then
	echo "Error: could not find $MAIN_FILE — is bin/build.sh still inside the plugin directory?" >&2
	exit 1
fi

VERSION=$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([0-9A-Za-z.+-]+).*/\1/p' "$MAIN_FILE" | head -n1)
if [ -z "$VERSION" ]; then
	echo "Error: could not read Version from the $PLUGIN_SLUG.php header." >&2
	exit 1
fi

BUILD_DIR="$PLUGIN_DIR/build"
STAGE_DIR="$BUILD_DIR/$PLUGIN_SLUG"
ZIP_FILE="$BUILD_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building $PLUGIN_SLUG $VERSION..."

rm -rf "$STAGE_DIR" "$ZIP_FILE"
mkdir -p "$STAGE_DIR"

# Copy everything except dev-only files/directories and previous build output.
rsync -a "$PLUGIN_DIR"/ "$STAGE_DIR"/ \
	--exclude ".git" \
	--exclude ".gitignore" \
	--exclude ".github" \
	--exclude ".DS_Store" \
	--exclude "build" \
	--exclude "bin" \
	--exclude "node_modules" \
	--exclude "package.json" \
	--exclude "package-lock.json" \
	--exclude "*.map"

# Strip stray .DS_Store files that can land inside subdirectories on macOS.
find "$STAGE_DIR" -name ".DS_Store" -delete

(cd "$BUILD_DIR" && zip -rq "$ZIP_FILE" "$PLUGIN_SLUG")
rm -rf "$STAGE_DIR"

echo "Built: $ZIP_FILE"
