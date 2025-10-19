# 🚀 Deployment Guide

## GitHub Repository Setup ✅ COMPLETED

### 1. Repository Created ✅
- Repository: https://github.com/Arena-Lab/udemy-course-bot
- Code successfully pushed to main branch
- All features and workflows are ready

### 2. Code Deployed ✅
```bash
✅ Code pushed to Arena-Lab/udemy-course-bot successfully
✅ GitHub Actions workflow configured for VPS deployment
✅ All bot features and admin controls ready
```

## VPS Deployment Configuration

### 3. Configure GitHub Secrets
Go to your repository → Settings → Secrets and variables → Actions → New repository secret

Add these secrets:

**VPS_HOST**
```
167.160.188.223
```

**VPS_USER**
```
root
```

**VPS_PASS**
```
taqLg4P838G2Ny2PHb
```

**BOT_ENV** (Complete .env file content)
```
# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=YOUR_BOT_TOKEN_HERE
TELEGRAM_CHANNEL_ID=@your_channel_or_-chat_id
TELEGRAM_ADMIN_ID=YOUR_ADMIN_USER_ID

# Bot Branding & Customization
BOT_NAME=Free Course Hunter
BOT_LOGO_EMOJI=🎓
CHANNEL_NAME=UdemyZap
CHANNEL_LINK=https://t.me/udemyzap
INVITE_FRIENDS_TEXT=👥 Invite friends:

# Scraping Configuration
SCRAPE_JOB_INTERVAL=180
POST_JOB_INTERVAL=60
MAX_COURSES_PER_RUN=60
ENABLE_DUPLICATE_DETECTION=true
COURSE_EXPIRY_HOURS=72
POSTS_PER_RUN=12

# Website Scraping Settings
ENABLE_DISCUDEMY=true

# Request Settings
REQUEST_TIMEOUT=30
REQUEST_DELAY=4
USER_AGENT=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36

# Telegram Rate Limiting Settings
TELEGRAM_POST_DELAY=5
TELEGRAM_MAX_POSTS_PER_MINUTE=20
TELEGRAM_BURST_LIMIT=3
TELEGRAM_BURST_DELAY=10

# Database Configuration
DATABASE_FILE=courses.db
DB_RETENTION_DAYS=15

# Logging Configuration
LOG_LEVEL=INFO
LOG_FILE=logs/bot.log
MAX_LOG_SIZE=10
LOG_BACKUP_COUNT=5
CONSOLE_LOG_LEVEL=INFO

# Message Formatting
INCLUDE_COURSE_RATING=true
INCLUDE_STUDENT_COUNT=true
INCLUDE_COURSE_DURATION=true
INCLUDE_LANGUAGE=true
INCLUDE_LAST_UPDATED=true

# DiscUdemy Settings
DISCUDEMY_FRESH_SLICE=3
DISCUDEMY_FRESH_PAGES=60
DISCUDEMY_VALIDATE_DEEP=false

# Advanced Settings
ENABLE_WEBHOOKS=false
ENABLE_ANALYTICS=true
ENABLE_ERROR_NOTIFICATIONS=true

# Diagnostics
DIAG_ENABLE_DISCUDEMY=true
DIAG_ENABLE_RUN_SUMMARY=true
DIAG_DIR=logs/diagnostics
DEFAULT_SOURCE_FOR_EMPTY=discudemy

# QuickTrends Integration
QUICKTRENDS_BASE_URL=https://quicktrends.in

# Website Environment
QT_AADS_ENABLED=true
QT_SITE_URL=https://quicktrends.in
QT_ADMIN_PASSWORD=ChangeThisNow123!
QT_AADS_728x90=YOUR_TOP_BANNER_UNIT_ID
QT_AADS_300x250=YOUR_RECTANGLE_UNIT_ID
```

### 4. Deploy to VPS
Once you push to the `main` branch, GitHub Actions will automatically:
- Connect to your VPS (167.160.188.223)
- Clone/update the code to `/opt/udemybot`
- Install Python dependencies
- Create systemd service `udemybot.service`
- Start the bot for 24/7 operation

## Manual VPS Setup (Alternative)

If you prefer manual setup:

```bash
# Connect to VPS
ssh root@167.160.188.223

# Clone repository
git clone https://github.com/Maneet106/udemy-course-bot.git /opt/udemybot
cd /opt/udemybot

# Create virtual environment
python3 -m venv .venv
source .venv/bin/activate

# Install dependencies
pip install -r requirements.txt

# Create .env file
nano .env
# (paste your bot configuration)

# Create systemd service
cat > /etc/systemd/system/udemybot.service << 'EOF'
[Unit]
Description=Udemy Course Bot
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/udemybot
EnvironmentFile=/opt/udemybot/.env
ExecStart=/opt/udemybot/.venv/bin/python /opt/udemybot/main.py
Restart=always
RestartSec=5
User=root

[Install]
WantedBy=multi-user.target
EOF

# Enable and start service
systemctl daemon-reload
systemctl enable --now udemybot

# Check status
systemctl status udemybot
journalctl -u udemybot -f
```

## Bot Commands Reference

### Admin Panel
- `/panel` - Interactive control panel with buttons
- `/help` - Comprehensive command reference

### Scheduling Controls
- `/schedule posts 15` - Set posts per run
- `/schedule scrape_interval 300` - Set scrape frequency (seconds)
- `/schedule post_interval 120` - Set post frequency (seconds)

### Runtime Controls
- `/shoot` - Resume posting
- `/hold` - Pause posting
- `/restart` - Restart bot

### Monitoring
- `/status` - Bot health and statistics
- `/stats` - Detailed metrics

## Verification Steps

1. **GitHub**: Verify code is pushed and Actions run successfully
2. **VPS**: Check `systemctl status udemybot`
3. **Telegram**: Send `/start` to your bot
4. **Admin**: Use `/panel` and `/status` commands
5. **Posting**: Use `/shoot` to start posting, `/schedule` to adjust intervals

## Troubleshooting

### Bot not starting
```bash
journalctl -u udemybot -f
```

### Check logs
```bash
tail -f /opt/udemybot/logs/bot.log
```

### Restart service
```bash
systemctl restart udemybot
```

### Update code
Push to GitHub main branch, or manually:
```bash
cd /opt/udemybot
git pull origin main
systemctl restart udemybot
```
