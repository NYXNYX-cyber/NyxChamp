"""Debug step-by-step scrape_portal flow."""
import asyncio
import logging
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

logging.basicConfig(level=logging.INFO, format="%(name)s %(levelname)s %(message)s")

from app.config import (  # type: ignore
    FIRECRAWL_API_URL,
    FIRECRAWL_API_KEY,
    LLM_API_KEY,
    LLM_BASE_URL,
    LLM_MODEL,
    require_runtime_keys,
)
from app.schemas import ScrapeRequest  # type: ignore
from app.services.firecrawl_client import FirecrawlClient  # type: ignore
from app.services.llm_extractor import LLMExtractor  # type: ignore
from app.services.portals import extract_detail_links, get_portal, hostname_of  # type: ignore


async def main():
    print("=== CONFIG ===")
    print(f"  FIRECRAWL_API_URL = {FIRECRAWL_API_URL}")
    print(f"  FIRECRAWL_API_KEY = {FIRECRAWL_API_KEY[:8]}...")
    print(f"  LLM_BASE_URL      = {LLM_BASE_URL}")
    print(f"  LLM_MODEL         = {LLM_MODEL}")
    print(f"  LLM_API_KEY       = {LLM_API_KEY[:8]}...")
    try:
        require_runtime_keys()
        print("  require_runtime_keys: OK")
    except RuntimeError as e:
        print(f"  require_runtime_keys: FAIL → {e}")
        return 1

    portal = get_portal("lombahub_com")
    print(f"\n=== STEP 1: scrape listing {portal.listing_url} ===")
    async with FirecrawlClient(base_url=FIRECRAWL_API_URL, api_key=FIRECRAWL_API_KEY, timeout=60) as fc:
        listing_md = await fc.scrape_markdown(portal.listing_url)
    print(f"  listing_md length = {len(listing_md)}")

    print("\n=== STEP 2: extract detail links ===")
    base_url = f"https://{hostname_of(portal.listing_url)}"
    detail_urls = extract_detail_links(listing_md, portal, base_url)
    detail_urls = detail_urls[:1]  # cap to 1 for debug
    print(f"  detail_urls ({len(detail_urls)})")
    for u in detail_urls:
        print(f"    - {u}")

    print("\n=== STEP 3: scrape 1 detail + LLM ===")
    if not detail_urls:
        print("  NO detail URLs!")
        return 1
    async with FirecrawlClient(base_url=FIRECRAWL_API_URL, api_key=FIRECRAWL_API_KEY, timeout=60) as fc:
        url = detail_urls[0]
        print(f"  scraping {url}")
        md = await fc.scrape_markdown(url)
        print(f"  markdown length = {len(md)}")
        print(f"  markdown first 400 chars = {md[:400]!r}")
    extractor = LLMExtractor()
    print(f"  extractor created, calling extract()")
    try:
        items = await extractor.extract(md, source_url_hint=url)
        print(f"  items extracted = {len(items)}")
        for i, item in enumerate(items):
            print(f"    [{i}] {item.title} | {item.organizer} | {item.registration_deadline} | {item.level} | {item.source_url}")
    except Exception as e:
        print(f"  LLM extract FAILED: {type(e).__name__}: {e}")


if __name__ == "__main__":
    asyncio.run(main())
