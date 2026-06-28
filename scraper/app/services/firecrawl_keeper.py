"""Auto-start Firecrawl self-hosted stack saat pipeline scrape jalan.

Firecrawl stack di-host (10.10.1.28) punya cron auto-stop 3 menit
setelah touch /tmp/firecrawl-active terakhir — fitur hemat RAM (lihat
AGENTS.md §6b). Karena itu, setiap kali pipeline scrape mau jalan,
kita harus pastikan stack hidup dulu.

Pendekatan:
1. Health check via HTTP /v1/scrape (timeout 3s)
2. Kalau down → SSH ke host, jalankan /usr/local/bin/firecrawl-start.sh
3. Poll /v1/scrape sampai ready (max WARMUP_TIMEOUT_SECONDS, default 60s)
4. Touch /tmp/firecrawl-active setelah start supaya auto-stop mundur 3 menit

Login SSH pakai password (env var FIRECRAWL_SSH_PASSWORD). Untuk prod,
ganti dengan SSH key + ssh agent (lihat TODO di AGENTS.md §7).
"""
from __future__ import annotations

import asyncio
import logging
import os
import shlex
import time
from dataclasses import dataclass

import httpx

logger = logging.getLogger(__name__)


@dataclass(frozen=True)
class FirecrawlSSHConfig:
    """Konfigurasi SSH untuk trigger start di remote host."""

    host: str
    port: int
    user: str
    password: str | None  # opsional, bisa pakai key-based
    key_path: str | None  # opsional, alternatif password

    @classmethod
    def from_env(cls) -> "FirecrawlSSHConfig":
        """Baca dari env. Wajib set host, port, user, dan (password ATAU key)."""
        host = os.getenv("FIRECRAWL_SSH_HOST", "10.10.1.28")
        port = int(os.getenv("FIRECRAWL_SSH_PORT", "22"))
        user = os.getenv("FIRECRAWL_SSH_USER", "root")
        password = os.getenv("FIRECRAWL_SSH_PASSWORD") or None
        key_path = os.getenv("FIRECRAWL_SSH_KEY_PATH") or None
        return cls(
            host=host, port=port, user=user, password=password, key_path=key_path
        )


# === Public API ===

WARMUP_TIMEOUT_SECONDS: int = int(os.getenv("FIRECRAWL_WARMUP_TIMEOUT_SECONDS", "60"))
HEALTH_CHECK_TIMEOUT_SECONDS: float = float(
    os.getenv("FIRECRAWL_HEALTH_CHECK_TIMEOUT_SECONDS", "3")
)
START_SCRIPT_PATH: str = "/usr/local/bin/firecrawl-start.sh"
TOUCH_COMMAND: str = "touch /tmp/firecrawl-active"


async def ensure_running(
    firecrawl_api_url: str,
    ssh: FirecrawlSSHConfig | None = None,
    *,
    warmup_timeout: int = WARMUP_TIMEOUT_SECONDS,
) -> bool:
    """Pastikan Firecrawl stack hidup. Return True kalau sudah ready.

    Args:
        firecrawl_api_url: Base URL Firecrawl (e.g. http://10.10.1.28:3002).
        ssh: Konfigurasi SSH. Kalau None, fallback ke from_env().
        warmup_timeout: Max detik menunggu Firecrawl ready setelah start.

    Returns:
        True kalau Firecrawl ready (baik sudah up atau berhasil di-start).
        False kalau gagal start atau warmup timeout.

    Raises:
        ValueError: Kalau SSH config tidak punya password/key.
    """
    if await _is_healthy(firecrawl_api_url):
        logger.info("firecrawl keeper: stack sudah hidup, skip start")
        return True

    cfg = ssh or FirecrawlSSHConfig.from_env()
    if not cfg.password and not cfg.key_path:
        raise ValueError(
            "Firecrawl auto-start butuh FIRECRAWL_SSH_PASSWORD atau "
            "FIRECRAWL_SSH_KEY_PATH. Lihat .env.example."
        )

    logger.info(
        "firecrawl keeper: stack down, trigger start di %s@%s",
        cfg.user, cfg.host,
    )
    ok = await _ssh_start_stack(cfg)
    if not ok:
        logger.error("firecrawl keeper: gagal trigger start script")
        return False

    return await _wait_until_ready(firecrawl_api_url, warmup_timeout)


# === Internal ===

async def _is_healthy(api_url: str) -> bool:
    """Health check via /v1/scrape dengan request minimal. True kalau HTTP 200."""
    url = api_url.rstrip("/") + "/v1/scrape"
    try:
        async with httpx.AsyncClient(timeout=HEALTH_CHECK_TIMEOUT_SECONDS) as client:
            r = await client.post(
                url,
                json={"url": "https://example.com", "formats": ["markdown"]},
                headers={"Authorization": "Bearer health-check"},
            )
            # 200 = up. 401/403 = up tapi token salah. 5xx/connection = down.
            return r.status_code < 500
    except (httpx.HTTPError, OSError):
        return False


async def _ssh_start_stack(cfg: FirecrawlSSHConfig) -> bool:
    """Trigger firecrawl-start.sh di remote + touch active flag.

    Subprocess ssh dengan password via stdin (pakai sshpass). Untuk key-based,
    kosongkan password dan pakai -i.
    """
    # Build sshpass command kalau pakai password; kalau key, pakai ssh langsung.
    if cfg.password:
        sshpass_path = _find_sshpass()
        if not sshpass_path:
            logger.error(
                "firecrawl keeper: 'sshpass' tidak ditemukan di PATH. "
                "Install sshpass atau pakai FIRECRAWL_SSH_KEY_PATH."
            )
            return False
        cmd = [
            sshpass_path, "-p", cfg.password,
            "ssh",
            "-o", "StrictHostKeyChecking=no",
            "-o", "UserKnownHostsFile=/dev/null",
            "-o", f"ConnectTimeout=5",
            "-p", str(cfg.port),
            f"{cfg.user}@{cfg.host}",
            f"{START_SCRIPT_PATH} && {TOUCH_COMMAND}",
        ]
    else:
        cmd = [
            "ssh",
            "-i", cfg.key_path,  # type: ignore[arg-type]
            "-o", "StrictHostKeyChecking=no",
            "-o", "UserKnownHostsFile=/dev/null",
            "-o", f"ConnectTimeout=5",
            "-p", str(cfg.port),
            f"{cfg.user}@{cfg.host}",
            f"{START_SCRIPT_PATH} && {TOUCH_COMMAND}",
        ]

    try:
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=30)
        if proc.returncode != 0:
            logger.error(
                "firecrawl keeper: ssh exit=%d stderr=%s",
                proc.returncode, stderr.decode(errors="replace")[:500],
            )
            return False
        logger.info(
            "firecrawl keeper: ssh ok stdout=%s",
            stdout.decode(errors="replace")[:200].strip(),
        )
        return True
    except asyncio.TimeoutError:
        logger.error("firecrawl keeper: ssh timeout 30s")
        return False
    except FileNotFoundError as exc:
        logger.error("firecrawl keeper: binary not found: %s", exc)
        return False


async def _wait_until_ready(api_url: str, timeout_seconds: int) -> bool:
    """Poll health check setiap 2 detik sampai ready atau timeout."""
    deadline = time.monotonic() + timeout_seconds
    attempt = 0
    while time.monotonic() < deadline:
        attempt += 1
        if await _is_healthy(api_url):
            logger.info("firecrawl keeper: ready setelah %d attempt", attempt)
            return True
        await asyncio.sleep(2)
    logger.error(
        "firecrawl keeper: timeout %ds, %d attempt, masih down",
        timeout_seconds, attempt,
    )
    return False


def _find_sshpass() -> str | None:
    """Cari binary sshpass di PATH. Return path atau None."""
    from shutil import which
    return which("sshpass")
