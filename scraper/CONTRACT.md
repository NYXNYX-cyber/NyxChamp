"""Kontrak JSON untuk scraper service.

Kontrak ini HARUS sinkron dengan struktur tabel `competitions` di
Laravel (lihat Rancangan §3 + AGENTS.md §3.3). Field `hash_md5` dihitung
otomatis oleh Pydantic (lihat `app/schemas.py`).

Contoh payload yang akan dikirim Laravel ke scraper:

    POST /scrape
    {
      "portal": "lombahub_com",
      "job_id": "uuid-atau-string-unik",
      "max_pages": 20
    }

Response yang diharapkan:

    {
      "job_id": "...",
      "portal": "lombahub_com",
      "items": [
        {
          "title": "Lomba Cipta Puisi Nasional 2026",
          "organizer": "Yayasan Sastra Indonesia",
          "description": "Lomba...",
          "registration_deadline": "2026-08-15",
          "level": "nasional",
          "registration_fee": 50000.0,
          "source_url": "https://lombahub.com/lomba/cipta-puisi-nasional-2026"
        }
      ],
      "errors": []
    }

Catatan: `hash_md5` tidak dikirim client (Laravel); Pydantic akan
menghitungnya dari `title + registration_deadline` saat divalidasi.
"""
