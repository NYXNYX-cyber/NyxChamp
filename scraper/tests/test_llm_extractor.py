"""Test LLM date normalizer (pure function, no LLM call)."""
import pytest

from app.services.llm_extractor import _normalize_date


@pytest.mark.parametrize("raw,expected", [
    ("2026-08-15", "2026-08-15"),
    ("  2026-08-15  ", "2026-08-15"),
    ("15 Agustus 2026", "2026-08-15"),
    ("1 Januari 2027", "2027-01-01"),
    ("15/08/2026", "2026-08-15"),
    ("1/1/2027", "2027-01-01"),
    ("August 15, 2026", "2026-08-15"),
    ("Sep 1, 2026", "2026-09-01"),
])
def test_normalize_date_valid(raw, expected):
    assert _normalize_date(raw) == expected


def test_normalize_date_passthrough():
    # Format aneh → pass-through (akan raise di Pydantic nanti)
    assert _normalize_date("not-a-date") == "not-a-date"
    assert _normalize_date("") == ""


def test_normalize_date_non_string():
    # Kalau LLM kasih non-string, pass-through
    assert _normalize_date(None) is None
    assert _normalize_date(20260815) == 20260815
