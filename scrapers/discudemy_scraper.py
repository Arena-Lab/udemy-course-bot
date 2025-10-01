"""
Scraper for DiscUdemy website
"""
from typing import List, Dict
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from scrapers.base_scraper import BaseScraper
import logging
import re

logger = logging.getLogger(__name__)

class DiscUdemyScraper(BaseScraper):
    """Scraper for discudemy.com website"""
    
    def __init__(self):
        super().__init__('discudemy', 'https://www.discudemy.com/')
    
    def scrape_courses(self, limit: int = None) -> List[Dict]:
        """Scrape courses from DiscUdemy"""
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
            
            # Look for course links that are NOT navigation/footer links
            exclude_patterns = ['all', 'category', 'language', 'search', 'about', 'contact', 'faq', 'review', 'frequently-asked-question', 'privacy', 'terms']
            
            # Find all links that could be courses
            all_links = soup.find_all('a', href=True)
            course_links = []
            
            for link in all_links:
                href = link.get('href', '')
                text = link.get_text(strip=True)
                
                # Skip if it's a navigation/footer link
                if any(pattern in href.lower() for pattern in exclude_patterns):
                    continue
                
                # Skip if it's just the homepage or feed
                if href in ['/', 'http://www.discudemy.com/', 'https://www.discudemy.com/']:
                    continue
                
                # Look for course-like links (either direct course pages or /go/ redirects)
                if ('discudemy.com/' in href and len(href.split('/')[-1]) > 10) or '/go/' in href:
                    course_links.append(link)
            
            logger.info(f"Found {len(course_links)} potential course links on {self.name}")
            
            # Process each course link
            for link in course_links[:limit] if limit else course_links:
                try:
                    course_data = self._extract_course_from_link(link)
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
    
    def _extract_course_from_link(self, link) -> Dict:
        """Extract course data from a DiscUdemy course link"""
        course_data = {}
        
        try:
            href = link.get('href', '')
            title = link.get_text(strip=True)
            
            # Skip if no title or href
            if not title or not href:
                return None
            
            # Make href absolute
            if href.startswith('/'):
                href = 'https://www.discudemy.com' + href
            
            course_data['title'] = title
            
            # If it's a /go/ link, follow it directly to get the Udemy URL
            if '/go/' in href:
                logger.debug(f"Found /go/ redirect link: {href}")
                real_udemy_url = self._follow_redirect_to_udemy(href)
                if real_udemy_url:
                    course_data['course_url'] = real_udemy_url
                    logger.info(f"Successfully extracted Udemy URL: {real_udemy_url}")
                else:
                    course_data['course_url'] = href  # Fallback
            else:
                # It's a regular course page, extract the /go/ link from it
                logger.debug(f"Processing course page: {href}")
                real_udemy_url = self._extract_real_udemy_url(href)
                if real_udemy_url:
                    course_data['course_url'] = real_udemy_url
                    logger.info(f"Successfully extracted Udemy URL: {real_udemy_url}")
                else:
                    course_data['course_url'] = href  # Fallback
            
            # Set default values for missing fields
            course_data.setdefault('instructor', 'Unknown')
            course_data.setdefault('discounted_price', 'Free')
            course_data.setdefault('original_price', '$199')
            course_data.setdefault('discount_percentage', '100% OFF')
            course_data.setdefault('rating', '4.5')
            course_data.setdefault('students_count', '1000+')
            course_data.setdefault('language', 'English')
            course_data.setdefault('category', 'Development')
            course_data.setdefault('description', 'Learn valuable skills with this comprehensive course.')
            course_data.setdefault('image_url', None)
            
            return course_data
            
        except Exception as e:
            logger.error(f"Error extracting course from link: {e}")
            return None
    
    def _extract_course_data(self, element) -> Dict:
        """Extract course data from a course element"""
        course_data = {}
        
        try:
            # For DiscUdemy, the element contains a link with the course title
            link_elem = element.find('a')
            if link_elem:
                # Title is the link text
                course_data['title'] = link_elem.get_text(strip=True)
                
                # Course URL - need to get the actual Udemy URL from the DiscUdemy page
                discudemy_url = link_elem.get('href')
                if discudemy_url:
                    if discudemy_url.startswith('/'):
                        discudemy_url = 'https://www.discudemy.com' + discudemy_url
                    
                    # Extract the real Udemy URL with coupon code
                    real_udemy_url = self._extract_real_udemy_url(discudemy_url)
                    if real_udemy_url:
                        course_data['course_url'] = real_udemy_url
                    else:
                        # Fallback to DiscUdemy URL if extraction fails
                        course_data['course_url'] = discudemy_url
            
            # Course URL
            link_elem = element.find('a', href=True)
            if link_elem:
                href = link_elem['href']
                if href.startswith('/'):
                    course_data['course_url'] = self.base_url.rstrip('/') + href
            
            # Instructor
            instructor_elem = element.find('p', class_='author')
            if instructor_elem:
                course_data['instructor'] = instructor_elem.get_text(strip=True)
            # Price and discount
            price_elem = element.find('span', class_='price')
            if price_elem:
                course_data['discounted_price'] = 'Free'
                course_data['discount_percentage'] = '100% OFF'
            
            # Rating
            rating_elem = element.find('span', class_='rating-number')
            if rating_elem:
                course_data['rating'] = rating_elem.get_text(strip=True)
            
            # Students
            students_elem = element.find('span', class_='student-count')
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
    
    def _extract_real_udemy_url(self, discudemy_url: str) -> str:
        """Extract the real Udemy URL with coupon code from DiscUdemy page"""
        try:
            logger.debug(f"Extracting real Udemy URL from: {discudemy_url}")
            
            # Make request to the DiscUdemy course page
            response = self._make_request(discudemy_url)
            if not response:
                return None
            
            soup = self._parse_html(response.text)
            if not soup:
                return None
            
            # Method 1: Look for the "Take Course" or "Get Course" button that leads to /go/ endpoint
            course_buttons = soup.find_all('a', string=lambda x: x and any(word in x.lower() for word in ['take course', 'get course', 'enroll', 'free']) if x else False)
            for button in course_buttons:
                href = button.get('href')
                if href and '/go/' in href:
                    # This is the redirect URL, follow it to get the real Udemy URL
                    if href.startswith('/'):
                        redirect_url = 'https://www.discudemy.com' + href
                    else:
                        redirect_url = href
                    
                    logger.debug(f"Found redirect URL: {redirect_url}")
                    real_url = self._follow_redirect_to_udemy(redirect_url)
                    if real_url:
                        return real_url
            
            # Method 2: Look for direct Udemy links in the page
            udemy_links = soup.find_all('a', href=lambda x: x and 'udemy.com' in x if x else False)
            for link in udemy_links:
                href = link.get('href')
                if href and 'couponCode=' in href:
                    logger.debug(f"Found direct Udemy link: {href}")
                    return href
            
            # Method 2: Look for JavaScript variables containing the Udemy URL
            scripts = soup.find_all('script')
            for script in scripts:
                if script.string:
                    script_content = script.string
                    
                    # Look for common patterns where Udemy URLs are stored
                    patterns = [
                        r'udemy\.com/course/[^"\']+\?couponCode=[^"\']+',
                        r'https://www\.udemy\.com/course/[^"\']+\?couponCode=[^"\']+',
                        r'"url":\s*"([^"]*udemy\.com[^"]*couponCode=[^"]*)"',
                        r'window\.location\s*=\s*["\']([^"\']*udemy\.com[^"\']*)["\']',
                        r'href\s*=\s*["\']([^"\']*udemy\.com[^"\']*couponCode=[^"\']*)["\']'
                    ]
                    
                    import re
                    for pattern in patterns:
                        matches = re.findall(pattern, script_content, re.IGNORECASE)
                        if matches:
                            udemy_url = matches[0] if isinstance(matches[0], str) else matches[0]
                            if not udemy_url.startswith('http'):
                                udemy_url = 'https://' + udemy_url
                            logger.debug(f"Found Udemy URL in script: {udemy_url}")
                            return udemy_url
            
            # Method 3: Look for buttons or elements with data attributes
            buttons = soup.find_all(['button', 'a', 'div'], attrs={'data-url': True})
            for button in buttons:
                data_url = button.get('data-url')
                if data_url and 'udemy.com' in data_url and 'couponCode=' in data_url:
                    logger.debug(f"Found Udemy URL in data attribute: {data_url}")
                    return data_url
            
            # Method 4: Look for meta tags with Udemy URLs
            meta_tags = soup.find_all('meta')
            for meta in meta_tags:
                content = meta.get('content', '')
                if 'udemy.com' in content and 'couponCode=' in content:
                    import re
                    match = re.search(r'https://[^"\']*udemy\.com[^"\']*couponCode=[^"\']*', content)
                    if match:
                        logger.debug(f"Found Udemy URL in meta tag: {match.group()}")
                        return match.group()
            
            # Method 5: Look for form actions or hidden inputs
            forms = soup.find_all('form')
            for form in forms:
                action = form.get('action', '')
                if 'udemy.com' in action:
                    logger.debug(f"Found Udemy URL in form action: {action}")
                    return action
                
                # Check hidden inputs in forms
                inputs = form.find_all('input', type='hidden')
                for inp in inputs:
                    value = inp.get('value', '')
                    if 'udemy.com' in value and 'couponCode=' in value:
                        logger.debug(f"Found Udemy URL in hidden input: {value}")
                        return value
            
            logger.warning(f"Could not extract real Udemy URL from: {discudemy_url}")
            return None
            
        except Exception as e:
            logger.error(f"Error extracting real Udemy URL from {discudemy_url}: {e}")
            return None
    
    def _follow_redirect_to_udemy(self, redirect_url: str) -> str:
        """Follow DiscUdemy redirect URL to get the real Udemy URL"""
        try:
            logger.debug(f"Following redirect: {redirect_url}")
            
            # First try: Follow redirects and check final URL
            response = self._make_request(redirect_url, allow_redirects=True)
            if not response:
                return None
            
            final_url = response.url
            logger.debug(f"Final URL after redirects: {final_url}")
            
            # Check if we got redirected to Udemy
            if 'udemy.com/course/' in final_url:
                logger.debug(f"Direct redirect to Udemy: {final_url}")
                return final_url
            
            # Second try: Parse the response content for Udemy URLs
            soup = self._parse_html(response.text)
            if soup:
                # Method 1: Look for JavaScript redirects
                scripts = soup.find_all('script')
                for script in scripts:
                    if script.string and 'udemy.com' in script.string:
                        content = script.string
                        import re
                        
                        # Enhanced patterns for Udemy URLs
                        patterns = [
                            r'https://www\.udemy\.com/course/[^"\'>\s]+\?couponCode=[A-Z0-9_]+',
                            r'"(https://www\.udemy\.com/course/[^"]+\?couponCode=[^"]+)"',
                            r"'(https://www\.udemy\.com/course/[^']+\?couponCode=[^']+)'",
                            r'window\.location\s*=\s*["\']([^"\']*udemy\.com/course/[^"\']*)["\']',
                            r'location\.href\s*=\s*["\']([^"\']*udemy\.com/course/[^"\']*)["\']',
                            r'document\.location\s*=\s*["\']([^"\']*udemy\.com/course/[^"\']*)["\']'
                        ]
                        
                        for pattern in patterns:
                            matches = re.findall(pattern, content, re.IGNORECASE)
                            if matches:
                                udemy_url = matches[0]
                                logger.debug(f"Found Udemy URL in JavaScript: {udemy_url}")
                                return udemy_url
                
                # Method 2: Look for direct Udemy links in HTML
                udemy_links = soup.find_all('a', href=lambda x: x and 'udemy.com/course/' in x if x else False)
                for link in udemy_links:
                    href = link.get('href')
                    if 'couponCode=' in href:
                        logger.debug(f"Found Udemy link with coupon: {href}")
                        return href
                    elif 'udemy.com/course/' in href:
                        logger.debug(f"Found Udemy link without coupon: {href}")
                        return href
                
                # Method 3: Search for any Udemy URLs in the entire page text
                page_text = response.text
                import re
                udemy_matches = re.findall(r'https://www\.udemy\.com/course/[^"\'>\s]+(?:\?couponCode=[^"\'>\s]+)?', page_text, re.IGNORECASE)
                if udemy_matches:
                    # Prefer URLs with coupon codes
                    for match in udemy_matches:
                        if 'couponCode=' in match:
                            logger.debug(f"Found Udemy URL with coupon in page text: {match}")
                            return match
                    # If no coupon codes, return the first Udemy URL
                    logger.debug(f"Found Udemy URL without coupon in page text: {udemy_matches[0]}")
                    return udemy_matches[0]
            
            logger.warning(f"Could not extract Udemy URL from: {redirect_url} -> {final_url}")
            return None
            
        except Exception as e:
            logger.error(f"Error following redirect {redirect_url}: {e}")
            return None
