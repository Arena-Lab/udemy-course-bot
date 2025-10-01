"""
Scraper for YoFreeSamples website
"""
from typing import List, Dict
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from scrapers.base_scraper import BaseScraper
import logging

logger = logging.getLogger(__name__)

class YoFreeSamplesScraper(BaseScraper):
    """Scraper for yofreesamples.com website"""
    
    def __init__(self):
        super().__init__('yofreesamples', 'https://yofreesamples.com/courses/free-discounted-udemy-courses-list/')
    
    def scrape_courses(self, limit: int = None) -> List[Dict]:
        """Scrape courses from YoFreeSamples"""
        courses = []
        
        try:
            logger.info(f"Starting scrape from {self.name}")
            
            response = self._make_request(self.base_url)
            if not response:
                return courses
            
            soup = self._parse_html(response.text)
            if not soup:
                return courses
            
            # Find course containers
            course_elements = soup.find_all('div', class_='course-item')
            
            if not course_elements:
                course_elements = soup.find_all('article')
                
            if not course_elements:
                course_elements = soup.find_all('div', class_='post')
            
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
            title_elem = (element.find('h2') or 
                         element.find('h3') or 
                         element.find('a', class_='course-title'))
            
            if title_elem:
                course_data['title'] = title_elem.get_text(strip=True)
            
            # Course URL - look for Udemy links
            links = element.find_all('a', href=True)
            for link in links:
                href = link['href']
                if 'udemy.com' in href:
                    course_data['course_url'] = href
                    break
            
            # If no direct Udemy link, use the first link
            if not course_data.get('course_url') and links:
                href = links[0]['href']
                if href.startswith('/'):
                    course_data['course_url'] = 'https://yofreesamples.com' + href
                else:
                    course_data['course_url'] = href
            
            # Instructor
            instructor_elem = element.find('span', class_='instructor')
            if instructor_elem:
                course_data['instructor'] = instructor_elem.get_text(strip=True)
            
            # Price info
            course_data['discounted_price'] = 'Free'
            course_data['discount_percentage'] = '100% OFF'
            
            # Rating
            rating_elem = element.find('span', class_='rating')
            if rating_elem:
                course_data['rating'] = rating_elem.get_text(strip=True)
            
            # Students
            students_elem = element.find('span', class_='students')
            if students_elem:
                course_data['students_count'] = students_elem.get_text(strip=True)
            
            # Image
            img_elem = element.find('img')
            if img_elem and img_elem.get('src'):
                course_data['image_url'] = img_elem['src']
            
            # Category
            category_elem = element.find('span', class_='category')
            if category_elem:
                course_data['category'] = category_elem.get_text(strip=True)
            
            # Language
            course_data['language'] = 'English'
            
        except Exception as e:
            logger.error(f"Error extracting course data: {e}")
        
        return course_data
