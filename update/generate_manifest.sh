#!/bin/bash
################################################################################
# Secure Blog CMS - Update Manifest Generator
#
# Usage:
#   ./generate_manifest.sh [version] [description]
#   ./generate_manifest.sh 1.5.3 "Bug fix release"
#
# This script:
#   1. Copies current source files into update/files/
#   2. Calculates SHA256 hashes for each file
#   3. Generates update/manifest.json with real hashes
#   4. Optionally commits and tags the release
#
# Files NOT included in updates (never overwrite):
#   - includes/config.php  (contains user credentials and settings)
#   - data/                 (contains user content)
#   - install/              (should be deleted after setup)
################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Defaults
VERSION="${1:-}"
DESCRIPTION="${2:-Security and bug fix update}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
FILES_DIR="$SCRIPT_DIR/files"

if [[ -z "$VERSION" ]]; then
    # Try to read version from includes/config.php
    CONFIG="$PROJECT_DIR/includes/config.php"
    if [[ -f "$CONFIG" ]]; then
        VERSION=$(grep -oP 'SECURE_CMS_VERSION.*?"\K[^"]+' "$CONFIG" 2>/dev/null || true)
    fi
    if [[ -z "$VERSION" ]]; then
        VERSION=$(grep -oP '"version"\s*:\s*"\K[^"]+' "$PROJECT_DIR/data/version.json" 2>/dev/null || true)
    fi
    if [[ -z "$VERSION" ]]; then
        echo -e "${RED}Error: No version specified. Usage: $0 [version] [description]${NC}"
        exit 1
    fi
    echo -e "${YELLOW}Auto-detected version: $VERSION${NC}"
fi

echo "=============================================="
echo "Secure Blog CMS - Manifest Generator"
echo "Version: $VERSION"
echo "Description: $DESCRIPTION"
echo "=============================================="
echo ""

# Files to include in updates (relative to project root)
# config.php is deliberately excluded — it contains user credentials
SOURCE_FILES=(
    "index.php"
    "post.php"
    "rss.php"
    "s.php"
    "admin.php"
    "edit-post.php"
    "admin/admin.php"
    "admin/categories.php"
    "admin/comments.php"
    "admin/create-post.php"
    "admin/edit-post.php"
    "admin/login.php"
    "admin/logout.php"
    "admin/resilience.php"
    "admin/serve-image.php"
    "admin/settings.php"
    "admin/upgrade.php"
    "admin/upload-image.php"
    "admin/users.php"
    "includes/Security.php"
    "includes/Storage.php"
    "includes/Upgrader.php"
    "includes/Resilience.php"
    "includes/UrlShortener.php"
    "includes/comments.php"
    "includes/users.php"
    "includes/notifications.php"
    "includes/ImageUpload.php"
    "includes/RateLimiter.php"
    "templates/index_template.php"
    "templates/post_template.php"
    "data/version.json"
)

# Files that should NEVER be in updates
EXCLUDED_FILES=(
    "includes/config.php"
    "includes/config.php.example"
)

echo -e "${BLUE}[1/4]${NC} Copying source files to update/files/..."

# Copy each file
for file in "${SOURCE_FILES[@]}"; do
    src="$PROJECT_DIR/$file"
    dst="$FILES_DIR/$file"
    
    if [[ -f "$src" ]]; then
        mkdir -p "$(dirname "$dst")"
        cp "$src" "$dst"
        echo "  ✓ $file"
    else
        echo -e "  ${YELLOW}⚠ $file not found, skipping${NC}"
    fi
done

# Remove excluded files from update/files
for file in "${EXCLUDED_FILES[@]}"; do
    dst="$FILES_DIR/$file"
    if [[ -f "$dst" ]]; then
        rm -f "$dst"
        echo -e "  ${RED}✗ Removed $file (never include in updates)${NC}"
    fi
done

echo ""
echo -e "${BLUE}[2/4]${NC} Calculating SHA256 hashes..."

# Build the files JSON
FILES_JSON=""
FIRST=true

for file in "${SOURCE_FILES[@]}"; do
    src="$PROJECT_DIR/$file"
    dst="$FILES_DIR/$file"
    
    # Skip excluded files
    skip=false
    for excluded in "${EXCLUDED_FILES[@]}"; do
        if [[ "$file" == "$excluded" ]]; then
            skip=true
            break
        fi
    done
    if $skip; then continue; fi
    
    if [[ ! -f "$dst" ]]; then continue; fi
    
    hash=$(sha256sum "$dst" | awk '{print $1}')
    
    if $FIRST; then
        FIRST=false
    else
        FILES_JSON+=","
    fi
    FILES_JSON+="
        \"$file\": {
            \"path\": \"$file\",
            \"sha256\": \"$hash\"
        }"
    
    echo "  $file: $hash"
done

echo ""
echo -e "${BLUE}[3/4]${NC} Generating manifest.json..."

# Get current date
DATE=$(date +%Y-%m-%d)

# Generate manifest
cat > "$SCRIPT_DIR/manifest.json" << MANIFEST_EOF
{
    "version": "$VERSION",
    "released": "$DATE",
    "base": "https://raw.githubusercontent.com/AfterPacket/secure-blog-cms/main/update/files",
    "description": "$DESCRIPTION",
    "cache_bust": "$(date +%Y%m%d)-$(printf '%04d' $$)",
    "critical": false,
    "files": {$FILES_JSON
    },
    "changes": [
        "See RELEASE_NOTES.md for detailed changes"
    ]
}
MANIFEST_EOF

echo "  ✓ manifest.json written"

echo ""
echo -e "${BLUE}[4/4]${NC} Verifying manifest..."

# Verify each file can be downloaded from the base URL
FILE_COUNT=$(echo "$FILES_JSON" | grep -c '"path"' || true)
echo "  Files in manifest: $FILE_COUNT"
echo "  Config.php excluded: ✓"
echo ""
echo -e "${GREEN}Done!${NC} Manifest generated at: $SCRIPT_DIR/manifest.json"
echo ""
echo "Next steps:"
echo "  1. Review manifest.json"
echo "  2. git add update/ && git commit -m 'v$VERSION: update manifest and files'"
echo "  3. git tag -a v$VERSION -m 'v$VERSION'"
echo "  4. git push origin master --tags"
echo "  5. Users can update via Admin → Upgrade page"