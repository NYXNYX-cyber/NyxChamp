"""Debug: lihat raw LLM output untuk 1 URL detail (bypass past-deadline filter)."""
import asyncio
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.config import (  # type: ignore
    FIRECRAWL_API_URL, FIRECRAWL_API_KEY,
    LLM_API_KEY, LLM_BASE_URL, LLM_MODEL,
)
from app.services.firecrawl_client import FirecrawlClient  # type: ignore
from app.services.llm_extractor import LLMExtractor, SYSTEM_PROMPT  # type: ignore


async def main():
    url = "https://lombahub.com/kompetisi-lomba/exfest-lomba-konsep-desain-alat"
    print(f"=== Target: {url}")
    async with FirecrawlClient(base_url=FIRECRAWL_API_URL, api_key=FIRECRAWL_API_KEY, timeout=60) as fc:
        md = await fc.scrape_markdown(url)
    print(f"Markdown length: {len(md)}")
    print("--- markdown ---")
    print(md[:2000])
    print("--- end ---")
    print()
    # Call LLM directly (bypass extractor)
    from openai import AsyncOpenAI
    client = AsyncOpenAI(api_key=LLM_API_KEY, base_url=LLM_BASE_URL)
    user_msg = f"Ekstrak lomba dari Markdown berikut.\n\nURL halaman: {url}\n\n---\n{md[:25_000]}"
    print("=== Calling LLM... ===")
    response = await client.chat.completions.create(
        model=LLM_MODEL,
        messages=[
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": user_msg},
        ],
        response_format={"type": "json_object"},
        temperature=0.1,
        max_tokens=4000,
    )
    raw = response.choices[0].message.content or ""
    print("=== LLM raw response ===")
    print(raw[:3000])
    print("...")
    print()
    try:
        data = json.loads(raw)
        if isinstance(data, dict) and "competitions" in data:
            comps = data["competitions"]
        elif isinstance(data, list):
            comps = data
        else:
            comps = []
        print(f"=== Parsed: {len(comps)} items ===")
        for i, c in enumerate(comps):
            print(f"--- item {i} ---")
            print(json.dumps(c, indent=2, ensure_ascii=False))
    except Exception as e:
        print(f"JSON parse error: {e}")


if __name__ == "__main__":
    asyncio.run(main())
