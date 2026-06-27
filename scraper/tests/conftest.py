"""Pytest fixtures (shared)."""
import sys
from pathlib import Path

# Ensure scraper package is importable when running pytest from scraper/
ROOT = Path(__file__).resolve().parent.parent
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))


import os

# Set env minimum untuk import (tidak trigger require_runtime_keys).
os.environ.setdefault("SCRAPER_LLM_API_KEY", "test-key")
os.environ.setdefault("FIRECRAWL_API_URL", "http://firecrawl.test:3002")
os.environ.setdefault("SCRAPER_SERVICE_TOKEN", "test-token")
