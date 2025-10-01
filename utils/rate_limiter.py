"""
Telegram Rate Limiter to avoid FloodWait errors
"""
import time
import asyncio
from collections import deque
from typing import Optional
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from config.settings import Config
import logging

logger = logging.getLogger(__name__)

class TelegramRateLimiter:
    """
    Rate limiter for Telegram Bot API to avoid FloodWait errors
    
    Based on Telegram documentation:
    - 30 messages per second across all chats
    - 1 message per second to same chat recommended
    - 20-25 messages per minute for channels is safe
    """
    
    def __init__(self):
        self.post_delay = Config.TELEGRAM_POST_DELAY
        self.max_posts_per_minute = Config.TELEGRAM_MAX_POSTS_PER_MINUTE
        self.burst_limit = Config.TELEGRAM_BURST_LIMIT
        self.burst_delay = Config.TELEGRAM_BURST_DELAY
        
        # Track message timestamps
        self.message_times = deque()
        self.last_post_time = 0
        self.consecutive_posts = 0
        
        logger.info(f"Rate limiter initialized: {self.post_delay}s delay, {self.max_posts_per_minute} posts/min max")
    
    async def wait_if_needed(self) -> float:
        """
        Wait if needed to respect rate limits
        Returns the actual delay time used
        """
        current_time = time.time()
        
        # Clean old timestamps (older than 1 minute)
        minute_ago = current_time - 60
        while self.message_times and self.message_times[0] < minute_ago:
            self.message_times.popleft()
        
        # Check if we've hit the per-minute limit
        if len(self.message_times) >= self.max_posts_per_minute:
            # Wait until the oldest message is more than a minute old
            wait_time = 60 - (current_time - self.message_times[0]) + 1
            logger.warning(f"Rate limit reached ({len(self.message_times)} posts/min). Waiting {wait_time:.1f}s")
            await asyncio.sleep(wait_time)
            current_time = time.time()
        
        # Check burst limit
        if self.consecutive_posts >= self.burst_limit:
            logger.info(f"Burst limit reached ({self.consecutive_posts} consecutive posts). Waiting {self.burst_delay}s")
            await asyncio.sleep(self.burst_delay)
            self.consecutive_posts = 0
            current_time = time.time()
        
        # Check minimum delay between posts
        time_since_last = current_time - self.last_post_time
        if time_since_last < self.post_delay:
            wait_time = self.post_delay - time_since_last
            logger.debug(f"Minimum delay not met. Waiting {wait_time:.1f}s")
            await asyncio.sleep(wait_time)
            current_time = time.time()
        
        # Record this message
        self.message_times.append(current_time)
        self.last_post_time = current_time
        self.consecutive_posts += 1
        
        return current_time - time.time() if current_time != time.time() else 0
    
    def reset_burst_counter(self):
        """Reset the burst counter (call after a longer pause)"""
        self.consecutive_posts = 0
    
    def get_stats(self) -> dict:
        """Get current rate limiter statistics"""
        current_time = time.time()
        
        # Clean old timestamps
        minute_ago = current_time - 60
        while self.message_times and self.message_times[0] < minute_ago:
            self.message_times.popleft()
        
        return {
            'posts_last_minute': len(self.message_times),
            'max_posts_per_minute': self.max_posts_per_minute,
            'consecutive_posts': self.consecutive_posts,
            'burst_limit': self.burst_limit,
            'time_since_last_post': current_time - self.last_post_time,
            'min_delay': self.post_delay
        }
    
    def update_settings(self, post_delay: Optional[int] = None, 
                       max_posts_per_minute: Optional[int] = None,
                       burst_limit: Optional[int] = None,
                       burst_delay: Optional[int] = None):
        """Update rate limiter settings"""
        if post_delay is not None:
            self.post_delay = post_delay
        if max_posts_per_minute is not None:
            self.max_posts_per_minute = max_posts_per_minute
        if burst_limit is not None:
            self.burst_limit = burst_limit
        if burst_delay is not None:
            self.burst_delay = burst_delay
            
        logger.info(f"Rate limiter settings updated: {self.post_delay}s delay, {self.max_posts_per_minute} posts/min max")
