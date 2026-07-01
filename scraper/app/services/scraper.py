"""Orkestrasi scraping per portal.

Alur (sesuai Rancangan §2 + AGENTS.md §3.2):
1. Ambil halaman listing portal via Firecrawl → Markdown
2. Extract link detail lomba dari Markdown
3. Untuk setiap detail URL (dibatasi max_pages): scrape Markdown + LLM extract
4. Kumpulkan semua Competition unik (dedup by hash_md5 di Pydantic)
5. Return ScrapeResponse ke Laravel

Concurrency: pakai asyncio.Semaphore untuk cap paralel (default 4).
Tiap detail URL = 1 task: scrape_markdown → LLM extract.
"""
from __future__ import annotations

import asyncio
import logging
from collections.abc import Awaitable

from app.config import (
    FIRECRAWL_API_URL,
    MAX_CONCURRENCY,
    MAX_PAGES_PER_PORTAL,
    require_runtime_keys,
)
from app.schemas import Competition, ScrapeRequest, ScrapeResponse
from app.services.exceptions import (
    LLMError,
    PortalBlockedError,
    PortalConfigError,
    ScraperError,
)
from app.services.firecrawl_client import FirecrawlClient
from app.services.firecrawl_keeper import ensure_running as firecrawl_ensure_running
from app.services.llm_extractor import LLMExtractor
from app.services.portals import (
    extract_detail_links,
    get_portal,
    hostname_of,
)

logger = logging.getLogger(__name__)


async def scrape_portal(req: ScrapeRequest) -> ScrapeResponse:
    """Pipeline utama. Dipanggil dari FastAPI endpoint /scrape.

    Args:
        req: ScrapeRequest dari Laravel (portal, job_id, max_pages optional).

    Returns:
        ScrapeResponse dengan items (Competition) + errors.
        Kalau portal di-block atau konfigurasi invalid → items=[], errors=[msg].
    """
    # Validasi env runtime. Kalau ada yang kurang, fail early.
    try:
        require_runtime_keys()
    except RuntimeError as exc:
        return ScrapeResponse(
            job_id=req.job_id,
            portal=req.portal,
            items=[],
            errors=[str(exc)],
        )

    # Resolve portal config.
    try:
        portal = get_portal(req.portal)
    except PortalConfigError as exc:
        return ScrapeResponse(
            job_id=req.job_id,
            portal=req.portal,
            items=[],
            errors=[str(exc)],
        )

    max_pages = min(req.max_pages or MAX_PAGES_PER_PORTAL, MAX_PAGES_PER_PORTAL)
    concurrency = min(MAX_CONCURRENCY, max_pages)

    errors: list[str] = []
    items: list[Competition] = []

    # Step 0: pastikan Firecrawl stack hidup (auto-start via SSH kalau down).
    # Host punya cron auto-stop 3 menit idle — hemat RAM saat idle.
    try:
        if not await firecrawl_ensure_running(FIRECRAWL_API_URL):  # type: ignore[arg-type]
            errors.append("Firecrawl stack gagal di-start (warmup timeout)")
            return ScrapeResponse(
                job_id=req.job_id, portal=req.portal, items=[], errors=errors
            )
    except ValueError as exc:
        errors.append(f"Firecrawl keeper config invalid: {exc}")
        return ScrapeResponse(
            job_id=req.job_id, portal=req.portal, items=[], errors=errors
        )
    except Exception as exc:
        errors.append(f"Firecrawl keeper error: {exc}")
        return ScrapeResponse(
            job_id=req.job_id, portal=req.portal, items=[], errors=errors
        )

    async with FirecrawlClient() as fc:
        # Step 1: scrape halaman listing
        # Untuk portal Tier 2 (login_required / parked) atau kalau
        # listing kosong setelah scrape, fallback ke Google Search
        # "site:<domain> lomba 2026" untuk dapat URL detail.
        listing_md = ""
        detail_urls: list[str] = []
        base_url = f"https://{hostname_of(portal.listing_url)}"

        if not portal.login_required:
            try:
                listing_md = await fc.scrape_markdown(portal.listing_url)
            except PortalBlockedError as exc:
                errors.append(f"listing diblokir: {exc}")
            except Exception as exc:
                errors.append(f"listing scrape gagal: {exc}")

            if listing_md:
                detail_urls = extract_detail_links(listing_md, portal, base_url)

        # Fallback ke Google Search kalau:
        # - portal login_required (Tier 2), ATAU
        # - listing kosong setelah direct scrape
        if not detail_urls:
            logger.info(
                "portal=%s tier=%d fallback=google_search listing_chars=%d",
                portal.key, portal.tier, len(listing_md),
            )
            try:
                search_query = f"site:{portal.hostname} lomba 2026"
                results = await fc.search(
                    search_query,
                    limit=min(max_pages * 2, 20),
                    include_domains=[portal.hostname],
                    lang="id",
                )
                for r in results:
                    url = r.get("url") or r.get("sourceUrl") or ""
                    if url and is_detail_link(url, portal):
                        detail_urls.append(url)
                    elif url and portal.hostname in url:
                        # Link lain dari portal yang sama (bukan pattern lomba)
                        detail_urls.append(url)
                detail_urls = detail_urls[:max_pages]
            except PortalBlockedError as exc:
                errors.append(f"google search diblokir: {exc}")
            except Exception as exc:
                errors.append(f"google search gagal: {exc}")

        logger.info(
            "portal=%s tier=%d listing=%d_chars detail_links=%d max_pages=%d",
            portal.key, portal.tier, len(listing_md), len(detail_urls), max_pages,
        )

        if not detail_urls:
            errors.append("tidak ada link detail lomba (listing + google search)")
            return ScrapeResponse(
                job_id=req.job_id, portal=req.portal, items=[], errors=errors
            )

        # Step 3: paralel scrape + extract detail URL
        try:
            extractor = LLMExtractor()
        except LLMError as exc:
            return ScrapeResponse(
                job_id=req.job_id,
                portal=req.portal,
                items=[],
                errors=[f"LLM init gagal: {exc}"],
            )

        sem = asyncio.Semaphore(concurrency)

        async def process_one(url: str) -> list[Competition]:
            async with sem:
                return await _scrape_and_extract(fc, extractor, url, errors)

        tasks: list[Awaitable[list[Competition]]] = [
            process_one(url) for url in detail_urls
        ]
        results = await asyncio.gather(*tasks, return_exceptions=True)

        for url, res in zip(detail_urls, results, strict=True):
            if isinstance(res, Exception):
                errors.append(f"{url}: {res}")
                continue
            items.extend(res)

    # Step 4: dedup by hash_md5 (preserve order, keep first)
    seen: set[str] = set()
    unique: list[Competition] = []
    for c in items:
        if c.hash_md5 in seen:
            continue
        seen.add(c.hash_md5)
        unique.append(c)

    logger.info(
        "portal=%s done items=%d unique=%d errors=%d",
        req.portal, len(items), len(unique), len(errors),
    )
    return ScrapeResponse(
        job_id=req.job_id, portal=req.portal, items=unique, errors=errors
    )


async def _scrape_and_extract(
    fc: FirecrawlClient,
    extractor: LLMExtractor,
    url: str,
    error_log: list[str],
) -> list[Competition]:
    """Scrape 1 URL detail + extract via LLM. Return list Competition (bisa kosong)."""
    try:
        md = await fc.scrape_markdown(url)
    except PortalBlockedError as exc:
        error_log.append(f"detail diblokir {url}: {exc}")
        return []
    except Exception as exc:
        error_log.append(f"detail scrape gagal {url}: {exc}")
        return []

    if not md:
        return []

    try:
        items = await extractor.extract(md, source_url_hint=url)
    except LLMError as exc:
        error_log.append(f"LLM extract gagal {url}: {exc}")
        return []
    except ScraperError as exc:
        error_log.append(f"scraper error {url}: {exc}")
        return []

    return items
