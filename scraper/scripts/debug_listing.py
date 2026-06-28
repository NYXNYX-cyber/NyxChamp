"""Debug: ambil markdown Lombahub via Firecrawl + tampilkan hasil extract."""
import asyncio
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.config import FIRECRAWL_API_URL, FIRECRAWL_API_KEY  # type: ignore
from app.services.firecrawl_client import FirecrawlClient  # type: ignore
from app.services.portals import extract_detail_links, get_portal  # type: ignore


async def main():
    print(f"Firecrawl URL: {FIRECRAWL_API_URL}")
    portal = get_portal("lombahub_com")
    print(f"Portal: {portal.name} hostname={portal.hostname}")
    print(f"Pattern: {portal.detail_pattern.pattern}")
    print()
    async with FirecrawlClient(
        base_url=FIRECRAWL_API_URL, api_key=FIRECRAWL_API_KEY, timeout=60
    ) as fc:
        md = await fc.scrape_markdown(portal.listing_url)
    print(f"Markdown length: {len(md)}")
    print("--- first 500 chars ---")
    print(md[:500])
    print("--- end ---")
    print()
    links = extract_detail_links(md, portal, portal.listing_url)
    print(f"Extracted {len(links)} detail links:")
    for l in links[:10]:
        print(f"  - {l}")


if __name__ == "__main__":
    asyncio.run(main())
