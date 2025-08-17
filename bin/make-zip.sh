#!/bin/bash

# SmartAlloc Plugin Build Script
# Creates a distributable ZIP file

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get plugin directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_NAME="smart-alloc"

# Extract version from plugin file
VERSION=$(grep "Version:" "$PLUGIN_DIR/smart-alloc.php" | sed 's/.*Version: *//' | tr -d ' ')
if [ -z "$VERSION" ]; then
    echo -e "${RED}ERROR: Could not extract version from plugin file${NC}"
    exit 1
fi

echo -e "${GREEN}Building SmartAlloc v$VERSION${NC}"

# Create build directory
BUILD_DIR="$PLUGIN_DIR/build"
PLUGIN_BUILD_DIR="$BUILD_DIR/$PLUGIN_NAME"

# Clean previous build
if [ -d "$BUILD_DIR" ]; then
    echo "Cleaning previous build..."
    rm -rf "$BUILD_DIR"
fi

# Create build structure
mkdir -p "$PLUGIN_BUILD_DIR"

# Copy plugin files
echo "Copying plugin files..."
cp -r "$PLUGIN_DIR/src" "$PLUGIN_BUILD_DIR/"
cp "$PLUGIN_DIR/smart-alloc.php" "$PLUGIN_BUILD_DIR/"
cp "$PLUGIN_DIR/composer.json" "$PLUGIN_BUILD_DIR/"
cp "$PLUGIN_DIR/phpcs.xml" "$PLUGIN_BUILD_DIR/"
cp "$PLUGIN_DIR/phpstan.neon" "$PLUGIN_BUILD_DIR/"
cp "$PLUGIN_DIR/phpunit.xml.dist" "$PLUGIN_BUILD_DIR/"

# Copy documentation
if [ -f "$PLUGIN_DIR/README.md" ]; then
    cp "$PLUGIN_DIR/README.md" "$PLUGIN_BUILD_DIR/"
fi

if [ -f "$PLUGIN_DIR/SECURITY.md" ]; then
    cp "$PLUGIN_DIR/SECURITY.md" "$PLUGIN_BUILD_DIR/"
fi

if [ -f "$PLUGIN_DIR/UPGRADE_GUIDE.md" ]; then
    cp "$PLUGIN_DIR/UPGRADE_GUIDE.md" "$PLUGIN_BUILD_DIR/"
fi

if [ -f "$PLUGIN_DIR/ARCHITECTURE.md" ]; then
    cp "$PLUGIN_DIR/ARCHITECTURE.md" "$PLUGIN_BUILD_DIR/"
fi

# Copy tests if they exist
if [ -d "$PLUGIN_DIR/tests" ]; then
    cp -r "$PLUGIN_DIR/tests" "$PLUGIN_BUILD_DIR/"
fi

# Copy assets if they exist
if [ -d "$PLUGIN_DIR/assets" ]; then
    cp -r "$PLUGIN_DIR/assets" "$PLUGIN_BUILD_DIR/"
fi

# Create ZIP file
ZIP_FILE="$BUILD_DIR/${PLUGIN_NAME}_v${VERSION}.zip"
echo "Creating ZIP file: $ZIP_FILE"

cd "$BUILD_DIR"
zip -r "$ZIP_FILE" "$PLUGIN_NAME" -x "*.DS_Store" "*/.*" "*/node_modules/*" "*/vendor/*" "*/tests/*"

# Verify ZIP was created
if [ -f "$ZIP_FILE" ]; then
    ZIP_SIZE=$(du -h "$ZIP_FILE" | cut -f1)
    echo -e "${GREEN}✓ Build successful!${NC}"
    echo -e "${GREEN}ZIP file: $ZIP_FILE (${ZIP_SIZE})${NC}"
else
    echo -e "${RED}ERROR: Failed to create ZIP file${NC}"
    exit 1
fi

echo -e "${GREEN}Build completed successfully!${NC}" 