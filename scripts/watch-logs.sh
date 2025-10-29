#!/bin/bash

###############################################################################
# Finix Plugin Log Monitor
#
# This script monitors the finix-debug.log file on your remote server
# in real-time via SFTP.
#
# Usage: ./watch-logs.sh [lines]
#   lines: Number of recent lines to fetch (default: 50)
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
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LINES=${1:-50}  # Default to last 50 lines

# Load SFTP configuration
CONFIG_FILE="$SCRIPT_DIR/sftp-config.sh"

if [ ! -f "$CONFIG_FILE" ]; then
    echo -e "${RED}Error: SFTP configuration file not found!${NC}"
    echo ""
    echo "Please create: $CONFIG_FILE"
    exit 1
fi

source "$CONFIG_FILE"

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

# Remote log file path (in wp-content/uploads/)
REMOTE_LOG_PATH="${REMOTE_UPLOADS_PATH:-/www/wp-content/uploads}/finix-debug.log"
LOCAL_LOG_FILE="/tmp/finix-debug.log"

echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Finix Plugin Log Monitor v1.0           ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${CYAN}Monitoring:${NC} $REMOTE_LOG_PATH"
echo -e "${CYAN}Refresh:${NC} Every 5 seconds"
echo -e "${CYAN}Lines:${NC} Last $LINES lines"
echo ""
echo -e "${YELLOW}Press Ctrl+C to stop monitoring${NC}"
echo ""
echo -e "${BLUE}════════════════════════════════════════════${NC}"
echo ""

# Function to download and display log
fetch_log() {
    # Download log file from remote server
    LFTP_COMMANDS="
    set sftp:auto-confirm yes
    set ssl:verify-certificate no
    open sftp://$SFTP_USER:$SFTP_PASS@$SFTP_HOST:${SFTP_PORT:-2222}
    get -O /tmp $REMOTE_LOG_PATH
    bye
    "

    if echo "$LFTP_COMMANDS" | lftp > /dev/null 2>&1; then
        if [ -f "$LOCAL_LOG_FILE" ]; then
            # Display last N lines with syntax highlighting
            tail -n "$LINES" "$LOCAL_LOG_FILE" | while IFS= read -r line; do
                # Color-code log levels
                if [[ $line == *"[ERROR]"* ]]; then
                    echo -e "${RED}$line${NC}"
                elif [[ $line == *"[WARN]"* ]] || [[ $line == *"[JS-WARN]"* ]]; then
                    echo -e "${YELLOW}$line${NC}"
                elif [[ $line == *"[API]"* ]]; then
                    echo -e "${CYAN}$line${NC}"
                elif [[ $line == *"[JS-ERROR]"* ]]; then
                    echo -e "${RED}$line${NC}"
                elif [[ $line == *"[JS-LOG]"* ]]; then
                    echo -e "${GREEN}$line${NC}"
                else
                    echo "$line"
                fi
            done

            # Show file size
            FILE_SIZE=$(du -h "$LOCAL_LOG_FILE" | cut -f1)
            echo ""
            echo -e "${BLUE}────────────────────────────────────────────${NC}"
            echo -e "${CYAN}Log size:${NC} $FILE_SIZE  |  ${CYAN}Last updated:${NC} $(date '+%H:%M:%S')"
        else
            echo -e "${YELLOW}⚠ Log file not found on server${NC}"
            echo ""
            echo "Make sure:"
            echo "1. Debug mode is enabled in Finix gateway settings"
            echo "2. You've visited the checkout page to generate logs"
            echo "3. Remote log path is correct: $REMOTE_LOG_PATH"
        fi
    else
        echo -e "${RED}✗ Failed to connect to server${NC}"
    fi
}

# Initial fetch
fetch_log

# Watch mode - refresh every 5 seconds
echo ""
echo -e "${YELLOW}Watching for changes...${NC}"
echo ""

while true; do
    sleep 5
    clear
    echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║   Finix Plugin Log Monitor v1.0           ║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
    echo ""
    fetch_log
done
