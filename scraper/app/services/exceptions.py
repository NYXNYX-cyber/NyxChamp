"""Exception hierarchy untuk scraper.

Dipakai oleh FirecrawlClient, LLMExtractor, dan Scraper orchestrator.
Tiga kategori error yang ingin dibedakan:
- ScraperError: parent, untuk semua error domain scraper
- PortalBlockedError: portal target mengembalikan 403/429 (rate limit)
- LLMError: LLM mengembalikan respons tidak valid / timeout / quota
- PortalConfigError: registry portal tidak punya listing_url dll
"""
from __future__ import annotations


class ScraperError(Exception):
    """Base exception untuk semua error di domain scraper."""


class PortalConfigError(ScraperError):
    """Konfigurasi portal di registry tidak lengkap (listing_url, dsb)."""


class PortalBlockedError(ScraperError):
    """Portal target mengembalikan 403/429 atau sinyal blocking lain.

    Bedanya dengan ScraperError generic: orchestrator bisa men-skip
    portal ini untuk siklus ini tanpa retry agresif.
    """


class LLMError(ScraperError):
    """LLM call gagal (timeout, response tidak valid JSON, quota habis)."""
