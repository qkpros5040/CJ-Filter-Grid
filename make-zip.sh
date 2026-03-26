#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="dynamic-post-grid-pro"
OUT_DIR="dist"

mkdir -p "$OUT_DIR"

ZIP_PATH="$OUT_DIR/${PLUGIN_SLUG}.zip"
rm -f "$ZIP_PATH"

# Zip the repo root as the installable plugin folder.
zip -r "$ZIP_PATH" . \
	-x ".git/*" ".gitignore" ".vscode/*" \
	-x "node_modules/*" \
	-x "dist/*" \
	-x "make-zip.sh" \
	-x "package.json" "package-lock.json" \
	-x "src/*" \
	-x "docs/*"

echo "Wrote: $ZIP_PATH"
