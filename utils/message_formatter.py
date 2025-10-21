"""
Message formatting utilities for consistent branding
"""
import re
from typing import Dict, Optional
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from config.settings import Config
import logging

logger = logging.getLogger(__name__)

class MessageFormatter:
    """Handles message formatting with consistent branding"""
    
    def __init__(self):
        self.config = Config()
    
    def format_course_message(self, course_data: Dict) -> str:
        """Format a course into a clean Telegram caption without inline URL (URL goes in button)."""
        try:
            title = self._clean_text(course_data.get('title', 'Unknown Course'))
            instructor = self._clean_text(course_data.get('instructor', ''))
            discount_percentage = course_data.get('discount_percentage', '100% OFF')
            # Always synthesize a USD original price and strike it through (ignore provided)
            original_price = None
            rating = course_data.get('rating')
            students = course_data.get('students_count')
            language = course_data.get('language')
            category = course_data.get('category')
            description = course_data.get('description', '')

            parts = []
            # Header/title
            parts.append(f"{Config.BOT_LOGO_EMOJI} <b>{title}</b>")
            # Make FREE highly visible
            parts.append("💯✨ <b>100% FREE</b> ✨")

            # Optional short description
            if description and len(description) > 20:
                # keep it compact, show inside Telegram's quote block
                if len(description) > 240:
                    description = description[:240] + "..."
                parts.append("")
                parts.append(f"<blockquote>{self._clean_text(description)}</blockquote>")

            # Details block
            details = []
            if instructor:
                details.append(f"👨‍🏫 <b>Instructor:</b> {instructor}")

            # Price line: always show strikethrough with a synthesized USD price
            if not original_price:
                original_price = self._synth_price()
            details.append(f"💰 <b>Price:</b> <s>{original_price}</s> ➜ <b>FREE</b>")

            if rating:
                stars = self._get_star_rating(rating)
                try:
                    rating_fmt = f"{float(rating):.1f}"
                except Exception:
                    rating_fmt = str(rating)
                details.append(f"⭐ <b>Rating:</b> {rating_fmt}/5 {stars}")

            if students:
                details.append(f"👥 <b>Students:</b> {self._format_number(students)}")

            if language:
                details.append(f"🌐 <b>Language:</b> {language}")

            if category:
                details.append(f"📚 <b>Category:</b> {category}")

            if details:
                parts.append("")
                parts.extend(details)

            # Footer: channel grid (2 columns) + invite line
            parts.append("")
            parts.append("🧭 <b>Explore our channels</b>")
            parts.append("• 🛡️ <b><a href=\"https://t.me/+Te_QWfS6W99mNjA1\">Hacking</a></b> | • 🧠 <b><a href=\"https://t.me/+5AF4k2itls1iYTM9\">Free AI Tools</a></b>")
            parts.append("• ⚡ <b><a href=\"https://t.me/+6tALVTxVuYMzNjdl\">Quick Deals</a></b> | • 📣 <b><a href=\"https://t.me/+RZf4mx2BiZhhMDdl\">Marketing Bot</a></b>")
            parts.append("• 🤖 <b><a href=\"https://t.me/AlienxSaver\">Free Bots</a></b> | • 💰 <b><a href=\"https://t.me/+kzHBAvWrS5hiOTg1\">Earning Channel</a></b>")
            parts.append("")
            parts.append("👥 <b>Invite friends:</b> 👉 <a href=\"https://t.me/udemyzap\">@udemyzap</a>")

            return "\n".join(parts)

        except Exception as e:
            logger.error(f"Error formatting course message: {e}")
            return self._get_error_message(course_data)
    
    def create_inline_keyboard(self, course_url: str):
        """Create inline keyboard with monetized enroll button"""
        from telegram import InlineKeyboardButton, InlineKeyboardMarkup
        from urllib.parse import quote
        
        if getattr(Config, 'DIRECT_LINKS', True):
            button_url = course_url
        else:
            base = getattr(Config, 'QUICKTRENDS_BASE_URL', 'https://quicktrends.in').rstrip('/')
            button_url = f"{base}/go.php?u={quote(course_url)}"
        
        return InlineKeyboardMarkup([[InlineKeyboardButton("🚀 Enroll Free", url=button_url)]])
    
    def format_stats_message(self, stats: Dict) -> str:
        """Format statistics message for admin"""
        try:
            message_parts = []
            message_parts.append(f"{Config.BOT_LOGO_EMOJI} <b>{Config.BOT_NAME} Statistics</b>")
            message_parts.append("")
            
            message_parts.append(f"📊 <b>Total Courses:</b> {stats.get('total_courses', 0)}")
            message_parts.append(f"✅ <b>Posted Courses:</b> {stats.get('posted_courses', 0)}")
            message_parts.append(f"⏳ <b>Pending Courses:</b> {stats.get('pending_courses', 0)}")
            
            # Source breakdown
            source_breakdown = stats.get('source_breakdown', {})
            if source_breakdown:
                message_parts.append("")
                message_parts.append("📈 <b>Sources Breakdown:</b>")
                for source, count in source_breakdown.items():
                    source_name = self._format_source_name(source)
                    message_parts.append(f"  • {source_name}: {count}")
            
            return "\n".join(message_parts)
            
        except Exception as e:
            logger.error(f"Error formatting stats message: {e}")
            return "❌ Error generating statistics"
    
    def format_error_message(self, error_msg: str, source: str = None) -> str:
        """Format error message for admin notifications"""
        message_parts = []
        message_parts.append("🚨 **Bot Error Alert**")
        message_parts.append("")
        
        if source:
            message_parts.append(f"**Source:** {source}")
        
        message_parts.append(f"**Error:** {error_msg}")
        message_parts.append(f"**Time:** {self._get_current_time()}")
        
        return "\n".join(message_parts)
    
    def _clean_text(self, text: str) -> str:
        """Clean text for Telegram message"""
        if not text:
            return ""
        
        # Remove HTML tags
        text = re.sub(r'<[^>]+>', '', text)
        
        # Remove extra whitespace
        text = re.sub(r'\s+', ' ', text).strip()
        
        # Limit length
        if len(text) > 200:
            text = text[:197] + "..."
        
        return text
    
    def _get_star_rating(self, rating: float) -> str:
        """Convert numeric rating to star emojis"""
        try:
            rating = float(rating)
            full_stars = int(rating)
            half_star = 1 if rating - full_stars >= 0.5 else 0
            empty_stars = 5 - full_stars - half_star
            
            return "⭐" * full_stars + "⭐" * half_star + "☆" * empty_stars
        except:
            return "⭐⭐⭐⭐⭐"
    
    def _format_number(self, number) -> str:
        """Format large numbers with K, M suffixes"""
        try:
            num = int(number) if isinstance(number, str) else number
            
            if num >= 1000000:
                return f"{num/1000000:.1f}M"
            elif num >= 1000:
                return f"{num/1000:.1f}K"
            else:
                return str(num)
        except:
            return str(number)

    def _synth_price(self) -> str:
        """Generate a realistic original price when real one isn't available."""
        try:
            import random
            # Common Udemy price points
            prices = ["$19.99", "$49.99", "$69.99", "$89.99", "$129.99", "$199.99", "$249.99"]
            return random.choice(prices)
        except Exception:
            return "$99.99"
    
    def _format_source_name(self, source: str) -> str:
        """Format source website name for display"""
        source_names = {
            'discudemy': 'DiscUdemy'
        }
        return source_names.get(source, source.title())
    
    def _get_current_time(self) -> str:
        """Get current time formatted for display"""
        from datetime import datetime
        return datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    
    def _get_error_message(self, course_data: Dict) -> str:
        """Generate a basic error message when formatting fails (HTML)"""
        title = course_data.get('title', 'Unknown Course')
        url = course_data.get('course_url', '#')
        return (
            f"{Config.BOT_LOGO_EMOJI} <b>Free Course Alert!</b>\n\n"
            f"<b>{self._clean_text(title)}</b>\n"
            f"✨ <b>💯FREE</b> ✨\n\n"
            f"🔗 <b>Enroll Link➤</b> {url}\n\n"
            f"🧭 <b>Explore our channels</b>\n"
            f"🛡️ <b><a href=\"https://t.me/+Te_QWfS6W99mNjA1\">Hacking</a></b> | 🧠 <b><a href=\"https://t.me/+5AF4k2itls1iYTM9\">Free AI Tools</a></b>\n"
            f"⚡ <b><a href=\"https://t.me/+6tALVTxVuYMzNjdl\">Quick Deals</a></b> | 📣 <b><a href=\"https://t.me/+RZf4mx2BiZhhMDdl\">Marketing Bot</a></b>\n"
            f"🤖 <b><a href=\"https://t.me/AlienxSaver\">Free Bots</a></b> | 💰 <b><a href=\"https://t.me/+kzHBAvWrS5hiOTg1\">Earning Channel</a></b>\n\n"
            f"👥 <b>Invite friends:</b> 👉 <a href=\"https://t.me/udemyzap\">@udemyzap</a>"
        ).strip()
