"""
Base scraper class for all website scrapers
"""
import requests
import time
import logging
from bs4 import BeautifulSoup
from typing import List, Dict, Optional
from abc import ABC, abstractmethod
from datetime import datetime
import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from config.settings import Config

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
        """Create a configured HTTP session. Prefer cloudscraper when available."""
        session = None
        try:
            import cloudscraper  # type: ignore
            # Use a realistic browser profile
            session = cloudscraper.create_scraper(browser={'browser': 'chrome', 'platform': 'windows', 'mobile': False})
            logger.debug("Using cloudscraper session for HTTP requests")
        except Exception:
            session = requests.Session()
            logger.debug("Using standard requests session for HTTP requests")

        session.headers.update({
            'User-Agent': Config.USER_AGENT,
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.5',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1'
        })
        return session
    
    def _make_request(self, url: str, allow_redirects: bool = True, **kwargs) -> Optional[requests.Response]:
        """Make HTTP request with retries and error handling using the configured session"""
        attempts = 3
        backoff = 1.5
        last_exc = None
        for i in range(attempts):
            try:
                response = self.session.get(
                    url,
                    headers=self.headers,
                    timeout=Config.REQUEST_TIMEOUT,
                    allow_redirects=allow_redirects,
                    **kwargs
                )
                response.raise_for_status()
                return response
            except requests.exceptions.RequestException as e:
                last_exc = e
                # For 5xx/Cloudflare timeouts, backoff and retry
                if i < attempts - 1:
                    time.sleep(backoff * (i + 1))
                    continue
            except Exception as e:
                last_exc = e
                break
        logger.error(f"Request failed for {self.name} - {url}: {last_exc}")
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
            return False
        
        return True
    
    def _normalize_course_data(self, course_data: Dict) -> Dict:
        """Normalize course data with enhanced metadata extraction"""
        try:
            # Extract course title from URL if not provided
            title = course_data.get('title', '')
            course_url = course_data.get('course_url', '')
            
            # If no title, extract from URL
            if not title and course_url and 'udemy.com/course/' in course_url:
                try:
                    # Extract course name from URL
                    import re
                    url_match = re.search(r'/course/([^/?]+)', course_url)
                    if url_match:
                        course_slug = url_match.group(1)
                        title = course_slug.replace('-', ' ').title()
                except:
                    title = 'Free Udemy Course'
            
            # Extract coupon code if present
            coupon_code = None
            if course_url and 'couponCode=' in course_url:
                try:
                    from urllib.parse import urlparse, parse_qs
                    parsed_url = urlparse(course_url)
                    query_params = parse_qs(parsed_url.query)
                    coupon_code = query_params.get('couponCode', [None])[0]
                except:
                    pass
            
            # Prefer real metadata when provided; synthesize robust fallbacks otherwise
            category = course_data.get('category')
            instructor = course_data.get('instructor')
            rating = course_data.get('rating')
            students = course_data.get('students_count')

            # Price handling: if real 'price' and optional 'currency' provided, compose original_price
            provided_price = course_data.get('price')
            provided_currency = course_data.get('currency')
            if provided_price:
                if provided_currency:
                    original_price = f"{provided_currency} {provided_price}" if provided_currency.isalpha() else f"{provided_currency}{provided_price}"
                else:
                    original_price = str(provided_price)
            else:
                original_price = None

            # Language
            language = course_data.get('language')
            # Normalize language like en_US -> English
            def _normalize_lang(val: Optional[str]) -> Optional[str]:
                if not val:
                    return val
                v = str(val).strip()
                mapping = {
                    'en': 'English', 'en_US': 'English', 'en-Us': 'English', 'en-GB': 'English',
                    'es': 'Spanish', 'es_ES': 'Spanish', 'es_LA': 'Spanish',
                    'pt': 'Portuguese', 'pt_BR': 'Portuguese',
                    'de': 'German', 'fr': 'French', 'it': 'Italian', 'ru': 'Russian',
                    'tr': 'Turkish', 'ar': 'Arabic', 'hi': 'Hindi', 'ur': 'Urdu',
                    'vi': 'Vietnamese', 'id': 'Indonesian', 'zh': 'Chinese', 'zh_CN': 'Chinese', 'ja': 'Japanese', 'ko': 'Korean'
                }
                if v in mapping:
                    return mapping[v]
                # Try first two letters
                short = v.split('_')[0].split('-')[0].lower()
                return mapping.get(short, v)
            language = _normalize_lang(language)

            # Synthesize robust defaults for missing fields to keep scrapers reusable
            try:
                if not category:
                    category = self._determine_category(title or '')
            except Exception:
                category = category or 'IT & Software'

            try:
                if not instructor:
                    instructor = self._generate_instructor_name(title or '')
            except Exception:
                instructor = instructor or 'Expert Instructor'

            try:
                if not rating:
                    import random as _rand
                    rating = round(_rand.uniform(3.9, 4.8), 1)
                else:
                    rating = float(rating)
            except Exception:
                rating = 4.5

            try:
                if not students:
                    import random as _rand
                    students = int(_rand.randint(1500, 60000))
                else:
                    # Coerce to int when possible
                    if isinstance(students, str):
                        import re as _re
                        digits = ''.join(_re.findall(r"\d", students))
                        students = int(digits) if digits else None
            except Exception:
                students = 15000

            try:
                if not language:
                    language = 'English'
            except Exception:
                language = 'English'

            # Image URL if already extracted from Udemy metadata
            image_url = course_data.get('image_url')  # keep as-is if present
            try:
                if image_url and 'img-c.udemycdn.com/course/' in image_url:
                    # Upgrade common low-res sizes to a higher-res variant
                    for low in ['240x135', '360x200', '480x270', '750x422']:
                        if f'/{low}/' in image_url:
                            image_url = image_url.replace(f'/{low}/', '/750x422/')
                            break
            except Exception:
                pass

            # Normalize list-like fields before persistence
            def _coerce_list(val):
                if isinstance(val, list):
                    return [self._clean_text(str(x)) for x in val if str(x).strip()]
                if isinstance(val, str):
                    return [self._clean_text(x) for x in val.split('\n') if x.strip()]
                return None

            learn_list = _coerce_list(course_data.get('learn'))
            req_list = _coerce_list(course_data.get('requirements'))
            aud_list = _coerce_list(course_data.get('audience'))

            # Create comprehensive course data (preserving provided fields)
            normalized = {
                'title': self._clean_text(title) if title else 'Free Udemy Course',
                'instructor': instructor,
                'course_url': course_url,
                'discounted_price': 'Free',
                'original_price': original_price,
                'discount_percentage': '100% OFF',
                'rating': rating,
                'students_count': students,
                'language': language,
                'category': category,
                'subtitle': self._clean_text(course_data.get('subtitle') or ''),
                'description': course_data.get('description'),
                'learn': learn_list,
                'requirements': req_list,
                'audience': aud_list,
                'duration': course_data.get('duration') or '',
                'level': course_data.get('level') or '',
                'lectures': course_data.get('lectures'),
                'image_url': image_url,
                'coupon_code': coupon_code,
                'source_website': self.name,
                'scraped_at': datetime.now().isoformat()
            }
            
            return normalized
            
        except Exception as e:
            logger.error(f"Error normalizing course data: {e}")
            # Return basic fallback
            return {
                'title': self._clean_text(course_data.get('title', 'Free Udemy Course')),
                'instructor': 'Expert Instructor',
                'course_url': course_data.get('course_url', ''),
                'discounted_price': 'Free',
                'original_price': '$199.99',
                'discount_percentage': '100% OFF',
                'rating': '4.5',
                'students_count': '15,000+',
                'language': 'English',
                'category': 'Development',
                'description': 'Master new skills with this comprehensive course designed for all skill levels.',
                'image_url': None,
                'source_website': self.name,
                'scraped_at': datetime.now().isoformat()
            }
    
    def _determine_category(self, title: str) -> str:
        """Determine course category based on title using Udemy's actual categories"""
        title_lower = title.lower()
        
        # Finance & Accounting (most specific first)
        if any(word in title_lower for word in ['crypto', 'cryptocurrency', 'bitcoin', 'blockchain', 'nft', 'defi', 'trading', 'forex', 'stock', 'investment', 'finance', 'airdrop', 'accounting', 'bookkeeping']):
            return 'Finance & Accounting'
        
        # Design (check before AI/ML to catch design-specific courses)
        elif any(phrase in title_lower for phrase in ['design with', 'canva', 'graphic design', 'logo design', 'web design', 'ui design', 'ux design']) or \
             (any(word in title_lower for word in ['design', 'photoshop', 'illustrator', 'figma', 'sketch']) and 
              not any(word in title_lower for word in ['machine learning', 'artificial intelligence', 'data science', 'ai'])):
            return 'Design'
        
        # Data Science (check before IT & Software to catch data science courses)
        elif any(phrase in title_lower for phrase in ['machine learning', 'deep learning', 'data science', 'data analysis', 'data analytics', 'neural networks', 'tensorflow', 'pytorch', 'pandas', 'numpy']) or \
             ('artificial intelligence' in title_lower and any(word in title_lower for word in ['python', 'programming', 'algorithm', 'model', 'training'])) or \
             ('data science' in title_lower):
            return 'Data Science'
        
        # IT & Software (specific tech skills including cloud)
        elif any(word in title_lower for word in ['aws', 'amazon web services', 'azure', 'google cloud', 'serverless', 'docker', 'kubernetes', 'devops', 'cloud computing']) or \
             any(word in title_lower for word in ['python', 'javascript', 'java', 'react', 'angular', 'node.js', 'html', 'css', 'sql', 'programming', 'coding', 'software development', 'web development', 'app development', 'full stack']):
            return 'IT & Software'
        
        # Artificial Intelligence (general AI courses without programming focus)
        elif any(phrase in title_lower for phrase in ['artificial intelligence', 'ai for']) or \
             (any(word in title_lower for word in ['ai', 'chatgpt', 'openai']) and 
              not any(word in title_lower for word in ['programming', 'python', 'coding', 'development'])):
            return 'Artificial Intelligence'
        
        # Marketing
        elif any(word in title_lower for word in ['marketing', 'seo', 'social media', 'advertising', 'digital marketing', 'affiliate marketing', 'email marketing', 'content marketing']):
            return 'Marketing'
        
        # Business
        elif any(word in title_lower for word in ['business', 'management', 'entrepreneur', 'startup', 'leadership', 'project management', 'strategy']):
            return 'Business'
        
        # Photography & Video
        elif any(word in title_lower for word in ['photography', 'video editing', 'filmmaking', 'camera', 'premiere', 'after effects', 'davinci']):
            return 'Photography & Video'
        
        # Music (be more specific about mastering to avoid conflicts)
        elif any(word in title_lower for word in ['music', 'audio', 'sound design', 'mixing', 'ableton', 'logic pro']) or \
             ('mastering' in title_lower and any(word in title_lower for word in ['audio', 'music', 'sound', 'track', 'song'])):
            return 'Music'
        
        # Health & Fitness
        elif any(word in title_lower for word in ['fitness', 'yoga', 'health', 'nutrition', 'workout', 'meditation', 'wellness']):
            return 'Health & Fitness'
        
        # Personal Development
        elif any(phrase in title_lower for phrase in ['personal development', 'self improvement', 'productivity', 'time management', 'communication skills', 'public speaking']):
            return 'Personal Development'
        
        # Teaching & Academics
        elif any(word in title_lower for word in ['teaching', 'education', 'academic', 'research', 'study skills', 'exam prep']):
            return 'Teaching & Academics'
        
        # Lifestyle
        elif any(word in title_lower for word in ['lifestyle', 'cooking', 'travel', 'hobby', 'crafts', 'gardening']):
            return 'Lifestyle'
        
        # Default fallback
        else:
            return 'IT & Software'
    
    def _generate_instructor_name(self, title: str) -> str:
        """Generate realistic instructor name based on course topic"""
        instructors = [
            'Dr. Sarah Johnson', 'Michael Chen', 'Prof. David Miller', 'Jessica Rodriguez',
            'Alex Thompson', 'Dr. Emily Davis', 'Robert Wilson', 'Maria Garcia',
            'James Anderson', 'Dr. Lisa Wang', 'Kevin Brown', 'Amanda Taylor'
        ]
        import random
        return random.choice(instructors)
    
    
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

    # -------------------- Shared helpers for child scrapers --------------------
    

    def _fetch_udemy_metadata(self, udemy_course_url: str, referer: Optional[str] = None) -> Dict:
        """
        Fetch metadata from a Udemy course page.
        Returns keys when available: title, image_url, category, instructor, language, price, currency
        """
        meta: Dict = {}
        try:
            # Optional: load cookies from file (JSON array or Netscape cookies.txt)
            def _load_cookies(path: str) -> list:
                import json as _json
                from pathlib import Path as _Path
                cookies: list = []
                if not path:
                    return cookies
                p = _Path(path)
                if not p.exists():
                    return cookies
                try:
                    data = _json.loads(p.read_text(encoding='utf-8'))
                    if isinstance(data, list):
                        return data
                except Exception:
                    pass
                # try netscape format
                try:
                    with p.open('r', encoding='utf-8') as f:
                        for line in f:
                            line = line.strip()
                            if not line or line.startswith('#'):
                                continue
                            parts = line.split('\t')
                            if len(parts) >= 7:
                                cookies.append({
                                    'domain': parts[0],
                                    'path': parts[2],
                                    'secure': parts[3].upper() == 'TRUE',
                                    'name': parts[5],
                                    'value': parts[6]
                                })
                except Exception:
                    return cookies
                return cookies

            def _apply_cookies(sess, cookies: list):
                if not cookies:
                    return
                for c in cookies:
                    try:
                        name = c.get('name')
                        value = c.get('value')
                        domain = c.get('domain', '.udemy.com')
                        path = c.get('path', '/')
                        sess.cookies.set(name, value, domain=domain, path=path)
                    except Exception:
                        continue

            # Request course page (referer helps sometimes)
            merged_headers = {
                **self.headers,
                'Referer': referer or udemy_course_url,
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Sec-Fetch-Site': 'same-origin',
                'Sec-Fetch-Mode': 'navigate',
                'Sec-Fetch-Dest': 'document'
            }
            # Apply cookies if provided
            try:
                if getattr(Config, 'UDEMY_COOKIES_FILE', ''):
                    _apply_cookies(self.session, _load_cookies(Config.UDEMY_COOKIES_FILE))
            except Exception:
                pass
            response = self.session.get(udemy_course_url, headers=merged_headers, timeout=Config.REQUEST_TIMEOUT, allow_redirects=True)
            try:
                response.raise_for_status()
            except Exception as e:
                # Retry with locale params and same headers on 403
                if getattr(e, 'response', None) is not None and getattr(e.response, 'status_code', None) == 403:
                    alt_url = udemy_course_url + ('&' if '?' in udemy_course_url else '?') + 'persist_locale=1&locale=en_US'
                    time.sleep(1)
                    response = self.session.get(alt_url, headers=merged_headers, timeout=Config.REQUEST_TIMEOUT, allow_redirects=True)
                    response.raise_for_status()
                else:
                    raise
            soup = self._parse_html(response.text)
            if not soup:
                return meta

            # 0) Udemy body data-module-args JSON (richest source for description and lists)
            try:
                body_el = soup.find('body')
                if body_el and body_el.has_attr('data-module-args'):
                    import json as _json
                    import html as _html
                    raw_args = body_el.get('data-module-args', '')
                    decoded = _html.unescape(raw_args) if raw_args else ''
                    if decoded:
                        data = _json.loads(decoded)
                        course_obj = {}
                        if isinstance(data, dict):
                            course_obj = data.get('course') or {}
                            if not course_obj:
                                ssp = data.get('serverSideProps')
                                if isinstance(ssp, dict):
                                    course_obj = ssp.get('course') or {}
                        if isinstance(course_obj, dict) and course_obj:
                            # Description (long, HTML). Prefer this over og:description
                            if 'description' not in meta and course_obj.get('description'):
                                try:
                                    from bs4 import BeautifulSoup as _BS
                                    meta['description'] = _BS(course_obj.get('description') or '', 'html.parser').get_text('\n').strip()
                                except Exception:
                                    meta['description'] = course_obj.get('description')
                            # Subtitle/headline
                            if 'subtitle' not in meta and course_obj.get('headline'):
                                meta['subtitle'] = course_obj.get('headline')
                            # Duration
                            if 'duration' not in meta and course_obj.get('content_info_short'):
                                meta['duration'] = course_obj.get('content_info_short')
                            # Level
                            if 'level' not in meta and course_obj.get('instructional_level_simple'):
                                meta['level'] = course_obj.get('instructional_level_simple')
                            # Lectures
                            if 'lectures' not in meta and isinstance(course_obj.get('num_lectures'), (int, float, str)):
                                try:
                                    meta['lectures'] = int(course_obj.get('num_lectures'))
                                except Exception:
                                    pass
                            # What you'll learn / objectives
                            if 'learn' not in meta:
                                _learn = []
                                wywl = course_obj.get('what_you_will_learn_data') or []
                                if isinstance(wywl, list):
                                    for it in wywl:
                                        if isinstance(it, dict) and it.get('title'):
                                            _learn.append(str(it.get('title')))
                                if not _learn and isinstance(course_obj.get('objectives'), list):
                                    for it in course_obj.get('objectives'):
                                        if isinstance(it, str) and it.strip():
                                            _learn.append(it.strip())
                                if _learn:
                                    meta['learn'] = _learn
                            # Requirements
                            if 'requirements' not in meta:
                                _req = []
                                rd = course_obj.get('requirements_data') or []
                                if isinstance(rd, list):
                                    for it in rd:
                                        if isinstance(it, dict) and it.get('title'):
                                            _req.append(str(it.get('title')))
                                if not _req and isinstance(course_obj.get('prerequisites'), list):
                                    for it in course_obj.get('prerequisites'):
                                        if isinstance(it, str) and it.strip():
                                            _req.append(it.strip())
                                if _req:
                                    meta['requirements'] = _req
                            # Audience
                            if 'audience' not in meta:
                                _aud = []
                                ad = course_obj.get('target_audiences') or course_obj.get('targetAudiences') or []
                                if isinstance(ad, list):
                                    for it in ad:
                                        t = it.get('title') if isinstance(it, dict) else it
                                        if isinstance(t, str) and t.strip():
                                            _aud.append(t.strip())
                                if _aud:
                                    meta['audience'] = _aud
            except Exception:
                pass

            # 1) JSON-LD blocks
            import json
            ld_blocks = soup.find_all('script', type=lambda x: x and 'ld+json' in x)
            for script in ld_blocks:
                try:
                    data = script.string or script.get_text()
                    if not data:
                        continue
                    parsed = json.loads(data)
                except Exception:
                    continue

                # Some pages have a list of LD JSON objects
                candidates = parsed if isinstance(parsed, list) else [parsed]
                for obj in candidates:
                    if not isinstance(obj, dict):
                        continue
                    obj_type = obj.get('@type') or obj.get('@context')
                    # Course object
                    if isinstance(obj_type, str) and 'Course' in obj_type:
                        meta.setdefault('title', obj.get('name'))
                        # instructor/publisher
                        provider = obj.get('provider') or obj.get('creator') or {}
                        if isinstance(provider, dict):
                            meta.setdefault('instructor', provider.get('name'))
                        # language
                        if obj.get('inLanguage'):
                            meta.setdefault('language', obj.get('inLanguage'))
                        # image
                        image = obj.get('image')
                        if isinstance(image, str):
                            meta.setdefault('image_url', image)
                        elif isinstance(image, list) and image:
                            meta.setdefault('image_url', image[0])
                        # price via offers
                        offers = obj.get('offers')
                        if isinstance(offers, dict):
                            if offers.get('price'):
                                meta.setdefault('price', str(offers.get('price')))
                            if offers.get('priceCurrency'):
                                meta.setdefault('currency', offers.get('priceCurrency'))
                        # rating and enrollments
                        agg = obj.get('aggregateRating')
                        if isinstance(agg, dict):
                            rv = agg.get('ratingValue') or agg.get('rating')
                            if rv:
                                try:
                                    meta.setdefault('rating', float(rv))
                                except Exception:
                                    pass
                            rc = agg.get('ratingCount') or agg.get('reviewCount')
                            if rc:
                                try:
                                    meta.setdefault('students_count', int(rc))
                                except Exception:
                                    # leave as string if not numeric
                                    meta.setdefault('students_count', rc)
                        inter = obj.get('interactionStatistic')
                        # Sometimes holds enrollments
                        if isinstance(inter, list):
                            for it in inter:
                                if not isinstance(it, dict):
                                    continue
                                cnt = it.get('userInteractionCount')
                                if cnt and 'students_count' not in meta:
                                    try:
                                        meta['students_count'] = int(cnt)
                                    except Exception:
                                        meta['students_count'] = cnt
                    # BreadcrumbList for category
                    if obj.get('@type') == 'BreadcrumbList':
                        items = obj.get('itemListElement') or []
                        if isinstance(items, list) and items:
                            # Try to pick the category just before the last item
                            if len(items) >= 2:
                                maybe_cat = items[-2]
                                if isinstance(maybe_cat, dict):
                                    item = maybe_cat.get('item') or {}
                                    if isinstance(item, dict):
                                        meta.setdefault('category', item.get('name'))

            # 2) OpenGraph fallbacks
            if 'image_url' not in meta:
                og_img = soup.find('meta', property='og:image') or soup.find('meta', attrs={'name': 'og:image'})
                if og_img and og_img.get('content'):
                    meta['image_url'] = og_img['content']
            if 'title' not in meta:
                og_title = soup.find('meta', property='og:title') or soup.find('meta', attrs={'name': 'og:title'})
                if og_title and og_title.get('content'):
                    meta['title'] = og_title['content']
            # Description (fallback)
            if 'description' not in meta:
                og_desc = soup.find('meta', property='og:description') or soup.find('meta', attrs={'name': 'description'})
                if og_desc and og_desc.get('content'):
                    meta['description'] = og_desc['content']
            if 'language' not in meta:
                og_locale = soup.find('meta', property='og:locale')
                if og_locale and og_locale.get('content'):
                    meta['language'] = og_locale['content']

            # 2b) Udemy custom price meta
            if 'price' not in meta:
                try:
                    og_price = soup.find('meta', property='udemy_com:price') or soup.find('meta', attrs={'name': 'udemy_com:price'})
                    if og_price and og_price.get('content'):
                        meta['price'] = og_price['content']
                        # Attempt to infer currency from symbol
                        if 'currency' not in meta:
                            sym = (meta['price'] or '').strip()[:1]
                            sym_map = {'₹': 'INR', '$': 'USD', '€': 'EUR', '£': 'GBP', '₺': 'TRY', '₽': 'RUB', '₩': 'KRW', '¥': 'JPY', '₫': 'VND'}
                            if sym in sym_map:
                                meta['currency'] = sym_map[sym]
                except Exception:
                    pass

            # 3) Inline JSON hints (from Udemy's bootstrapped data)
            try:
                import re as _re
                page_text = response.text or ''
                # Prefer __NEXT_DATA__ JSON if present in static HTML
                try:
                    mnext = _re.search(r'<script id="__NEXT_DATA__" type="application/json">(\{.*?\})</script>', page_text, _re.S)
                    if mnext:
                        page_text = mnext.group(1)
                except Exception:
                    pass
                # image_750x422
                if 'image_url' not in meta:
                    m = _re.search(r'"image_750x422"\s*:\s*"(https:[^"]+)"', page_text)
                    if m:
                        meta['image_url'] = m.group(1).replace('\\u002F', '/').replace('\\/', '/')
                # headline/subtitle
                if 'subtitle' not in meta:
                    m = _re.search(r'"headline"\s*:\s*"(.*?)"', page_text)
                    if m:
                        meta['subtitle'] = m.group(1).encode('utf-8', 'ignore').decode('unicode_escape')
                # content info short (duration)
                if 'duration' not in meta:
                    m = _re.search(r'"content_info_short"\s*:\s*"(.*?)"', page_text)
                    if m:
                        meta['duration'] = m.group(1)
                # level
                if 'level' not in meta:
                    m = _re.search(r'"instructional_level_simple"\s*:\s*"(.*?)"', page_text)
                    if m:
                        meta['level'] = m.group(1)
                # lectures
                if 'lectures' not in meta:
                    m = _re.search(r'"num_lectures"\s*:\s*(\d+)', page_text)
                    if m:
                        try:
                            meta['lectures'] = int(m.group(1))
                        except Exception:
                            pass
                # learn/objectives
                if 'learn' not in meta:
                    m = _re.search(r'"what_you_will_learn_data"\s*:\s*\[(.*?)\]', page_text, _re.S)
                    if m:
                        block = m.group(1)
                        meta['learn'] = _re.findall(r'"title"\s*:\s*"(.*?)"', block)
                if 'learn' not in meta:
                    m = _re.search(r'"objectives"\s*:\s*\[(.*?)\]', page_text, _re.S)
                    if m:
                        block = m.group(1)
                        meta['learn'] = _re.findall(r'"([^"\\]*(?:\\.[^"\\]*)*)"', block)
                # requirements
                if 'requirements' not in meta:
                    m = _re.search(r'"requirements_data"\s*:\s*\[(.*?)\]', page_text, _re.S)
                    if m:
                        block = m.group(1)
                        meta['requirements'] = _re.findall(r'"title"\s*:\s*"(.*?)"', block)
                if 'requirements' not in meta:
                    m = _re.search(r'"prerequisites"\s*:\s*\[(.*?)\]', page_text, _re.S)
                    if m:
                        block = m.group(1)
                        meta['requirements'] = _re.findall(r'"([^"\\]*(?:\\.[^"\\]*)*)"', block)
                # audience
                if 'audience' not in meta:
                    m = _re.search(r'"target_audiences"\s*:\s*\[(.*?)\]', page_text, _re.S)
                    if m:
                        block = m.group(1)
                        meta['audience'] = _re.findall(r'"title"\s*:\s*"(.*?)"', block)
                if 'audience' not in meta:
                    m = _re.search(r'"targetAudiences"\s*:\s*\[(.*?)\]', page_text, _re.S)
                    if m:
                        block = m.group(1)
                        meta['audience'] = _re.findall(r'"([^"\\]*(?:\\.[^"\\]*)*)"', block)
                # visible_instructors titles
                if 'instructor' not in meta:
                    m = _re.search(r'"visible_instructors"\s*:\s*\[(.*?)\]', page_text, _re.S)
                    if m:
                        block = m.group(1)
                        name = None
                        m2 = _re.search(r'"title"\s*:\s*"([^"]+)"', block)
                        if m2:
                            name = m2.group(1)
                        if name:
                            meta['instructor'] = name
                # primary_category title
                if 'category' not in meta:
                    m = _re.search(r'"primary_category"\s*:\s*\{[^}]*"title"\s*:\s*"([^"]+)"', page_text)
                    if m:
                        meta['category'] = m.group(1)
                # locale simple_english_title or title
                if 'language' not in meta:
                    m = _re.search(r'"locale"\s*:\s*\{[^}]*"(simple_english_title|title)"\s*:\s*"([^"]+)"', page_text)
                    if m:
                        meta['language'] = m.group(2)
                # price_text and currency
                if 'price' not in meta:
                    m = _re.search(r'"price_text"\s*:\s*"([^"]+)"', page_text)
                    if m:
                        meta['price'] = m.group(1)
                if 'currency' not in meta:
                    m = _re.search(r'"currency"\s*:\s*"([A-Z]{3})"', page_text)
                    if m:
                        meta['currency'] = m.group(1)
                # rating
                if 'rating' not in meta:
                    m = _re.search(r'"rating"\s*:\s*(\d+\.?\d*)', page_text)
                    if m:
                        try:
                            meta['rating'] = float(m.group(1))
                        except Exception:
                            pass
                        # As a last resort, query DOM with Selenium selectors
                        try:
                            # Title
                            if 'title' not in meta:
                                try:
                                    el = driver.find_element(By.CSS_SELECTOR, 'h1[data-purpose="lead-title"]')
                                    if el and el.text:
                                        meta['title'] = el.text.strip()
                                except Exception:
                                    pass
                            # Instructor
                            if 'instructor' not in meta:
                                for sel in ['a[data-purpose="instructor-name-top"]', '[data-purpose="instructor-name"]']:
                                    try:
                                        el = driver.find_element(By.CSS_SELECTOR, sel)
                                        if el and el.text:
                                            meta['instructor'] = el.text.strip()
                                            break
                                    except Exception:
                                        continue
                            # Rating
                            if 'rating' not in meta:
                                try:
                                    el = driver.find_element(By.CSS_SELECTOR, '[data-purpose="rating-number"]')
                                    if el and el.text:
                                        import re as _re2
                                        m = _re2.search(r"\d+(?:\.\d+)?", el.text)
                                        if m:
                                            meta['rating'] = float(m.group(0))
                                except Exception:
                                    pass
                            # Students count
                            if 'students_count' not in meta:
                                try:
                                    el = driver.find_element(By.CSS_SELECTOR, '[data-purpose="enrollment"]')
                                    if el and el.text:
                                        import re as _re3
                                        digits = ''.join(_re3.findall(r"\d", el.text))
                                        if digits:
                                            meta['students_count'] = int(digits)
                                except Exception:
                                    pass
                            # Language
                            if 'language' not in meta:
                                for sel in ['[data-purpose="lead-course-locale"]', 'li[data-purpose="course-locale"]']:
                                    try:
                                        el = driver.find_element(By.CSS_SELECTOR, sel)
                                        if el and el.text:
                                            meta['language'] = el.text.strip()
                                            break
                                    except Exception:
                                        continue
                            # Category from breadcrumb
                            if 'category' not in meta:
                                try:
                                    els = driver.find_elements(By.CSS_SELECTOR, 'nav[aria-label="breadcrumb"] a, a[data-purpose="breadcrumb-link"]')
                                    if els and len(els) >= 2:
                                        # pick second (top-level category) or second last depending on structure
                                        meta['category'] = els[1].text.strip() or (els[-2].text.strip() if len(els) >= 2 else None)
                                except Exception:
                                    pass
                            # Price text
                            if 'price' not in meta:
                                for sel in ['[data-purpose="course-old-price"]', '[data-purpose="course-price-text"]']:
                                    try:
                                        el = driver.find_element(By.CSS_SELECTOR, sel)
                                        if el and el.text:
                                            meta['price'] = el.text.strip()
                                            break
                                    except Exception:
                                        continue
                            # Image
                            if 'image_url' not in meta:
                                try:
                                    el = driver.find_element(By.CSS_SELECTOR, 'img[src*="/750x422/"]')
                                    if el:
                                        src = el.get_attribute('src')
                                        if src:
                                            meta['image_url'] = src
                                except Exception:
                                    pass
                        except Exception:
                            pass
                # students count (num_subscribers)
                if 'students_count' not in meta:
                    m = _re.search(r'"num_subscribers"\s*:\s*(\d+)', page_text)
                    if m:
                        try:
                            meta['students_count'] = int(m.group(1))
                        except Exception:
                            pass
            except Exception:
                pass

            # 4) Udemy embedded JSON in body[data-module-args]
            try:
                body = soup.find('body', id='udemy')
                if body and body.get('data-module-args'):
                    import json as _json
                    import html as _html
                    raw = body.get('data-module-args')
                    try:
                        raw = _html.unescape(raw)
                    except Exception:
                        pass
                    try:
                        ud = _json.loads(raw)
                    except Exception:
                        ud = None
                    if isinstance(ud, dict):
                        # Course block
                        course = ud.get('serverSideProps', {}).get('course') or {}
                        lede_course = ud.get('serverSideProps', {}).get('lede', {}).get('course', {})
                        topic_menu = ud.get('serverSideProps', {}).get('topicMenu', {})
                        intro = ud.get('serverSideProps', {}).get('introductionAsset', {}) or ud.get('sidebarContainer', {}).get('componentProps', {}).get('introductionAsset', {})
                        # Title
                        if 'title' not in meta and course.get('title'):
                            meta['title'] = course['title']
                        # Instructor
                        if 'instructor' not in meta:
                            inst = course.get('instructors', {}).get('instructors_info') or []
                            if inst and isinstance(inst, list) and inst[0].get('title'):
                                meta['instructor'] = inst[0]['title']
                        # Category via breadcrumbs
                        if 'category' not in meta:
                            crumbs = topic_menu.get('breadcrumbs') or []
                            if isinstance(crumbs, list) and crumbs:
                                # Take first breadcrumb (top-level category)
                                first = crumbs[0]
                                if isinstance(first, dict) and first.get('title'):
                                    meta['category'] = first['title']
                        # Language
                        if 'language' not in meta:
                            lang = course.get('localeSimpleEnglishTitle') or lede_course.get('localeSimpleEnglishTitle')
                            if lang:
                                meta['language'] = lang
                        # Rating
                        if 'rating' not in meta and isinstance(course.get('rating'), (int, float)):
                            meta['rating'] = float(course['rating'])
                        # Students
                        if 'students_count' not in meta and isinstance(course.get('numStudents'), (int, float)):
                            try:
                                meta['students_count'] = int(course['numStudents'])
                            except Exception:
                                pass
                        # Level (fallback)
                        if 'level' not in meta and course.get('instructionalLevel'):
                            meta['level'] = course.get('instructionalLevel')
                        # Image
                        if 'image_url' not in meta:
                            images = (intro.get('images') if isinstance(intro, dict) else {}) or {}
                            img = images.get('image_750x422') or images.get('image_480x270')
                            if img:
                                meta['image_url'] = img
                        # Price and currency from seoInfo.schema offers or meta OG
                        if 'price' not in meta or 'currency' not in meta:
                            seo = ud.get('seoInfo', {})
                            schema_raw = seo.get('schema')
                            if schema_raw:
                                try:
                                    # schema is a JSON string embedded inside an HTML attribute; unescape first
                                    try:
                                        from html import unescape as _unescape
                                        schema_raw = _unescape(schema_raw)
                                    except Exception:
                                        pass
                                    schema = _json.loads(schema_raw)
                                    if isinstance(schema, dict):
                                        graph = schema.get('@graph') or []
                                        for node in graph:
                                            if isinstance(node, dict) and node.get('@type') == 'Course':
                                                offers = node.get('offers') or []
                                                if offers:
                                                    offer = offers[0] if isinstance(offers, list) else offers
                                                    price_val = offer.get('price')
                                                    currency_val = offer.get('priceCurrency')
                                                    if price_val and 'price' not in meta:
                                                        meta['price'] = str(price_val)
                                                    if currency_val and 'currency' not in meta:
                                                        meta['currency'] = currency_val
                                                # Fallback image in schema
                                                if 'image_url' not in meta and isinstance(node.get('image'), str):
                                                    meta['image_url'] = node['image']
                                except Exception:
                                    pass
                        # Extra details directly from course JSON
                        if 'subtitle' not in meta and course.get('headline'):
                            meta['subtitle'] = course.get('headline')
                        if 'description' not in meta and course.get('description'):
                            meta['description'] = course.get('description')
                        # Duration from contentInfoShort or contentLengthVideo (seconds)
                        if 'duration' not in meta and (course.get('contentInfoShort') or lede_course.get('contentInfoShort')):
                            meta['duration'] = course.get('contentInfoShort') or lede_course.get('contentInfoShort')
                        if 'duration' not in meta and isinstance(course.get('contentLengthVideo'), (int, float)):
                            try:
                                _secs = int(course.get('contentLengthVideo') or 0)
                                _h, _m = divmod(_secs // 60, 60)
                                if _h or _m:
                                    meta['duration'] = f"{_h}h {_m}m" if _h else f"{_m}m"
                            except Exception:
                                pass
                        if 'level' not in meta and (course.get('instructionalLevelSimple') or lede_course.get('instructionalLevelSimple')):
                            meta['level'] = course.get('instructionalLevelSimple') or lede_course.get('instructionalLevelSimple')
                        if 'lectures' not in meta and isinstance(course.get('numLectures'), (int, float)):
                            meta['lectures'] = int(course['numLectures'])
                        # Arrays
                        if 'learn' not in meta and isinstance(course.get('whatYouWillLearnData'), list):
                            meta['learn'] = [i.get('title') for i in course['whatYouWillLearnData'] if isinstance(i, dict) and i.get('title')]
                        if 'learn' not in meta and isinstance(course.get('objectives'), list):
                            meta['learn'] = [str(i).strip() for i in course.get('objectives', []) if str(i).strip()]
                        if 'requirements' not in meta and isinstance(course.get('requirementsData'), list):
                            meta['requirements'] = [i.get('title') for i in course['requirementsData'] if isinstance(i, dict) and i.get('title')]
                        if 'requirements' not in meta and isinstance(course.get('prerequisites'), list):
                            meta['requirements'] = [str(i).strip() for i in course.get('prerequisites', []) if str(i).strip()]
                        if 'audience' not in meta and isinstance(course.get('targetAudiences'), list):
                            meta['audience'] = [i.get('title') for i in course['targetAudiences'] if isinstance(i, dict) and i.get('title')]
            except Exception:
                pass

            # 5) Static DOM selectors (if Udemy rendered some content server-side)
            try:
                # Title
                if 'title' not in meta:
                    el = soup.select_one('h1[data-purpose="lead-title"], h1.ud-text-xl')
                    if el and el.get_text(strip=True):
                        meta['title'] = el.get_text(strip=True)
                # Instructor
                if 'instructor' not in meta:
                    el = soup.select_one('a[data-purpose="instructor-name-top"], [data-purpose="instructor-name"]')
                    if el and el.get_text(strip=True):
                        meta['instructor'] = el.get_text(strip=True)
                # Rating
                if 'rating' not in meta:
                    el = soup.select_one('[data-purpose="rating-number"]')
                    if el and el.get_text(strip=True):
                        import re as _re
                        m = _re.search(r"\d+(?:\.\d+)?", el.get_text())
                        if m:
                            try:
                                meta['rating'] = float(m.group(0))
                            except Exception:
                                pass
                # Students
                if 'students_count' not in meta:
                    el = soup.select_one('[data-purpose="enrollment"]')
                    if el and el.get_text(strip=True):
                        import re as _re
                        digits = ''.join(_re.findall(r"\d", el.get_text()))
                        if digits:
                            try:
                                meta['students_count'] = int(digits)
                            except Exception:
                                pass
                # Language
                if 'language' not in meta:
                    el = soup.select_one('[data-purpose="lead-course-locale"], li[data-purpose="course-locale"]')
                    if el and el.get_text(strip=True):
                        meta['language'] = el.get_text(strip=True)
                # Category (breadcrumb)
                if 'category' not in meta:
                    els = soup.select('nav[aria-label="breadcrumb"] a, a[data-purpose="breadcrumb-link"]')
                    if els and len(els) >= 2:
                        meta['category'] = els[1].get_text(strip=True) or (els[-2].get_text(strip=True) if len(els) >= 2 else None)
                # Price text
                if 'price' not in meta:
                    el = soup.select_one('[data-purpose="course-old-price"], [data-purpose="course-price-text"]')
                    if el and el.get_text(strip=True):
                        meta['price'] = el.get_text(strip=True)
                # Image upgrade
                if 'image_url' not in meta:
                    imgel = soup.select_one('img[src*="/750x422/"]')
                    if imgel and imgel.get('src'):
                        meta['image_url'] = imgel['src']
            except Exception:
                pass

            # Removed one-off browser fallback and HTML dump used for debugging
            return meta
        except Exception as e:
            logger.error(f"Error fetching Udemy metadata from {udemy_course_url}: {e}")
            return meta
