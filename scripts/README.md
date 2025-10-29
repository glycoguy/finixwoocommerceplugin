# Finix Testing Scripts - Quick Reference

## 🚀 One-Time Setup (5 minutes)

```bash
# 1. Install lftp
sudo apt-get install lftp  # Ubuntu/Debian
brew install lftp           # macOS

# 2. Configure SFTP
cp sftp-config.sh.example sftp-config.sh
nano sftp-config.sh  # Add your Flywheel credentials

# 3. Make executable
chmod +x *.sh
```

## ⚡ Quick Commands

### Full Workflow (Recommended)
```bash
./deploy-and-monitor.sh
```
Deploys plugin, then starts log monitoring.

### Deploy Only
```bash
./deploy.sh
```
Just uploads the plugin zip to your server.

### Monitor Logs Only
```bash
./watch-logs.sh       # Last 50 lines
./watch-logs.sh 100   # Last 100 lines
```
Watches logs in real-time (refreshes every 5s).

## 📂 Files

- `deploy.sh` - Zips and uploads plugin to WordPress
- `watch-logs.sh` - Monitors finix-debug.log in real-time
- `deploy-and-monitor.sh` - Combined workflow
- `sftp-config.sh.example` - Template for SFTP credentials
- `sftp-config.sh` - Your actual credentials (gitignored)

## 🔗 Links

- Full documentation: `../TESTING-WORKFLOW.md`
- Plugin code: `../current-plugin/finix-v1.8.0/`
- Logger class: `../current-plugin/finix-v1.8.0/includes/class-finix-logger.php`

## 💡 Pro Tips

1. **Run monitor in split terminal** - Keep it open while testing
2. **Use private browser** - Avoids cache issues
3. **Enable debug mode first** - Before deploying
4. **Watch colors** - Red=errors, Yellow=warnings, Cyan=API calls

## 🆘 Common Issues

| Problem | Solution |
|---------|----------|
| "lftp not found" | `sudo apt-get install lftp` |
| "Connection failed" | Check `sftp-config.sh` credentials |
| "Permission denied" | `chmod +x *.sh` |
| "Log not found" | Enable debug mode in gateway settings |

---

**See `../TESTING-WORKFLOW.md` for complete guide**
