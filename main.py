"""
Main Udemy Course Bot - Automatically scrapes and posts free Udemy courses
"""
import asyncio
import logging
import signal
import sys
from datetime import datetime, timedelta
from typing import Optional

from telegram import Bot, Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application, CommandHandler, ContextTypes
from telegram.error import TelegramError

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from config.settings import Config
from utils.database import CourseDatabase
from utils.message_formatter import MessageFormatter
from utils.scraper_manager import ScraperManager
from utils.logger import setup_logging
from utils.rate_limiter import TelegramRateLimiter

# Setup logging
logger = setup_logging()

class UdemyCourseBot:
    """Main bot class for managing Udemy course scraping and posting"""
    def __init__(self):
        self.config = Config()
        self.db = CourseDatabase()
        self.formatter = MessageFormatter()
        self.scraper_manager = ScraperManager()
        self.rate_limiter = TelegramRateLimiter()
        self.application: Optional[Application] = None
        self.bot: Optional[Bot] = None
        self.is_running = False
        
    async def initialize(self):
        """Initialize the bot and validate configuration"""
        try:
            # Validate configuration
            Config.validate_config()
            
            # Initialize Telegram bot
            self.application = Application.builder().token(Config.TELEGRAM_BOT_TOKEN).build()
            self.bot = self.application.bot
            
            # Test bot connection
            bot_info = await self.bot.get_me()
            logger.info(f"Bot initialized successfully: @{bot_info.username}")
            
            # Setup command handlers
            self._setup_handlers()
            
            # Send startup message to admin
            if Config.TELEGRAM_ADMIN_ID:
                await self._send_admin_message("🚀 Bot started successfully!")
            
            return True
            
        except Exception as e:
            logger.error(f"Failed to initialize bot: {e}")
            return False
    
    def _setup_handlers(self):
        """Setup command handlers for the bot"""
        handlers = [
            CommandHandler("start", self.cmd_start),
            CommandHandler("help", self.cmd_help),
            CommandHandler("stats", self.cmd_stats),
            CommandHandler("test", self.cmd_test_scrapers),
            CommandHandler("scrape", self.cmd_manual_scrape),
            CommandHandler("post", self.cmd_post_pending),
            CommandHandler("status", self.cmd_status),
            CommandHandler("ratelimit", self.cmd_rate_limit)
        ]
        
        for handler in handlers:
            self.application.add_handler(handler)
    
    async def cmd_start(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Handle /start command"""
        message = f"""
🎓 <b>{Config.BOT_NAME}</b> - Welcome!

This bot automatically finds and posts free Udemy courses to your channel.

<b>Available Commands:</b>
/help - Show this help message
/stats - Show bot statistics  
/test - Test all scrapers
/scrape - Manual scrape (admin only)
/post - Post pending courses (admin only)
/status - Show bot status
/ratelimit - Adjust rate limiting (admin only)

<b>Channel:</b> {Config.CHANNEL_LINK}
        """
        await update.message.reply_text(message, parse_mode='HTML')
    
    async def cmd_help(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Handle /help command"""
        await self.cmd_start(update, context)
    
    async def cmd_stats(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Handle /stats command"""
        try:
            db_stats = self.db.get_statistics()
            scraper_stats = self.scraper_manager.get_scraper_statistics()
            
            stats_message = self.formatter.format_stats_message(db_stats)
            
            # Add scraper stats
            stats_message += "\n\n📊 <b>Scraper Statistics:</b>"
            for name, stats in scraper_stats.items():
                success_rate = stats.get('success_rate', 0)
                stats_message += f"\n  • {name}: {stats['courses_scraped']} courses ({success_rate:.1f}% success)"
            
            await update.message.reply_text(stats_message, parse_mode='HTML')
            
        except Exception as e:
            logger.error(f"Error in stats command: {e}")
            await update.message.reply_text("❌ Error retrieving statistics")
    
    async def cmd_test_scrapers(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Handle /test command"""
        if not self._is_admin(update.effective_user.id):
            await update.message.reply_text("❌ Admin access required")
            return
        
        try:
            await update.message.reply_text("🔄 Testing all scrapers...")
            
            test_results = self.scraper_manager.test_scrapers()
            
            message = "🧪 <b>Scraper Test Results:</b>\n\n"
            for name, result in test_results.items():
                status_emoji = "✅" if result['status'] == 'success' else "❌"
                message += f"{status_emoji} <b>{name}:</b> {result['courses_found']} courses\n"
                
                if result['status'] == 'error':
                    message += f"   Error: {result['error'][:100]}...\n"
            
            await update.message.reply_text(message, parse_mode='HTML')
            
        except Exception as e:
            logger.error(f"Error in test command: {e}")
            await update.message.reply_text("❌ Error testing scrapers")
    
    async def cmd_manual_scrape(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Handle /scrape command"""
        if not self._is_admin(update.effective_user.id):
            await update.message.reply_text("❌ Admin access required")
            return
        
        try:
            await update.message.reply_text("🔄 Starting manual scrape...")
            
            courses = await self._scrape_and_store_courses()
            
            message = f"✅ Manual scrape completed!\n\n"
            message += f"📚 <b>New courses found:</b> {len(courses)}\n"
            message += f"💾 <b>Total in database:</b> {self.db.get_statistics()['total_courses']}"
            
            await update.message.reply_text(message, parse_mode='HTML')
            
        except Exception as e:
            logger.error(f"Error in manual scrape: {e}")
            await update.message.reply_text("❌ Error during manual scrape")
    
    async def cmd_post_pending(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Handle /post command"""
        if not self._is_admin(update.effective_user.id):
            await update.message.reply_text("❌ Admin access required")
            return
        
        try:
            await update.message.reply_text("📤 Posting pending courses...")
            
            posted_count = await self._post_pending_courses()
            
            message = f"✅ Posting completed!\n\n📤 <b>Courses posted:</b> {posted_count}"
            await update.message.reply_text(message, parse_mode='HTML')
            
        except Exception as e:
            logger.error(f"Error in post command: {e}")
            await update.message.reply_text("❌ Error posting courses")
    
    async def cmd_status(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Handle /status command"""
        try:
            # Get rate limiter stats
            rate_stats = self.rate_limiter.get_stats()
            
            status_message = f"""
🤖 <b>{Config.BOT_NAME} Status</b>

<b>Status:</b> {'🟢 Running' if self.is_running else '🔴 Stopped'}
<b>Scrapers:</b> {len(self.scraper_manager.get_enabled_scrapers())} enabled
<b>Database:</b> {self.db.get_statistics()['total_courses']} total courses
<b>Pending:</b> {self.db.get_statistics()['pending_courses']} courses

<b>📊 Rate Limiter:</b>
• Posts last minute: {rate_stats['posts_last_minute']}/{rate_stats['max_posts_per_minute']}
• Consecutive posts: {rate_stats['consecutive_posts']}/{rate_stats['burst_limit']}
• Min delay: {rate_stats['min_delay']}s
• Time since last: {rate_stats['time_since_last_post']:.1f}s

<b>Last Update:</b> {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
            """
            
            await update.message.reply_text(status_message, parse_mode='HTML')
            
        except Exception as e:
            logger.error(f"Error in status command: {e}")
            await update.message.reply_text("❌ Error retrieving status")
    
    async def cmd_rate_limit(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Handle /ratelimit command - adjust rate limiting settings"""
        if not self._is_admin(update.effective_user.id):
            await update.message.reply_text("❌ Admin access required")
            return
        
        try:
            args = context.args
            if not args:
                # Show current settings
                rate_stats = self.rate_limiter.get_stats()
                message = f"""
🚦 <b>Rate Limiter Settings</b>

<b>Current Settings:</b>
• Post delay: {rate_stats['min_delay']}s
• Max posts/minute: {rate_stats['max_posts_per_minute']}
• Burst limit: {rate_stats['burst_limit']}

<b>Current Status:</b>
• Posts last minute: {rate_stats['posts_last_minute']}
• Consecutive posts: {rate_stats['consecutive_posts']}
• Time since last: {rate_stats['time_since_last_post']:.1f}s

<b>Usage:</b>
/ratelimit delay 5 - Set post delay to 5 seconds
/ratelimit maxpm 15 - Set max posts per minute to 15
/ratelimit burst 3 - Set burst limit to 3 posts
                """
                await update.message.reply_text(message, parse_mode='HTML')
                return
            
            # Parse arguments
            if len(args) >= 2:
                setting = args[0].lower()
                value = int(args[1])
                
                if setting == 'delay':
                    self.rate_limiter.update_settings(post_delay=value)
                    await update.message.reply_text(f"✅ Post delay set to {value} seconds")
                elif setting == 'maxpm':
                    self.rate_limiter.update_settings(max_posts_per_minute=value)
                    await update.message.reply_text(f"✅ Max posts per minute set to {value}")
                elif setting == 'burst':
                    self.rate_limiter.update_settings(burst_limit=value)
                    await update.message.reply_text(f"✅ Burst limit set to {value} posts")
                else:
                    await update.message.reply_text("❌ Invalid setting. Use: delay, maxpm, or burst")
            else:
                await update.message.reply_text("❌ Usage: /ratelimit <setting> <value>")
                
        except ValueError:
            await update.message.reply_text("❌ Invalid value. Please use numbers only.")
        except Exception as e:
            logger.error(f"Error in rate limit command: {e}")
            await update.message.reply_text("❌ Error adjusting rate limit settings")
    
    def _is_admin(self, user_id: int) -> bool:
        """Check if user is admin"""
        admin_id = str(Config.TELEGRAM_ADMIN_ID).strip()
        user_id_str = str(user_id).strip()
        logger.info(f"Admin check: user_id={user_id_str}, admin_id={admin_id}, match={user_id_str == admin_id}")
        return user_id_str == admin_id
    
    async def _send_admin_message(self, message: str):
        """Send message to admin"""
        if Config.TELEGRAM_ADMIN_ID and self.bot:
            try:
                await self.bot.send_message(
                    chat_id=Config.TELEGRAM_ADMIN_ID,
                    text=message,
                    parse_mode='HTML'
                )
            except Exception as e:
                logger.debug(f"Failed to send admin message: {e}")  # Changed to debug to reduce noise
    
    async def _scrape_and_store_courses(self) -> list:
        """Scrape courses from all sources and store in database"""
        try:
            logger.info("Starting course scraping from all sources")
            
            # Scrape from all enabled sources
            courses = self.scraper_manager.scrape_all_sources(
                limit_per_source=Config.MAX_COURSES_PER_RUN
            )
            
            # Store courses in database
            new_courses = []
            for course in courses:
                if self.db.add_course(course):
                    new_courses.append(course)
            
            logger.info(f"Scraped {len(courses)} courses, {len(new_courses)} new")
            
            # Cleanup expired courses
            self.db.cleanup_expired_courses()
            
            return new_courses
            
        except Exception as e:
            logger.error(f"Error scraping and storing courses: {e}")
            return []
    
    async def _post_pending_courses(self) -> int:
        """Post pending courses to the channel"""
        try:
            # Get unposted courses
            pending_courses = self.db.get_unposted_courses(limit=Config.MAX_COURSES_PER_RUN)
            
            if not pending_courses:
                logger.info("No pending courses to post")
                return 0
            
            posted_count = 0
            
            for course in pending_courses:
                try:
                    # Format the message
                    message = self.formatter.format_course_message(course)
                    
                    # Create inline keyboard
                    keyboard = self.formatter.create_inline_keyboard(course['course_url'])
                    
                    # Send to channel
                    await self.bot.send_message(
                        chat_id=Config.TELEGRAM_CHANNEL_ID,
                        text=message,
                        parse_mode='HTML',
                        reply_markup=keyboard,
                        disable_web_page_preview=False
                    )
                    
                    # Mark as posted
                    self.db.mark_course_posted(course['id'])
                    posted_count += 1
                    
                    logger.info(f"Posted course: {course['title']}")
                    
                    # Use rate limiter to avoid FloodWait errors
                    await self.rate_limiter.wait_if_needed()
                    
                except TelegramError as e:
                    logger.error(f"Telegram error posting course {course['id']}: {e}")
                    continue
                except Exception as e:
                    logger.error(f"Error posting course {course['id']}: {e}")
                    continue
            
            logger.info(f"Posted {posted_count} courses to channel")
            return posted_count
            
        except Exception as e:
            logger.error(f"Error posting pending courses: {e}")
            return 0
    
    async def _scheduled_scrape_and_post_job(self, context):
        """Job queue scheduled task to scrape and post courses"""
        logger.info("Job queue triggered scraping task")
        await self._scheduled_scrape_and_post()
    
    async def _scheduled_scrape_and_post(self):
        """Scheduled task to scrape and post courses"""
        try:
            logger.info("Running scheduled scrape and post")
            
            # Scrape new courses
            new_courses = await self._scrape_and_store_courses()
            
            # Post pending courses
            posted_count = await self._post_pending_courses()
            
            # Send summary to admin if there's activity
            if new_courses or posted_count > 0:
                summary = f"""
📊 <b>Scheduled Update Complete</b>

🆕 <b>New courses found:</b> {len(new_courses)}
📤 <b>Courses posted:</b> {posted_count}
⏰ <b>Next update:</b> {(datetime.now() + timedelta(seconds=Config.SCRAPING_INTERVAL)).strftime('%H:%M')}
                """
                await self._send_admin_message(summary)
            
        except Exception as e:
            logger.error(f"Error in scheduled task: {e}")
            error_msg = self.formatter.format_error_message(str(e), "Scheduled Task")
            await self._send_admin_message(error_msg)
    
    async def start_bot(self):
        """Start the bot with scheduled tasks"""
        try:
            if not await self.initialize():
                return False
            
            self.is_running = True
            logger.info(f"Starting {Config.BOT_NAME}")
            
            # Start the application
            await self.application.initialize()
            await self.application.start()
            
            # Add job queue for scheduling
            from telegram.ext import JobQueue
            job_queue = self.application.job_queue
            
            # Schedule periodic scraping
            job_queue.run_repeating(
                self._scheduled_scrape_and_post_job,
                interval=Config.SCRAPING_INTERVAL,
                first=10  # Start after 10 seconds
            )
            
            logger.info(f"Scheduled scraping every {Config.SCRAPING_INTERVAL} seconds")
            
            # Start polling for commands
            await self.application.updater.start_polling()
            
            # Keep the bot running
            while self.is_running:
                await asyncio.sleep(1)
            
            await self.stop_bot()
            return True
            
        except Exception as e:
            logger.error(f"Error starting bot: {e}")
            return False
    
    async def stop_bot(self):
        """Stop the bot gracefully"""
        try:
            self.is_running = False
            
            if self.application:
                await self.application.updater.stop()
                await self.application.stop()
                await self.application.shutdown()
            
            await self._send_admin_message("🛑 Bot stopped")
            logger.info("Bot stopped successfully")
            
        except Exception as e:
            logger.error(f"Error stopping bot: {e}")

# Global bot instance
bot_instance = None

def signal_handler(signum, frame):
    """Handle shutdown signals"""
    logger.info(f"Received signal {signum}")
    if bot_instance:
        asyncio.create_task(bot_instance.stop_bot())

async def main():
    """Main entry point"""
    global bot_instance
    
    # Setup signal handlers
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    # Create and start bot
    bot_instance = UdemyCourseBot()
    
    try:
        success = await bot_instance.start_bot()
        if not success:
            logger.error("Failed to start bot")
            sys.exit(1)
            
    except KeyboardInterrupt:
        logger.info("Bot interrupted by user")
    except Exception as e:
        logger.error(f"Unexpected error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("Bot shutdown complete")
    except Exception as e:
        logger.error(f"Fatal error: {e}")
        sys.exit(1)
