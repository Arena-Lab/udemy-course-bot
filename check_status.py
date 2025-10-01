"""
Check bot status and recent activity
"""
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from utils.database import CourseDatabase
from datetime import datetime, timedelta

def check_bot_status():
    """Check current bot status"""
    print("🤖 Udemy Course Bot Status Check")
    print("=" * 50)
    
    # Check database
    try:
        db = CourseDatabase()
        stats = db.get_statistics()
        
        print("📊 Database Statistics:")
        print(f"  • Total Courses: {stats.get('total_courses', 0)}")
        print(f"  • Posted Courses: {stats.get('posted_courses', 0)}")
        print(f"  • Pending Courses: {stats.get('pending_courses', 0)}")
        
        # Source breakdown
        source_breakdown = stats.get('source_breakdown', {})
        if source_breakdown:
            print(f"\n📈 Sources Breakdown:")
            for source, count in source_breakdown.items():
                print(f"  • {source}: {count} courses")
        
        # Check recent activity
        recent_courses = db.get_unposted_courses(limit=5)
        if recent_courses:
            print(f"\n📋 Recent Pending Courses:")
            for course in recent_courses[:3]:
                print(f"  • {course['title'][:50]}...")
        else:
            print(f"\n📋 No pending courses")
            
    except Exception as e:
        print(f"❌ Database error: {e}")
    
    # Check log file
    try:
        log_file = "logs/bot.log"
        if os.path.exists(log_file):
            with open(log_file, 'r') as f:
                lines = f.readlines()
                
            recent_lines = lines[-10:] if len(lines) >= 10 else lines
            
            print(f"\n📝 Recent Log Activity:")
            for line in recent_lines[-5:]:
                if "Posted course:" in line or "ERROR" in line or "INFO" in line:
                    timestamp = line.split(' - ')[0] if ' - ' in line else ''
                    message = line.split(' - ')[-1].strip() if ' - ' in line else line.strip()
                    print(f"  {timestamp}: {message}")
        else:
            print(f"\n📝 No log file found")
            
    except Exception as e:
        print(f"❌ Log error: {e}")
    
    print("\n" + "=" * 50)
    print("✅ Status check complete!")

if __name__ == "__main__":
    check_bot_status()
