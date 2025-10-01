"""
Database utilities for managing course data
"""
import sqlite3
import hashlib
import json
from datetime import datetime, timedelta
from typing import List, Dict, Optional
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from config.settings import Config
import logging

logger = logging.getLogger(__name__)

class CourseDatabase:
    """Handles all database operations for courses"""
    
    def __init__(self, db_file: str = None):
        self.db_file = db_file or Config.DATABASE_FILE
        self.init_database()
    
    def init_database(self):
        """Initialize the database with required tables"""
        try:
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                
                # Create courses table
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS courses (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        course_hash TEXT UNIQUE NOT NULL,
                        title TEXT NOT NULL,
                        instructor TEXT,
                        original_price TEXT,
                        discounted_price TEXT,
                        discount_percentage TEXT,
                        coupon_code TEXT,
                        course_url TEXT NOT NULL,
                        image_url TEXT,
                        rating REAL,
                        students_count INTEGER,
                        duration TEXT,
                        language TEXT,
                        category TEXT,
                        last_updated TEXT,
                        source_website TEXT NOT NULL,
                        scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        posted_to_channel BOOLEAN DEFAULT FALSE,
                        expires_at TIMESTAMP,
                        is_active BOOLEAN DEFAULT TRUE
                    )
                ''')
                
                # Create analytics table
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS analytics (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        date DATE NOT NULL,
                        courses_scraped INTEGER DEFAULT 0,
                        courses_posted INTEGER DEFAULT 0,
                        duplicates_filtered INTEGER DEFAULT 0,
                        errors_count INTEGER DEFAULT 0,
                        source_breakdown TEXT  -- JSON string
                    )
                ''')
                
                # Create indexes for better performance
                cursor.execute('CREATE INDEX IF NOT EXISTS idx_course_hash ON courses(course_hash)')
                cursor.execute('CREATE INDEX IF NOT EXISTS idx_scraped_at ON courses(scraped_at)')
                cursor.execute('CREATE INDEX IF NOT EXISTS idx_posted ON courses(posted_to_channel)')
                cursor.execute('CREATE INDEX IF NOT EXISTS idx_active ON courses(is_active)')
                
                conn.commit()
                logger.info("Database initialized successfully")
                
        except Exception as e:
            logger.error(f"Failed to initialize database: {e}")
            raise
    
    def generate_course_hash(self, course_data: Dict) -> str:
        """Generate a unique hash for a course to detect duplicates"""
        # Use title + instructor + course_url for uniqueness
        hash_string = f"{course_data.get('title', '')}{course_data.get('instructor', '')}{course_data.get('course_url', '')}"
        return hashlib.md5(hash_string.encode()).hexdigest()
    
    def course_exists(self, course_hash: str) -> bool:
        """Check if a course already exists in the database"""
        try:
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                cursor.execute('SELECT 1 FROM courses WHERE course_hash = ? AND is_active = TRUE', (course_hash,))
                return cursor.fetchone() is not None
        except Exception as e:
            logger.error(f"Error checking course existence: {e}")
            return False
    
    def add_course(self, course_data: Dict) -> bool:
        """Add a new course to the database"""
        try:
            course_hash = self.generate_course_hash(course_data)
            
            # Check for duplicates if enabled
            if Config.ENABLE_DUPLICATE_DETECTION and self.course_exists(course_hash):
                logger.debug(f"Duplicate course detected: {course_data.get('title', 'Unknown')}")
                return False
            
            # Calculate expiry time
            expires_at = datetime.now() + timedelta(hours=Config.COURSE_EXPIRY_HOURS)
            
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                cursor.execute('''
                    INSERT OR REPLACE INTO courses (
                        course_hash, title, instructor, original_price, discounted_price,
                        discount_percentage, coupon_code, course_url, image_url, rating,
                        students_count, duration, language, category, last_updated,
                        source_website, expires_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ''', (
                    course_hash,
                    course_data.get('title', ''),
                    course_data.get('instructor', ''),
                    course_data.get('original_price', ''),
                    course_data.get('discounted_price', ''),
                    course_data.get('discount_percentage', ''),
                    course_data.get('coupon_code', ''),
                    course_data.get('course_url', ''),
                    course_data.get('image_url', ''),
                    course_data.get('rating'),
                    course_data.get('students_count'),
                    course_data.get('duration', ''),
                    course_data.get('language', ''),
                    course_data.get('category', ''),
                    course_data.get('last_updated', ''),
                    course_data.get('source_website', ''),
                    expires_at
                ))
                conn.commit()
                logger.info(f"Added new course: {course_data.get('title', 'Unknown')}")
                return True
                
        except Exception as e:
            logger.error(f"Error adding course to database: {e}")
            return False
    
    def get_unposted_courses(self, limit: int = None) -> List[Dict]:
        """Get courses that haven't been posted to the channel yet"""
        try:
            with sqlite3.connect(self.db_file) as conn:
                conn.row_factory = sqlite3.Row
                cursor = conn.cursor()
                
                query = '''
                    SELECT * FROM courses 
                    WHERE posted_to_channel = FALSE 
                    AND is_active = TRUE 
                    AND expires_at > CURRENT_TIMESTAMP
                    ORDER BY scraped_at ASC
                '''
                
                if limit:
                    query += f' LIMIT {limit}'
                
                cursor.execute(query)
                return [dict(row) for row in cursor.fetchall()]
                
        except Exception as e:
            logger.error(f"Error getting unposted courses: {e}")
            return []
    
    def mark_course_posted(self, course_id: int) -> bool:
        """Mark a course as posted to the channel"""
        try:
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                cursor.execute(
                    'UPDATE courses SET posted_to_channel = TRUE WHERE id = ?',
                    (course_id,)
                )
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Error marking course as posted: {e}")
            return False
    
    def cleanup_expired_courses(self) -> int:
        """Remove expired courses from the database"""
        try:
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                cursor.execute(
                    'UPDATE courses SET is_active = FALSE WHERE expires_at < CURRENT_TIMESTAMP'
                )
                affected_rows = cursor.rowcount
                conn.commit()
                logger.info(f"Cleaned up {affected_rows} expired courses")
                return affected_rows
        except Exception as e:
            logger.error(f"Error cleaning up expired courses: {e}")
            return 0
    
    def get_statistics(self) -> Dict:
        """Get database statistics"""
        try:
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                
                # Total courses
                cursor.execute('SELECT COUNT(*) FROM courses WHERE is_active = TRUE')
                total_courses = cursor.fetchone()[0]
                
                # Posted courses
                cursor.execute('SELECT COUNT(*) FROM courses WHERE posted_to_channel = TRUE AND is_active = TRUE')
                posted_courses = cursor.fetchone()[0]
                
                # Pending courses
                cursor.execute('SELECT COUNT(*) FROM courses WHERE posted_to_channel = FALSE AND is_active = TRUE')
                pending_courses = cursor.fetchone()[0]
                
                # Courses by source
                cursor.execute('''
                    SELECT source_website, COUNT(*) 
                    FROM courses 
                    WHERE is_active = TRUE 
                    GROUP BY source_website
                ''')
                source_breakdown = dict(cursor.fetchall())
                
                return {
                    'total_courses': total_courses,
                    'posted_courses': posted_courses,
                    'pending_courses': pending_courses,
                    'source_breakdown': source_breakdown
                }
                
        except Exception as e:
            logger.error(f"Error getting statistics: {e}")
            return {}
    
    def backup_database(self, backup_file: str = None) -> bool:
        """Create a backup of the database"""
        try:
            if not backup_file:
                timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
                backup_file = f"backup_{timestamp}.db"
            
            with sqlite3.connect(self.db_file) as source:
                with sqlite3.connect(backup_file) as backup:
                    source.backup(backup)
            
            logger.info(f"Database backed up to {backup_file}")
            return True
            
        except Exception as e:
            logger.error(f"Error backing up database: {e}")
            return False
