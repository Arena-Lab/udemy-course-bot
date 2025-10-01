"""
Base scraper class for all website scrapers
"""
import requests
import time
import logging
from abc import ABC, abstractmethod
from typing import List, Dict, Optional
from bs4 import BeautifulSoup
import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from config.settings import Config
import random

logger = logging.getLogger(__name__)

class BaseScraper(ABC):
    """Abstract base class for all course scrapers"""
    
    def __init__(self, name: str, base_url: str):
        self.name = name
        self.base_url = base_url
        self.session = self._create_session()
        self.headers = {
            'User-Agent': Config.USER_AGENT,
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.5',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1'
        }
        self.courses_scraped = 0
        self.errors_count = 0
    
    def _create_session(self) -> requests.Session:
        """Create a configured requests session"""
        session = requests.Session()
        session.headers.update({
            'User-Agent': Config.USER_AGENT,
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.5',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1'
        })
        return session
    
    def _make_request(self, url: str, allow_redirects: bool = True, **kwargs) -> Optional[requests.Response]:
        """Make HTTP request with error handling"""
        try:
            response = requests.get(
                url,
                headers=self.headers,
                timeout=Config.REQUEST_TIMEOUT,
                allow_redirects=allow_redirects,
                **kwargs
            )
            response.raise_for_status()
            return response
            
        except requests.exceptions.RequestException as e:
            logger.error(f"Request failed for {self.name} - {url}: {e}")
            self.errors_count += 1
            return None
        except Exception as e:
            logger.error(f"Unexpected error in {self.name} request: {e}")
            self.errors_count += 1
            return None
    
    def _parse_html(self, html_content: str) -> Optional[BeautifulSoup]:
        """Parse HTML content safely"""
        try:
            return BeautifulSoup(html_content, 'html.parser')
        except Exception as e:
            logger.error(f"HTML parsing failed for {self.name}: {e}")
            return None
    
    def _clean_text(self, text: str) -> str:
        """Clean and normalize text"""
        if not text:
            return ""
        
        # Remove extra whitespace and newlines
        text = ' '.join(text.split())
        
        # Remove special characters that might break formatting
        text = text.replace('\n', ' ').replace('\r', ' ').replace('\t', ' ')
        
        return text.strip()
    
    def _extract_price(self, price_text: str) -> str:
        """Extract and normalize price from text"""
        if not price_text:
            return ""
        
        # Remove currency symbols and extra text
        import re
        price_match = re.search(r'[\d,]+\.?\d*', price_text.replace(',', ''))
        if price_match:
            return f"${price_match.group()}"
        
        return price_text.strip()
    
    def _extract_rating(self, rating_text: str) -> Optional[float]:
        """Extract numeric rating from text"""
        if not rating_text:
            return None
        
        try:
            import re
            rating_match = re.search(r'(\d+\.?\d*)', rating_text)
            if rating_match:
                rating = float(rating_match.group(1))
                return min(rating, 5.0)  # Cap at 5.0
        except:
            pass
        
        return None
    
    def _extract_students_count(self, students_text: str) -> Optional[int]:
        """Extract student count from text"""
        if not students_text:
            return None
        
        try:
            import re
            # Handle formats like "1,234 students", "1.2K students", "1.5M students"
            students_text = students_text.lower().replace(',', '')
            
            if 'k' in students_text:
                match = re.search(r'(\d+\.?\d*)\s*k', students_text)
                if match:
                    return int(float(match.group(1)) * 1000)
            elif 'm' in students_text:
                match = re.search(r'(\d+\.?\d*)\s*m', students_text)
                if match:
                    return int(float(match.group(1)) * 1000000)
            else:
                match = re.search(r'(\d+)', students_text)
                if match:
                    return int(match.group(1))
        except:
            pass
        
        return None
    
    def _is_valid_course(self, course_data: Dict) -> bool:
        """Validate if course data is complete and valid"""
        required_fields = ['title', 'course_url']
        
        for field in required_fields:
            if not course_data.get(field):
                logger.debug(f"Invalid course - missing {field}: {course_data}")
                return False
        
        # Check if URL is actually a Udemy URL
        course_url = course_data.get('course_url', '')
        if 'udemy.com' not in course_url:
            logger.debug(f"Invalid course - not a Udemy URL: {course_url}")
            return False
        
        return True
    
    def _normalize_course_data(self, course_data: Dict) -> Dict:
        """Normalize course data with consistent field names"""
        normalized = {
            'title': self._clean_text(course_data.get('title', '')),
            'instructor': self._clean_text(course_data.get('instructor', '')),
            'original_price': self._extract_price(course_data.get('original_price', '')),
            'discounted_price': self._extract_price(course_data.get('discounted_price', '')),
            'discount_percentage': course_data.get('discount_percentage', ''),
            'coupon_code': course_data.get('coupon_code', ''),
            'course_url': course_data.get('course_url', ''),
            'image_url': course_data.get('image_url', ''),
            'rating': self._extract_rating(course_data.get('rating', '')),
            'students_count': self._extract_students_count(course_data.get('students_count', '')),
            'duration': self._clean_text(course_data.get('duration', '')),
            'language': self._clean_text(course_data.get('language', '')),
            'category': self._clean_text(course_data.get('category', '')),
            'last_updated': self._clean_text(course_data.get('last_updated', '')),
            'source_website': self.name
        }
        
        return normalized
    
    @abstractmethod
    def scrape_courses(self, limit: int = None) -> List[Dict]:
        """
        Scrape courses from the website
        
        Args:
            limit: Maximum number of courses to scrape
            
        Returns:
            List of course dictionaries
        """
        pass
    
    def get_statistics(self) -> Dict:
        """Get scraper statistics"""
        return {
            'name': self.name,
            'courses_scraped': self.courses_scraped,
            'errors_count': self.errors_count,
            'success_rate': (self.courses_scraped / max(1, self.courses_scraped + self.errors_count)) * 100
        }
    
    def reset_statistics(self):
        """Reset scraper statistics"""
        self.courses_scraped = 0
        self.errors_count = 0
