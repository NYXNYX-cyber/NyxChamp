"""Registry 6 portal target scraping.

Setiap portal punya:
- key: identifier (dipakai di CLI flag --portal=lombahub_com)
- name: nama display (untuk log + UI)
- listing_url: URL halaman index/listing lomba
- hostname: domain portal (untuk filter link detail)
- detail_pattern: regex terhadap href (absolute URL) untuk deteksi
  link detail lomba. Harus cocok dengan hostname + path pattern.

Penambahan portal baru: cek ToS & rate-limit dulu, lalu tambah entry
di TARGET_PORTALS (config.py) dan dict ini.
"""
from __future__ import annotations

import re
from dataclasses import dataclass
from urllib.parse import urljoin, urlparse

from app.services.exceptions import PortalConfigError


def _make_detail_pattern(hostname: str) -> re.Pattern[str]:
    """Compile regex untuk URL detail lomba di hostname tertentu.

    Match path: /lomba/..., /event/..., /kompetisi/..., /kompetisi-lomba/...
    atau /competition/..., /info-lomba/...
    Exclude: /category/..., /tag/..., /author/..., halaman root.
    """
    # Escape dot di hostname (kalau subdomain aneh).
    host = re.escape(hostname.lower())
    return re.compile(
        rf"^https?://{host}/(lomba|event|events|kompetisi|kompetisi-lomba|competition|competitions|info-lomba)/[^/?#]+/?$",
        re.IGNORECASE,
    )


@dataclass(frozen=True)
class Portal:
    """Konfigurasi satu portal target."""

    key: str
    name: str
    listing_url: str
    hostname: str
    detail_pattern: re.Pattern[str]


# Setiap entry di bawah ini manual: hostname + pattern. Tidak ada shared
# regex generik (terlalu longgar, match portal lain).
PORTALS: dict[str, Portal] = {
    "lombahub_com": Portal(
        key="lombahub_com",
        name="Lombahub",
        listing_url="https://lombahub.com/",
        hostname="lombahub.com",
        detail_pattern=_make_detail_pattern("lombahub.com"),
    ),
    "ikutlomba_id": Portal(
        key="ikutlomba_id",
        name="Ikutlomba.id",
        listing_url="https://www.ikutlomba.id/",
        hostname="www.ikutlomba.id",
        detail_pattern=_make_detail_pattern("www.ikutlomba.id"),
    ),
    "kompetisi_co_id": Portal(
        key="kompetisi_co_id",
        name="Kompetisi.co.id",
        listing_url="https://home.kompetisi.co.id/",
        hostname="home.kompetisi.co.id",
        detail_pattern=_make_detail_pattern("home.kompetisi.co.id"),
    ),
    "ajangjuara_com": Portal(
        key="ajangjuara_com",
        name="AjangJuara",
        listing_url="https://ajangjuara.com/",
        hostname="ajangjuara.com",
        detail_pattern=_make_detail_pattern("ajangjuara.com"),
    ),
    "sejutacita_id": Portal(
        key="sejutacita_id",
        name="SejutaCita",
        listing_url="https://sejutacita.id/",
        hostname="sejutacita.id",
        detail_pattern=_make_detail_pattern("sejutacita.id"),
    ),
    "luarkampus_id": Portal(
        key="luarkampus_id",
        name="LuarKampus",
        listing_url="https://luarkampus.id/events",
        hostname="luarkampus.id",
        detail_pattern=_make_detail_pattern("luarkampus.id"),
    ),
}


def get_portal(key: str) -> Portal:
    """Lookup portal by key. Raise PortalConfigError kalau tidak ada."""
    if key not in PORTALS:
        raise PortalConfigError(
            f"Portal '{key}' tidak ada di registry. Pilihan: {', '.join(sorted(PORTALS))}"
        )
    return PORTALS[key]


def is_detail_link(href: str, portal: Portal) -> bool:
    """Cek apakah sebuah href adalah link detail lomba untuk portal tertentu."""
    if not href:
        return False
    return bool(portal.detail_pattern.match(href.strip()))


def extract_detail_links(markdown: str, portal: Portal, base_url: str) -> list[str]:
    """Ambil list URL detail lomba dari Markdown halaman listing.

    Pendekatan: cari semua URL yang match `detail_pattern`. Tangani
    link relatif (prefix dengan base_url host).
    """
    seen: set[str] = set()
    out: list[str] = []

    # Markdown link format: [text](href)
    md_link_re = re.compile(r"\[[^\]]*\]\((https?://[^\s)]+|/[^\s)]*)\)")
    for m in md_link_re.finditer(markdown):
        href = m.group(1)
        if href.startswith("/"):
            href = urljoin(base_url, href)
        href = href.split("#", 1)[0].rstrip("/")
        if is_detail_link(href, portal) and href not in seen:
            seen.add(href)
            out.append(href)

    # Bare URLs (tanpa markdown link syntax) — fallback.
    bare_url_re = re.compile(r"https?://[^\s)<>\"']+")
    for m in bare_url_re.finditer(markdown):
        href = m.group(0).split("#", 1)[0].rstrip("/")
        if is_detail_link(href, portal) and href not in seen:
            seen.add(href)
            out.append(href)

    return out


def hostname_of(url: str) -> str:
    return urlparse(url).netloc.lower()
