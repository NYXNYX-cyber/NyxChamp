# NyxChamp Scraper (layanan Python)

Layanan terpisah dari aplikasi Laravel (lihat `Rancangan Portal Lomba Laravel.md` §2
dan `AGENTS.md` §3.2). Tugasnya: menjelajah 6 portal kompetisi Indonesia, mengekstrak
data kompetisi via **Crawl4AI** / **Firecrawl** + **OpenAI GPT-4o-mini**, lalu mengembalikan
JSON siap-INSERT ke Laravel.

> **Status**: skeleton + kontrak JSON. Implementasi nyata (Crawl4AI, Firecrawl, GPT-4o-mini)
> menyusul di **Fase 5**. Saat ini service sudah bisa dijalankan dan dipanggil Laravel,
> tapi `/scrape` mengembalikan list kosong dengan pesan error "belum diimplementasikan".

## Cara Jalankan (dev)

```bash
cd scraper
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python run.py
```

Service listen di `http://127.0.0.1:8001` (lihat `app/config.py`).

## Endpoint

- `GET  /health` — liveness, tanpa auth
- `GET  /portals` — daftar portal target
- `POST /scrape` — trigger scrape, butuh `Authorization: Bearer <token>`

Lihat `CONTRACT.md` untuk format request/response. Token diset via
`SCRAPER_SERVICE_TOKEN` di `.env` Laravel dan `.env` scraper (harus sama).

## Kontrak Data

Output `/scrape` adalah list `Competition` (lihat `app/schemas.py`).
Field `hash_md5` dihitung otomatis oleh Pydantic dari
`title + registration_deadline` — Laravel tinggal INSERT, dedup otomatis.

## Portal Target (lihat Rancangan §1)

| Key              | URL                       |
| :--              | :--                       |
| `kompetisi_co_id`  | https://kompetisi.co.id   |
| `ikutlomba_id`     | https://ikutlomba.id      |
| `ajangjuara_com`   | https://ajangjuara.com    |
| `sejutacita_id`    | https://sejutacita.id     |
| `luarkampus_id`    | https://luarkampus.id     |
| `lombahub_com`     | https://lombahub.com      |

> Tambah portal baru? Cek ToS & rate-limit dulu. Update `TARGET_PORTALS` di
> `app/config.py` setelah dapat lampu hijau.
