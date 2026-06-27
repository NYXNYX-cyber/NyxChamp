"""Konfigurasi scraper service.

Baca dari environment variables. File .env di root Laravel (dipakai
bersama) atau .env lokal di scraper/.env (override).

Lihat AGENTS.md §3.2 untuk pipeline + §3.4 untuk jadwal + §3.6 untuk
konvensi. Kontrak JSON dengan Laravel lihat CONTRACT.md.
"""
from __future__ import annotations

import os
from pathlib import Path

from dotenv import load_dotenv

# Muat .env dari root repo (Laravel) dulu, lalu override dengan scraper/.env
# kalau ada (untuk development terisolasi).
_repo_root = Path(__file__).resolve().parent.parent.parent
_scraper_root = Path(__file__).resolve().parent.parent

load_dotenv(_repo_root / ".env", override=False)
load_dotenv(_scraper_root / ".env", override=True)


# === Service (FastAPI server) ===

SERVICE_HOST: str = os.getenv("SCRAPER_SERVICE_HOST", "127.0.0.1")
SERVICE_PORT: int = int(os.getenv("SCRAPER_SERVICE_PORT", "8001"))

# Token yang harus dikirim Laravel saat panggil endpoint private.
# Diset sama dengan SCRAPER_SERVICE_TOKEN di .env Laravel.
SERVICE_TOKEN: str = os.getenv(
    "SCRAPER_SERVICE_TOKEN", "local-dev-token-ganti-saat-deploy"
)


# === Firecrawl (self-hosted) ===

# URL basis Firecrawl. Tidak ada default — wajib di-set di .env sebelum run
# pipeline scraping. Contoh: http://10.10.1.28:3002
FIRECRAWL_API_URL: str | None = os.getenv("FIRECRAWL_API_URL")

# Self-hosted Firecrawl biasanya tanpa auth; kalau pakai token, set di sini.
FIRECRAWL_API_KEY: str | None = os.getenv("FIRECRAWL_API_KEY") or None


# === LLM (DeepSeek v4 Flash via OpenCode Zen) ===

# OpenAI-compatible base URL. Default ke OpenCode Zen.
LLM_BASE_URL: str = os.getenv(
    "SCRAPER_LLM_BASE_URL", "https://opencode.ai/zen/v1"
)

# Model LLM. Default DeepSeek v4 Flash. Locked in AGENTS.md §3.1.
LLM_MODEL: str = os.getenv("SCRAPER_LLM_MODEL", "deepseek-v4-flash")

# API key. Wajib di-set. Tidak ada default karena akan incur cost kalau lupa.
LLM_API_KEY: str | None = os.getenv("SCRAPER_LLM_API_KEY") or None


# === Concurrency ===

# Batas paralel asyncio.gather per call. Jangan lebih dari 8 — Firecrawl
# rate-limit dan LLM rate-limit bisa kena.
MAX_CONCURRENCY: int = int(os.getenv("SCRAPER_MAX_CONCURRENCY", "4"))

# Batas halaman listing yang di-crawl per portal. Deep crawl max 200.
MAX_PAGES_PER_PORTAL: int = int(os.getenv("SCRAPER_MAX_PAGES_PER_PORTAL", "20"))


# === Daftar portal target (lihat Rancangan §1) ===
# Tambah baru di sini setelah cek ToS & rate-limit portal target.
TARGET_PORTALS: list[str] = [
    "lombahub_com",
    "ikutlomba_id",
    "kompetisi_co_id",
    "ajangjuara_com",
    "sejutacita_id",
    "luarkampus_id",
]


def require_runtime_keys() -> None:
    """Validasi env yang wajib ada saat pipeline scraping berjalan.

    Dipanggil dari scraper.py orchestrator (bukan dari FastAPI startup,
    karena endpoint /health harus bisa jalan tanpa API key untuk liveness
    check). Naikkan RuntimeError dengan pesan jelas kalau ada yang kurang.
    """
    missing: list[str] = []
    if not FIRECRAWL_API_URL:
        missing.append("FIRECRAWL_API_URL")
    if not LLM_API_KEY:
        missing.append("SCRAPER_LLM_API_KEY")
    if missing:
        raise RuntimeError(
            "Env berikut wajib di-set untuk scraping: " + ", ".join(missing)
            + ". Lihat .env.example."
        )
