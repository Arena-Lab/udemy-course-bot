"""
Scraper for Udemy Freebies website
"""
from typing import List, Dict
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from scrapers.base_scraper import BaseScraper
import logging

logger = logging.getLogger(__name__)

class UdemyFreebiesScraper(BaseScraper):
    """Scraper for udemyfreebies.com website"""
    
    def __init__(self):
        super().__init__('udemy_freebies', 'https://www.udemyfreebies.com/')
    
    def scrape_courses(self, limit: int = None) -> List[Dict]:
        """Scrape courses from Udemy Freebies"""
        courses = []
        
        try:
            logger.info(f"Starting scrape from {self.name}")
            
            response = self._make_request(self.base_url)
            if not response:
                return courses
            
            soup = self._parse_html(response.text)
            if not soup:
                return courses
            
            # UdemyFreebies has course links with specific pattern
            # Look for h4 elements that contain course titles and links
            course_headers = soup.find_all('h4')
            course_elements = []
            
            for header in course_headers:
                course_link = header.find('a', href=lambda x: x and '/free-udemy-course/' in x if x else False)
                if course_link:
                    # Create a wrapper that includes the header and surrounding content
                    wrapper = header.parent if header.parent else header
                    course_elements.append(wrapper)
            
            # Also try to find course containers with different selectors
            if not course_elements:
                course_elements = soup.find_all('div', class_='theme-block')
                
            if not course_elements:
                course_elements = soup.find_all('article')
                
            if not course_elements:
                # Look for any element containing course links
                all_links = soup.find_all('a', href=lambda x: x and '/free-udemy-course/' in x if x else False)
                course_elements = [link.parent if link.parent else link for link in all_links]
            
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
            # Title - look for h4 with course link or any link with course URL
            title_elem = element.find('h4')
            if title_elem:
                link_in_title = title_elem.find('a')
                if link_in_title:
                    course_data['title'] = link_in_title.get_text(strip=True)
                else:
                    course_data['title'] = title_elem.get_text(strip=True)
            else:
                # Fallback to any link text
                link_elem = element.find('a', href=lambda x: x and '/free-udemy-course/' in x if x else False)
                if link_elem:
                    course_data['title'] = link_elem.get_text(strip=True)
            
            # Course URL - look for UdemyFreebies course detail page
            link_elem = element.find('a', href=lambda x: x and '/free-udemy-course/' in x if x else False)
            if link_elem:
                href = link_elem['href']
                if href.startswith('/'):
                    course_data['course_url'] = self.base_url.rstrip('/') + href
                else:
                    course_data['course_url'] = href
            
            # Instructor
            instructor_elem = element.find('span', class_='author')
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
