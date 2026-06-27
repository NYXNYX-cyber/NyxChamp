"""Orkestrasi scraping per portal.

Untuk Fase 5 (ekstraksi & integrasi Crawl4AI/Firecrawl + GPT-4o-mini),
logic asli akan ada di sini. Saat ini placeholder: kembalikan list kosong
supaya service tetap up dan bisa dipanggil dari Laravel.
"""
from __future__ import annotations

from app.schemas import ScrapeRequest, ScrapeResponse


def scrape_portal(req: ScrapeRequest) -> ScrapeResponse:
    """Stub. Implementasi nyata ada di Fase 5.

    Alur (sesuai Rancangan §2):
    1. Crawl halaman listing portal via Crawl4AI / Firecrawl
    2. Tiap halaman -> Markdown (siap LLM)
    3. GPT-4o-mini ekstrak entitas Competition dari Markdown
    4. Kumpulkan semua Competition, hitung hash_md5 otomatis (lihat schemas)
    5. Return ScrapeResponse ke Laravel
    """
    return ScrapeResponse(
        job_id=req.job_id,
        portal=req.portal,
        items=[],
        errors=[f"Scraper untuk '{req.portal}' belum diimplementasikan (Fase 5)"],
    )
