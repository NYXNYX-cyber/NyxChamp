"""Test FirecrawlKeeper (auto-start via SSH)."""
import asyncio
import httpx
import pytest
import respx

from app.services.firecrawl_keeper import (
    FirecrawlSSHConfig,
    _is_healthy,
    _wait_until_ready,
    ensure_running,
)


# === _is_healthy ===

@pytest.mark.asyncio
@respx.mock
async def test_is_healthy_returns_true_on_200():
    respx.post("http://10.10.1.28:3002/v1/scrape").mock(
        return_value=httpx.Response(200, json={"success": True, "data": {"markdown": "ok"}})
    )
    assert await _is_healthy("http://10.10.1.28:3002") is True


@pytest.mark.asyncio
@respx.mock
async def test_is_healthy_returns_true_on_401():
    """401 = API hidup tapi token salah. Anggap up."""
    respx.post("http://10.10.1.28:3002/v1/scrape").mock(
        return_value=httpx.Response(401, text="Unauthorized")
    )
    assert await _is_healthy("http://10.10.1.28:3002") is True


@pytest.mark.asyncio
@respx.mock
async def test_is_healthy_returns_false_on_500():
    respx.post("http://10.10.1.28:3002/v1/scrape").mock(
        return_value=httpx.Response(500, text="Internal Server Error")
    )
    assert await _is_healthy("http://10.10.1.28:3002") is False


@pytest.mark.asyncio
@respx.mock
async def test_is_healthy_returns_false_on_connection_error():
    respx.post("http://10.10.1.28:3002/v1/scrape").mock(
        side_effect=httpx.ConnectError("Connection refused")
    )
    assert await _is_healthy("http://10.10.1.28:3002") is False


# === _wait_until_ready ===

@pytest.mark.asyncio
@respx.mock
async def test_wait_until_ready_succeeds_quickly():
    """Kalau langsung up, harus return True dalam < 5 detik."""
    respx.post("http://10.10.1.28:3002/v1/scrape").mock(
        return_value=httpx.Response(200, json={"success": True})
    )
    import time
    start = time.monotonic()
    ok = await _wait_until_ready("http://10.10.1.28:3002", timeout_seconds=10)
    elapsed = time.monotonic() - start
    assert ok is True
    assert elapsed < 5  # langsung ready, no polling


@pytest.mark.asyncio
@respx.mock
async def test_wait_until_ready_times_out():
    """Kalau tetap down, return False setelah timeout."""
    respx.post("http://10.10.1.28:3002/v1/scrape").mock(
        side_effect=httpx.ConnectError("Connection refused")
    )
    ok = await _wait_until_ready("http://10.10.1.28:3002", timeout_seconds=4)
    assert ok is False


# === FirecrawlSSHConfig.from_env ===

def test_ssh_config_from_env_with_password(monkeypatch):
    monkeypatch.setenv("FIRECRAWL_SSH_HOST", "10.10.1.28")
    monkeypatch.setenv("FIRECRAWL_SSH_PORT", "2222")
    monkeypatch.setenv("FIRECRAWL_SSH_USER", "deploy")
    monkeypatch.setenv("FIRECRAWL_SSH_PASSWORD", "secret123")
    monkeypatch.delenv("FIRECRAWL_SSH_KEY_PATH", raising=False)
    cfg = FirecrawlSSHConfig.from_env()
    assert cfg.host == "10.10.1.28"
    assert cfg.port == 2222
    assert cfg.user == "deploy"
    assert cfg.password == "secret123"
    assert cfg.key_path is None


def test_ssh_config_from_env_with_key(monkeypatch):
    monkeypatch.setenv("FIRECRAWL_SSH_KEY_PATH", "/root/.ssh/id_firecrawl")
    monkeypatch.delenv("FIRECRAWL_SSH_PASSWORD", raising=False)
    cfg = FirecrawlSSHConfig.from_env()
    assert cfg.key_path == "/root/.ssh/id_firecrawl"
    assert cfg.password is None


def test_ssh_config_defaults(monkeypatch):
    monkeypatch.delenv("FIRECRAWL_SSH_HOST", raising=False)
    monkeypatch.delenv("FIRECRAWL_SSH_PORT", raising=False)
    monkeypatch.delenv("FIRECRAWL_SSH_USER", raising=False)
    monkeypatch.delenv("FIRECRAWL_SSH_PASSWORD", raising=False)
    monkeypatch.delenv("FIRECRAWL_SSH_KEY_PATH", raising=False)
    cfg = FirecrawlSSHConfig.from_env()
    assert cfg.host == "10.10.1.28"
    assert cfg.port == 22
    assert cfg.user == "root"
    assert cfg.password is None
    assert cfg.key_path is None


# === ensure_running ===

@pytest.mark.asyncio
@respx.mock
async def test_ensure_running_skips_ssh_when_already_up(monkeypatch):
    """Kalau Firecrawl up, tidak ada SSH call sama sekali."""
    respx.post("http://10.10.1.28:3002/v1/scrape").mock(
        return_value=httpx.Response(200, json={"success": True})
    )
    ssh_called = False

    async def fake_ssh_start(cfg):
        nonlocal ssh_called
        ssh_called = True
        return True

    monkeypatch.setattr("app.services.firecrawl_keeper._ssh_start_stack", fake_ssh_start)
    ok = await ensure_running("http://10.10.1.28:3002", warmup_timeout=5)
    assert ok is True
    assert ssh_called is False


@pytest.mark.asyncio
@respx.mock
async def test_ensure_running_raises_when_no_ssh_credentials(monkeypatch):
    """Kalau Firecrawl down tapi tidak ada SSH credentials → ValueError."""
    # First call: down. Second call (after ssh): up. But we never get to second.
    route = respx.post("http://10.10.1.28:3002/v1/scrape").mock(
        side_effect=httpx.ConnectError("Connection refused")
    )
    monkeypatch.setattr(
        "app.services.firecrawl_keeper.FirecrawlSSHConfig.from_env",
        lambda: FirecrawlSSHConfig(
            host="10.10.1.28", port=22, user="root",
            password=None, key_path=None,
        ),
    )
    with pytest.raises(ValueError, match="FIRECRAWL_SSH_PASSWORD"):
        await ensure_running("http://10.10.1.28:3002", warmup_timeout=5)
    # confirm at least one health check happened
    assert route.call_count >= 1


@pytest.mark.asyncio
@respx.mock
async def test_ensure_running_calls_ssh_then_waits(monkeypatch):
    """Kalau down: SSH start (mocked) + poll → ready."""
    # First call: down. Second call: up.
    route = respx.post("http://10.10.1.28:3002/v1/scrape").mock(
        side_effect=[
            httpx.Response(500, text="down"),  # initial check: down
            httpx.Response(200, json={"success": True}),  # after start: up
        ]
    )

    async def fake_ssh_start(cfg):
        return True

    monkeypatch.setattr("app.services.firecrawl_keeper._ssh_start_stack", fake_ssh_start)
    monkeypatch.setattr(
        "app.services.firecrawl_keeper.FirecrawlSSHConfig.from_env",
        lambda: FirecrawlSSHConfig(
            host="10.10.1.28", port=22, user="root",
            password="x", key_path=None,
        ),
    )
    ok = await ensure_running("http://10.10.1.28:3002", warmup_timeout=10)
    assert ok is True
    assert route.call_count == 2
