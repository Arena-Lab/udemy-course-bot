"""
Logging configuration for the bot
"""
import logging
import logging.handlers
import os
from pathlib import Path
import sys
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from config.settings import Config

def setup_logging():
    """Setup logging configuration"""
    
    # Create logs directory if it doesn't exist
    log_dir = Path(Config.LOG_FILE).parent
    log_dir.mkdir(exist_ok=True)
    
    # Create logger
    logger = logging.getLogger()
    logger.setLevel(getattr(logging, Config.LOG_LEVEL.upper(), logging.INFO))
    
    # Clear existing handlers
    logger.handlers.clear()
    
    # Create formatters
    detailed_formatter = logging.Formatter(
        '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    simple_formatter = logging.Formatter(
        '%(levelname)s - %(message)s'
    )
    
    # File handler with rotation (UTF-8)
    file_handler = logging.handlers.RotatingFileHandler(
        Config.LOG_FILE,
        maxBytes=Config.MAX_LOG_SIZE * 1024 * 1024,  # Convert MB to bytes
        backupCount=Config.LOG_BACKUP_COUNT,
        encoding='utf-8'
    )
    file_handler.setFormatter(detailed_formatter)
    file_handler.setLevel(logging.DEBUG)
    
    # Console handler (try force UTF-8 on Windows console)
    try:
        # Python 3.7+: reconfigure stdout to utf-8 if supported
        if hasattr(sys.stdout, 'reconfigure'):
            sys.stdout.reconfigure(encoding='utf-8')
    except Exception:
        pass

    class _ConsoleNoiseFilter(logging.Filter):
        def filter(self, record: logging.LogRecord) -> bool:
            msg = str(record.getMessage())
            # Drop polling/http chatter in console only; file handler remains verbose
            drop_substrings = [
                'HTTP Request:',
                '/getUpdates',
                '/getMe',
                '/deleteWebhook',
            ]
            return not any(sub in msg for sub in drop_substrings)

    console_handler = logging.StreamHandler(stream=sys.stdout)
    console_handler.setFormatter(simple_formatter)
    # Console verbosity independent from file logging level
    console_handler.setLevel(getattr(logging, getattr(Config, 'CONSOLE_LOG_LEVEL', 'WARNING').upper(), logging.WARNING))
    console_handler.addFilter(_ConsoleNoiseFilter())
    
    # Add handlers to logger
    logger.addHandler(file_handler)
    logger.addHandler(console_handler)
    
    # Set specific loggers to appropriate levels
    logging.getLogger('requests').setLevel(logging.WARNING)
    logging.getLogger('urllib3').setLevel(logging.WARNING)
    logging.getLogger('httpx').setLevel(logging.WARNING)
    logging.getLogger('aiohttp').setLevel(logging.WARNING)
    # Suppress python-telegram-bot HTTP request noise in console
    for name in [
        'telegram',
        'telegram.bot',
        'telegram.request',
        'telegram._http',
        'telegram.ext._application',
        'telegram.ext._updater',
        'telegram.ext.jobqueue',
        'telegram.ext.dispatcher',
    ]:
        lg = logging.getLogger(name)
        lg.setLevel(logging.CRITICAL + 1)  # effectively disable
        lg.propagate = False
    
    return logger
