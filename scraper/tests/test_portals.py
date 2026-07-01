"""Test Portals registry + URL extraction."""
import pytest

from app.services.exceptions import PortalConfigError
from app.services.portals import (
    PORTALS,
    extract_detail_links,
    get_portal,
    get_tier1_portals,
    is_detail_link,
)


def test_get_portal_known():
    p = get_portal("lombahub_com")
    assert p.key == "lombahub_com"
    assert "lombahub.com" in p.listing_url
    assert p.detail_pattern is not None


def test_get_portal_unknown_raises():
    with pytest.raises(PortalConfigError) as exc:
        get_portal("nonexistent_portal_xyz")
    assert "tidak ada" in str(exc.value).lower() or "tidak" in str(exc.value)


def test_all_target_portals_registered():
    """Setiap TARGET_PORTALS di config harus ada di PORTALS registry."""
    from app.config import TARGET_PORTALS
    for key in TARGET_PORTALS:
        assert key in PORTALS, f"Portal {key} ada di config.TARGET_PORTALS tapi tidak di registry"


def test_is_detail_link_valid():
    p = get_portal("lombahub_com")
    assert is_detail_link("https://lombahub.com/lomba/foo", p)
    assert is_detail_link("https://lombahub.com/event/bar", p)
    assert is_detail_link("https://lombahub.com/kompetisi-lomba/baz", p)
    assert is_detail_link("https://lombahub.com/info-lomba/qux", p)


def test_is_detail_link_invalid():
    p = get_portal("lombahub_com")
    # Link ke category/index
    assert not is_detail_link("https://lombahub.com/", p)
    assert not is_detail_link("https://lombahub.com/category/desain", p)
    # External
    assert not is_detail_link("https://other.com/lomba/foo", p)
    # Empty
    assert not is_detail_link("", p)


def test_extract_detail_links_from_markdown():
    p = get_portal("lombahub_com")
    md = """
    Homepage Lombahub

    [Lomba Cipta Puisi](https://lombahub.com/lomba/cipta-puisi-2026)
    [Web Design](https://lombahub.com/lomba/web-design-2026)
    [Kategori Desain](https://lombahub.com/category/desain)
    [Hackathon](https://lombahub.com/event/hackathon-2026)
    [Lomba Lama](https://lombahub.com/lomba/lomba-lama-2025)
    """
    links = extract_detail_links(md, p, "https://lombahub.com")
    assert len(links) == 4
    assert "https://lombahub.com/lomba/cipta-puisi-2026" in links
    assert "https://lombahub.com/category/desain" not in links  # excluded


def test_extract_detail_links_dedup():
    p = get_portal("lombahub_com")
    md = """
    [A](https://lombahub.com/lomba/x-2026)
    [A again](https://lombahub.com/lomba/x-2026)
    [B](https://lombahub.com/lomba/x-2026/)
    """
    links = extract_detail_links(md, p, "https://lombahub.com")
    # Dedup considers trailing slash
    assert len(links) == 1
    assert links[0] == "https://lombahub.com/lomba/x-2026"


def test_extract_detail_links_relative_urls():
    p = get_portal("lombahub_com")
    md = "[Lomba](/lomba/relative-path-2026)"
    links = extract_detail_links(md, p, "https://lombahub.com")
    assert len(links) == 1
    assert links[0] == "https://lombahub.com/lomba/relative-path-2026"


# ===== Tier 1 / login_required tests (Fase 5.5) =====

def test_ikutlomba_marked_login_required():
    """ikutlomba.id: login required, Tier 2. Scraper pakai Google Search fallback."""
    p = get_portal("ikutlomba_id")
    assert p.tier == 2
    assert p.login_required is True


def test_sejutacita_marked_login_required():
    """sejutacita.id: parked/empty, Tier 2. Scraper pakai Google Search fallback."""
    p = get_portal("sejutacita_id")
    assert p.tier == 2
    assert p.login_required is True


def test_kompetisi_tier1_public_listing():
    """kompetisi.co.id: public listing visible, Tier 1. Direct scrape OK."""
    p = get_portal("kompetisi_co_id")
    assert p.tier == 1
    assert p.login_required is False


def test_lombahub_tier1_public_listing():
    p = get_portal("lombahub_com")
    assert p.tier == 1
    assert p.login_required is False


def test_luarkampus_tier1_public_listing():
    p = get_portal("luarkampus_id")
    assert p.tier == 1
    assert p.login_required is False


def test_ajangjuara_tier1_public_listing():
    p = get_portal("ajangjuara_com")
    assert p.tier == 1
    assert p.login_required is False


def test_get_tier1_portals_returns_4():
    """Tier 1 = 4 portal: lombahub, kompetisi, luarkampus, ajangjuara."""
    tier1 = get_tier1_portals()
    assert len(tier1) == 4
    keys = sorted(p.key for p in tier1)
    assert keys == ["ajangjuara_com", "kompetisi_co_id", "lombahub_com", "luarkampus_id"]
