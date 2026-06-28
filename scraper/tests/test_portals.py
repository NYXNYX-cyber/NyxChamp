"""Test Portals registry + URL extraction."""
import pytest

from app.services.exceptions import PortalConfigError
from app.services.portals import (
    PORTALS,
    extract_detail_links,
    get_portal,
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
