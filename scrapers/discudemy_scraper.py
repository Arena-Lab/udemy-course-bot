"""
Scraper for DiscUdemy website
"""
from typing import List, Dict, Optional
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from scrapers.base_scraper import BaseScraper
import logging
import re
import time
from config.settings import Config
from utils.database import CourseDatabase

logger = logging.getLogger(__name__)

class DiscUdemyScraper(BaseScraper):
    
    def __init__(self):
        super().__init__('discudemy', 'https://www.discudemy.com/')
        # Persist backfill cursor using DB
        self._db = CourseDatabase()
    
    def scrape_courses(self, limit: int = None) -> List[Dict]:
        """Scrape courses from DiscUdemy with basic pagination on listing pages"""
        courses = []
        try:
            logger.info(f"Starting scrape from {self.name}")
            # Fresh lane rotation over /all pages 1..N (N<=60).
            # Always include '/all' (page 1) every run; rotate pages 2..N across runs.
            max_fresh = max(1, min(int(getattr(Config, 'DISCUDEMY_FRESH_PAGES', 10)), 60))
            slice_conf = max(1, int(getattr(Config, 'DISCUDEMY_FRESH_SLICE', 3)))
            # There are (max_fresh-1) non-root pages (2..N). Slice cannot exceed this count.
            non_root_count = max(0, max_fresh - 1)
            fresh_slice = min(slice_conf, non_root_count) if non_root_count > 0 else 0
            fresh_cursor_key = f"{self.name}:fresh_cursor"
            cursor_val = self._db.get_progress_key(fresh_cursor_key, default=1)
            # Normalize cursor to 1..non_root_count
            if non_root_count <= 0:
                cursor_val = 0
            else:
                if cursor_val < 1 or cursor_val > non_root_count:
                    cursor_val = 1
            # Build full page list and split root/non-root
            end_index = 1 + (max_fresh - 1)
            all_pages = ['https://www.discudemy.com/all'] + [
                f'https://www.discudemy.com/all/{i}' for i in range(2, end_index + 1)
            ]
            non_root_pages = all_pages[1:]
            # Compute rotating slice over non-root pages (2..N)
            rotated = []
            if non_root_count > 0 and fresh_slice > 0:
                for j in range(fresh_slice):
                    idx = (cursor_val - 1 + j) % non_root_count
                    rotated.append(non_root_pages[idx])
            # Always include '/all' then the rotating selection
            fresh_pages = [all_pages[0]] + rotated

            # Only fresh lane: we do not perform deep/backfill crawling anymore
            backfill_pages = []
            start_page = 0
            end_page = 0
            listing_pages = fresh_pages

            # Quotas: single lane (fresh only)
            total_limit = int(limit) if isinstance(limit, int) and limit > 0 else int(getattr(Config, 'MAX_COURSES_PER_RUN', 50))
            backfill_quota = 0
            fresh_quota = max(0, total_limit)
            fresh_added = 0
            backfill_added = 0
            total_added = 0

            logger.info(f"{self.name}: quotas -> fresh {fresh_quota}; fresh_cursor={cursor_val}, slice={fresh_slice}; max_fresh={max_fresh}")

            # Optional diagnostics structure
            diag_enabled = bool(getattr(Config, 'DIAG_ENABLE_DISCUDEMY', False))
            if diag_enabled:
                from pathlib import Path
                import json
                diag_dir = Path(getattr(Config, 'DIAG_DIR', 'logs/diagnostics'))
                try:
                    diag_dir.mkdir(parents=True, exist_ok=True)
                except Exception:
                    pass
                run_diag = {
                    'source': self.name,
                    'fresh_cursor': int(cursor_val),
                    'fresh_slice': int(fresh_slice),
                    'backfill': {'start': int(start_page), 'end': int(end_page)},
                    'quotas': {'fresh': int(fresh_quota), 'backfill': int(backfill_quota), 'total_limit': int(total_limit)},
                    'pages': []
                }

            # Fresh-page stale streak: if several fresh pages yield nothing, move on
            stale_page_streak = 0
            stale_page_threshold = 2

            for idx, page_url in enumerate(listing_pages, start=1):
                # Stop early if both quotas are satisfied (avoid fetching more pages needlessly)
                if fresh_added >= fresh_quota and backfill_added >= backfill_quota:
                    break

                # Skip fresh pages if fresh quota is already met
                if (page_url in fresh_pages) and (fresh_added >= fresh_quota):
                    continue

                # Skip backfill pages entirely when quota is 0 or already met
                if (page_url not in fresh_pages):
                    continue
                # We won't stop before a page starts; allow finishing current page
                if total_added >= total_limit:
                    # We'll still process this page but enforce a page-specific cap below
                    pass
                resp = self._make_request(page_url)
                if not resp:
                    continue
                soup = self._parse_html(resp.text)
                if not soup:
                    continue

                # Find course links via card grid (robust against ads and layout noise)
                course_links = []
                cards_root = soup.find('article', class_='ui four stackable cards') or soup
                for card in cards_root.find_all('section', class_='card'):
                    # Skip ad card by label text or presence of adsbygoogle
                    label = card.find('label', class_='ui green disc-fee label')
                    if label and label.get_text(strip=True).lower() == 'ads':
                        continue
                    if card.find('ins', class_='adsbygoogle'):
                        continue
                    header_a = card.find('a', class_='card-header')
                    if not header_a or not header_a.get('href'):
                        continue
                    href = header_a['href']
                    if href.startswith('/'):
                        abs_href = 'https://www.discudemy.com' + href
                    elif href.startswith('http'):
                        abs_href = href
                    else:
                        continue
                    course_links.append((header_a, abs_href))

                logger.info(f"Found {len(course_links)} potential course links on {page_url}")
                if diag_enabled:
                    page_diag = {
                        'page_url': page_url,
                        'is_backfill': (page_url not in fresh_pages),
                        'candidates': len(course_links),
                        'resolved': 0,
                        'validated': 0,
                        'added': 0,
                        'db_duplicates': 0,
                        'no_real_url': 0,
                        'failed_validation': 0,
                        'quota_skipped': 0,
                        'errors': 0,
                        'samples': []
                    }

                # Compute a page-specific hard cap so we can finish the current page
                # without stopping mid-page. We allow up to all candidates on this page,
                # bounded by a small configurable overflow beyond total_limit.
                try:
                    overflow_allow = int(getattr(Config, 'FINISH_PAGE_OVERFLOW', 20))
                except Exception:
                    overflow_allow = 20
                max_overall = total_limit + max(0, overflow_allow)
                page_cap = min(max_overall, max(total_limit, total_added + len(course_links)))

                for link, abs_href in course_links:
                    # Enforce the page-specific cap; do not stop mid-page just because
                    # total_limit was reached earlier in the run.
                    if total_added >= page_cap:
                        break
                    try:
                        # Extract real Udemy URL from DiscUdemy link
                        real_udemy_url = None
                        
                        # Fast-path: if the card already links directly to Udemy, accept it
                        if abs_href.startswith('https://www.udemy.com/') or abs_href.startswith('https://udemy.com/'):
                            real_udemy_url = abs_href
                        elif '/go/' in abs_href:
                            real_udemy_url = self._extract_udemy_from_go_page(abs_href)
                        else:
                            # DiscUdemy internal course page -> extract the Udemy target
                            real_udemy_url = self._extract_real_udemy_url(abs_href)
                        
                        # Skip if extraction failed or invalid URL
                        if not real_udemy_url or 'discudemy.com' in real_udemy_url or not ('udemy.com/course/' in real_udemy_url):
                            if diag_enabled:
                                page_diag['no_real_url'] += 1
                            continue

                        # Build course data
                        course_data = {
                            'title': link.get_text(strip=True) or 'Free Udemy Course',
                            'course_url': real_udemy_url,
                            'discounted_price': 'Free',
                            'discount_percentage': '100% OFF'
                        }
                        
                        # Success - extracted valid Udemy URL
                        logger.info(f"• Found: {course_data['title'][:50]}{'...' if len(course_data['title']) > 50 else ''}")

                        # Fetch real metadata from Udemy and merge
                        try:
                            meta = self._fetch_udemy_metadata(real_udemy_url, referer=abs_href) or {}
                            # Prefer real title if present
                            if meta.get('title'):
                                course_data['title'] = meta['title']
                            # Attach additional metadata (expanded)
                            enriched_keys = 0
                            for k in [
                                'image_url', 'category', 'instructor', 'language', 'price', 'currency',
                                'rating', 'students_count', 'subtitle', 'description', 'duration',
                                'level', 'lectures', 'learn', 'requirements', 'audience'
                            ]:
                                if meta.get(k) not in [None, '', []]:
                                    course_data[k] = meta[k]
                                    enriched_keys += 1
                            if enriched_keys > 0:
                                course_data['meta_enriched'] = True
                            # Tiny delay to avoid triggering anti-bot
                            time.sleep(0.4)
                        except Exception:
                            pass

                        # Optional validation for deep pages (> page 10 in our combined list)
                        is_deep_page = idx > len(fresh_pages)
                        if is_deep_page and getattr(Config, 'DISCUDEMY_VALIDATE_DEEP', True):
                            if not self._validate_udemy_url(real_udemy_url):
                                if diag_enabled:
                                    page_diag['failed_validation'] += 1
                                continue

                        if course_data and self._is_valid_course(course_data):
                            normalized_course = self._normalize_course_data(course_data)
                            courses.append(normalized_course)
                            self.courses_scraped += 1
                            if diag_enabled:
                                page_diag['resolved'] += 1
                            fresh_added += 1
                            total_added += 1
                            if diag_enabled:
                                # Check DB duplicate expectation to explain later filters in main
                                try:
                                    ch = self._db.generate_course_hash(normalized_course)
                                    if self._db.course_exists(ch):
                                        page_diag['db_duplicates'] += 1
                                except Exception:
                                    pass
                                # 'added' reflects items appended from this page so far (sum of resolved here)
                                page_diag['added'] = page_diag.get('resolved', 0)
                                if len(page_diag['samples']) < 5:
                                    page_diag['samples'].append({
                                        'title': normalized_course.get('title'),
                                        'url': normalized_course.get('course_url')
                                    })
                            
                            # Stop only if we reached the page-specific cap
                            if total_added >= page_cap:
                                break
                    except Exception as e:
                        logger.error(f"Error extracting course from {self.name}: {e}")
                        self.errors_count += 1
                        if diag_enabled:
                            page_diag['errors'] += 1
                        continue

                # Append per-page diagnostics
                if diag_enabled:
                    try:
                        run_diag['pages'].append(page_diag)
                    except Exception:
                        pass

                # After finishing this page, stop iterating pages if we've at least
                # reached the global total limit. We allowed finishing the page above.
                if total_added >= total_limit:
                    break

                # If this was a fresh page and we added nothing, increase stale streak
                if (page_url in fresh_pages) and (fresh_added == 0):
                    stale_page_streak += 1
                    if stale_page_streak >= stale_page_threshold:
                        logger.info(f"{self.name}: fresh pages appear stale (streak {stale_page_streak}), advancing slice early")
                        # Skip remaining fresh pages in this run
                        # Move index to start of backfill segment by simulating quotas met for fresh
                        fresh_added = fresh_quota
                else:
                    # Reset streak if we added any fresh item
                    if page_url in fresh_pages:
                        stale_page_streak = 0
                        pass
                try:
                    # Write diagnostics file (pages were already appended per-iteration)
                    from datetime import datetime
                    ts = datetime.now().strftime('%Y%m%d_%H%M%S')
                    import json
                    from pathlib import Path
                    diag_path = Path(getattr(Config, 'DIAG_DIR', 'logs/diagnostics')) / f"discudemy_run_{ts}.json"
                    with open(diag_path, 'w', encoding='utf-8') as f:
                        json.dump(run_diag, f, ensure_ascii=False, indent=2)
                except Exception:
                    pass
        except Exception as e:
            logger.error(f"Error scraping {self.name}: {e}")
            self.errors_count += 1
        
        # No backfill progress to update

        # Advance fresh cursor for next run (rotate over non-root pages only)
        try:
            try:
                _max_fresh = max(1, min(int(getattr(Config, 'DISCUDEMY_FRESH_PAGES', 10)), 60))
            except Exception:
                _max_fresh = 10
            _non_root = max(0, _max_fresh - 1)
            if _non_root > 0 and fresh_slice > 0:
                next_cursor = ((max(1, int(cursor_val)) - 1 + fresh_slice) % _non_root) + 1
            else:
                next_cursor = 1
            self._db.set_progress_key(f"{self.name}:fresh_cursor", int(next_cursor))
        except Exception:
            pass

        logger.info(f"✅ Scraped {len(courses)} courses from DiscUdemy")
        return courses

    def _validate_udemy_url(self, url: Optional[str]) -> bool:
        """Validation for deep pages.
        Default: offline pattern validation (no network) to avoid false negatives.
        Optional online validation can be enabled via Config.VALIDATION_ONLINE = True.
        """
        if not url:
            return False
        # Offline pattern check
        try:
            import re as _re
            pattern = r"https?://(?:www\.)?udemy\.com/course/[^\s\"'>]+"
            if not _re.search(pattern, url):
                return False
        except Exception:
            # Fallback simple contains check
            if 'udemy.com/course/' not in url:
                return False

        # Online check (optional)
        if getattr(Config, 'VALIDATION_ONLINE', False):
            try:
                resp = self.session.get(
                    url,
                    headers=self.headers,
                    timeout=getattr(Config, 'VALIDATION_TIMEOUT', 8),
                    allow_redirects=True,
                )
                status = getattr(resp, 'status_code', 500)
                final_url = getattr(resp, 'url', '') if resp is not None else ''
                if hasattr(resp, 'close'):
                    try:
                        resp.close()
                    except Exception:
                        pass
                # Treat common bot-block statuses as acceptable if URL pattern matches
                if status in (401, 403, 404, 429):
                    return 'udemy.com/course/' in final_url or 'udemy.com/course/' in url
                return status < 500 and ('udemy.com/course/' in final_url or 'udemy.com/course/' in url)
            except Exception:
                # If network validation fails, fall back to offline acceptance
                return True
        return True
    
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
                real_udemy_url = self._extract_udemy_from_go_page(href)
                if real_udemy_url and 'udemy.com/course/' in real_udemy_url:
                    course_data['course_url'] = real_udemy_url
                    logger.info(f"Successfully extracted Udemy URL: {real_udemy_url}")
                else:
                    # NEVER fallback to DiscUdemy URL - return None to reject this course
                    logger.warning(f"Failed to extract Udemy URL from /go/ link: {href}")
                    return None
            else:
                # It's a regular course page, extract the /go/ link from it
                logger.debug(f"Processing course page: {href}")
                real_udemy_url = self._extract_real_udemy_url(href)
                if real_udemy_url and 'udemy.com/course/' in real_udemy_url:
                    course_data['course_url'] = real_udemy_url
                    logger.info(f"Successfully extracted Udemy URL: {real_udemy_url}")
                else:
                    # NEVER fallback to DiscUdemy URL - return None to reject this course
                    logger.warning(f"Failed to extract Udemy URL from course page: {href}")
                    return None
            
            # Only set basic required fields - metadata will be extracted from Udemy
            course_data.setdefault('discounted_price', 'Free')
            course_data.setdefault('discount_percentage', '100% OFF')
            
            return course_data
            
        except Exception as e:
            logger.error(f"Error extracting course from link: {e}")
            return None
    
    def _extract_real_udemy_url(self, discudemy_url: str) -> str:
        """Extract the real Udemy URL with coupon code from DiscUdemy page"""
        try:
            # Make request to the DiscUdemy course page
            response = self._make_request(discudemy_url)
            if not response:
                return None
            
            soup = self._parse_html(response.text)
            if not soup:
                return None
            
            # Method 1: Look for the "Take Course" button with class="discBtn" that leads to /go/ endpoint
            # This is the most reliable method based on the HTML structure
            take_course_button = soup.find('a', class_='discBtn')
            if take_course_button:
                href = take_course_button.get('href')
                if href and '/go/' in href:
                    # This is the redirect URL, follow it to get the real Udemy URL
                    if href.startswith('/'):
                        redirect_url = 'https://www.discudemy.com' + href
                    else:
                        redirect_url = href
                    
                    real_url = self._extract_udemy_from_go_page(redirect_url)
                    if real_url and 'udemy.com/course/' in real_url:
                        return real_url
            
            # Method 2: Look for any "Take Course" or "Get Course" button that leads to /go/ endpoint
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
                    real_url = self._extract_udemy_from_go_page(redirect_url)
                    if real_url:
                        return real_url
            
            # Method 3: Fallback - any anchor with '/go/' regardless of text
            generic_go_links = soup.find_all('a', href=lambda x: x and '/go/' in x if x else False)
            for a in generic_go_links:
                href = a.get('href')
                if not href:
                    continue
                redirect_url = ('https://www.discudemy.com' + href) if href.startswith('/') else href
                real_url = self._extract_udemy_from_go_page(redirect_url)
                if real_url:
                    return real_url

            # Method 4: Look for direct Udemy links in the page (rare but possible)
            udemy_links = soup.find_all('a', href=lambda x: x and 'udemy.com' in x if x else False)
            for link in udemy_links:
                href = link.get('href')
                if href and 'couponCode=' in href:
                    logger.debug(f"Found direct Udemy link: {href}")
                    return href
            
            # Method 5: Look for JavaScript variables containing the Udemy URL
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
            
            # Method 6: Look for buttons or elements with data attributes
            buttons = soup.find_all(['button', 'a', 'div'], attrs={'data-url': True})
            for button in buttons:
                data_url = button.get('data-url')
                if data_url and 'udemy.com' in data_url and 'couponCode=' in data_url:
                    logger.debug(f"Found Udemy URL in data attribute: {data_url}")
                    return data_url
            
            # Method 7: Look for meta tags with Udemy URLs
            meta_tags = soup.find_all('meta')
            for meta in meta_tags:
                content = meta.get('content', '')
                if 'udemy.com' in content and 'couponCode=' in content:
                    import re
                    match = re.search(r'https://[^"\']*udemy\.com[^"\']*couponCode=[^"\']*', content)
                    if match:
                        logger.debug(f"Found Udemy URL in meta tag: {match.group()}")
                        return match.group()

            # Method 8: Meta refresh redirects that point to /go/ or Udemy
            meta_refresh = soup.find('meta', attrs={'http-equiv': lambda x: x and x.lower() == 'refresh'})
            if meta_refresh:
                content = meta_refresh.get('content', '')
                import re as _re
                m = _re.search(r'url=([^;]+)', content, _re.IGNORECASE)
                if m:
                    refresh_url = m.group(1).strip().strip('"\'')
                    if refresh_url:
                        if refresh_url.startswith('/'):
                            refresh_url = 'https://www.discudemy.com' + refresh_url
                        if 'udemy.com' in refresh_url:
                            return refresh_url
                        if '/go/' in refresh_url:
                            real_url = self._extract_udemy_from_go_page(refresh_url)
                            if real_url:
                                return real_url
            
            # Method 9: Look for form actions or hidden inputs
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
            
            # Could not extract real Udemy URL - return None
            return None
            
        except Exception as e:
            logger.error(f"Error extracting real Udemy URL from {discudemy_url}: {e}")
            return None
    
    
    
    def _extract_udemy_from_go_page(self, go_url: str) -> str:
        """Extract Udemy URL with coupon from DiscUdemy /go/ page"""
        try:
            # Add small delay to avoid rate limiting
            time.sleep(0.5)
            
            # Make request to the /go/ page
            response = self._make_request(go_url)
            if not response:
                return None
            
            # Parse the response
            soup = self._parse_html(response.text)
            if not soup:
                return None
            
            # Method 1: Look for direct Udemy links with couponCode in the page
            udemy_links = soup.find_all('a', href=lambda x: x and 'udemy.com/course/' in x and 'couponCode=' in x if x else False)
            for link in udemy_links:
                href = link.get('href')
                if href:
                    logger.debug(f"Found Udemy link with coupon on /go/ page: {href}")
                    return href
            
            # Method 2: Search for Udemy URLs in the page text (most reliable for /go/ pages)
            page_text = response.text
            import re
            
            # Enhanced patterns specifically for /go/ pages
            patterns = [
                r'https://www\.udemy\.com/course/[^"\'>\s\)]+\?couponCode=[A-Z0-9_]+',
                r'"(https://www\.udemy\.com/course/[^"]+\?couponCode=[^"]+)"',
                r"'(https://www\.udemy\.com/course/[^']+\?couponCode=[^']+)'",
                r'Course Coupon:\s*\n?\s*(https://www\.udemy\.com/course/[^\s]+\?couponCode=[^\s]+)',
                r'href="(https://www\.udemy\.com/course/[^"]+\?couponCode=[^"]+)"'
            ]
            
            for pattern in patterns:
                matches = re.findall(pattern, page_text, re.IGNORECASE | re.MULTILINE)
                if matches:
                    udemy_url = matches[0]
                    logger.debug(f"Found Udemy URL with pattern on /go/ page: {udemy_url}")
                    return udemy_url
            
            # Method 3: Look for any Udemy course URLs (even without coupon)
            udemy_pattern = r'https://www\.udemy\.com/course/[^"\'>\s\)]+'
            udemy_matches = re.findall(udemy_pattern, page_text, re.IGNORECASE)
            if udemy_matches:
                for match in udemy_matches:
                    if 'couponCode=' in match:
                        logger.debug(f"Found Udemy URL with coupon in text: {match}")
                        return match
                # If no coupon found, return the first Udemy URL
                logger.debug(f"Found Udemy URL without coupon: {udemy_matches[0]}")
                return udemy_matches[0]
            
            # Method 4: Check for JavaScript redirects
            scripts = soup.find_all('script')
            for script in scripts:
                if script.string and 'udemy.com' in script.string:
                    content = script.string
                    js_patterns = [
                        r'window\.location\s*=\s*["\']([^"\']*udemy\.com/course/[^"\']*)["\']',
                        r'location\.href\s*=\s*["\']([^"\']*udemy\.com/course/[^"\']*)["\']',
                        r'document\.location\s*=\s*["\']([^"\']*udemy\.com/course/[^"\']*)["\']'
                    ]
                    
                    for js_pattern in js_patterns:
                        js_matches = re.findall(js_pattern, content, re.IGNORECASE)
                        if js_matches:
                            udemy_url = js_matches[0]
                            logger.debug(f"Found Udemy URL in JavaScript: {udemy_url}")
                            return udemy_url
            
            logger.warning(f"Could not extract Udemy URL from /go/ page: {go_url}")
            return None
            
        except Exception as e:
            logger.error(f"Error extracting Udemy URL from /go/ page {go_url}: {e}")
            return None
