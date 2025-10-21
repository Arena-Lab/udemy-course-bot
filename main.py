"""
Main Udemy Course Bot - Automatically scrapes and posts free Udemy courses
"""
import asyncio
import logging
import signal
import sys
from datetime import datetime, timedelta
from typing import Optional

from telegram import Bot, Update
from telegram.ext import Application, CommandHandler, ContextTypes, CallbackQueryHandler
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
from utils.exporter import export_active_courses_json

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
        # Runtime control flags (start on HOLD by default; admin must /shoot)
        self.posting_paused = True
        self.scraping_paused = False
        # Job handles for dynamic rescheduling
        self.scrape_job = None
        self.post_job = None
        self.maint_job = None
        self.heart_job = None
        
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
            CommandHandler("ratelimit", self.cmd_rate_limit),
            CommandHandler("schedule", self.cmd_schedule),
            # Admin runtime controls
            CommandHandler("discsetpages", self.cmd_disc_set_pages),
            CommandHandler("discsetslice", self.cmd_disc_set_slice),
            # Backward-compat alias
            CommandHandler("setfreshpages", self.cmd_set_fresh_pages),
            CommandHandler("shoot", self.cmd_shoot),
            CommandHandler("hold", self.cmd_hold),
            CommandHandler("startscrape", self.cmd_start_scrape),
            CommandHandler("stopscrape", self.cmd_stop_scrape),
            # Admin convenience
            CommandHandler("env", self.cmd_env),
            CommandHandler("restart", self.cmd_restart),
            CommandHandler("panel", self.cmd_panel),
        ]
        
        for handler in handlers:
            self.application.add_handler(handler)
        # Callback handler for admin panel buttons
        self.application.add_handler(CallbackQueryHandler(self.cb_panel, pattern=r"^panel:"))
    
    async def cmd_start(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Handle /start command"""
        # Show current DiscUdemy values inline for clarity
        message = f"""
🎓 <b>{Config.BOT_NAME}</b> - Welcome!

This bot automatically finds and posts free Udemy courses to your channel.

<b>Available Commands:</b>
/stats - Show bot statistics  
/test - Test all scrapers
/scrape - Manual scrape (admin only)
/post - Post pending courses (admin only)
/status - Show bot status
/ratelimit - Adjust rate limiting (admin only)
/discsetpages N - DiscUdemy: set total fresh pages (1-60). Current: {getattr(Config, 'DISCUDEMY_FRESH_PAGES', 10)}
/discsetslice S - DiscUdemy: set rotating slice over 2..N per run. Current: {getattr(Config, 'DISCUDEMY_FRESH_SLICE', 3)}
/shoot - Resume posting to the channel (admin)
/hold - Pause posting to the channel (admin)
/startscrape - Resume scraping jobs (admin)
/stopscrape - Pause scraping jobs (admin)

<b>Channel:</b> {Config.CHANNEL_LINK}
        """
        await update.message.reply_text(message, parse_mode='HTML')
    
    async def cmd_help(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Handle /help command with comprehensive command list"""
        help_message = f"""
🎓 <b>{Config.BOT_NAME} - Command Reference</b>

<b>📊 General Commands</b>
• <code>/start</code> - Welcome message and bot info
• <code>/help</code> - Show this comprehensive help
• <code>/stats</code> - Display bot statistics and metrics
• <code>/status</code> - Show current bot status and health

<b>🔧 Admin Controls</b> (Admin Only)
• <code>/panel</code> - Interactive admin control panel
• <code>/shoot</code> - Resume posting to channel
• <code>/hold</code> - Pause posting (scraping continues)
• <code>/startscrape</code> - Resume background scraping
• <code>/stopscrape</code> - Pause background scraping
• <code>/restart</code> - Restart the bot completely

<b>⏱️ Scheduling Controls</b> (Admin Only)
• <code>/schedule</code> - Show current schedule settings
• <code>/schedule posts &lt;N&gt;</code> - Set posts per run (e.g., <code>/schedule posts 15</code>)
• <code>/schedule scrape_interval &lt;sec&gt;</code> - Set scrape frequency (e.g., <code>/schedule scrape_interval 300</code>)
• <code>/schedule post_interval &lt;sec&gt;</code> - Set posting frequency (e.g., <code>/schedule post_interval 120</code>)

<b>🚦 Rate Limiting</b> (Admin Only)
• <code>/ratelimit</code> - Show current rate limit settings
• <code>/ratelimit delay &lt;sec&gt;</code> - Set delay between posts (e.g., <code>/ratelimit delay 5</code>)
• <code>/ratelimit maxpm &lt;N&gt;</code> - Max posts per minute (e.g., <code>/ratelimit maxpm 20</code>)
• <code>/ratelimit burst &lt;N&gt;</code> - Burst limit before delay (e.g., <code>/ratelimit burst 3</code>)

<b>🔍 Testing & Manual Operations</b> (Admin Only)
• <code>/test</code> - Test all scrapers and show results
• <code>/scrape</code> - Manual scraping session
• <code>/post</code> - Post all pending courses immediately

<b>⚙️ Advanced Configuration</b> (Admin Only)
• <code>/env KEY=VALUE</code> - Update environment settings (e.g., <code>/env POSTS_PER_RUN=10</code>)
• <code>/discsetpages &lt;1-60&gt;</code> - Set DiscUdemy pages to scrape (e.g., <code>/discsetpages 30</code>)
• <code>/discsetslice &lt;N&gt;</code> - Set DiscUdemy rotation slice (e.g., <code>/discsetslice 5</code>)

<b>📈 Current Settings</b>
• Posts per run: <b>{getattr(Config, 'POSTS_PER_RUN', 12)}</b>
• Scrape interval: <b>{getattr(Config, 'SCRAPE_JOB_INTERVAL', 180)}s</b>
• Post interval: <b>{getattr(Config, 'POST_JOB_INTERVAL', 60)}s</b>
• DiscUdemy pages: <b>{getattr(Config, 'DISCUDEMY_FRESH_PAGES', 10)}</b>

<b>💡 Quick Tips</b>
• Use <code>/panel</code> for quick controls with buttons
• All schedule changes are saved to .env automatically
• Use <code>/status</code> to monitor bot health
• Settings persist across restarts

<b>🔗 Channel:</b> {Config.CHANNEL_LINK}
        """
        await update.message.reply_text(help_message, parse_mode='HTML')

    async def cmd_set_fresh_pages(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Backward-compat alias: tell admins to use /discsetpages"""
        if not self._is_admin(update.effective_user.id):
            await update.message.reply_text("❌ Admin access required")
            return
        await update.message.reply_text("ℹ️ Command renamed. Use /discsetpages <1-60> for DiscUdemy.")

    async def cmd_disc_set_pages(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """/discsetpages N -> set Config.DISCUDEMY_FRESH_PAGES at runtime and persist to .env"""
        try:
            if not self._is_admin(update.effective_user.id):
                await update.message.reply_text("❌ Admin access required")
                return
            if not context.args:
                await update.message.reply_text("❌ Usage: /discsetpages <1-60>")
                return
            val = int(context.args[0])
            if val < 1 or val > 60:
                await update.message.reply_text("❌ Value must be between 1 and 60")
                return
            setattr(Config, 'DISCUDEMY_FRESH_PAGES', val)
            await asyncio.to_thread(self._persist_env, 'DISCUDEMY_FRESH_PAGES', str(val))
            await update.message.reply_text(f"✅ DiscUdemy pages set to {val} and saved to .env")
        except ValueError:
            await update.message.reply_text("❌ Invalid number. Usage: /discsetpages <1-60>")
        except Exception as e:
            logger.error(f"Error in discsetpages: {e}")
            await update.message.reply_text("❌ Error updating setting")

    async def cmd_disc_set_slice(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """/discsetslice S -> set Config.DISCUDEMY_FRESH_SLICE (window over 2..N) and persist to .env"""
        try:
            if not self._is_admin(update.effective_user.id):
                await update.message.reply_text("❌ Admin access required")
                return
            if not context.args:
                await update.message.reply_text("❌ Usage: /discsetslice <1..N-1>")
                return
            val = int(context.args[0])
            max_pages = max(1, min(int(getattr(Config, 'DISCUDEMY_FRESH_PAGES', 10)), 60))
            max_slice = max(1, max_pages - 1)
            if val < 1 or val > max_slice:
                await update.message.reply_text(f"❌ Value must be between 1 and {max_slice} (given current pages={max_pages})")
                return
            setattr(Config, 'DISCUDEMY_FRESH_SLICE', val)
            await asyncio.to_thread(self._persist_env, 'DISCUDEMY_FRESH_SLICE', str(val))
            await update.message.reply_text(f"✅ DiscUdemy slice set to {val} and saved to .env (pages={max_pages})")
        except ValueError:
            await update.message.reply_text("❌ Invalid number. Usage: /discsetslice <1..N-1>")
        except Exception as e:
            logger.error(f"Error in discsetslice: {e}")
            await update.message.reply_text("❌ Error updating setting")

    async def cmd_shoot(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Resume posting to the channel while keeping all limits."""
        if not self._is_admin(update.effective_user.id):
            await update.message.reply_text("❌ Admin access required")
            return
        self.posting_paused = False
        await update.message.reply_text("🟢 Posting resumed. Scheduled posts will continue.")

    async def cmd_hold(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Pause posting to channel; scraping continues."""
        if not self._is_admin(update.effective_user.id):
            await update.message.reply_text("❌ Admin access required")
            return
        self.posting_paused = True
        await update.message.reply_text("⏸️ Posting paused. Scraping will continue.")

    async def cmd_start_scrape(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Resume background scraping jobs."""
        if not self._is_admin(update.effective_user.id):
            await update.message.reply_text("❌ Admin access required")
            return
        self.scraping_paused = False
        await update.message.reply_text("🟢 Scraping resumed.")

    async def cmd_stop_scrape(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Pause background scraping jobs."""
        if not self._is_admin(update.effective_user.id):
            await update.message.reply_text("❌ Admin access required")
            return
        self.scraping_paused = True
        await update.message.reply_text("⏸️ Scraping paused. Posting state unchanged.")
    
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

    async def cmd_schedule(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """/schedule posts <N> | post_interval <sec> | scrape_interval <sec> -> persist to .env and apply."""
        if not self._is_admin(update.effective_user.id):
            await update.message.reply_text("❌ Admin access required")
            return
        try:
            args = context.args
            if not args or len(args) < 2:
                msg = (
                    "⏱️ <b>Schedule Settings</b>\n\n"
                    f"• Posts per run: {getattr(Config, 'POSTS_PER_RUN', 12)}\n"
                    f"• Scrape interval: {getattr(Config, 'SCRAPE_JOB_INTERVAL', 180)}s\n"
                    f"• Post interval: {getattr(Config, 'POST_JOB_INTERVAL', 60)}s\n\n"
                    "Usage:\n"
                    "/schedule posts 10\n"
                    "/schedule scrape_interval 300\n"
                    "/schedule post_interval 120\n"
                )
                await update.message.reply_text(msg, parse_mode='HTML')
                return
            key = args[0].lower()
            val = int(args[1])
            if key == 'posts':
                setattr(Config, 'POSTS_PER_RUN', val)
                await asyncio.to_thread(self._persist_env, 'POSTS_PER_RUN', str(val))
                await update.message.reply_text(f"✅ Posts per run set to {val} and saved to .env")
            elif key == 'scrape_interval':
                setattr(Config, 'SCRAPE_JOB_INTERVAL', val)
                await asyncio.to_thread(self._persist_env, 'SCRAPE_JOB_INTERVAL', str(val))
                await update.message.reply_text(f"✅ Scrape interval set to {val}s and saved to .env")
                await self._reschedule_jobs()
            elif key == 'post_interval':
                setattr(Config, 'POST_JOB_INTERVAL', val)
                await asyncio.to_thread(self._persist_env, 'POST_JOB_INTERVAL', str(val))
                await update.message.reply_text(f"✅ Post interval set to {val}s and saved to .env")
                await self._reschedule_jobs()
            else:
                await update.message.reply_text("❌ Invalid key. Use: posts | scrape_interval | post_interval")
        except ValueError:
            await update.message.reply_text("❌ Invalid value. Use numbers only.")
        except Exception as e:
            logger.error(f"Error in schedule command: {e}")
            await update.message.reply_text("❌ Error updating schedule")

    async def cmd_env(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Admin: /env KEY=VALUE [KEY2=VALUE2 ...] -> persist to .env and apply some at runtime."""
        try:
            if not self._is_admin(update.effective_user.id):
                await update.message.reply_text("❌ Admin access required")
                return
            # Expect the full text after command
            text = (update.message.text or "").strip()
            parts = text.split(" ", 1)
            if len(parts) < 2 or not parts[1].strip():
                await update.message.reply_text("❌ Usage: /env KEY=VALUE [KEY2=VALUE2 ...]")
                return
            kv_str = parts[1].strip()
            tokens = [t for t in kv_str.split() if "=" in t]
            if not tokens:
                await update.message.reply_text("❌ Provide at least one KEY=VALUE pair")
                return
            updated = []
            for tok in tokens:
                k, v = tok.split("=", 1)
                k = k.strip()
                v = v.strip()
                if not k:
                    continue
                await asyncio.to_thread(self._persist_env, k, v)
                # Apply selected keys at runtime (no restart):
                try:
                    if k in {"QUICKTRENDS_BASE_URL"}:
                        setattr(Config, k, v)
                    elif k in {"DIRECT_LINKS"}:
                        setattr(Config, k, str(v).strip().lower() == 'true')
                    elif k in {"DISCUDEMY_FRESH_PAGES", "DISCUDEMY_FRESH_SLICE", "POSTS_PER_RUN", "SCRAPE_JOB_INTERVAL", "POST_JOB_INTERVAL"}:
                        setattr(Config, k, int(v))
                        if k in {"SCRAPE_JOB_INTERVAL", "POST_JOB_INTERVAL"}:
                            # Reschedule with new intervals without manual restart
                            await self._reschedule_jobs()
                except Exception:
                    pass
                updated.append(f"{k}")
            msg = "✅ Updated: " + ", ".join(updated)
            # Note about restarts for schedule changes
            msg += "\nℹ️ Some settings require /restart to take full effect (schedules, telegram token, etc.)."
            await update.message.reply_text(msg)
        except Exception as e:
            logger.error(f"Error in env command: {e}")
            await update.message.reply_text("❌ Error applying settings")

    async def cmd_restart(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Admin: /restart -> gracefully stop and exit. Expect systemd to auto-restart."""
        try:
            if not self._is_admin(update.effective_user.id):
                await update.message.reply_text("❌ Admin access required")
                return
            await update.message.reply_text("♻️ Restarting bot... (it will be back in a few seconds)")
            await self._send_admin_message("♻️ Restart requested by admin")
            # Give Telegram time to send ack before shutting down
            await asyncio.sleep(1)
            await self.stop_bot()
            os._exit(0)
        except Exception as e:
            logger.error(f"Error in restart command: {e}")
            await update.message.reply_text("❌ Restart failed")

    async def cmd_panel(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Show admin control panel with buttons."""
        if not self._is_admin(update.effective_user.id):
            await update.message.reply_text("❌ Admin access required")
            return
        from telegram import InlineKeyboardMarkup, InlineKeyboardButton
        kb = InlineKeyboardMarkup([
            [InlineKeyboardButton("🟢 Resume Posting", callback_data="panel:shoot"), InlineKeyboardButton("⏸️ Pause Posting", callback_data="panel:hold")],
            [InlineKeyboardButton("🟢 Resume Scraping", callback_data="panel:startscrape"), InlineKeyboardButton("⏸️ Pause Scraping", callback_data="panel:stopscrape")],
            [InlineKeyboardButton("♻️ Restart Bot", callback_data="panel:restart")],
        ])
        await update.message.reply_text("🔧 Admin Panel", reply_markup=kb)

    async def cb_panel(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Callback handler for admin panel actions."""
        q = update.callback_query
        await q.answer()
        data = (q.data or "")
        if not self._is_admin(update.effective_user.id):
            await q.edit_message_text("❌ Admin access required")
            return
        if data == "panel:shoot":
            self.posting_paused = False
            await q.edit_message_text("🟢 Posting resumed")
        elif data == "panel:hold":
            self.posting_paused = True
            await q.edit_message_text("⏸️ Posting paused")
        elif data == "panel:startscrape":
            self.scraping_paused = False
            await q.edit_message_text("🟢 Scraping resumed")
        elif data == "panel:stopscrape":
            self.scraping_paused = True
            await q.edit_message_text("⏸️ Scraping paused")
        elif data == "panel:restart":
            await q.edit_message_text("♻️ Restarting...")
            await self._send_admin_message("♻️ Restart requested via panel")
            await asyncio.sleep(1)
            await self.stop_bot()
            os._exit(0)
        else:
            await q.edit_message_text("❌ Unknown action")

    async def _reschedule_jobs(self):
        """Reschedule scrape/post jobs to respect updated Config intervals."""
        try:
            if not self.application or not self.application.job_queue:
                return
            jq = self.application.job_queue
            # Remove existing repeating jobs
            try:
                if self.scrape_job:
                    self.scrape_job.schedule_removal()
            except Exception:
                pass
            try:
                if self.post_job:
                    self.post_job.schedule_removal()
            except Exception:
                pass
            # Schedule new ones with current intervals
            self.scrape_job = jq.run_repeating(
                self._scheduled_scrape_job,
                interval=Config.SCRAPE_JOB_INTERVAL,
                first=5
            )
            self.post_job = jq.run_repeating(
                self._scheduled_post_job,
                interval=Config.POST_JOB_INTERVAL,
                first=10
            )
            await self._send_admin_message(
                f"🔁 Rescheduled jobs: scrape={Config.SCRAPE_JOB_INTERVAL}s, post={Config.POST_JOB_INTERVAL}s"
            )
        except Exception as e:
            logger.error(f"Reschedule error: {e}")
    
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
            
            # Reset scraper statistics before each run
            for scraper in self.scraper_manager.get_enabled_scrapers():
                scraper.reset_statistics()
            
            # Scrape from all enabled sources
            courses = self.scraper_manager.scrape_all_sources(
                limit_per_source=Config.MAX_COURSES_PER_RUN
            )
            
            # Filter out duplicates before storing
            new_courses = []
            duplicate_count = 0
            
            for course in courses:
                # Check if course already exists before adding
                course_hash = self.db.generate_course_hash(course)
                if self.db.course_exists(course_hash):
                    duplicate_count += 1
                    logger.debug(f"Duplicate course detected: {course.get('title', 'Unknown')}")
                    continue
                    
                # Add new course to database
                if self.db.add_course(course):
                    new_courses.append(course)
            
            if duplicate_count > 0:
                logger.info(f"Scraped {len(courses)} courses, {len(new_courses)} new, {duplicate_count} duplicates skipped")
            else:
                logger.info(f"Scraped {len(courses)} courses, {len(new_courses)} new")
            
            # Adjust scraper statistics to reflect only unique courses
            self._adjust_scraper_statistics(courses, new_courses)
            
            # Cleanup expired courses
            self.db.cleanup_expired_courses()
            # Export active courses to website JSON feed
            try:
                export_active_courses_json(self.db, website_dir="quicktrends_files", filename="courses.json", limit=1000)
                logger.info("Exported active courses to website JSON")
            except Exception as _e:
                logger.debug(f"Website export failed: {_e}")
            
            return new_courses
            
        except Exception as e:
            logger.error(f"Error scraping and storing courses: {e}")
            return []

    def _scrape_and_store_courses_sync(self) -> list:
        """Synchronous variant to run in a background thread without blocking the event loop."""
        try:
            logger.info("[BG] Starting course scraping from all sources")
            # Reset scraper statistics before each run
            for scraper in self.scraper_manager.get_enabled_scrapers():
                scraper.reset_statistics()
            # Scrape from all enabled sources
            courses = self.scraper_manager.scrape_all_sources(
                limit_per_source=Config.MAX_COURSES_PER_RUN
            )
            # Filter out duplicates before storing
            new_courses = []
            duplicate_count = 0
            for course in courses:
                course_hash = self.db.generate_course_hash(course)
                if self.db.course_exists(course_hash):
                    duplicate_count += 1
                    continue
                if self.db.add_course(course):
                    new_courses.append(course)
            if duplicate_count > 0:
                logger.info(f"[BG] Scraped {len(courses)} courses, {len(new_courses)} new, {duplicate_count} duplicates skipped")
            else:
                logger.info(f"[BG] Scraped {len(courses)} courses, {len(new_courses)} new")
            # Adjust stats and cleanup
            self._adjust_scraper_statistics(courses, new_courses)
            self.db.cleanup_expired_courses()
            # Export active courses to website JSON feed
            try:
                export_active_courses_json(self.db, website_dir="quicktrends_files", filename="courses.json", limit=1000)
                logger.info("[BG] Exported active courses to website JSON")
            except Exception as _e:
                logger.debug(f"[BG] Website export failed: {_e}")
            # Optional: write top-level per-run summary diagnostics
            try:
                if getattr(Config, 'DIAG_ENABLE_RUN_SUMMARY', False):
                    from pathlib import Path
                    import json
                    diag_dir = Path(getattr(Config, 'DIAG_DIR', 'logs/diagnostics'))
                    diag_dir.mkdir(parents=True, exist_ok=True)
                    summary = {
                        'timestamp': datetime.now().isoformat(),
                        'max_per_source': int(getattr(Config, 'MAX_COURSES_PER_RUN', 50)),
                        'total_scraped': len(courses),
                        'new_courses': len(new_courses),
                        'duplicates': int(duplicate_count),
                        'db_stats': self.db.get_statistics(),
                        'per_scraper': self.scraper_manager.get_scraper_statistics()
                    }
                    ts = datetime.now().strftime('%Y%m%d_%H%M%S')
                    outfile = diag_dir / f"run_summary_{ts}.json"
                    with outfile.open('w', encoding='utf-8') as f:
                        json.dump(summary, f, ensure_ascii=False, indent=2)
            except Exception as _e:
                logger.debug(f"Failed to write run summary diagnostics: {_e}")
            return new_courses
        except Exception as e:
            logger.error(f"[BG] Error scraping and storing courses: {e}")
            return []
    
    def _adjust_scraper_statistics(self, all_courses: list, new_courses: list):
        """Adjust scraper statistics to reflect only unique courses"""
        try:
            # Count new courses by source
            new_by_source = {}
            for course in new_courses:
                source = course.get('source', 'unknown')
                new_by_source[source] = new_by_source.get(source, 0) + 1
            
            # Adjust each scraper's statistics
            for scraper in self.scraper_manager.get_enabled_scrapers():
                scraper_name = scraper.name
                new_count = new_by_source.get(scraper_name, 0)
                
                # Set courses_scraped to only count unique courses
                scraper.courses_scraped = new_count
                
        except Exception as e:
            logger.error(f"Error adjusting scraper statistics: {e}")
    
    async def _post_pending_courses(self) -> int:
        """Post pending courses to the channel"""
        try:
            # Get unposted courses, but cap posts per run to drip-feed
            fetch_limit = max(1, min(Config.POSTS_PER_RUN, Config.MAX_COURSES_PER_RUN))
            pending_courses = self.db.get_unposted_courses(limit=fetch_limit)
            
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
                    
                    # Check if course has an image from Udemy metadata
                    image_url = course.get('image_url')
                    
                    if image_url and image_url.startswith('http'):
                        # Send message with photo from Udemy
                        try:
                            await self.bot.send_photo(
                                chat_id=Config.TELEGRAM_CHANNEL_ID,
                                photo=image_url,
                                caption=message,
                                parse_mode='HTML',
                                reply_markup=keyboard
                            )
                            logger.info(f"Posted course with image: {course['title']}")
                        except Exception as img_error:
                            logger.warning(f"Failed to send photo, sending text instead: {img_error}")
                            # Fallback to text message if photo fails
                            await self.bot.send_message(
                                chat_id=Config.TELEGRAM_CHANNEL_ID,
                                text=message,
                                parse_mode='HTML',
                                reply_markup=keyboard,
                                disable_web_page_preview=True
                            )
                            logger.info(f"Posted course as text: {course['title']}")
                    else:
                        # Send text message without photo
                        await self.bot.send_message(
                            chat_id=Config.TELEGRAM_CHANNEL_ID,
                            text=message,
                            parse_mode='HTML',
                            reply_markup=keyboard,
                            disable_web_page_preview=True
                        )
                        logger.info(f"Posted course as text: {course['title']}")
                    
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
    
    async def _scheduled_scrape_job(self, context):
        """Job: scrape only, offloaded to a background thread to keep bot responsive"""
        logger.info("Job queue triggered scrape-only task")
        if getattr(self, 'scraping_paused', False):
            logger.info("Skip scrape: scraping is paused by admin")
            return
        # Avoid overlapping background scrapes
        if getattr(self, "_scrape_running", False):
            logger.info("Skip scrape: previous scrape still running")
            return
        self._scrape_running = True
        try:
            loop = asyncio.get_running_loop()
            # Run the heavy scrape in a thread
            new_courses = await asyncio.to_thread(self._scrape_and_store_courses_sync)
            # Optional: notify admin summarizing scrape in background
            if new_courses:
                await self._send_admin_message(f"🆕 Scrape complete in background: {len(new_courses)} new courses")
        finally:
            self._scrape_running = False

    async def _scheduled_post_job(self, context):
        """Job: post only, no scraping"""
        logger.info("Job queue triggered post-only task")
        if getattr(self, 'posting_paused', False):
            logger.info("Skip posting: posting is paused by admin")
            return
        await self._post_pending_courses()
    
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
            job_queue = self.application.job_queue
            
            # Schedule decoupled scraping and posting
            self.scrape_job = job_queue.run_repeating(
                self._scheduled_scrape_job,
                interval=Config.SCRAPE_JOB_INTERVAL,
                first=10  # Start after 10 seconds
            )
            self.post_job = job_queue.run_repeating(
                self._scheduled_post_job,
                interval=Config.POST_JOB_INTERVAL,
                first=20  # Start posting shortly after scraping begins
            )
            
            logger.info(f"Scheduled scrape job every {Config.SCRAPE_JOB_INTERVAL} seconds; post job every {Config.POST_JOB_INTERVAL} seconds")

            # Schedule daily maintenance: cleanup expired and purge old inactive
            async def _daily_maintenance_job(context):
                try:
                    logger.info("Running daily DB maintenance")
                    cleaned = self.db.cleanup_expired_courses()
                    purged = self.db.purge_inactive_courses(Config.DB_RETENTION_DAYS)
                    # Weekly VACUUM on Sundays 03:00 local time window simulation by modulo day check
                    if datetime.now().weekday() == 6:  # Sunday
                        self.db.vacuum()
                    await self._send_admin_message(
                        f"🧹 DB maintenance: cleaned {cleaned}, purged {purged}{' + vacuum' if datetime.now().weekday()==6 else ''}"
                    )
                except Exception as e:
                    logger.error(f"Maintenance job error: {e}")
            # Run daily after startup delay
            self.maint_job = job_queue.run_repeating(
                _daily_maintenance_job,
                interval=24*60*60,
                first=60  # run first maintenance after 1 minute
            )

            # Schedule daily heartbeat to admin with quick stats
            async def _daily_heartbeat_job(context):
                try:
                    stats = self.db.get_statistics()
                    scraper_stats = self.scraper_manager.get_scraper_statistics()
                    enabled = len(self.scraper_manager.get_enabled_scrapers())
                    new_total = stats.get('total_courses', 0)
                    pending = stats.get('pending_courses', 0)
                    posted = stats.get('posted_courses', 0)
                    msg = (
                        f"✅ Daily Heartbeat\n"
                        f"• Scrapers enabled: {enabled}\n"
                        f"• Total: {new_total} | Posted: {posted} | Pending: {pending}\n"
                        f"• Next scrape every: {Config.SCRAPING_INTERVAL}s"
                    )
                    await self._send_admin_message(msg)
                except Exception as e:
                    logger.error(f"Heartbeat job error: {e}")
            self.heart_job = job_queue.run_repeating(
                _daily_heartbeat_job,
                interval=24*60*60,
                first=120  # first heartbeat after 2 minutes
            )
            
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

    def _persist_env(self, key: str, value: str):
        """Create or update a key=value in the project's .env file."""
        try:
            env_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), '.env')
            # If main.py is in project root, join to root/.env
            env_path = os.path.abspath(os.path.join(os.path.dirname(__file__), '.env'))
            lines = []
            existing = False
            if os.path.exists(env_path):
                with open(env_path, 'r', encoding='utf-8') as f:
                    for line in f.readlines():
                        if line.strip().startswith(f"{key}="):
                            lines.append(f"{key}={value}\n")
                            existing = True
                        else:
                            lines.append(line)
            if not existing:
                lines.append(f"{key}={value}\n")
            with open(env_path, 'w', encoding='utf-8') as f:
                f.writelines(lines)
        except Exception as e:
            logger.error(f"Failed to persist .env setting {key}: {e}")

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
