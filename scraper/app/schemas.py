"""Pydantic schemas untuk kontrak data antara scraper dan Laravel.

Output JSON harus sama persis dengan kolom tabel `competitions` di
Laravel (lihat Rancangan §3 + AGENTS.md §3.3). `hash_md5` dihitung dari
`title + registration_deadline` dan dipakai untuk dedup.
"""
from __future__ import annotations

import hashlib
from datetime import date
from enum import Enum
from typing import Optional

from pydantic import BaseModel, Field, computed_field


class Level(str, Enum):
    KABUPATEN = "kabupaten"
    PROVINSI = "provinsi"
    NASIONAL = "nasional"
    INTERNASIONAL = "internasional"


class Competition(BaseModel):
    """Satu entri kompetisi hasil scraping (sudah bersih, siap INSERT)."""

    title: str = Field(..., max_length=255)
    organizer: str = Field(..., max_length=255)
    description: str
    registration_deadline: date
    level: Level
    registration_fee: float = Field(0.0, ge=0, le=99999999.99)
    source_url: str
    # URL gambar utama/poster dari halaman detail lomba. Dipakai Laravel
    # untuk download lokal dan simpan di storage/app/competitions/.
    # Optional — kalau portal tidak punya hero image, null.
    image_url: Optional[str] = Field(default=None, max_length=500)

    @computed_field  # type: ignore[prop-decorator]
    @property
    def hash_md5(self) -> str:
        """MD5 dari title + deadline untuk dedup lintas portal."""
        raw = f"{self.title}{self.isoformat_deadline()}".encode("utf-8")
        return hashlib.md5(raw).hexdigest()

    def isoformat_deadline(self) -> str:
        return self.registration_deadline.isoformat()


class ScrapeRequest(BaseModel):
    """Payload dari Laravel saat dispatch job scraping."""

    portal: str
    job_id: str
    max_pages: Optional[int] = Field(default=None, ge=1, le=200)


class ScrapeResponse(BaseModel):
    job_id: str
    portal: str
    items: list[Competition]
    errors: list[str] = []
