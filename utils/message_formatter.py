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
        """Format a course into a branded Telegram message"""
        try:
            # Clean and prepare course data
            title = self._clean_text(course_data.get('title', 'Unknown Course'))
            instructor = self._clean_text(course_data.get('instructor', 'Unknown Instructor'))
            course_url = course_data.get('course_url', '')
            
            # Build the message
            message_parts = []
            
            # Header with branding
            message_parts.append(f"{Config.BOT_LOGO_EMOJI} <b>Free Courses With Certificates!</b>")
            message_parts.append("")
            
            # Course title and instructor
            if course_data.get('discount_percentage'):
                discount_text = f"({course_data['discount_percentage']} Free) "
            else:
                discount_text = "(100% Free) "
            
            message_parts.append(f"{discount_text}<b>{title}</b>")
            
            if instructor and instructor != 'Unknown Instructor':
                message_parts.append(f"👨‍🏫 <b>Instructor:</b> {instructor}")
            
            # Course details
            details = []
            
            if Config.MESSAGE_OPTIONS['include_rating'] and course_data.get('rating'):
                rating = course_data['rating']
                stars = self._get_star_rating(rating)
                details.append(f"⭐ <b>Rating:</b> {rating}/5 {stars}")
            
            if Config.MESSAGE_OPTIONS['include_students'] and course_data.get('students_count'):
                students = self._format_number(course_data['students_count'])
                details.append(f"👥 <b>Students:</b> {students}")
            
            if Config.MESSAGE_OPTIONS['include_duration'] and course_data.get('duration'):
                details.append(f"⏱️ <b>Duration:</b> {course_data['duration']}")
            
            if Config.MESSAGE_OPTIONS['include_language'] and course_data.get('language'):
                details.append(f"🌐 <b>Language:</b> {course_data['language']}")
            
            if course_data.get('category'):
                details.append(f"📚 <b>Category:</b> {course_data['category']}")
            
            if Config.MESSAGE_OPTIONS['include_last_updated'] and course_data.get('last_updated'):
                details.append(f"🔄 <b>Last Updated:</b> {course_data['last_updated']}")
            
            # Add details to message
            if details:
                message_parts.append("")
                message_parts.extend(details)
            
            # Enrollment link
            message_parts.append("")
            message_parts.append(f"🔗 <b>Enroll Link➤</b> {course_url}")
            
            # Branding footer
            message_parts.append("")
            message_parts.append(f"🔥<b>{Config.SECRET_CHANNEL_TEXT}</b>")
            message_parts.append(f"{Config.INVITE_FRIENDS_TEXT} {Config.CHANNEL_LINK}")
            
            return "\n".join(message_parts)
            
        except Exception as e:
            logger.error(f"Error formatting course message: {e}")
            return self._get_error_message(course_data)
    
    def create_inline_keyboard(self, course_url: str):
        """Create inline keyboard with enroll button"""
        from telegram import InlineKeyboardButton, InlineKeyboardMarkup
        return InlineKeyboardMarkup([
            [InlineKeyboardButton("🎓 Enroll Now", url=course_url)]
        ])
    
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
    
    def _format_source_name(self, source: str) -> str:
        """Format source website name for display"""
        source_names = {
            'real_discount': 'Real Discount',
            'discudemy': 'DiscUdemy',
            'udemy_freebies': 'Udemy Freebies',
            'yofreesamples': 'YoFreeSamples',
            'coursesity': 'Coursesity'
        }
        return source_names.get(source, source.title())
    
    def _get_current_time(self) -> str:
        """Get current time formatted for display"""
        from datetime import datetime
        return datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    
    def _get_error_message(self, course_data: Dict) -> str:
        """Generate a basic error message when formatting fails"""
        title = course_data.get('title', 'Unknown Course')
        url = course_data.get('course_url', '#')
        
        return f"""
{Config.BOT_LOGO_EMOJI} **Free Course Alert!**

**{title}**

🔗 **Enroll Link➤** {url}

🔥**{Config.SECRET_CHANNEL_TEXT}**
{Config.INVITE_FRIENDS_TEXT} {Config.CHANNEL_LINK}
        """.strip()
    
    def create_inline_keyboard(self, course_url: str) -> Dict:
        """Create inline keyboard for course message"""
        return {
            "inline_keyboard": [
                [
                    {
                        "text": "🎓 Enroll Now",
                        "url": course_url
                    },
                    {
                        "text": "📢 Join Channel",
                        "url": Config.CHANNEL_LINK
                    }
                ]
            ]
        }
