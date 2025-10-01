"""
Scrapers package initialization
"""
from .real_discount_scraper import RealDiscountScraper
from .discudemy_scraper import DiscUdemyScraper
from .udemy_freebies_scraper import UdemyFreebiesScraper
from .yofreesamples_scraper import YoFreeSamplesScraper
from .coursesity_scraper import CoursesityScraper

__all__ = [
    'RealDiscountScraper',
    'DiscUdemyScraper', 
    'UdemyFreebiesScraper',
    'YoFreeSamplesScraper',
    'CoursesityScraper'
]
