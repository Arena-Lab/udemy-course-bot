# 🎓 Udemy Course Bot

A professional Telegram bot that automatically scrapes free Udemy courses from multiple sources and posts them to your Telegram channel with beautiful formatting and branding.

## ✨ Features

- **Multi-Source Scraping**: Scrapes from 5 major coupon websites
- **Intelligent Duplicate Detection**: Prevents posting the same course multiple times
- **Professional Branding**: Consistent, beautiful message formatting
- **Fully Customizable**: Easy configuration via `.env` file
- **Admin Commands**: Full control via Telegram commands
- **Automatic Scheduling**: Runs continuously with configurable intervals
- **Error Handling**: Robust error handling with admin notifications
- **Statistics Tracking**: Detailed analytics and reporting
- **Database Management**: SQLite database with automatic cleanup

## 🌐 Supported Sources

1. **Real Discount** - Major aggregator with active monitoring
2. **DiscUdemy** - Zero-broken-link technology
3. **Udemy Freebies** - Popular free course source
4. **YoFreeSamples** - Course listings with coupons
5. **Coursesity** - 50,000+ free courses listed

## 🚀 Quick Start

### 1. Prerequisites

- Python 3.9 or higher
- Telegram Bot Token (from [@BotFather](https://t.me/botfather))
- Telegram Channel (where courses will be posted)

### 2. Installation

```bash
# Clone or download the project
cd udemy-course-bot

# Install dependencies
pip install -r requirements.txt
```

### 3. Configuration

1. Copy `.env` file and fill in your credentials:

```env
# Required Settings
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_CHANNEL_ID=@your_channel_username
TELEGRAM_ADMIN_ID=your_admin_user_id

# Customize Branding
BOT_NAME=Free Course Hunter
CHANNEL_NAME=Your Channel Name
CHANNEL_LINK=https://t.me/your_channel
```

### 4. Run the Bot

```bash
python main.py
```

## ⚙️ Configuration Options

### Basic Settings
- `BOT_NAME`: Your bot's display name
- `BOT_LOGO_EMOJI`: Emoji for branding (default: 🎓)
- `CHANNEL_NAME`: Your channel's name
- `CHANNEL_LINK`: Link to your channel

### Scraping Settings
- `SCRAPING_INTERVAL`: How often to scrape (seconds, default: 300)
- `MAX_COURSES_PER_RUN`: Maximum courses per scraping session
- `ENABLE_DUPLICATE_DETECTION`: Prevent duplicate posts (true/false)

### Source Control
Enable/disable specific scrapers:
- `ENABLE_REAL_DISCOUNT=true`
- `ENABLE_DISCUDEMY=true`
- `ENABLE_UDEMY_FREEBIES=true`
- `ENABLE_YOFREESAMPLES=true`
- `ENABLE_COURSESITY=true`

### Message Formatting
Customize what information to include:
- `INCLUDE_COURSE_RATING=true`
- `INCLUDE_STUDENT_COUNT=true`
- `INCLUDE_COURSE_DURATION=true`
- `INCLUDE_LANGUAGE=true`

## 🤖 Bot Commands

### Public Commands
- `/start` - Welcome message and help
- `/help` - Show available commands
- `/stats` - Display bot statistics
- `/status` - Show current bot status

### Admin Commands (require admin privileges)
- `/test` - Test all scrapers
- `/scrape` - Manual scraping session
- `/post` - Post pending courses immediately

## 📊 Message Format

The bot posts courses with this professional format:

```
🎓 Free Courses With Certificates!

(100% Free) Master Java, Python, C & C++: All-in-One Programming Course
👨‍🏫 Instructor: John Doe

⭐ Rating: 4.5/5 ⭐⭐⭐⭐⭐
👥 Students: 15.2K
⏱️ Duration: 8 hours
🌐 Language: English
📚 Category: Development

🔗 Enroll Link➤ https://udemy.com/course/...

🔥Secret channel, Join Now or Regret later
🔶 Invite Friends➤ https://t.me/your_channel
```

## 🗂️ Project Structure

```
udemy-course-bot/
├── main.py                 # Main bot application
├── config/
│   └── settings.py         # Configuration management
├── scrapers/
│   ├── base_scraper.py     # Base scraper class
│   ├── real_discount_scraper.py
│   ├── discudemy_scraper.py
│   ├── udemy_freebies_scraper.py
│   ├── yofreesamples_scraper.py
│   └── coursesity_scraper.py
├── utils/
│   ├── database.py         # Database operations
│   ├── message_formatter.py # Message formatting
│   ├── scraper_manager.py  # Scraper coordination
│   └── logger.py          # Logging setup
├── logs/                   # Log files
├── .env                    # Configuration file
├── requirements.txt        # Dependencies
└── README.md              # This file
```

## 🔧 Advanced Features

### Database Management
- Automatic duplicate detection using course hashes
- Expired course cleanup
- Statistics tracking
- Backup functionality

### Error Handling
- Robust request handling with retries
- Admin error notifications
- Graceful degradation when sources are down
- Comprehensive logging

### Scalability
- Concurrent scraping from multiple sources
- Rate limiting to avoid IP blocks
- Configurable delays and timeouts
- Memory-efficient processing

## 📈 Monitoring

### Statistics Available
- Total courses scraped
- Courses posted to channel
- Success rates per source
- Error counts and types
- Database statistics

### Logging
- Rotating log files
- Configurable log levels
- Separate error tracking
- Admin notifications for critical errors

## 🛡️ Security & Best Practices

### Rate Limiting
- Built-in delays between requests
- Randomized request timing
- Respectful scraping practices

### Error Recovery
- Automatic retry on failures
- Graceful handling of network issues
- Continue operation if one source fails

### Data Privacy
- No personal data collection
- Secure credential management
- Local database storage

## 🔄 Maintenance

### Regular Tasks
- Monitor log files for errors
- Check database size and performance
- Update scraper selectors if sites change
- Review and adjust scraping intervals

### Troubleshooting
- Check `.env` configuration
- Verify Telegram bot permissions
- Test individual scrapers with `/test` command
- Review logs in `logs/bot.log`

## 📝 License

This project is for educational purposes only. Please respect the terms of service of the websites being scraped and Telegram's API usage policies.

## 🤝 Contributing

1. Test the bot thoroughly before deployment
2. Monitor for any changes in source website structures
3. Keep dependencies updated for security
4. Follow ethical scraping practices

## 📞 Support

If you encounter issues:
1. Check the logs in `logs/bot.log`
2. Verify your `.env` configuration
3. Test scrapers individually with `/test`
4. Ensure your bot has proper channel permissions

---

**⚠️ Disclaimer**: This bot is for educational purposes. Always respect website terms of service and rate limits. The authors are not responsible for any misuse of this software.
