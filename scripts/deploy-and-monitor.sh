#!/bin/bash

###############################################################################
# Finix Plugin Deploy & Monitor
#
# This script combines deployment and log monitoring:
# 1. Deploys the plugin to your WordPress site
# 2. Waits for you to test
# 3. Automatically starts monitoring logs
#
# Usage: ./deploy-and-monitor.sh
###############################################################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Finix Deploy & Monitor Workflow         ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo ""

# Step 1: Deploy the plugin
echo -e "${CYAN}Step 1: Deploying plugin...${NC}"
echo ""

bash "$SCRIPT_DIR/deploy.sh"

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Deployment failed${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}✓ Deployment complete!${NC}"
echo ""

# Step 2: Wait for user to extract and activate
echo -e "${CYAN}Step 2: Activate plugin on your site${NC}"
echo ""
echo "Please:"
echo "1. SSH into your server and extract the zip (or use FileZilla)"
echo "2. Activate/update the plugin in WordPress admin"
echo "3. Enable Debug Mode in: WooCommerce > Settings > Payments > Finix Card Gateway"
echo ""
read -p "Press Enter when you're ready to start testing..."
echo ""

# Step 3: Start log monitoring
echo -e "${CYAN}Step 3: Starting log monitor${NC}"
echo ""
echo "The log monitor will now start. Open a private browser window and:"
echo "1. Go to your checkout page"
echo "2. Add a subscription product to cart"
echo "3. Proceed to checkout"
echo "4. All Finix-related errors will appear below in real-time"
echo ""
read -p "Press Enter to start monitoring logs..."
echo ""

# Start log watcher
bash "$SCRIPT_DIR/watch-logs.sh" 100
