#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="dynamic-post-grid-pro"
OUT_DIR="dist"

mkdir -p "$OUT_DIR"

ZIP_PATH="$OUT_DIR/${PLUGIN_SLUG}.zip"
rm -f "$ZIP_PATH"

# Bump plugin/build versions for each packaging run.
BUILD_VERSION="$(date +%Y.%m.%d.%H%M%S)"
python3 - <<PY
import re
from pathlib import Path

version = "${BUILD_VERSION}"

main = Path("dynamic-post-grid-pro.php")
asset = Path("build/index.asset.php")

main_text = main.read_text(encoding="utf-8")
main_text, n1 = re.subn(r"^\\s*\\*\\s*Version:\\s*.+\\s*$", f" * Version: {version}", main_text, flags=re.M)
main_text, n2 = re.subn(r"define\\(\\s*'DPG_VERSION'\\s*,\\s*'[^']*'\\s*\\);", f"define( 'DPG_VERSION', '{version}' );", main_text)
if n1 < 1 or n2 < 1:
    raise SystemExit("Failed to bump version in dynamic-post-grid-pro.php")
main.write_text(main_text, encoding="utf-8")

asset_text = asset.read_text(encoding="utf-8")
asset_text, n3 = re.subn(r"'version'\\s*=>\\s*'[^']*'", f"'version'      => '{version}'", asset_text)
if n3 < 1:
    raise SystemExit("Failed to bump version in build/index.asset.php")
asset.write_text(asset_text, encoding="utf-8")
PY

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
