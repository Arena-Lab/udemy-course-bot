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
        # Ensure absolute path to avoid mismatched files across working directories
        if not os.path.isabs(self.db_file):
            project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
            self.db_file = os.path.join(project_root, self.db_file)
        self.init_database()
    
    def init_database(self):
        """Initialize the database with required tables"""
        try:
            with sqlite3.connect(self.db_file) as conn:
                # Pragmas for reliability and performance on long-running bots
                try:
                    conn.execute('PRAGMA journal_mode=WAL')
                    conn.execute('PRAGMA synchronous=NORMAL')
                    conn.execute('PRAGMA temp_store=MEMORY')
                    conn.execute('PRAGMA foreign_keys=ON')
                except Exception:
                    pass
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
                        subtitle TEXT,
                        description TEXT,
                        level TEXT,
                        lectures INTEGER,
                        learn TEXT,
                        requirements TEXT,
                        audience TEXT,
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
                
                # Create scrape progress table (for backfill cursors per source)
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS scrape_progress (
                        source TEXT PRIMARY KEY,
                        last_page INTEGER DEFAULT 0,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ''')
                
                # Generic key-value progress store for arbitrary cursors (e.g., fresh rotation)
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS scrape_kv (
                        k TEXT PRIMARY KEY,
                        v TEXT,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ''')
                
                # Create indexes for better performance
                cursor.execute('CREATE INDEX IF NOT EXISTS idx_course_hash ON courses(course_hash)')
                cursor.execute('CREATE INDEX IF NOT EXISTS idx_scraped_at ON courses(scraped_at)')
                cursor.execute('CREATE INDEX IF NOT EXISTS idx_posted ON courses(posted_to_channel)')
                cursor.execute('CREATE INDEX IF NOT EXISTS idx_active ON courses(is_active)')
                
                conn.commit()

                # Migration: ensure new columns exist for older DBs
                try:
                    cursor.execute("PRAGMA table_info(courses)")
                    cols = {row[1] for row in cursor.fetchall()}
                    def ensure_col(name: str, type_decl: str):
                        if name not in cols:
                            try:
                                cursor.execute(f"ALTER TABLE courses ADD COLUMN {name} {type_decl}")
                            except Exception:
                                pass
                    ensure_col('subtitle', 'TEXT')
                    ensure_col('description', 'TEXT')
                    ensure_col('level', 'TEXT')
                    ensure_col('lectures', 'INTEGER')
                    ensure_col('learn', 'TEXT')  # JSON array
                    ensure_col('requirements', 'TEXT')  # JSON array
                    ensure_col('audience', 'TEXT')  # JSON array
                    conn.commit()
                except Exception:
                    pass
                logger.info("Database initialized successfully")
                # One-time/backfill: if legacy rows have empty source_website, and only one
                # scraper is enabled, assign that scraper name so breakdowns are accurate.
                try:
                    enabled = [k for k, v in getattr(Config, 'SCRAPERS', {}).items() if v]
                    if len(enabled) == 1:
                        default_source = enabled[0]
                        cursor.execute(
                            """
                            UPDATE courses
                               SET source_website = ?
                             WHERE (source_website IS NULL OR source_website = '')
                            """,
                            (default_source,)
                        )
                        conn.commit()
                    # Also honor explicit default if provided via config
                    explicit_default = getattr(Config, 'DEFAULT_SOURCE_FOR_EMPTY', '').strip()
                    if explicit_default:
                        cursor.execute(
                            """
                            UPDATE courses
                               SET source_website = ?
                             WHERE (source_website IS NULL OR source_website = '')
                            """,
                            (explicit_default,)
                        )
                        conn.commit()
                except Exception:
                    pass
                
        except Exception as e:
            logger.error(f"Failed to initialize database: {e}")
            raise
    
    def generate_course_hash(self, course_data: Dict) -> str:
        """Generate a unique hash for a course to detect duplicates"""
        # Use only stable identifiers: title + course_url (instructor is randomized)
        title = course_data.get('title', '').strip().lower()
        course_url = course_data.get('course_url', '').strip()
        
        # Extract course slug from URL for more reliable matching
        course_slug = ''
        if 'udemy.com/course/' in course_url:
            try:
                import re
                match = re.search(r'/course/([^/?]+)', course_url)
                if match:
                    course_slug = match.group(1)
            except:
                pass
        
        # Use title + course_slug for uniqueness (more reliable than full URL with changing coupon codes)
        hash_string = f"{title}|{course_slug}|{course_url.split('?')[0]}"  # URL without query params
        return hashlib.md5(hash_string.encode()).hexdigest()
    
    def course_exists(self, course_hash: str) -> bool:
        """Check if a course already exists in the database"""
        try:
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                try:
                    cursor.execute('SELECT 1 FROM courses WHERE course_hash = ? AND is_active = TRUE', (course_hash,))
                except sqlite3.OperationalError as oe:
                    if 'no such table' in str(oe).lower():
                        try:
                            self.init_database()
                            cursor.execute('SELECT 1 FROM courses WHERE course_hash = ? AND is_active = TRUE', (course_hash,))
                        except Exception:
                            raise
                    else:
                        raise
                return cursor.fetchone() is not None
        except Exception as e:
            logger.error(f"Error checking course existence: {e}")
            return False

    def _enrich_existing_course(self, course_hash: str, course_data: Dict) -> None:
        """Update existing course row with any newly provided real metadata fields."""
        try:
            updatable_fields = [
                'title', 'instructor', 'original_price', 'discounted_price', 'discount_percentage',
                'coupon_code', 'image_url', 'rating', 'students_count', 'duration', 'language',
                'category', 'subtitle', 'description', 'level', 'lectures', 'learn', 'requirements', 'audience', 'last_updated', 'source_website'
            ]
            # Build dynamic SET clause only for fields present in course_data and non-empty
            sets = []
            values = []
            for f in updatable_fields:
                if f in course_data and course_data.get(f) not in [None, '', []]:
                    sets.append(f"{f} = ?")
                    values.append(course_data.get(f))
            if not sets:
                return
            values.append(course_hash)
            query = f"UPDATE courses SET {', '.join(sets)} WHERE course_hash = ?"
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                cursor.execute(query, values)
                conn.commit()
        except Exception as e:
            logger.debug(f"No enrichment applied (or failed) for course_hash {course_hash}: {e}")
    
    def enrich_with(self, course_data: Dict) -> None:
        """Public helper: compute hash and enrich existing course if present."""
        try:
            course_hash = self.generate_course_hash(course_data)
            if self.course_exists(course_hash):
                self._enrich_existing_course(course_hash, course_data)
        except Exception as e:
            logger.debug(f"enrich_with failed: {e}")
    
    def add_course(self, course_data: Dict) -> bool:
        """Add a new course to the database"""
        try:
            course_hash = self.generate_course_hash(course_data)
            
            # Check for duplicates if enabled
            if Config.ENABLE_DUPLICATE_DETECTION and self.course_exists(course_hash):
                # Enrich existing record with any newly available real metadata
                self._enrich_existing_course(course_hash, course_data)
                logger.debug(f"Duplicate course enriched (if applicable): {course_data.get('title', 'Unknown')}")
                return False
            
            # Calculate expiry time
            expires_at = datetime.now() + timedelta(hours=Config.COURSE_EXPIRY_HOURS)
            
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                # Serialize list-like fields to JSON strings
                def _to_json(val):
                    try:
                        if isinstance(val, (list, dict)):
                            return json.dumps(val, ensure_ascii=False)
                        return json.dumps(val, ensure_ascii=False) if val not in [None, ''] else None
                    except Exception:
                        return None

                cursor.execute('''
                    INSERT OR REPLACE INTO courses (
                        course_hash, title, instructor, original_price, discounted_price,
                        discount_percentage, coupon_code, course_url, image_url, rating,
                        students_count, duration, language, category, subtitle, description,
                        level, lectures, learn, requirements, audience, last_updated,
                        source_website, expires_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                    course_data.get('subtitle', ''),
                    course_data.get('description', ''),
                    course_data.get('level', ''),
                    course_data.get('lectures'),
                    _to_json(course_data.get('learn')),
                    _to_json(course_data.get('requirements')),
                    _to_json(course_data.get('audience')),
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
    
    def get_active_courses(self, limit: int = None) -> List[Dict]:
        """Get active, non-expired courses"""
        try:
            with sqlite3.connect(self.db_file) as conn:
                conn.row_factory = sqlite3.Row
                cursor = conn.cursor()
                query = '''
                    SELECT * FROM courses
                    WHERE is_active = TRUE
                      AND expires_at > CURRENT_TIMESTAMP
                    ORDER BY scraped_at DESC
                '''
                if limit:
                    query += f' LIMIT {int(limit)}'
                cursor.execute(query)
                return [dict(row) for row in cursor.fetchall()]
        except Exception as e:
            logger.error(f"Error getting active courses: {e}")
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

    # ---------------- Progress helpers ----------------
    def get_scrape_progress(self, source: str) -> int:
        """Return last_page cursor for a given source, or 0 if none."""
        try:
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                cursor.execute('SELECT last_page FROM scrape_progress WHERE source = ?', (source,))
                row = cursor.fetchone()
                return int(row[0]) if row and row[0] is not None else 0
        except Exception as e:
            logger.error(f"Error reading scrape progress for {source}: {e}")
            return 0

    def set_scrape_progress(self, source: str, last_page: int) -> bool:
        """Upsert last_page cursor for a given source."""
        try:
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                cursor.execute(
                    '''INSERT INTO scrape_progress (source, last_page, updated_at)
                       VALUES (?, ?, CURRENT_TIMESTAMP)
                       ON CONFLICT(source) DO UPDATE SET last_page = excluded.last_page, updated_at = CURRENT_TIMESTAMP''',
                    (source, int(last_page))
                )
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Error writing scrape progress for {source}: {e}")
            return False

    # ---------------- Generic KV helpers ----------------
    def get_progress_key(self, key: str, default: int = 0) -> int:
        try:
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                cursor.execute('SELECT v FROM scrape_kv WHERE k = ?', (key,))
                row = cursor.fetchone()
                if not row or row[0] is None:
                    return int(default)
                try:
                    return int(row[0])
                except Exception:
                    return int(default)
        except Exception as e:
            logger.error(f"Error reading KV progress for {key}: {e}")
            return int(default)

    def set_progress_key(self, key: str, value: int) -> bool:
        try:
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                cursor.execute(
                    '''INSERT INTO scrape_kv (k, v, updated_at)
                       VALUES (?, ?, CURRENT_TIMESTAMP)
                       ON CONFLICT(k) DO UPDATE SET v = excluded.v, updated_at = CURRENT_TIMESTAMP''',
                    (key, str(int(value)))
                )
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Error writing KV progress for {key}: {e}")
            return False

    def purge_inactive_courses(self, older_than_days: int = 30) -> int:
        """Hard-delete inactive courses older than retention window to keep DB lean."""
        try:
            with sqlite3.connect(self.db_file) as conn:
                cursor = conn.cursor()
                cursor.execute(
                    '''DELETE FROM courses
                       WHERE is_active = FALSE
                         AND scraped_at < datetime('now', ?)
                    ''', (f'-{older_than_days} days',)
                )
                deleted = cursor.rowcount
                conn.commit()
                if deleted:
                    logger.info(f"Purged {deleted} inactive courses older than {older_than_days} days")
                return deleted or 0
        except Exception as e:
            logger.error(f"Error purging inactive courses: {e}")
            return 0

    def vacuum(self) -> bool:
        """Compact the database to reclaim disk space (safe to run periodically)."""
        try:
            with sqlite3.connect(self.db_file) as conn:
                conn.isolation_level = None  # Required by VACUUM in some contexts
                cursor = conn.cursor()
                cursor.execute('VACUUM')
            logger.info("Database VACUUM completed")
            return True
        except Exception as e:
            logger.error(f"Error running VACUUM: {e}")
            return False
    
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
                # Coalesce empty/NULL sources into a configured default or 'unknown'
                if '' in source_breakdown or None in source_breakdown:
                    empty_total = (source_breakdown.get('', 0) or 0) + (source_breakdown.get(None, 0) or 0)
                    # Remove raw keys first
                    if '' in source_breakdown:
                        del source_breakdown['']
                    if None in source_breakdown:
                        del source_breakdown[None]
                    target = getattr(Config, 'DEFAULT_SOURCE_FOR_EMPTY', '').strip() or 'unknown'
                    source_breakdown[target] = source_breakdown.get(target, 0) + empty_total
                
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
