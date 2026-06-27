# AGENTS.md — NyxChamp

> Repo ini **masih dalam fase desain**. Belum ada kode, belum ada `composer.json`/`package.json`, belum ada layanan Python. Satu-satunya sumber kebenaran adalah `Rancangan Portal Lomba Laravel.md` di root.

## 1. Status Repo
- **Fase**: implementasi awal (Fase 0–3 selesai, lihat §6b)
- **Git**: sudah di-init di branch `main` (2026-06-27)
- **Remote**: `origin` = `https://github.com/NYXNYX-cyber/NyxChamp.git` (token tersimpan di `.git/config` lokal, tidak akan ter-push)
- **Akun dev seed**: `admin@nyxchamp.test` / `guru@nyxchamp.test` / `siswa@nyxchamp.test` (semua password: `password`)

### Stack yang sudah terpasang
- **Laravel 13.17** + Breeze (Inertia + React + TypeScript) + Reverb
- **Python 3.14** + FastAPI + Pydantic di `scraper/` (layanan terpisah)
- **SQLite** untuk dev (`.env` default). MySQL 8+ dipakai via Docker mulai Fase 6
- **Cache/Queue/Session** = `database` driver (SQLite). Switch ke `redis` di prod

> Lihat §6 untuk perintah dev harian. Untuk MySQL/Redis lokal, lihat `docker-compose.yml` (akan ditambah saat Fase 6).

## 2. Cara Menavigasi Dokumen Rancangan

Baca `Rancangan Portal Lomba Laravel.md` (297 baris). Peta section:

| Baris | Topik | Isi Singkat |
| :-- | :-- | :-- |
| 1–18 | §1 Lanskap & target portal | 6 portal Indonesia yang di-scrape (Kompetisi.co.id, Ikutlomba.id, Ajangjuara.com, Sejutacita.id, Luarkampus.id, Lombahub.com) |
| 19–38 | §2 Arsitektur scraper multi-agent paralel | Rumus total waktu scraping, perbandingan Crawl4AI vs Firecrawl vs Apify, alur HTML→Markdown→LLM→JSON |
| 39–113 | §3 Skema database (3NF) | 5 tabel lengkap: `users`, `competitions`, `chat_rooms`, `chat_room_members`, `chat_messages` |
| 114–154 | §4 Chat real-time Reverb | Diagram alur, 3 channel types (public/private/presence), alur guru-murid |
| 155–195 | §5 UI/UX Neo-Brutalisme | Aturan border/shadow/warna, palette HEX, tipografi, micro-interaction |
| 196–244 | §6 Tech stack & alur data end-to-end | Tabel teknologi, diagram alur, 4 siklus operasional |
| 285–297 | Lampiran | Inline base64 untuk formula matematis di §2 — tidak perlu dibuka manual |

> **Catatan**: Dokumen `.docx` (3 MB) sudah dihapus karena isinya identik dengan `.md`. Untuk kontribusi, edit langsung file `.md`-nya.

## 3. Keputusan Terkunci (jangan dilanggar tanpa diskusi eksplisit)

### 3.1 Tech Stack
| Layer | Teknologi | Catatan |
| :-- | :-- | :-- |
| Backend | **Laravel 11/12/13** | Bawa Eloquent, Scheduler, Event Broadcasting |
| WebSocket | **Laravel Reverb** | Native, **bukan** Pusher / Node sidecar |
| Frontend | **React.js via Inertia.js** | **Bukan** Vue, **bukan** Livewire |
| Styling | **Tailwind CSS** | Untuk utility kelas border/shadow/warna solid |
| Cache & Queue | **Redis** | Antrean scraping + state Reverb |
| Database | **MySQL ≥ 8.0** | Transaksional relasional, indeks komposit |
| Scraper (layanan terpisah) | **Python FastAPI** | **Bukan** Artisan command, **bukan** PHP scraper |
| AI scraping libs | **Firecrawl** & **Crawl4AI** | Bypass proteksi bot, Markdown siap-LLM |
| LLM | **DeepSeek v4 Flash** (via OpenCode Zen, OpenAI-compatible) | Ekstraksi entitas HTML→JSON terstandar; cost rendah, response cepat |

### 3.2 Arsitektur
- **Microservices split**: Laravel = aplikasi utama; Python = scraper. Tidak boleh digabung.
- **Pipeline scraping** (2× seminggu, lihat §3.4):
  `Laravel Scheduler` → `Redis queue` → `Python worker (paralel)` → `Firecrawl` → `LLM (DeepSeek v4 Flash via OpenCode Zen)` → `JSON payload` → `Laravel` → `MySQL`
- Reverb berjalan **native** di ekosistem PHP; tidak butuh Node sebagai sidecar.

### 3.3 Skema Database (3NF, 5 tabel)
- `users` — `id, name, email (UNIQUE), password, role enum[student|teacher|admin] (default 'student'), institution (nullable), timestamps`
- `competitions` — `id, title, slug (UNIQUE), organizer, description, registration_deadline, level enum[kabupaten|provinsi|nasional|internasional], registration_fee (decimal 10,2, default 0), source_url, hash_md5 (UNIQUE, 32 char), timestamps`
- `chat_rooms` — `id, competition_id (FK nullable), name, is_group (bool), created_by (FK), created_at`
- `chat_room_members` — `chat_room_id (FK), user_id (FK), joined_at` (composite key)
- `chat_messages` — `id, chat_room_id (FK), sender_id (FK), message_text, created_at`

**Aturan penting**:
- `competitions.hash_md5` **wajib** untuk dedup lintas portal sumber (rumus: MD5 dari `title + registration_deadline`).
- `chat_rooms.competition_id` **nullable** — grup non-kompetisi (mis. diskusi internal) diperbolehkan.
- `users.role` menentukan fitur kolaborasi (lihat §3.5).

### 3.4 Scraping & Jadwal
- **Frekuensi: 2× seminggu** (Senin 05:00 + Jumat 15:00 WIB). Bukan harian. Alasan eksplisit: Senin = info segar untuk showcase setelah upacara sekolah; Jumat = info untuk weekend planning. **Jangan dinaikkan tanpa diskusi.**
- **Target**: 6 portal Indonesia tercantum di §1. Tambah portal baru → wajib cek ToS & rate-limit target dulu.
- **Stealth parameters** (wajib): rotasi User-Agent, browser fingerprinting, simulasi scroll & delay acak.
- **Pipeline data**: HTML → Markdown (Firecrawl) → LLM (DeepSeek v4 Flash via OpenCode Zen) bersihkan iklan/navigasi/copyright → JSON sesuai skema → hash MD5 → INSERT ke `competitions`.
- **Window eksekusi**: jadwalkan di hari/jam lalu lintas rendah portal target (detail ada di §1).

### 3.5 Chat Real-Time (Reverb)
- **3 channel types**:
  - **Public channel** — menyiarkan info kompetisi baru. Saat scraper mendeteksi kompetisi baru, sistem **otomatis** membuat `chat_room` publik yang terikat `competition_id`.
  - **Private channel** — grup bimbingan tertutup guru–murid. Otorisasi via sesi Laravel / token. Guru buat lewat tombol **"Buat Grup Bimbingan"** di halaman detail kompetisi.
  - **Presence channel** — deteksi status online/offline + indikator "sedang mengetik" untuk grup koordinasi aktif.
- **Flow end-to-end**:
  `Inertia POST` → simpan ke `chat_messages` (MySQL) → dispatch `MessageSent` event → Reverb push via WSS → Echo client re-render.
- **Akses kontrol**: guru (`users.role = 'teacher'`) hanya bisa membuat grup bimbingan; siswa (`'student'`) hanya bisa join via invite.

### 3.6 UI/UX: Neo-Brutalisme (WAJIB, bukan preferensi)
Mengganti salah satu token di bawah = **melanggar spec**. Diskusikan dulu.

| Parameter | Aturan |
| :-- | :-- |
| Border | 3–4px **solid** `#000000` (bukan abu tipis, bukan tanpa border) |
| Shadow | Hard offset, **tanpa blur**, pure black (bukan Gaussian lembut) |
| Color | **Flat solid only** — NO gradient, NO glassmorphism, NO transparency |
| Corner radius | `rounded-none` atau 2–4px maksimal (bukan fully rounded 16px+) |
| Tipografi display | **Syne** (judul besar, hero, logo) |
| Tipografi header | **Space Grotesk** (judul kartu, navigasi, tajuk chat) |
| Tipografi metadata | **JetBrains Mono** (tenggat, biaya, tanggal, isi chat) |
| Kontras | WCAG AAA |

**Palette HEX (satu-satunya warna yang boleh dipakai)**:
- `#F5F0E6` Cream/Beige — background utama
- `#000000` Solid Black — teks, border, shadow
- `#FF4081` Neon Pink — primary action, kategori lomba desain
- `#FFEB3B` Neon Yellow — badge tingkat kompetisi (nasional/internasional)
- `#0000EE` Hyperlink Blue — link eksternal
- `#4CAF50` Neo Emerald — indikator "pendaftaran masih buka"

**Micro-interactions tombol**:
- Hover: geser 3px ke kiri-atas, shadow memanjang
- Active: geser 2px ke kanan-bawah, shadow menyusut (efek saklar fisik)

### 3.7 Bahasa
- **UI, komentar kode, dokumentasi, commit message**: Bahasa Indonesia.
- **Istilah teknis global** (channel, queue, scheduler, scraper, broadcast, event, dll.) tetap istilah Inggris.
- **Tidak perlu translate** nama library/framework (Laravel, Reverb, Inertia, MySQL, Redis, Tailwind, dst.).

## 4. Yang TIDAK Boleh Dilakukan Tanpa Diskusi
- Ganti tech stack (mis. Reverb → Pusher, React → Vue, Reverb → Soketi)
- Longgarkan salah satu aturan Neo-Brutalisme (gradient, blur, border tipis, radius besar)
- Tambah portal target scraping tanpa cek ToS & rate-limit
- Naikkan frekuensi scraping di atas mingguan
- Tambah route real-time di luar channel chat (overhead WebSocket tak perlu)
- Ubah enum values di DB (`users.role`, `competitions.level`) tanpa strategi migrasi
- Hapus/komentari `hash_md5` di `competitions` (dedup bergantung padanya)

## 5. Konvensi Saat Kode Dimulai
- **PHP**: PSR-12 (default Laravel). Tidak perlu ditulis ulang di sini.
- **JavaScript/React**: ESLint default Inertia. Tidak perlu ditulis ulang di sini.
- **Python**: PEP 8 + formatter `black`.
- **Komentar kode**: Bahasa Indonesia, singkat, hanya untuk menjelaskan **mengapa** (bukan apa).
- **Commit message**: Bahasa Indonesia, format `tipo: subjek` (mis. `fitur: tambah endpoint scrape`, `perbaiki: hash MD5 duplikat`). Conventional Commits penuh (scope, body) **belum diputuskan** — lihat §7.

## 6. Perintah Dev (untuk nanti)
Saat kode sudah ada, perintah standar yang akan dipakai:
- `php artisan serve` — dev server Laravel
- `php artisan queue:work` — worker antrean Redis (untuk scrapping job)
- `php artisan reverb:start` — WebSocket server
- `php artisan schedule:work` — scheduler lokal (untuk trigger scraping mingguan)
- `npm run dev` — Vite/Inertia dev server
- `php artisan test` / `php artisan test --filter=...` — PHPUnit
- `npm run build` — build frontend untuk produksi

> **Jangan tulis README/runbook panjang** tentang cara setup di masa depan. Saat kode ada, update §6 dengan perintah aktual yang terbukti jalan.

## 6b. Fase yang Sudah Selesai
- **Fase 0** — Fondasi: Laravel 13.17 + Breeze (Inertia + React + TS) + Reverb + Python FastAPI scraper skeleton di `scraper/`
- **Fase 1** — Skema DB 3NF (5 tabel: users, competitions, chat_rooms, chat_room_members, chat_messages) + Eloquent models + `CompetitionHash` service
- **Fase 2** — Auth + RBAC: `RoleMiddleware` (alias `role:admin,teacher`), `institution` field, profile form lokal-ID
- **Fase 3** — Fondasi UI Neo-Brutalisme: Tailwind tokens (palette HEX, font families, box-shadow tanpa blur, borderWidth 3), Google Fonts (Syne/Space Grotesk/JetBrains Mono), `Components/Brutal/*` (Button, Card, Badge, Link, Heading), `AuthenticatedLayout`/`GuestLayout` lokal-ID
- **Fase 4** — Modul Kompetisi read-only: `CompetitionController` (index/show), filter (level/status/pencarian), `Pages/Competitions/{Index,Show}`, `Components/Brutal/CompetitionCard`, `CompetitionFactory` + `CompetitionSeeder` (8 lomba contoh), nav "Lomba" di `AuthenticatedLayout` + CTA "Jelajahi Lomba" di Welcome/Dashboard
- **Fase 5** — Scraper integration Laravel ↔ Python: `ScraperService` (HTTP client + retry), `CompetitionIngestor` (dedup + auto-create public room), `ScrapePortalJob` (queueable + backoff 60/300/900s), `ScrapeCommand` (artisan `--portal`/`--max-pages`/`--sync`), `NewCompetitionDetected` event + `LogNewCompetition` listener (broadcast stub), scheduler Senin 05:00 + Jumat 15:00 WIB. Python: `FirecrawlClient` (async httpx), `LLMExtractor` (OpenAI SDK + custom base_url ke OpenCode Zen), `Portals` registry 6 portal Indonesia, `scraper.py` orchestrator dengan `asyncio.gather` (concurrency=4)

Test lulus: **49 passed, 168 assertions** (`php artisan test`) per `6e5a6a7`. Screenshot preview: `docs/screenshots/`.

## 7. TODO & Keputusan yang Belum Diambil
- [ ] Conventional Commits penuh (scope, body, footer) — perlu keputusan tim
- [ ] Strategi branch (`feat/*` `fix/*` `chore/*`) & PR template
- [ ] Strategi migrasi DB awal (pertama kali `php artisan migrate` di env dev)
- [ ] Retry policy scraper saat gagal (backoff? max retry? dead letter queue?)
- [ ] Rate-limit per portal target (scrape terlalu agresif = IP kena blok)
- [ ] Strategi auth channel Reverb (sesi Laravel vs JWT) — rancangan sebut dua-duanya
- [ ] Inisialisasi `composer.json` & `package.json` (saat coding mulai)
- [ ] Setup CI/CD (GitHub Actions?) — belum diputuskan

## 8. Catatan untuk Agen Berikutnya
- Jangan mulai dari猜测. Baca `Rancangan Portal Lomba Laravel.md` dulu sebelum usulkan perubahan.
- Jika ada konflik antara dokumen rancangan dan best practice modern, **dokumen rancangan menang** sampai ada diskusi eksplisit untuk meng-update rancangan.
- Jika ingin mengubah salah satu keputusan §3, **buka diskusi dulu**. Jangan langsung commit.
- Jika menemukan hal di §7 yang menghambat, **flag ke user** — jangan putuskan sendiri.
