"""
One-time metadata enrichment for existing DB courses.
- Loads active, unposted courses
- For each, fetches Udemy metadata via DiscUdemyScraper/BaseScraper helpers
- Normalizes with BaseScraper._normalize_course_data
- Updates DB row with any newly available real fields

Usage:
  python scripts/enrich_db_metadata.py [--limit 100]
"""
from __future__ import annotations

import argparse
import sys
import os
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from utils.database import CourseDatabase
from scrapers.discudemy_scraper import DiscUdemyScraper


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--limit', type=int, default=150, help='Max courses to attempt to enrich')
    args = ap.parse_args()

    db = CourseDatabase()
    scraper = DiscUdemyScraper()

    # pull a batch of pending (unposted) first
    pending = db.get_unposted_courses(limit=args.limit)

    # If none pending, we can still try recent active ones by reading directly
    if not pending:
        # fallback: naive select recent unposted is empty; we skip
        print('No pending courses found. Nothing to enrich.')
        return

    count = 0
    enriched = 0
    for row in pending:
        count += 1
        url = row.get('course_url')
        if not url or 'udemy.com/course/' not in url:
            continue
        try:
            meta = scraper._fetch_udemy_metadata(url, referer=url) or {}
            if not meta:
                continue
            # Preserve existing minimal fields
            base = {
                'title': row.get('title'),
                'course_url': url,
                'discounted_price': 'Free',
            }
            # merge meta
            for k in ['title','image_url','category','instructor','language','price','currency','rating','students_count']:
                v = meta.get(k)
                if v:
                    base[k] = v
            # Normalize using scraper's normalization (handles image upgrade and language mapping)
            normalized = scraper._normalize_course_data(base)
            # Push to DB (will update existing row via hash)
            db.enrich_with(normalized)
            enriched += 1
        except Exception:
            continue

    print(f'Attempted: {count}, enriched: {enriched}')


if __name__ == '__main__':
    main()
