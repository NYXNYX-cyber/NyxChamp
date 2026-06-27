"""Test Scraper orchestrator dengan Firecrawl + LLM di-mock."""
import json
from datetime import date, timedelta

import httpx
import pytest
import respx

from app.schemas import ScrapeRequest
from app.services.scraper import scrape_portal


def _listing_html_markdown() -> str:
    """Konversi HTML fixture ke representasi Markdown ala Firecrawl."""
    from pathlib import Path
    html = (Path(__file__).parent / "fixtures" / "lombahub_listing.html").read_text()
    # Untuk test ini, langsung scrape detail URL-nya manual (skip extract
    # link dari markdown). Mock Firecrawl return detail markdown per URL.
    return html  # tidak dipakai; lihat mock di test


def _detail_markdown(title: str, organizer: str, deadline: str, level: str) -> str:
    return f"# {title}\n\n{organizer} — Deadline {deadline}\n\nKategori: {level}"


def _fake_llm_json(items: list[dict]) -> dict:
    return {"competitions": items}


def _make_item(title: str, days_from_now: int, level: str = "nasional") -> dict:
    d = (date.today() + timedelta(days=days_from_now)).isoformat()
    return {
        "title": title,
        "organizer": "Test Org",
        "description": f"Deskripsi untuk {title}",
        "registration_deadline": d,
        "level": level,
        "registration_fee": 0,
        "source_url": "https://lombahub.com/lomba/test",
    }


@pytest.mark.asyncio
@respx.mock
async def test_scrape_portal_end_to_end_happy_path(monkeypatch):
    """Full E2E: 1 portal, 3 detail URL, LLM return 3 items valid."""
    from app.services.llm_extractor import LLMExtractor

    # 1. Firecrawl: listing markdown berisi 3 link detail
    listing_md = """
    [Lomba A](https://lombahub.com/lomba/a-2026)
    [Lomba B](https://lombahub.com/lomba/b-2026)
    [Lomba C](https://lombahub.com/lomba/c-2026)
    [Kategori Desain](https://lombahub.com/category/desain)
    """
    respx.post("http://firecrawl.test:3002/v1/scrape").mock(
        side_effect=[
            httpx.Response(200, json={"success": True, "data": {"markdown": listing_md}}),
            httpx.Response(200, json={"success": True, "data": {"markdown": _detail_markdown("A", "Org A", "2026-12-01", "nasional")}}),
            httpx.Response(200, json={"success": True, "data": {"markdown": _detail_markdown("B", "Org B", "2026-12-02", "provinsi")}}),
            httpx.Response(200, json={"success": True, "data": {"markdown": _detail_markdown("C", "Org C", "2026-12-03", "nasional")}}),
        ]
    )

    # 2. LLMExtractor: monkey-patch extract() untuk return 1 Competition
    from app.schemas import Competition

    async def fake_extract(self, markdown, *, source_url_hint=""):
        # Map URL → item
        mapping = {
            "https://lombahub.com/lomba/a-2026": _make_item("Lomba A 2026", 60),
            "https://lombahub.com/lomba/b-2026": _make_item("Lomba B 2026", 70, "provinsi"),
            "https://lombahub.com/lomba/c-2026": _make_item("Lomba C 2026", 80),
        }
        return [Competition(**mapping[source_url_hint])]

    monkeypatch.setattr(LLMExtractor, "extract", fake_extract)

    req = ScrapeRequest(portal="lombahub_com", job_id="job-1", max_pages=5)
    resp = await scrape_portal(req)

    assert resp.job_id == "job-1"
    assert resp.portal == "lombahub_com"
    assert len(resp.items) == 3
    titles = sorted(c.title for c in resp.items)
    assert titles == ["Lomba A 2026", "Lomba B 2026", "Lomba C 2026"]
    # Hash deterministik
    for c in resp.items:
        assert len(c.hash_md5) == 32
    assert resp.errors == []


@pytest.mark.asyncio
@respx.mock
async def test_scrape_portal_dedup_across_calls(monkeypatch):
    """Kalau LLM return item duplikat, dedup by hash_md5."""
    from app.services.llm_extractor import LLMExtractor
    from app.schemas import Competition

    listing_md = "[Lomba A](https://lombahub.com/lomba/a-2026)"
    respx.post("http://firecrawl.test:3002/v1/scrape").mock(
        side_effect=[
            httpx.Response(200, json={"success": True, "data": {"markdown": listing_md}}),
            httpx.Response(200, json={"success": True, "data": {"markdown": "..."}}),
        ]
    )

    async def fake_extract(self, markdown, *, source_url_hint=""):
        # Return 2 item identik
        item = _make_item("Lomba Duplikat", 60)
        return [Competition(**item), Competition(**item)]

    monkeypatch.setattr(LLMExtractor, "extract", fake_extract)

    req = ScrapeRequest(portal="lombahub_com", job_id="job-2", max_pages=5)
    resp = await scrape_portal(req)

    assert len(resp.items) == 1


@pytest.mark.asyncio
@respx.mock
async def test_scrape_portal_blocked_returns_error_only():
    """Kalau Firecrawl return 403, response berisi error tanpa items."""
    respx.post("http://firecrawl.test:3002/v1/scrape").mock(
        return_value=httpx.Response(403, text="Forbidden")
    )

    req = ScrapeRequest(portal="lombahub_com", job_id="job-3", max_pages=5)
    resp = await scrape_portal(req)

    assert resp.items == []
    assert len(resp.errors) >= 1
    assert "diblokir" in resp.errors[0].lower() or "listing" in resp.errors[0].lower()


@pytest.mark.asyncio
async def test_scrape_portal_unknown_portal_returns_error():
    req = ScrapeRequest(portal="unknown_xyz", job_id="job-4")
    resp = await scrape_portal(req)
    assert resp.items == []
    assert len(resp.errors) >= 1
