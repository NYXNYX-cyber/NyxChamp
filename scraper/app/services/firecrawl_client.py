"""Async HTTP client untuk Firecrawl self-hosted.

Endpoint yang dipakai (lihat https://docs.firecrawl.dev):
- POST /v1/scrape  — single URL → markdown/json
- POST /v1/crawl   — multi URL (crawl job, async polling)

Versi ini fokus ke /v1/scrape untuk MVP. Multi-URL crawl ditambahkan
nanti kalau perlu (lihat scraper/app/services/scraper.py).

Pattern retry pakai tenacity (opsional, fallback ke manual loop).
"""
from __future__ import annotations

import asyncio
import logging
from typing import Any

import httpx

from app.config import FIRECRAWL_API_KEY, FIRECRAWL_API_URL
from app.services.exceptions import PortalBlockedError

logger = logging.getLogger(__name__)

# Firecrawl default timeout per request. Deep crawl bisa lebih lama,
# tapi untuk /scrape single page 60s cukup.
DEFAULT_TIMEOUT_S: float = 60.0

# Retry: total 3 attempt, exponential 1s/2s/4s. 4xx (kecuali 429) tidak retry.
MAX_ATTEMPTS: int = 3


class FirecrawlClient:
    """Async client untuk Firecrawl /v1/scrape.

    Usage::

        client = FirecrawlClient()
        markdown = await client.scrape_markdown("https://lombahub.com/lomba")

    Attributes:
        base_url: Basis URL Firecrawl (wajib di-set via env).
        api_key: Bearer token (opsional, None kalau self-hosted tanpa auth).
        timeout: Per-request timeout dalam detik.
    """

    def __init__(
        self,
        base_url: str | None = FIRECRAWL_API_URL,
        api_key: str | None = FIRECRAWL_API_KEY,
        timeout: float = DEFAULT_TIMEOUT_S,
    ) -> None:
        if not base_url:
            raise ValueError(
                "FIRECRAWL_API_URL belum di-set. Tambahkan di .env (lihat .env.example)."
            )
        self.base_url: str = base_url.rstrip("/")
        self.api_key = api_key
        self.timeout = timeout
        headers: dict[str, str] = {"Content-Type": "application/json"}
        if self.api_key:
            headers["Authorization"] = f"Bearer {self.api_key}"
        self._client = httpx.AsyncClient(
            base_url=self.base_url,
            headers=headers,
            timeout=timeout,
        )

    async def close(self) -> None:
        await self._client.aclose()

    async def __aenter__(self) -> "FirecrawlClient":
        return self

    async def __aexit__(self, *exc: Any) -> None:
        await self.close()

    async def scrape_markdown(
        self, url: str, *, formats: list[str] | None = None
    ) -> str:
        """Scrape satu URL, kembalikan Markdown body.

        Args:
            url: URL halaman yang akan di-scrape.
            formats: List format Firecrawl. Default ["markdown"].

        Returns:
            Markdown body string. Empty string kalau Firecrawl tidak
            mengembalikan markdown (mis. halaman JS-only gagal render).

        Raises:
            PortalBlockedError: Kalau Firecrawl/portal mengembalikan 403/429
                setelah retry. Orchestrator akan skip portal ini.
            httpx.HTTPError: Untuk error HTTP lain (5xx, timeout) yang tidak
                bisa di-recover.
        """
        formats = formats or ["markdown"]
        payload = {"url": url, "formats": formats}

        last_exc: Exception | None = None
        for attempt in range(1, MAX_ATTEMPTS + 1):
            try:
                response = await self._client.post("/v1/scrape", json=payload)
            except httpx.TimeoutException as exc:
                last_exc = exc
                logger.warning(
                    "firecrawl timeout attempt=%d url=%s", attempt, url
                )
                await self._sleep_backoff(attempt)
                continue
            except httpx.HTTPError as exc:
                # Connection error, dll. Retry-able.
                last_exc = exc
                logger.warning(
                    "firecrawl httpx error attempt=%d url=%s err=%s",
                    attempt,
                    url,
                    exc,
                )
                await self._sleep_backoff(attempt)
                continue

            status = response.status_code
            if status == 200:
                data = response.json()
                return self._extract_markdown(data)
            if status in (403, 429):
                # Blocked / rate-limited. Jangan retry — langsung raise.
                raise PortalBlockedError(
                    f"Firecrawl status={status} url={url} body={response.text[:200]}"
                )
            if 500 <= status < 600:
                # Server error. Retry dengan backoff.
                last_exc = httpx.HTTPStatusError(
                    f"server {status}", request=response.request, response=response
                )
                logger.warning(
                    "firecrawl 5xx attempt=%d url=%s status=%d",
                    attempt,
                    url,
                    status,
                )
                await self._sleep_backoff(attempt)
                continue
            # 4xx lain (400, 404, dll) — tidak retry, langsung fail.
            raise httpx.HTTPStatusError(
                f"client {status} url={url} body={response.text[:200]}",
                request=response.request,
                response=response,
            )

        # Semua attempt habis.
        assert last_exc is not None
        raise last_exc

    @staticmethod
    async def _sleep_backoff(attempt: int) -> None:
        # 1s, 2s, 4s
        await asyncio.sleep(2 ** (attempt - 1))

    @staticmethod
    def _extract_markdown(data: dict[str, Any]) -> str:
        """Ambil markdown dari response Firecrawl.

        Format response (v1): {"success": true, "data": {"markdown": "...", ...}}
        """
        if not data.get("success", False):
            return ""
        inner = data.get("data") or {}
        md = inner.get("markdown") or ""
        if isinstance(md, str):
            return md
        return ""

    async def search(
        self,
        query: str,
        *,
        limit: int = 10,
        include_domains: list[str] | None = None,
        lang: str = "id",
    ) -> list[dict[str, Any]]:
        """Cari via Firecrawl /v1/search.

        Dipakai sebagai fallback kalau direct scrape gagal atau portal
        login-required (lihat scraper.py step 0). Return list of result
        dicts dengan minimal key: {url, title, markdown?, description?}.

        Args:
            query: Search query string. Pakai site:<domain> operator untuk
                filter ke domain tertentu (mis. "site:ikutlomba.id lomba 2026").
            limit: Maks hasil (default 10, max 50 di Firecrawl).
            include_domains: Opsional, list domain yang diizinkan
                (Firecrawl akan enforce).
            lang: Bahasa hasil (default "id" Indonesia).

        Returns:
            List of result dicts. Bisa kosong kalau search gagal atau
            tidak ada hasil.

        Raises:
            httpx.HTTPError: Untuk error HTTP 5xx/timeout yang tidak bisa recover.
        """
        if not query:
            return []

        payload: dict[str, Any] = {
            "query": query,
            "limit": min(limit, 50),
            "lang": lang,
        }
        if include_domains:
            payload["includeDomains"] = include_domains

        last_exc: Exception | None = None
        for attempt in range(1, MAX_ATTEMPTS + 1):
            try:
                response = await self._client.post("/v1/search", json=payload)
            except httpx.TimeoutException as exc:
                last_exc = exc
                logger.warning(
                    "firecrawl search timeout attempt=%d query=%s",
                    attempt, query,
                )
                await self._sleep_backoff(attempt)
                continue
            except httpx.HTTPError as exc:
                last_exc = exc
                logger.warning(
                    "firecrawl search httpx error attempt=%d query=%s err=%s",
                    attempt, query, exc,
                )
                await self._sleep_backoff(attempt)
                continue

            status = response.status_code
            if status == 200:
                data = response.json()
                if not data.get("success", False):
                    logger.warning(
                        "firecrawl search success=false query=%s body=%s",
                        query, response.text[:200],
                    )
                    return []
                # Format response: {"success": true, "data": [...]} atau
                # {"success": true, "data": {"results": [...]}}
                inner = data.get("data") or {}
                if isinstance(inner, list):
                    return inner
                if isinstance(inner, dict):
                    return inner.get("results") or inner.get("data") or []
                return []
            if status in (403, 429):
                # Blocked / rate-limited. Jangan retry.
                raise PortalBlockedError(
                    f"Firecrawl search status={status} query={query} body={response.text[:200]}"
                )
            if 500 <= status < 600:
                last_exc = httpx.HTTPStatusError(
                    f"server {status}", request=response.request, response=response
                )
                logger.warning(
                    "firecrawl search 5xx attempt=%d status=%d",
                    attempt, status,
                )
                await self._sleep_backoff(attempt)
                continue
            raise httpx.HTTPStatusError(
                f"client {status} query={query} body={response.text[:200]}",
                request=response.request,
                response=response,
            )

        assert last_exc is not None
        raise last_exc
