# Finix Plugin - Automated Testing Workflow

This guide explains the new automated testing workflow that **dramatically speeds up** your development and testing cycle.

## 🚀 What's New

Previously, testing required:
- ⏱️ Manually zipping the plugin
- ⏱️ Uploading via FileZilla
- ⏱️ Manually downloading wp-debug logs (if they didn't crash your site)
- ⏱️ Copy/pasting console logs from browser
- ⏱️ **Total time: 5-10 minutes per test cycle**

Now, you can:
- ✨ Deploy with a single command
- ✨ Monitor logs in real-time from your terminal
- ✨ No more wp-debug memory crashes
- ✨ Automatic capture of JavaScript console errors
- ✨ **Total time: ~30 seconds per test cycle**

---

## 📋 Prerequisites

### 1. Install `lftp` (one-time setup)

```bash
# Ubuntu/Debian
sudo apt-get install lftp

# macOS
brew install lftp
```

### 2. Configure SFTP Credentials (one-time setup)

```bash
# Copy the template
cd scripts
cp sftp-config.sh.example sftp-config.sh

# Edit with your Flywheel credentials
nano sftp-config.sh
```

**How to get Flywheel SFTP credentials:**

1. Log into Flywheel (getflywheel.com)
2. Click on your site
3. Go to **SFTP/SSH** tab
4. Copy:
   - Host (e.g., `yoursite.flywheelsites.com`)
   - Username (e.g., `yoursite-12345`)
   - Password (click "Show")
   - Port (usually `2222`)

5. Paste into `sftp-config.sh`:

```bash
SFTP_HOST="yoursite.flywheelsites.com"
SFTP_USER="yoursite-12345"
SFTP_PASS="your-password-here"
SFTP_PORT="2222"
REMOTE_WP_PATH="/www/wp-content/plugins"
REMOTE_UPLOADS_PATH="/www/wp-content/uploads"
```

### 3. Make Scripts Executable (one-time setup)

```bash
chmod +x scripts/*.sh
```

---

## 🎯 Quick Start - Testing Workflow

### Option A: Deploy & Monitor (Recommended)

This is the **fastest way** to test changes:

```bash
./scripts/deploy-and-monitor.sh
```

This will:
1. ✅ Zip your plugin
2. ✅ Upload to Flywheel via SFTP
3. ⏸️ Wait for you to extract/activate
4. ✅ Start monitoring logs in real-time

**Then you:**
1. SSH into server and extract the zip (or use FileZilla)
2. Go to WordPress admin and activate/update the plugin
3. Enable **Debug Mode** in: WooCommerce → Settings → Payments → Finix Card Gateway
4. Open a private browser and test checkout
5. Watch errors appear **in real-time** in your terminal

### Option B: Deploy Only

```bash
./scripts/deploy.sh
```

Uploads the plugin to your server. You'll need to extract manually.

### Option C: Monitor Logs Only

```bash
./scripts/watch-logs.sh [lines]
```

- `lines`: Number of recent lines to show (default: 50)
- Example: `./scripts/watch-logs.sh 100` shows last 100 lines
- Refreshes every 5 seconds automatically
- Color-coded by log level (red=errors, yellow=warnings, cyan=API calls)

---

## 🔧 How It Works

### Custom Logger (No More wp-debug Crashes!)

The plugin now includes a **custom logging system** that:

- ✅ Writes to a separate file: `/wp-content/uploads/finix-debug.log`
- ✅ **Bypasses wp-debug.log** (no more memory errors from other plugins!)
- ✅ Captures **both PHP errors AND JavaScript console messages**
- ✅ Auto-rotates when file gets too large (5MB limit)
- ✅ Can be toggled on/off from plugin settings

**Enable it:**
1. Go to: WooCommerce → Settings → Payments → Finix Card Gateway
2. Check: **"Enable custom debug logging"**
3. Save changes

**View logs:**
- In WordPress admin: WooCommerce → **Finix Debug Log**
- From terminal: `./scripts/watch-logs.sh`
- Via SFTP: `/wp-content/uploads/finix-debug.log`

### JavaScript Console Capture

The logger automatically captures JavaScript errors from the checkout page:

```javascript
// These console messages are automatically sent to the server log:
console.log("Finix payment initialized");  // Logged
console.error("Finix API error:", error);  // Logged
console.warn("Finix validation warning");  // Logged
```

**Only Finix-related messages are logged** (filters by keyword "finix").

---

## 📊 Log Viewer Features

### In Terminal (watch-logs.sh)

```bash
./scripts/watch-logs.sh 100
```

- Real-time updates (refreshes every 5s)
- Color-coded by severity:
  - 🔴 Red: Errors
  - 🟡 Yellow: Warnings
  - 🔵 Cyan: API calls
  - 🟢 Green: JavaScript logs
- Shows file size and last update time
- Press `Ctrl+C` to stop

### In WordPress Admin

Go to: **WooCommerce → Finix Debug Log**

- View/download logs from browser
- Filter by number of lines
- Auto-refresh toggle (5s intervals)
- Clear log button
- Shows log file size and path

---

## 🎨 Example Workflow

Here's a typical bug fix workflow:

```bash
# 1. Make code changes in your editor
vim current-plugin/finix-v1.8.0/includes/class-finix-api.php

# 2. Deploy and start monitoring
./scripts/deploy-and-monitor.sh

# 3. SSH into server (in another terminal)
ssh yoursite@yoursite.flywheelsites.com
cd /www/wp-content/plugins
unzip -o finix-woocommerce-subscriptions-v1.8.1.zip
exit

# 4. Activate plugin in WordPress admin

# 5. Enable Debug Mode in gateway settings

# 6. Test checkout in private browser
# Errors appear in real-time in your terminal!

# 7. See error, fix code, repeat
# Go back to step 1
```

**Testing time: ~30 seconds** (down from 5-10 minutes!)

---

## 🐛 Troubleshooting

### "lftp: command not found"

Install lftp:
```bash
sudo apt-get install lftp  # Ubuntu/Debian
brew install lftp           # macOS
```

### "SFTP connection failed"

Check your credentials in `sftp-config.sh`:
- Verify host, username, password
- Flywheel's SFTP port is usually `2222` (not 22)
- Test connection manually:
  ```bash
  lftp sftp://user:pass@host:2222
  ```

### "Log file not found on server"

Make sure:
1. Debug mode is enabled in gateway settings
2. You've visited checkout page at least once (to generate logs)
3. Remote uploads path is correct in `sftp-config.sh`

### "Permission denied" errors

The scripts need execute permissions:
```bash
chmod +x scripts/*.sh
```

### Logs show memory errors

The custom logger should **prevent** wp-debug memory issues. If you still see them:
1. Make sure you're using the **custom logger** (not wp-debug)
2. Check that `FINIX_WC_SUBS_VERSION` is `1.8.1` or higher
3. Verify debug mode is enabled in **Finix gateway settings** (not wp-config.php)

---

## 📝 Additional Features

### Clear Logs Remotely

```bash
# Download, clear, and re-upload
ssh yoursite@host "rm /www/wp-content/uploads/finix-debug.log"
```

### Download Logs for Analysis

```bash
# Download to local machine
lftp -e "get /www/wp-content/uploads/finix-debug.log; bye" \
  sftp://user:pass@host:2222
```

### Monitor Multiple Files

Edit `watch-logs.sh` to monitor additional log files (e.g., PHP error logs).

---

## 🎓 Tips

1. **Use two terminals**: One for deployment, one for monitoring
2. **Keep log monitor running**: Start it before testing, leave it open
3. **Use private browser**: Avoids cache issues
4. **Enable debug early**: Turn on debug mode before deploying
5. **Watch the logs**: Don't just test, watch the logs in real-time to catch issues early

---

## 📚 Next Steps

- Read: `current-plugin/finix-v1.8.0/includes/class-finix-logger.php` for logger implementation
- Customize: Edit scripts to match your specific workflow
- Extend: Add more automation (e.g., Puppeteer for browser testing)

---

## 🆘 Support

If you encounter issues:
1. Check the troubleshooting section above
2. Verify all prerequisites are installed
3. Test SFTP connection manually with FileZilla
4. Check that Flywheel SFTP credentials are correct

---

**Happy Testing! 🚀**
