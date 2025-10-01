"""
Scraper manager to coordinate all scrapers
"""
from typing import List, Dict
import logging
from concurrent.futures import ThreadPoolExecutor, as_completed
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from config.settings import Config
from scrapers.real_discount_scraper import RealDiscountScraper
from scrapers.discudemy_scraper import DiscUdemyScraper
from scrapers.udemy_freebies_scraper import UdemyFreebiesScraper
from scrapers.yofreesamples_scraper import YoFreeSamplesScraper
from scrapers.coursesity_scraper import CoursesityScraper
from scrapers.tutorialbar_scraper import TutorialBarScraper
from scrapers.freebiesglobal_scraper import FreebiesGlobalScraper

logger = logging.getLogger(__name__)

class ScraperManager:
    """Manages all course scrapers"""
    
    def __init__(self):
        self.scrapers = self._initialize_scrapers()
    
    def _initialize_scrapers(self) -> Dict:
        """Initialize all enabled scrapers"""
        scrapers = {}
        
        if Config.SCRAPERS.get('real_discount', False):
            scrapers['real_discount'] = RealDiscountScraper()
            
        if Config.SCRAPERS.get('discudemy', False):
            scrapers['discudemy'] = DiscUdemyScraper()
            
        if Config.SCRAPERS.get('udemy_freebies', False):
            scrapers['udemy_freebies'] = UdemyFreebiesScraper()
            
        if Config.SCRAPERS.get('yofreesamples', False):
            scrapers['yofreesamples'] = YoFreeSamplesScraper()
            
        if Config.SCRAPERS.get('coursesity', False):
            scrapers['coursesity'] = CoursesityScraper()
            
        if Config.SCRAPERS.get('tutorialbar', False):
            scrapers['tutorialbar'] = TutorialBarScraper()
            
        if Config.SCRAPERS.get('freebiesglobal', False):
            scrapers['freebiesglobal'] = FreebiesGlobalScraper()
        
        logger.info(f"Initialized {len(scrapers)} scrapers: {list(scrapers.keys())}")
        return scrapers
    
    def scrape_all_sources(self, limit_per_source: int = None) -> List[Dict]:
        """Scrape courses from all enabled sources"""
        all_courses = []
        
        if not self.scrapers:
            logger.warning("No scrapers enabled")
            return all_courses
        
        # Use ThreadPoolExecutor for concurrent scraping
        with ThreadPoolExecutor(max_workers=len(self.scrapers)) as executor:
            # Submit scraping tasks
            future_to_scraper = {
                executor.submit(scraper.scrape_courses, limit_per_source): name 
                for name, scraper in self.scrapers.items()
            }
            
            # Collect results as they complete
            for future in as_completed(future_to_scraper):
                scraper_name = future_to_scraper[future]
                try:
                    courses = future.result(timeout=60)  # 60 second timeout per scraper
                    all_courses.extend(courses)
                    logger.info(f"Scraper {scraper_name} completed: {len(courses)} courses")
                    
                except Exception as e:
                    logger.error(f"Scraper {scraper_name} failed: {e}")
        
        logger.info(f"Total courses scraped from all sources: {len(all_courses)}")
        return all_courses
    
    def scrape_single_source(self, source_name: str, limit: int = None) -> List[Dict]:
        """Scrape courses from a single source"""
        if source_name not in self.scrapers:
            logger.error(f"Scraper {source_name} not found or not enabled")
            return []
        
        try:
            scraper = self.scrapers[source_name]
            courses = scraper.scrape_courses(limit)
            logger.info(f"Scraped {len(courses)} courses from {source_name}")
            return courses
            
        except Exception as e:
            logger.error(f"Error scraping from {source_name}: {e}")
            return []
    
    def get_scraper_statistics(self) -> Dict:
        """Get statistics from all scrapers"""
        stats = {}
        
        for name, scraper in self.scrapers.items():
            stats[name] = scraper.get_statistics()
        
        return stats
    
    def reset_all_statistics(self):
        """Reset statistics for all scrapers"""
        for scraper in self.scrapers.values():
            scraper.reset_statistics()
        
        logger.info("Reset statistics for all scrapers")
    
    def test_scrapers(self) -> Dict:
        """Test all scrapers with a small sample"""
        test_results = {}
        
        for name, scraper in self.scrapers.items():
            try:
                logger.info(f"Testing scraper: {name}")
                courses = scraper.scrape_courses(limit=2)
                
                test_results[name] = {
                    'status': 'success' if courses else 'no_courses',
                    'courses_found': len(courses),
                    'sample_course': courses[0] if courses else None
                }
                
            except Exception as e:
                test_results[name] = {
                    'status': 'error',
                    'error': str(e),
                    'courses_found': 0
                }
                logger.error(f"Test failed for {name}: {e}")
        
        return test_results
    
    def get_enabled_scrapers(self) -> List[str]:
        """Get list of enabled scraper names"""
        return list(self.scrapers.keys())
    
    def disable_scraper(self, scraper_name: str):
        """Disable a specific scraper"""
        if scraper_name in self.scrapers:
            del self.scrapers[scraper_name]
            logger.info(f"Disabled scraper: {scraper_name}")
    
    def enable_scraper(self, scraper_name: str):
        """Enable a specific scraper"""
        scraper_classes = {
            'real_discount': RealDiscountScraper,
            'discudemy': DiscUdemyScraper,
            'udemy_freebies': UdemyFreebiesScraper,
            'yofreesamples': YoFreeSamplesScraper,
            'coursesity': CoursesityScraper
        }
        
        if scraper_name in scraper_classes and scraper_name not in self.scrapers:
            self.scrapers[scraper_name] = scraper_classes[scraper_name]()
            logger.info(f"Enabled scraper: {scraper_name}")
        else:
            logger.warning(f"Cannot enable scraper: {scraper_name}")
