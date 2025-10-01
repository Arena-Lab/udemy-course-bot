"""
Scraper for Real Discount website
"""
from typing import List, Dict
import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from scrapers.base_scraper import BaseScraper
import logging
import re

logger = logging.getLogger(__name__)

class RealDiscountScraper(BaseScraper):
    """Scraper for real.discount website"""
    
    def __init__(self):
        super().__init__('real_discount', 'https://www.real.discount/')
    
    def scrape_courses(self, limit: int = None) -> List[Dict]:
        """Scrape courses from Real Discount"""
        courses = []
        
        try:
            logger.info(f"Starting scrape from {self.name}")
            
            # Get the main page
            response = self._make_request(self.base_url)
            if not response:
                return courses
            
            soup = self._parse_html(response.text)
            if not soup:
                return courses
            
            # Find course containers with multiple fallback selectors
            course_elements = soup.find_all('div', class_='ui-item')
            
            if not course_elements:
                course_elements = soup.find_all('div', class_='course-item')
                
            if not course_elements:
                course_elements = soup.find_all('article')
                
            if not course_elements:
                # Try more generic selectors
                course_elements = soup.find_all('div', class_=lambda x: x and 'course' in x.lower() if x else False)
                
            if not course_elements:
                course_elements = soup.find_all('div', class_=lambda x: x and 'item' in x.lower() if x else False)
                
            if not course_elements:
                # Last resort - look for any divs with links to udemy
                all_divs = soup.find_all('div')
                course_elements = [div for div in all_divs if div.find('a', href=lambda x: x and 'udemy.com' in x if x else False)]
            
            logger.info(f"Found {len(course_elements)} course elements on {self.name}")
            
            for element in course_elements[:limit] if limit else course_elements:
                try:
                    course_data = self._extract_course_data(element)
                    if course_data and self._is_valid_course(course_data):
                        normalized_course = self._normalize_course_data(course_data)
                        courses.append(normalized_course)
                        self.courses_scraped += 1
                        
                        if limit and len(courses) >= limit:
                            break
                            
                except Exception as e:
                    logger.error(f"Error extracting course from {self.name}: {e}")
                    self.errors_count += 1
                    continue
            
            logger.info(f"Successfully scraped {len(courses)} courses from {self.name}")
            
        except Exception as e:
            logger.error(f"Error scraping {self.name}: {e}")
            self.errors_count += 1
        
        return courses
    
    def _extract_course_data(self, element) -> Dict:
        """Extract course data from a course element"""
        course_data = {}
        
        try:
            # Title
            title_elem = (element.find('h3') or 
                         element.find('h2') or 
                         element.find('a', class_='course-title') or
                         element.find('div', class_='course-title'))
            
            if title_elem:
                course_data['title'] = title_elem.get_text(strip=True)
            
            # Course URL - try multiple approaches
            link_elem = element.find('a', href=True)
            if not link_elem:
                # Look for any link in the element
                link_elem = element.find('a')
            
            if link_elem and link_elem.get('href'):
                href = link_elem['href']
                if 'udemy.com' in href:
                    course_data['course_url'] = href
                elif href.startswith('/'):
                    course_data['course_url'] = self.base_url.rstrip('/') + href
                else:
                    course_data['course_url'] = href
            else:
                # Look for onclick or data attributes with URLs
                for attr in ['onclick', 'data-url', 'data-link', 'data-href']:
                    attr_value = element.get(attr, '')
                    if 'udemy.com' in attr_value:
                        import re
                        url_match = re.search(r'https://[^"\'\s]+udemy\.com[^"\'\s]*', attr_value)
                        if url_match:
                            course_data['course_url'] = url_match.group()
                            break
            
            # Instructor
            instructor_elem = (element.find('span', class_='author') or
                             element.find('div', class_='instructor') or
                             element.find('p', class_='instructor'))
            
            if instructor_elem:
                course_data['instructor'] = instructor_elem.get_text(strip=True)
            
            # Price information
            price_elem = element.find('span', class_='price')
            if price_elem:
                price_text = price_elem.get_text(strip=True)
                if 'free' in price_text.lower() or '$0' in price_text:
                    course_data['discounted_price'] = 'Free'
                    course_data['discount_percentage'] = '100% OFF'
            
            # Original price
            original_price_elem = element.find('span', class_='original-price')
            if original_price_elem:
                course_data['original_price'] = original_price_elem.get_text(strip=True)
            
            # Rating
            rating_elem = (element.find('span', class_='rating') or
                          element.find('div', class_='rating'))
            if rating_elem:
                course_data['rating'] = rating_elem.get_text(strip=True)
            
            # Students count
            students_elem = element.find('span', class_='students')
            if students_elem:
                course_data['students_count'] = students_elem.get_text(strip=True)
            
            # Image
            img_elem = element.find('img')
            if img_elem and img_elem.get('src'):
                img_src = img_elem['src']
                if img_src.startswith('/'):
                    course_data['image_url'] = self.base_url.rstrip('/') + img_src
                else:
                    course_data['image_url'] = img_src
            
            # Category
            category_elem = (element.find('span', class_='category') or
                           element.find('div', class_='category'))
            if category_elem:
                course_data['category'] = category_elem.get_text(strip=True)
            
            # Duration
            duration_elem = element.find('span', class_='duration')
            if duration_elem:
                course_data['duration'] = duration_elem.get_text(strip=True)
            
            # Language (often not available, default to English)
            course_data['language'] = 'English'
            
            # If we don't have a direct Udemy URL, try to extract it from onclick or data attributes
            if not course_data.get('course_url') or 'udemy.com' not in course_data.get('course_url', ''):
                # Look for data attributes or onclick handlers that might contain the real URL
                for attr in ['data-url', 'data-link', 'data-course-url']:
                    if element.get(attr):
                        course_data['course_url'] = element[attr]
                        break
                
                # Check onclick attribute
                onclick = element.get('onclick', '')
                if onclick and 'udemy.com' in onclick:
                    url_match = re.search(r'https://[^"\']+udemy\.com[^"\']*', onclick)
                    if url_match:
                        course_data['course_url'] = url_match.group()
            
        except Exception as e:
            logger.error(f"Error extracting course data from element: {e}")
        
        return course_data
