"""One-shot smoke test scraper orchestrator. Tidak butuh FastAPI.

Panggil langsung fungsi scrape_portal dengan request LombaHub. Cocok
untuk verifikasi pipeline Firecrawl+LLM end-to-end tanpa harus
maintain daemon process.
"""
import asyncio
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.config import LLM_MODEL, FIRECRAWL_API_URL  # type: ignore
from app.schemas import ScrapeRequest  # type: ignore
from app.services.scraper import scrape_portal  # type: ignore


async def main() -> int:
    print(f"FIRECRAWL_API_URL = {FIRECRAWL_API_URL}")
    print(f"LLM_MODEL = {LLM_MODEL}")
    req = ScrapeRequest(portal="lombahub_com", job_id="smoke-cli-001", max_pages=1)
    print(f"Request: portal={req.portal} job_id={req.job_id} max_pages={req.max_pages}")
    print("Memulai scrape (bisa 30-90 detik)...")
    resp = await scrape_portal(req)
    print()
    print("=" * 60)
    print(f"portal       = {resp.portal}")
    print(f"items_count  = {len(resp.items)}")
    print(f"errors_count = {len(resp.errors)}")
    print("=" * 60)
    if resp.errors:
        print("ERRORS:")
        for e in resp.errors[:5]:
            print(f"  - {e}")
    for i, item in enumerate(resp.items):
        print()
        print(f"--- ITEM {i} ---")
        print(f"  title       = {item.title}")
        print(f"  organizer   = {item.organizer}")
        print(f"  deadline    = {item.registration_deadline.isoformat()}")
        print(f"  level       = {item.level.value}")
        print(f"  fee         = {item.registration_fee}")
        print(f"  source_url  = {item.source_url}")
        print(f"  hash_md5    = {item.hash_md5}")
        print(f"  description (first 200) =")
        for line in item.description.splitlines()[:5]:
            print(f"    {line[:120]}")
    return 0 if resp.items else 1


if __name__ == "__main__":
    sys.exit(asyncio.run(main()))
