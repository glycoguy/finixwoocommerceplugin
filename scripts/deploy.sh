#!/bin/bash

###############################################################################
# Finix Plugin Deployment Script
#
# This script automatically:
# 1. Zips the current plugin version
# 2. Uploads to your WordPress site via SFTP
# 3. Optionally backs up the old version
#
# Usage: ./deploy.sh
#
# Requirements:
# - lftp (install with: sudo apt-get install lftp)
# - SFTP credentials configured in sftp-config.sh
###############################################################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_DIR="$PROJECT_DIR/current-plugin/finix-v1.8.0"
ZIP_DIR="$PROJECT_DIR/dist"
PLUGIN_NAME="finix-woocommerce-subscriptions"
VERSION="1.8.1"
ZIP_FILE="$ZIP_DIR/${PLUGIN_NAME}-v${VERSION}.zip"

# Load SFTP configuration
CONFIG_FILE="$SCRIPT_DIR/sftp-config.sh"

if [ ! -f "$CONFIG_FILE" ]; then
    echo -e "${RED}Error: SFTP configuration file not found!${NC}"
    echo ""
    echo "Please create: $CONFIG_FILE"
    echo ""
    echo "Template:"
    echo "#!/bin/bash"
    echo "SFTP_HOST=\"your-site.flywheelsites.com\""
    echo "SFTP_USER=\"your-site\""
    echo "SFTP_PASS=\"your-password\""
    echo "SFTP_PORT=\"2222\""
    echo "REMOTE_WP_PATH=\"/www/wp-content/plugins\""
    echo ""
    exit 1
fi

source "$CONFIG_FILE"

echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Finix Plugin Deployment Script v1.0     ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo ""

# Validate required variables
if [ -z "$SFTP_HOST" ] || [ -z "$SFTP_USER" ] || [ -z "$SFTP_PASS" ]; then
    echo -e "${RED}Error: SFTP credentials not configured properly!${NC}"
    exit 1
fi

# Check if lftp is installed
if ! command -v lftp &> /dev/null; then
    echo -e "${RED}Error: lftp is not installed!${NC}"
    echo "Install with: sudo apt-get install lftp"
    exit 1
fi

# Step 1: Create zip directory if it doesn't exist
echo -e "${YELLOW}[1/5]${NC} Preparing deployment..."
mkdir -p "$ZIP_DIR"

# Step 2: Create plugin zip
echo -e "${YELLOW}[2/5]${NC} Creating plugin zip..."

# Remove old zip if exists
if [ -f "$ZIP_FILE" ]; then
    rm "$ZIP_FILE"
fi

# Create zip from plugin directory
cd "$PROJECT_DIR/current-plugin"
zip -r "$ZIP_FILE" "finix-v1.8.0" \
    -x "*.git*" \
    -x "*node_modules*" \
    -x "*.DS_Store" \
    -x "*__MACOSX*" \
    > /dev/null 2>&1

if [ -f "$ZIP_FILE" ]; then
    FILE_SIZE=$(du -h "$ZIP_FILE" | cut -f1)
    echo -e "${GREEN}✓${NC} Plugin zipped successfully ($FILE_SIZE)"
else
    echo -e "${RED}✗${NC} Failed to create zip file"
    exit 1
fi

# Step 3: Upload to SFTP
echo -e "${YELLOW}[3/5]${NC} Uploading to WordPress site..."

LFTP_COMMANDS="
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$SFTP_USER:$SFTP_PASS@$SFTP_HOST:${SFTP_PORT:-2222}
cd $REMOTE_WP_PATH
put -O . $ZIP_FILE
bye
"

if echo "$LFTP_COMMANDS" | lftp > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Upload successful"
else
    echo -e "${RED}✗${NC} Upload failed"
    exit 1
fi

# Step 4: Extract on remote server
echo -e "${YELLOW}[4/5]${NC} Extracting plugin on remote server..."

EXTRACT_COMMANDS="
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$SFTP_USER:$SFTP_PASS@$SFTP_HOST:${SFTP_PORT:-2222}
cd $REMOTE_WP_PATH
!unzip -o ${PLUGIN_NAME}-v${VERSION}.zip
!rm ${PLUGIN_NAME}-v${VERSION}.zip
bye
"

# Note: SFTP doesn't support unzip directly, we'll need SSH for this
# For now, we'll provide manual instructions
echo -e "${YELLOW}⚠${NC}  Manual step required:"
echo ""
echo "Please SSH into your server and run:"
echo ""
echo -e "${BLUE}cd $REMOTE_WP_PATH${NC}"
echo -e "${BLUE}unzip -o ${PLUGIN_NAME}-v${VERSION}.zip${NC}"
echo -e "${BLUE}rm ${PLUGIN_NAME}-v${VERSION}.zip${NC}"
echo ""
echo "Or use FileZilla to extract the zip file on the server."
echo ""

# Step 5: Done
echo -e "${YELLOW}[5/5]${NC} Deployment complete!"
echo ""
echo -e "${GREEN}✓${NC} Plugin uploaded to: $SFTP_HOST"
echo -e "${GREEN}✓${NC} Remote path: $REMOTE_WP_PATH/${PLUGIN_NAME}-v${VERSION}.zip"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "1. Extract the zip file on your server (see instructions above)"
echo "2. Activate the plugin in WordPress admin"
echo "3. Run: ${BLUE}./watch-logs.sh${NC} to monitor logs in real-time"
echo ""
