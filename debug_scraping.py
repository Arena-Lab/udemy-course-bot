"""
Debug scraping to see why it's not finding more courses
"""
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from utils.scraper_manager import ScraperManager
import asyncio

async def debug_scraping():
    """Debug each scraper individually"""
    print("🔍 Debugging Scraping Issues")
    print("=" * 50)
    
    scraper_manager = ScraperManager()
    
    # Test each scraper individually
    for scraper_name in scraper_manager.get_enabled_scrapers():
        print(f"\n🧪 Testing {scraper_name}:")
        print("-" * 30)
        
        try:
            courses = scraper_manager.scrape_single_source(scraper_name, limit=20)
            print(f"✅ Found {len(courses)} courses")
            
            if courses:
                print("📋 Sample courses:")
                for i, course in enumerate(courses[:3]):
                    print(f"  {i+1}. {course.get('title', 'No title')[:50]}...")
                    print(f"     URL: {course.get('course_url', 'No URL')}")
                    print(f"     Source: {course.get('source_website', 'Unknown')}")
            else:
                print("❌ No courses found - checking website structure...")
                
                # Get the scraper and test basic connectivity
                scraper = scraper_manager.scrapers.get(scraper_name)
                if scraper:
                    response = scraper._make_request(scraper.base_url)
                    if response:
                        print(f"✅ Website accessible: {response.status_code}")
                        soup = scraper._parse_html(response.text)
                        if soup:
                            # Check for common course elements
                            elements = soup.find_all(['div', 'article', 'section'])
                            print(f"✅ Found {len(elements)} HTML elements")
                            
                            # Look for course-related classes
                            course_indicators = ['course', 'card', 'item', 'post', 'deal']
                            found_indicators = []
                            for indicator in course_indicators:
                                matching = soup.find_all(class_=lambda x: x and indicator in x.lower() if x else False)
                                if matching:
                                    found_indicators.append(f"{indicator}: {len(matching)}")
                            
                            if found_indicators:
                                print(f"🔍 Potential course elements: {', '.join(found_indicators)}")
                            else:
                                print("⚠️  No obvious course elements found")
                        else:
                            print("❌ Failed to parse HTML")
                    else:
                        print("❌ Website not accessible")
                        
        except Exception as e:
            print(f"❌ Error testing {scraper_name}: {e}")
    
    print("\n" + "=" * 50)
    print("🎯 Recommendations:")
    print("1. Check if websites have changed their structure")
    print("2. Verify if sites are blocking our requests")
    print("3. Consider adding more delay between requests")
    print("4. Check if sites require JavaScript rendering")

if __name__ == "__main__":
    asyncio.run(debug_scraping())
