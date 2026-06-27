"""Ekstrak entitas Competition dari Markdown via LLM.

Pakai OpenAI Python SDK (OpenAI-compatible) dengan custom base_url
default ke OpenCode Zen (DeepSeek v4 Flash). Kalau OpenAI SDK belum
terinstall, module ini tetap import-able tapi __init__ akan raise
saat dipanggil (lihat _get_client()).

Prompt LLM:
- Input: Markdown hasil Firecrawl (siap-LLM, sudah bersih dari navigasi)
- Output: JSON list of Competition (lihat schemas.py)
- Mode: json_object/response_format (paksa JSON valid)
- Constraint: title + registration_deadline wajib, hash_md5 dihitung di
  Python Pydantic (lihat schemas.Competition) bukan di LLM (menghindari
  LLM halusinasi hash).
"""
from __future__ import annotations

import json
import logging
import re
from datetime import date
from typing import Any

from app.config import LLM_API_KEY, LLM_BASE_URL, LLM_MODEL
from app.schemas import Competition, Level
from app.services.exceptions import LLMError

logger = logging.getLogger(__name__)

# Batas input chars untuk LLM. Markdown Firecrawl bisa panjang; potong
# untuk hemat token. ~25k chars ≈ 6-8k token, masih dalam window DeepSeek.
MAX_INPUT_CHARS: int = 25_000

# System prompt yang menentukan task + JSON schema eksplisit.
# LLM DeepSeek family dukung response_format=json_object, kita pakai itu.
SYSTEM_PROMPT: str = (
    "Anda adalah AI extractor untuk situs lomba Indonesia. "
    "Tugas: dari Markdown halaman listing lomba, ekstrak daftar entri "
    "lomba. Untuk SETIAP lomba, kembalikan object dengan field:\n"
    "- title (string, wajib)\n"
    "- organizer (string, wajib — nama penyelenggara)\n"
    "- description (string, ringkas 1-3 kalimat)\n"
    "- registration_deadline (string ISO date YYYY-MM-DD, wajib)\n"
    "- level (salah satu: kabupaten, provinsi, nasional, internasional)\n"
    "- registration_fee (number, 0 kalau gratis, dalam IDR)\n"
    "- source_url (string URL lengkap, wajib)\n\n"
    "ATURAN:\n"
    "1. Hanya ekstrak lomba yang BENAR-ADA di Markdown, jangan mengarang.\n"
    "2. Kalau tanggal tidak jelas, skip entri tersebut.\n"
    "3. Kalau level tidak bisa ditentukan, default ke 'nasional'.\n"
    "4. Kembalikan HANYA JSON object dengan key 'competitions' berisi array. "
    "Jangan tambahkan teks lain di luar JSON.\n"
)


class LLMExtractor:
    """Ekstrak Competition list dari Markdown via OpenAI-compatible API.

    Usage::

        extractor = LLMExtractor()
        competitions = await extractor.extract(markdown, source_url_hint="...")

    Raises:
        LLMError: Kalau API call gagal, timeout, quota habis, atau
            respons tidak bisa di-parse ke JSON valid sesuai schema.
    """

    def __init__(
        self,
        api_key: str | None = LLM_API_KEY,
        base_url: str = LLM_BASE_URL,
        model: str = LLM_MODEL,
    ) -> None:
        if not api_key:
            raise LLMError(
                "SCRAPER_LLM_API_KEY belum di-set. Tambahkan di .env."
            )
        self.api_key = api_key
        self.base_url = base_url
        self.model = model
        self._client = self._get_client()

    @staticmethod
    def _get_client() -> Any:
        """Lazy import openai. Kalau belum terinstall, raise informatif."""
        try:
            from openai import AsyncOpenAI
        except ImportError as exc:
            raise LLMError(
                "Library 'openai' belum terinstall. pip install openai>=1.59"
            ) from exc
        return None  # actual client dibuat per-call (lihat _call)

    async def extract(
        self,
        markdown: str,
        *,
        source_url_hint: str = "",
    ) -> list[Competition]:
        """Ekstrak list Competition dari Markdown.

        Args:
            markdown: Body Markdown hasil Firecrawl.
            source_url_hint: URL asal halaman (untuk fallback kalau LLM
                tidak sertakan source_url di output).

        Returns:
            List Competition valid (sudah lewat Pydantic validation,
            hash_md5 auto-computed). Bisa kosong kalau LLM tidak
            menemukan lomba apapun.

        Raises:
            LLMError: Kalau API call gagal atau output tidak valid.
        """
        if not markdown or not markdown.strip():
            return []

        truncated = markdown[:MAX_INPUT_CHARS]
        if len(markdown) > MAX_INPUT_CHARS:
            logger.info(
                "llm input truncated %d → %d chars", len(markdown), MAX_INPUT_CHARS
            )

        raw_json = await self._call(truncated, source_url_hint)
        items = self._parse_response(raw_json, source_url_hint)
        return items

    async def _call(self, markdown: str, source_url_hint: str) -> str:
        """Panggil LLM API. Kembalikan string JSON (mentah)."""
        from openai import AsyncOpenAI

        client = AsyncOpenAI(api_key=self.api_key, base_url=self.base_url)

        user_msg = f"Ekstrak lomba dari Markdown berikut.\n\nURL halaman: {source_url_hint or '(tidak diketahui)'}\n\n---\n{markdown}"

        try:
            response = await client.chat.completions.create(
                model=self.model,
                messages=[
                    {"role": "system", "content": SYSTEM_PROMPT},
                    {"role": "user", "content": user_msg},
                ],
                response_format={"type": "json_object"},
                temperature=0.1,
                max_tokens=4000,
            )
        except Exception as exc:  # broad: openai SDK raises various exception types
            raise LLMError(f"LLM call gagal: {exc}") from exc

        try:
            content = response.choices[0].message.content or ""
        except (AttributeError, IndexError) as exc:
            raise LLMError(f"LLM response tidak ada message content: {exc}") from exc

        return content

    @staticmethod
    def _parse_response(raw: str, source_url_hint: str) -> list[Competition]:
        """Parse JSON string dari LLM, validasi ke Competition.

        LLM kadang membungkus array di {"competitions": [...]} (sesuai
        instruksi) atau kadang langsung [...]. Handle dua-duanya.
        """
        if not raw or not raw.strip():
            return []

        # Strip markdown code fence kalau LLM bandel.
        cleaned = re.sub(r"^```(?:json)?\s*|\s*```$", "", raw.strip(), flags=re.M)

        try:
            data = json.loads(cleaned)
        except json.JSONDecodeError as exc:
            raise LLMError(f"LLM output bukan JSON valid: {exc}; raw={raw[:200]}") from exc

        # Normalisasi: kalau dict dengan key 'competitions' → ambil array.
        if isinstance(data, dict) and "competitions" in data:
            items = data["competitions"]
        elif isinstance(data, list):
            items = data
        else:
            raise LLMError(
                f"LLM JSON shape tidak dikenal: type={type(data).__name__}; raw={raw[:200]}"
            )

        if not isinstance(items, list):
            raise LLMError(f"LLM competitions bukan list: {type(items).__name__}")

        valid: list[Competition] = []
        for raw_item in items:
            if not isinstance(raw_item, dict):
                continue
            # Fallback source_url kalau LLM tidak isi.
            if "source_url" not in raw_item or not raw_item["source_url"]:
                if source_url_hint:
                    raw_item["source_url"] = source_url_hint
                else:
                    continue
            # Normalisasi level kalau LLM kasih kapitalisasi aneh.
            if "level" in raw_item and isinstance(raw_item["level"], str):
                raw_item["level"] = raw_item["level"].lower().strip()
            # Normalisasi date kalau LLM kasih string non-ISO.
            if "registration_deadline" in raw_item:
                raw_item["registration_deadline"] = _normalize_date(
                    raw_item["registration_deadline"]
                )
            try:
                comp = Competition(**raw_item)
            except Exception as exc:
                # Skip item invalid, log saja — jangan fail seluruh batch.
                logger.warning(
                    "skip invalid LLM item: %s | item=%s", exc, raw_item
                )
                continue
            # Filter: skip deadline yang sudah lewat (konsisten dengan Laravel
            # scope 'open').
            if comp.registration_deadline < date.today():
                logger.info("skip past-deadline: %s", comp.title)
                continue
            valid.append(comp)

        return valid


# Mapping bulan Indonesia → nomor (untuk normalisasi tanggal).
_BULAN_ID: dict[str, int] = {
    "januari": 1, "februari": 2, "maret": 3, "april": 4,
    "mei": 5, "juni": 6, "juli": 7, "agustus": 8,
    "september": 9, "oktober": 10, "november": 11, "desember": 12,
}


def _normalize_date(value: Any) -> str:
    """Best-effort convert ke ISO YYYY-MM-DD. Pass-through kalau sudah ISO.

    Handle:
    - "2026-08-15" → "2026-08-15"
    - "15 Agustus 2026" → "2026-08-15"
    - "15/08/2026" → "2026-08-15"
    - "Aug 15, 2026" → "2026-08-15"
    - invalid → return as-is, Pydantic akan raise nanti
    """
    if not isinstance(value, str):
        return value
    s = value.strip()
    if not s:
        return s
    # Sudah ISO YYYY-MM-DD
    if re.match(r"^\d{4}-\d{2}-\d{2}$", s):
        return s

    # DD Month YYYY (id)
    m = re.match(r"^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$", s)
    if m:
        day, month_name, year = m.group(1), m.group(2).lower(), m.group(3)
        if month_name in _BULAN_ID:
            return f"{year}-{_BULAN_ID[month_name]:02d}-{int(day):02d}"

    # DD/MM/YYYY
    m = re.match(r"^(\d{1,2})/(\d{1,2})/(\d{4})$", s)
    if m:
        day, month, year = m.group(1), m.group(2), m.group(3)
        return f"{year}-{int(month):02d}-{int(day):02d}"

    # Month DD, YYYY (en) — short forms included (Jan, Feb, Sep, dll)
    m = re.match(r"^([A-Za-z]+)\s+(\d{1,2}),?\s+(\d{4})$", s)
    if m:
        month_name, day, year = m.group(1).lower(), m.group(2), m.group(3)
        en_months = {
            "january": 1, "jan": 1,
            "february": 2, "feb": 2,
            "march": 3, "mar": 3,
            "april": 4, "apr": 4,
            "may": 5,
            "june": 6, "jun": 6,
            "july": 7, "jul": 7,
            "august": 8, "aug": 8,
            "september": 9, "sep": 9, "sept": 9,
            "october": 10, "oct": 10,
            "november": 11, "nov": 11,
            "december": 12, "dec": 12,
        }
        if month_name in en_months:
            return f"{year}-{en_months[month_name]:02d}-{int(day):02d}"

    return s  # pass-through, Pydantic akan raise kalau invalid
