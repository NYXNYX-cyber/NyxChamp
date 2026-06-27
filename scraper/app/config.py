"""Konfigurasi scraper service.

Baca dari environment variables. File .env di root scraper/ (opsional,
hanya untuk dev). Nilai default di sini cocok untuk dijalankan lokal
tanpa dependency eksternal (kecuali API key saat scraping beneran).
"""
from __future__ import annotations

import os
from pathlib import Path

from dotenv import load_dotenv

load_dotenv(Path(__file__).resolve().parent.parent / ".env")


SERVICE_HOST: str = os.getenv("SCRAPER_SERVICE_HOST", "127.0.0.1")
SERVICE_PORT: int = int(os.getenv("SCRAPER_SERVICE_PORT", "8001"))

# Token yang harus dikirim Laravel saat panggil endpoint private.
# Diset sama dengan SCRAPER_SERVICE_TOKEN di .env Laravel.
SERVICE_TOKEN: str = os.getenv("SCRAPER_SERVICE_TOKEN", "local-dev-token-ganti-saat-deploy")

# API keys (diisi saat benar-benar scrape, tidak wajib untuk dev)
OPENAI_API_KEY: str | None = os.getenv("OPENAI_API_KEY")
FIRECRAWL_API_KEY: str | None = os.getenv("FIRECRAWL_API_KEY")

# LLM yang dipakai untuk ekstraksi entitas (lihat Rancangan §6)
LLM_MODEL: str = os.getenv("SCRAPER_LLM_MODEL", "gpt-4o-mini")

# Daftar portal target (lihat Rancangan §1). Tambah baru di sini setelah
# cek ToS & rate-limit portal target.
TARGET_PORTALS: list[str] = [
    "kompetisi_co_id",
    "ikutlomba_id",
    "ajangjuara_com",
    "sejutacita_id",
    "luarkampus_id",
    "lombahub_com",
]
