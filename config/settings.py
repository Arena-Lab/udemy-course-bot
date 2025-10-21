"""
Configuration settings for the Udemy Course Bot
"""
import os
from dotenv import load_dotenv
from pathlib import Path

# Load environment variables
load_dotenv()

class Config:
    """Main configuration class"""
    
    # Telegram Configuration
    TELEGRAM_BOT_TOKEN = os.getenv('TELEGRAM_BOT_TOKEN')
    TELEGRAM_CHANNEL_ID = os.getenv('TELEGRAM_CHANNEL_ID')
    TELEGRAM_ADMIN_ID = os.getenv('TELEGRAM_ADMIN_ID')
    
    # Bot Branding
    BOT_NAME = os.getenv('BOT_NAME', 'Free Course Hunter')
    BOT_LOGO_EMOJI = os.getenv('BOT_LOGO_EMOJI', '🎓')
    CHANNEL_NAME = os.getenv('CHANNEL_NAME', 'Your Channel Name')
    CHANNEL_LINK = os.getenv('CHANNEL_LINK', 'https://t.me/your_channel')
    SECRET_CHANNEL_TEXT = os.getenv('SECRET_CHANNEL_TEXT', '🔥Secret channel, Join Now or Regret later')
    INVITE_FRIENDS_TEXT = os.getenv('INVITE_FRIENDS_TEXT', '🔶 Invite Friends➤')
    
    # Scraping Configuration
    SCRAPING_INTERVAL = int(os.getenv('SCRAPING_INTERVAL', 300))
    MAX_COURSES_PER_RUN = int(os.getenv('MAX_COURSES_PER_RUN', 10))
    ENABLE_DUPLICATE_DETECTION = os.getenv('ENABLE_DUPLICATE_DETECTION', 'true').lower() == 'true'
    COURSE_EXPIRY_HOURS = int(os.getenv('COURSE_EXPIRY_HOURS', 48))
    
    # Website Scraping Toggles
    SCRAPERS = {
        'discudemy': os.getenv('ENABLE_DISCUDEMY', 'true').lower() == 'true'
    }
    
    # Request Settings
    REQUEST_TIMEOUT = int(os.getenv('REQUEST_TIMEOUT', 30))
    USER_AGENT = os.getenv('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
    # Udemy metadata fetch options
    UDEMY_COOKIES_FILE = os.getenv('UDEMY_COOKIES_FILE', '')

    # DiscUdemy fresh rotation settings only (backfill removed)
    VALIDATION_TIMEOUT = int(os.getenv('VALIDATION_TIMEOUT', 8))
    DISCUDEMY_FRESH_SLICE = int(os.getenv('DISCUDEMY_FRESH_SLICE', 3))
    DISCUDEMY_FRESH_PAGES = max(1, min(int(os.getenv('DISCUDEMY_FRESH_PAGES', 10)), 60))
    
    # Optional diagnostics (write JSON per-run summaries instead of console spam)
    DIAG_ENABLE_DISCUDEMY = os.getenv('DIAG_ENABLE_DISCUDEMY', 'false').lower() == 'true'
    DIAG_DIR = os.getenv('DIAG_DIR', 'logs/diagnostics')
    DIAG_ENABLE_RUN_SUMMARY = os.getenv('DIAG_ENABLE_RUN_SUMMARY', 'false').lower() == 'true'
    # If set, rows with empty source_website will be treated/migrated as this source name
    DEFAULT_SOURCE_FOR_EMPTY = os.getenv('DEFAULT_SOURCE_FOR_EMPTY', '').strip()
 
    # Telegram Rate Limiting Settings
    TELEGRAM_POST_DELAY = int(os.getenv('TELEGRAM_POST_DELAY', 3))
    TELEGRAM_MAX_POSTS_PER_MINUTE = int(os.getenv('TELEGRAM_MAX_POSTS_PER_MINUTE', 20))
    TELEGRAM_BURST_LIMIT = int(os.getenv('TELEGRAM_BURST_LIMIT', 5))
    TELEGRAM_BURST_DELAY = int(os.getenv('TELEGRAM_BURST_DELAY', 10))
    # Cap how many items are posted per scheduled run (drip-feed)
    POSTS_PER_RUN = int(os.getenv('POSTS_PER_RUN', 12))
    # Decoupled scheduler intervals (seconds)
    SCRAPE_JOB_INTERVAL = int(os.getenv('SCRAPE_JOB_INTERVAL', os.getenv('SCRAPING_INTERVAL', 120)))
    POST_JOB_INTERVAL = int(os.getenv('POST_JOB_INTERVAL', 60))
    
    # Database Configuration
    DATABASE_FILE = os.getenv('DATABASE_FILE', 'courses.db')
    DB_RETENTION_DAYS = int(os.getenv('DB_RETENTION_DAYS', 30))  # hard-delete inactive rows older than this
    
    # Logging Configuration
    LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')
    LOG_FILE = os.getenv('LOG_FILE', 'logs/bot.log')
    MAX_LOG_SIZE = int(os.getenv('MAX_LOG_SIZE', 10))
    LOG_BACKUP_COUNT = int(os.getenv('LOG_BACKUP_COUNT', 5))
    # Console-only verbosity (independent from file LOG_LEVEL)
    CONSOLE_LOG_LEVEL = os.getenv('CONSOLE_LOG_LEVEL', 'WARNING')
    
    # Message Formatting Options
    MESSAGE_OPTIONS = {
        'include_rating': os.getenv('INCLUDE_COURSE_RATING', 'true').lower() == 'true',
        'include_students': os.getenv('INCLUDE_STUDENT_COUNT', 'true').lower() == 'true',
        'include_duration': os.getenv('INCLUDE_COURSE_DURATION', 'true').lower() == 'true',
        'include_language': os.getenv('INCLUDE_LANGUAGE', 'true').lower() == 'true',
        'include_last_updated': os.getenv('INCLUDE_LAST_UPDATED', 'true').lower() == 'true'
    }
    
    # Advanced Settings (removed unused webhook/analytics toggles)
    
    
    @classmethod
    def validate_config(cls):
        """Validate required configuration"""
        required_fields = ['TELEGRAM_BOT_TOKEN', 'TELEGRAM_CHANNEL_ID']
        missing_fields = []
        
        for field in required_fields:
            if not getattr(cls, field):
                missing_fields.append(field)
        
        if missing_fields:
            raise ValueError(f"Missing required configuration: {', '.join(missing_fields)}")
        
        return True

 
