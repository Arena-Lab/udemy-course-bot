"""
Logging configuration for the bot
"""
import logging
import logging.handlers
import os
from pathlib import Path
import sys
import os
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
    
    # File handler with rotation
    file_handler = logging.handlers.RotatingFileHandler(
        Config.LOG_FILE,
        maxBytes=Config.MAX_LOG_SIZE * 1024 * 1024,  # Convert MB to bytes
        backupCount=Config.LOG_BACKUP_COUNT
    )
    file_handler.setFormatter(detailed_formatter)
    file_handler.setLevel(logging.DEBUG)
    
    # Console handler
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(simple_formatter)
    console_handler.setLevel(getattr(logging, Config.LOG_LEVEL.upper(), logging.INFO))
    
    # Add handlers to logger
    logger.addHandler(file_handler)
    logger.addHandler(console_handler)
    
    # Set specific loggers to appropriate levels
    logging.getLogger('requests').setLevel(logging.WARNING)
    logging.getLogger('urllib3').setLevel(logging.WARNING)
    logging.getLogger('telegram').setLevel(logging.INFO)
    
    return logger
