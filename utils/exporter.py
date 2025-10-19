import json
from pathlib import Path
from datetime import datetime
from typing import List, Dict, Any, Optional

from .database import CourseDatabase


def export_active_courses_json(db: CourseDatabase, website_dir: str = "quicktrends_files", filename: str = "courses.json", limit: Optional[int] = 500) -> Path:
    """
    Export active, non-expired courses to a lightweight JSON file for the website to consume.
    Structure:
    {
      "generated_at": "2025-01-01T12:34:56Z",
      "count": N,
      "courses": [
        {"title": ..., "url": ..., "image": ..., "rating": 4.5, "students": 1234, "duration": "5h", "category": "...", "expires_at": "..."},
        ...
      ]
    }
    """
    site_path = Path(website_dir)
    site_path.mkdir(parents=True, exist_ok=True)
    out_path = site_path / filename

    courses = db.get_active_courses(limit=limit) if limit else db.get_active_courses()

    items: List[Dict[str, Any]] = []
    for c in courses:
        # Normalize fields used by the website
        rating_val = None
        try:
            if c.get("rating") is not None:
                rating_val = float(c.get("rating"))
        except Exception:
            rating_val = None

        students_val = None
        try:
            if c.get("students_count") is not None:
                students_val = int(c.get("students_count"))
        except Exception:
            students_val = None

        # decode JSON arrays stored as TEXT
        def _from_json(val):
            try:
                if isinstance(val, str) and val.strip():
                    return json.loads(val)
            except Exception:
                pass
            return None

        items.append({
            "title": c.get("title", ""),
            "url": c.get("course_url", ""),
            "image": c.get("image_url", ""),
            "rating": rating_val,
            "students": students_val,
            "duration": c.get("duration", ""),
            "category": c.get("category", ""),
            "language": c.get("language", ""),
            "instructor": c.get("instructor", ""),
            "subtitle": c.get("subtitle"),
            "description": c.get("description"),
            "level": c.get("level"),
            "lectures": c.get("lectures"),
            "learn": _from_json(c.get("learn")) or [],
            "requirements": _from_json(c.get("requirements")) or [],
            "audience": _from_json(c.get("audience")) or [],
            "last_updated": c.get("last_updated"),
            "scraped_at": c.get("scraped_at"),
            "expires_at": c.get("expires_at"),
        })

    payload = {
        "generated_at": datetime.utcnow().isoformat() + "Z",
        "count": len(items),
        "courses": items,
    }

    with out_path.open("w", encoding="utf-8") as f:
        json.dump(payload, f, ensure_ascii=False)

    return out_path
