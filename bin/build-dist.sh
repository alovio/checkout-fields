#!/usr/bin/env bash
#
# Build a clean wp.org distribution zip: production autoloader (no dev deps) and
# only shippable files (dev/test/build-tooling excluded). Output: dist/<slug>.zip
#
set -euo pipefail

SLUG="corelabs-checkout-fields"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/dist"
DEST="$OUT/$SLUG"

rm -rf "$OUT"
mkdir -p "$DEST"

# Copy shippable files. Per wp.org Guideline 1/4 we ship the human-readable
# source (src/, assets/) and build tools (package.json, webpack.config.js,
# composer.json) alongside the compiled build/ so the bundle is not obfuscated.
rsync -a \
	--exclude='.git' --exclude='.github' --exclude='.gitignore' --exclude='.distignore' --exclude='.DS_Store' \
	--exclude='.wp-env.json' --exclude='.playwright-mcp' --exclude='.wordpress-org' --exclude='node_modules' \
	--exclude='tests' --exclude='docs' --exclude='bin' \
	--exclude='package-lock.json' \
	--exclude='phpunit.xml.dist' --exclude='.phpunit.result.cache' --exclude='dist' \
	--exclude='vendor' \
	"$ROOT/" "$DEST/"

# Production Composer autoloader (no dev deps). composer.json is kept in the zip;
# autoload-dev/require-dev are simply ignored.
cp "$ROOT/composer.json" "$ROOT/composer.lock" "$DEST/" 2>/dev/null || cp "$ROOT/composer.json" "$DEST/"
( cd "$DEST" && composer install --no-dev --optimize-autoloader --quiet )

# Zip the free plugin.
( cd "$OUT" && zip -rqX "$SLUG.zip" "$SLUG" )
echo "Built: $OUT/$SLUG.zip"

