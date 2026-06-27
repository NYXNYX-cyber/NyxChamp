"""Test FirecrawlClient (HTTP mocked via respx)."""
import httpx
import pytest
import respx

from app.services.exceptions import PortalBlockedError
from app.services.firecrawl_client import FirecrawlClient


@pytest.mark.asyncio
@respx.mock
async def test_scrape_markdown_success():
    respx.post("http://firecrawl.test:3002/v1/scrape").mock(
        return_value=httpx.Response(
            200,
            json={
                "success": True,
                "data": {"markdown": "# Hello\n\nWorld content"},
            },
        )
    )
    async with FirecrawlClient(base_url="http://firecrawl.test:3002") as fc:
        md = await fc.scrape_markdown("https://example.com")
    assert md == "# Hello\n\nWorld content"


@pytest.mark.asyncio
@respx.mock
async def test_scrape_markdown_403_raises_blocked():
    respx.post("http://firecrawl.test:3002/v1/scrape").mock(
        return_value=httpx.Response(403, text="Forbidden")
    )
    async with FirecrawlClient(base_url="http://firecrawl.test:3002") as fc:
        with pytest.raises(PortalBlockedError):
            await fc.scrape_markdown("https://example.com")


@pytest.mark.asyncio
@respx.mock
async def test_scrape_markdown_429_raises_blocked():
    respx.post("http://firecrawl.test:3002/v1/scrape").mock(
        return_value=httpx.Response(429, text="Too Many Requests")
    )
    async with FirecrawlClient(base_url="http://firecrawl.test:3002") as fc:
        with pytest.raises(PortalBlockedError):
            await fc.scrape_markdown("https://example.com")


@pytest.mark.asyncio
@respx.mock
async def test_scrape_markdown_500_retries_then_raises():
    route = respx.post("http://firecrawl.test:3002/v1/scrape").mock(
        return_value=httpx.Response(500, text="Internal Server Error")
    )
    async with FirecrawlClient(base_url="http://firecrawl.test:3002", timeout=5) as fc:
        with pytest.raises(httpx.HTTPStatusError):
            await fc.scrape_markdown("https://example.com")
    # 3 attempts (retry 2x setelah attempt pertama)
    assert route.call_count == 3


@pytest.mark.asyncio
@respx.mock
async def test_scrape_markdown_empty_response():
    respx.post("http://firecrawl.test:3002/v1/scrape").mock(
        return_value=httpx.Response(
            200,
            json={"success": True, "data": {"markdown": ""}},
        )
    )
    async with FirecrawlClient(base_url="http://firecrawl.test:3002") as fc:
        md = await fc.scrape_markdown("https://example.com")
    assert md == ""


@pytest.mark.asyncio
@respx.mock
async def test_scrape_markdown_sends_bearer_token():
    route = respx.post("http://firecrawl.test:3002/v1/scrape").mock(
        return_value=httpx.Response(
            200, json={"success": True, "data": {"markdown": "ok"}}
        )
    )
    async with FirecrawlClient(
        base_url="http://firecrawl.test:3002", api_key="my-secret-key"
    ) as fc:
        await fc.scrape_markdown("https://example.com")
    request = route.calls.last.request
    assert request.headers["Authorization"] == "Bearer my-secret-key"
