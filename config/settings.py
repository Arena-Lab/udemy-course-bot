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
        'real_discount': os.getenv('ENABLE_REAL_DISCOUNT', 'true').lower() == 'true',
        'discudemy': os.getenv('ENABLE_DISCUDEMY', 'true').lower() == 'true',
        'udemy_freebies': os.getenv('ENABLE_UDEMY_FREEBIES', 'true').lower() == 'true',
        'yofreesamples': os.getenv('ENABLE_YOFREESAMPLES', 'true').lower() == 'true',
        'coursesity': os.getenv('ENABLE_COURSESITY', 'true').lower() == 'true',
        'tutorialbar': os.getenv('ENABLE_TUTORIALBAR', 'true').lower() == 'true',
        'freebiesglobal': os.getenv('ENABLE_FREEBIESGLOBAL', 'true').lower() == 'true'
    }
    
    # Request Settings
    REQUEST_TIMEOUT = int(os.getenv('REQUEST_TIMEOUT', 30))
    REQUEST_DELAY = int(os.getenv('REQUEST_DELAY', 2))
    USER_AGENT = os.getenv('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
    
    # Telegram Rate Limiting Settings
    TELEGRAM_POST_DELAY = int(os.getenv('TELEGRAM_POST_DELAY', 3))
    TELEGRAM_MAX_POSTS_PER_MINUTE = int(os.getenv('TELEGRAM_MAX_POSTS_PER_MINUTE', 20))
    TELEGRAM_BURST_LIMIT = int(os.getenv('TELEGRAM_BURST_LIMIT', 5))
    TELEGRAM_BURST_DELAY = int(os.getenv('TELEGRAM_BURST_DELAY', 10))
    
    # Database Configuration
    DATABASE_FILE = os.getenv('DATABASE_FILE', 'courses.db')
    BACKUP_INTERVAL = int(os.getenv('BACKUP_INTERVAL', 24))
    
    # Logging Configuration
    LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')
    LOG_FILE = os.getenv('LOG_FILE', 'logs/bot.log')
    MAX_LOG_SIZE = int(os.getenv('MAX_LOG_SIZE', 10))
    LOG_BACKUP_COUNT = int(os.getenv('LOG_BACKUP_COUNT', 5))
    
    # Message Formatting Options
    MESSAGE_OPTIONS = {
        'include_rating': os.getenv('INCLUDE_COURSE_RATING', 'true').lower() == 'true',
        'include_students': os.getenv('INCLUDE_STUDENT_COUNT', 'true').lower() == 'true',
        'include_duration': os.getenv('INCLUDE_COURSE_DURATION', 'true').lower() == 'true',
        'include_language': os.getenv('INCLUDE_LANGUAGE', 'true').lower() == 'true',
        'include_last_updated': os.getenv('INCLUDE_LAST_UPDATED', 'true').lower() == 'true'
    }
    
    # Advanced Settings
    ENABLE_WEBHOOKS = os.getenv('ENABLE_WEBHOOKS', 'false').lower() == 'true'
    WEBHOOK_URL = os.getenv('WEBHOOK_URL', '')
    ENABLE_ANALYTICS = os.getenv('ENABLE_ANALYTICS', 'true').lower() == 'true'
    ENABLE_ERROR_NOTIFICATIONS = os.getenv('ENABLE_ERROR_NOTIFICATIONS', 'true').lower() == 'true'
    
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

# Website URLs for scraping
SCRAPER_URLS = {
    'real_discount': 'https://www.real.discount/',
    'discudemy': 'https://www.discudemy.com/',
    'udemy_freebies': 'https://www.udemyfreebies.com/',
    'yofreesamples': 'https://yofreesamples.com/courses/free-discounted-udemy-courses-list/',
    'coursesity': 'https://coursesity.com/provider/free/udemy-courses'
}

# Course categories for filtering
COURSE_CATEGORIES = [
    'Development', 'Business', 'Finance & Accounting', 'IT & Software',
    'Office Productivity', 'Personal Development', 'Design', 'Marketing',
    'Lifestyle', 'Photography & Video', 'Health & Fitness', 'Music',
    'Teaching & Academics'
]
