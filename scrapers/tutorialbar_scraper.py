"""
Scraper for TutorialBar website - Primary upstream source
"""
from typing import List, Dict
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from scrapers.base_scraper import BaseScraper
import logging

logger = logging.getLogger(__name__)

class TutorialBarScraper(BaseScraper):
    """Scraper for tutorialbar.com website - mentioned as primary source"""
    
    def __init__(self):
        super().__init__('tutorialbar', 'https://www.tutorialbar.com/')
    
    def scrape_courses(self, limit: int = None) -> List[Dict]:
        """Scrape courses from TutorialBar"""
        courses = []
        
        try:
            logger.info(f"Starting scrape from {self.name}")
            
            # Try multiple endpoints
            endpoints = [
                'https://www.tutorialbar.com/',
                'https://www.tutorialbar.com/all-courses/',
                'https://www.tutorialbar.com/free-courses/'
            ]
            
            for endpoint in endpoints:
                response = self._make_request(endpoint)
                if not response:
                    continue
                
                soup = self._parse_html(response.text)
                if not soup:
                    continue
                
                # Find course containers
                course_elements = soup.find_all('div', class_='course-item')
                
                if not course_elements:
                    course_elements = soup.find_all('article')
                    
                if not course_elements:
                    course_elements = soup.find_all('div', class_=lambda x: x and 'course' in x.lower() if x else False)
                
                if not course_elements:
                    # Look for any divs with Udemy links
                    all_divs = soup.find_all('div')
                    course_elements = [div for div in all_divs if div.find('a', href=lambda x: x and 'udemy.com' in x if x else False)]
                
                logger.info(f"Found {len(course_elements)} course elements on {self.name} from {endpoint}")
                
                if course_elements:
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
                    
                    if courses:
                        break  # Found courses, no need to try other endpoints
            
            logger.info(f"Successfully scraped {len(courses)} courses from {self.name}")
            
        except Exception as e:
            logger.error(f"Error scraping {self.name}: {e}")
            self.errors_count += 1
        
        return courses
    
    def _extract_course_data(self, element) -> Dict:
        """Extract course data from a course element"""
        course_data = {}
        
        try:
            # Title - try multiple selectors
            title_elem = (element.find('h3') or 
                         element.find('h2') or 
                         element.find('h1') or
                         element.find('a', class_='course-title') or
                         element.find('div', class_='title'))
            
            if title_elem:
                course_data['title'] = title_elem.get_text(strip=True)
            
            # Course URL - prioritize Udemy links
            udemy_link = element.find('a', href=lambda x: x and 'udemy.com' in x if x else False)
            if udemy_link:
                course_data['course_url'] = udemy_link['href']
            else:
                # Fallback to any link
                link_elem = element.find('a', href=True)
                if link_elem:
                    href = link_elem['href']
                    if href.startswith('/'):
                        course_data['course_url'] = 'https://www.tutorialbar.com' + href
                    else:
                        course_data['course_url'] = href
            
            # Instructor
            instructor_elem = (element.find('span', class_='author') or
                             element.find('div', class_='instructor') or
                             element.find('p', class_='by'))
            
            if instructor_elem:
                course_data['instructor'] = instructor_elem.get_text(strip=True)
            
            # Price information
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
