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
| LLM | **DeepSeek v4 Flash** (via OpenCode Zen path `/go/v1`, OpenAI-compatible) | Ekstraksi entitas HTML→JSON terstandar; cost rendah, response cepat. Path `/go/` khusus subscription OpenCode Go; pakai `/v1` biasa untuk model free. |

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
- **Prioritas portal** (berdasarkan public-listing accessibility, lihat `portals.py` di scraper):
  - **Tier 1 (primary, direct scrape)**: lombahub.com, kompetisi.co.id, luarkampus.id, ajangjuara.com — public listing visible tanpa login. Scraper pakai direct Firecrawl + LLM.
  - **Tier 2 (degraded, Google Search fallback)**: ikutlomba.id (login required, SPA shell 4.6KB), sejutacita.id (parked/empty, response 1.4KB). Scraper pakai Firecrawl `/v1/search` dengan operator `site:<domain>` + filter `includeDomains`. **Lihat §3.4a.**
  - Logika: scrape langsung kalau `tier=1`. Kalau `tier=2` ATAU `tier=1` tapi listing kosong → fallback Google Search "site:\<domain\> lomba 2026" → top 20 hasil → LLM extract dari hasil tersebut.
- **Stealth parameters** (wajib): rotasi User-Agent, browser fingerprinting, simulasi scroll & delay acak.
- **Pipeline data**: HTML → Markdown (Firecrawl) → LLM (DeepSeek v4 Flash via OpenCode Zen) bersihkan iklan/navigasi/copyright → JSON sesuai skema → hash MD5 → INSERT ke `competitions`.
- **Window eksekusi**: jadwalkan di hari/jam lalu lintas rendah portal target (detail ada di §1).

### 3.4a Google Search Fallback (Fase 5.5)
- **Tujuan**: Handle portal yang login-required atau parked (lihat §3.4 Tier 2). Siswa/guru akan kecewa kalau klik `source_url` dapat 404 — search fallback memastikan URL kompetisi valid.
- **Trigger**: 
  - Portal `login_required=True` → langsung pakai search (skip direct scrape)
  - Portal `tier=1` tapi listing scrape return empty → fallback ke search
  - Portal `tier=1` tapi Firecrawl 5xx/timeout → fallback ke search
- **Mekanisme**: Firecrawl `POST /v1/search` dengan payload `{query: "site:ikutlomba.id lomba 2026", limit: 20, lang: "id", includeDomains: ["ikutlomba.id"]}`. Top 20 hasil di-extract detail URL-nya, lalu pipeline normal (scrape + LLM).
- **Cost**: ~sama dengan direct scrape (1 search = 1 credit, 1 detail scrape = 1 credit). Beda sumber, bukan tambahan.
- **Limitasi**: Search mungkin return URL yang sudah expired / 404. Pipeline tetap harus handle ini (skip + log).

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

## 6. Perintah Dev (sudah terbukti jalan)

### Laravel
- `php artisan serve` — dev server Laravel (HTTP di :8000)
- `php artisan queue:work --queue=scraping` — worker antrean Redis (untuk ScrapePortalJob). Production pakai systemd unit `nyxchamp-queue.service`.
- `php artisan reverb:start --port=8080` — WebSocket server (Fase 7 chat real-time). Production pakai systemd `nyxchamp-reverb.service`. (Fase 7). Production pakai systemd unit `nyxchamp-reverb.service`.
- `php artisan schedule:work` — scheduler lokal (trigger Senin 05:00 + Jumat 15:00 WIB). Production pakai cron `* * * * * php artisan schedule:run`.
- `php artisan scrape --portal=lombahub_com --sync` — one-shot scrape, sinkron. Tanpa flag `--sync` masuk antrian 'scraping'.
- `php artisan scrape --health` — cek apakah Python scraper service hidup.
- `POST /admin/scrape/trigger` (web) — admin manual trigger semua portal, cooldown 5 menit. Pakai darurat kalau jadwal auto gagal atau ada info lomba urgent mid-week.
- `php artisan test` / `php artisan test --filter=...` — PHPUnit (86 test, 244 assertion)
- `php artisan migrate:fresh --seed` — reset DB + seed 8 lomba contoh
- `php artisan queue:failed` — lihat job yang sudah gagal permanen
- `php artisan queue:retry <uuid>` — retry job yang gagal

### Frontend
- `npm run dev` — Vite/Inertia dev server
- `npm run build` — build frontend untuk produksi

### Docker stack (MySQL + Redis, di Scraping server)
- `cd /opt/nyxchamp-stack && docker compose up -d` — start stack
- `docker compose ps` — status (harus healthy)
- `docker compose logs -f mysql` — tail log
- `docker compose down` — stop
- `docker compose down -v` — stop + hapus data (HATI-HATI)

### Python scraper
- `cd scraper && .venv/bin/uvicorn app.main:app --port 8001` — FastAPI service
- `cd scraper && .venv/bin/python scripts/smoke_e2e.py` — one-shot E2E test (no daemon, ~10-30s)
- `cd scraper && .venv/bin/pytest` — test (40 pass)

### Firecrawl (di Scraping server)
- `bash /usr/local/bin/firecrawl-start.sh` — manual start (auto-start via `firecrawl_keeper.py` kalau ada env SSH)
- `bash /usr/local/bin/firecrawl-stop.sh` — manual stop
- `touch /tmp/firecrawl-active` — refresh auto-stop window (3 menit idle)
- `tail /var/log/firecrawl-auto-stop.log` — log auto-stop

### Smoke test (Fase besar)
- `php scripts/smoke_fase8.php` — chat polish E2E (11 check: edit/delete/read receipts)
- `php scripts/smoke_fase9.php` — file attachment E2E (11 check: upload/download/reject/soft-delete)

> Setup lengkap lihat `deploy/SETUP.md`. Catatan Fase 9: production butuh `post_max_size=20M` + `upload_max_filesize=20M` di `php.ini` (5 lampiran × 10MB max = 50MB worst case, 20M cukup untuk single message dengan image 5MB).

## 6b. Fase yang Sudah Selesai
- **Fase 0** — Fondasi: Laravel 13.17 + Breeze (Inertia + React + TS) + Reverb + Python FastAPI scraper skeleton di `scraper/`
- **Fase 1** — Skema DB 3NF (5 tabel: users, competitions, chat_rooms, chat_room_members, chat_messages) + Eloquent models + `CompetitionHash` service
- **Fase 2** — Auth + RBAC: `RoleMiddleware` (alias `role:admin,teacher`), `institution` field, profile form lokal-ID
- **Fase 3** — Fondasi UI Neo-Brutalisme: Tailwind tokens (palette HEX, font families, box-shadow tanpa blur, borderWidth 3), Google Fonts (Syne/Space Grotesk/JetBrains Mono), `Components/Brutal/*` (Button, Card, Badge, Link, Heading), `AuthenticatedLayout`/`GuestLayout` lokal-ID
- **Fase 4** — Modul Kompetisi read-only: `CompetitionController` (index/show), filter (level/status/pencarian), `Pages/Competitions/{Index,Show}`, `Components/Brutal/CompetitionCard`, `CompetitionFactory` + `CompetitionSeeder` (8 lomba contoh), nav "Lomba" di `AuthenticatedLayout` + CTA "Jelajahi Lomba" di Welcome/Dashboard
- **Fase 5** — Scraper integration Laravel ↔ Python: `ScraperService` (HTTP client + retry), `CompetitionIngestor` (dedup + auto-create public room), `ScrapePortalJob` (queueable + backoff 60/300/900s), `ScrapeCommand` (artisan `--portal`/`--max-pages`/`--sync`), `NewCompetitionDetected` event + `LogNewCompetition` listener (broadcast stub), scheduler Senin 05:00 + Jumat 15:00 WIB. **Admin dashboard** (`/admin`) dengan tombol "Jalankan Scraping Sekarang" (manual trigger, cooldown 5 menit) + "Cek Status Scraper" (health check) — pakai kalau ada info lomba urgent mid-week atau recovery dari gagal auto. **Fase 5.6** poster download: `PosterDownloader` (Http facade + 5MB limit + Content-Type allowlist jpeg/png/webp/gif, SVG ditolak XSS-prevention konsisten dgn Fase 9), kolom `poster_path` (nullable string), disk `competitions` (private), route `GET /lomba/{slug}/poster` → StreamedResponse dengan `Cache-Control: public, max-age=86400`. Frontend: hero poster di `Show.tsx` (border-4 ink + shadow-brutal, lazy load), thumbnail kecil di `CompetitionCard.tsx` (h-32, border-b-3 ink). Python: `image_url` di Pydantic `Competition` schema + LLM SYSTEM_PROMPT minta extract image URL (og:image / hero image, null kalau tidak ada). Disk usage: ~200KB/poster, 100 lomba = ~20MB. Test: 7 unit test (Http::fake mock). Python: `FirecrawlClient` (async httpx + `/v1/search` method), `LLMExtractor` (OpenAI SDK + custom base_url ke OpenCode Zen `/go/v1`), `Portals` registry 6 portal Indonesia (Tier 1: lombahub, kompetisi, luarkampus, ajangjuara; Tier 2: ikutlomba, sejutacita dengan `login_required=True`), `scraper.py` orchestrator dengan `asyncio.gather` (concurrency=4) + Google Search fallback (Fase 5.5) untuk Tier 2, `firecrawl_keeper.py` (auto-start stack via SSH kalau host-nya down — host pakai cron auto-stop 3 menit idle untuk hemat RAM).
- **Fase 6** — Production-like stack: MySQL 8 + Redis 7 di Scraping server (`docker/nyxchamp-stack/docker-compose.yml`, port 3306 + 6380). Laravel `.env`: `DB_CONNECTION=mysql`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`. `php artisan migrate:fresh` jalan sukses (8 migrasi: users, cache, jobs, role+institution, competitions, chat_rooms, chat_room_members, chat_messages). Queue Redis verified: dispatch + worker (backoff 60s dihormati) + `failed()` callback log ke `storage/logs/scraper.log`. Systemd unit: `deploy/systemd/nyxchamp-queue.service` (Production-ready) + `nyxchamp-reverb.service` (Fase 7 placeholder). Panduan deploy lengkap di `deploy/SETUP.md`.
- **Fase 7** — Chat real-time Reverb + 3 channel types: `ChatController` (index, show, store message, invite member, create group bimbingan), `MessageSent` event broadcast ke private `chat.room.{id}`, channel auth di `routes/channels.php` (private: anggota; presence: anggota + emit info online), route group bimbingan di halaman detail lomba (teacher/admin only, idempotent per guru). React: `Pages/Chat/{Index,Show}.tsx` dengan `useEcho` (live message) + `useEchoPresence` (indikator online + jumlah user aktif), layout Neo-Brutalisme (border 3-4px, shadow hard offset, JetBrains Mono untuk teks chat). Tombol "Buat Grup Bimbingan" di `Pages/Competitions/Show.tsx` aktif untuk teacher/admin. Reverb server start: `php artisan reverb:start --port=8080` (systemd `nyxchamp-reverb.service` di prod).
- **Fase 8** — Chat polish (edit/delete/typing/read receipts): migration `chat_messages` (tambah `edited_at`, `deleted_at`, `deleted_by`) + tabel baru `chat_room_reads` (composite PK room_id+user_id). `ChatMessage` model: accessor `displayText()`, `isEdited()`, `isDeleted()`, `isEditable()` (window 15 menit). 3 event baru: `MessageEdited` (broadcast text baru + edited_at), `MessageDeleted` (broadcast id saja, defense-in-depth), `MessagesRead` (ke presence channel, payload: user_id + last_read_message_id). `ChatController` +3 method: `updateMessage` (sender only, 403 kalau lewat window atau deleted), `deleteMessage` (sender atau admin; soft delete, text asli tetap di DB), `markRead` (upsert `chat_room_reads`, dispatch event). Routes: PATCH `chat.messages.update`, DELETE `chat.messages.delete`, POST `chat.messages.read`. Frontend `Pages/Chat/Show.tsx`: tombol Edit (disabled kalau lewat window) + inline form; tombol Hapus (konfirmasi); label "(diedit)" + placeholder "[Pesan dihapus]"; auto mark-read setiap ada message baru; render "✓ Dibaca oleh X" per message; typing indicator via presence channel `whisper('typing', {userId})` + debounce 2.5 detik; emit typing throttled 1 detik.
- **Fase 9** — File attachment: migration `chat_attachments` (id, message_id FK CASCADE, uploaded_by FK RESTRICT, disk, file_path, original_name, mime_type, size_bytes). Disk `chat` baru di `config/filesystems.php` (`storage/app/chat`, private, throw/report on). `ChatController` +2 method: `uploadAttachment` (additive, sender only) + `downloadAttachment` (StreamedResponse, cek membership, 404 kalau file hilang). `storeMessage` diupdate: `message_text` jadi nullable (boleh text-only, file-only, atau keduanya), validate `attachments[]` (max 5, whitelist MIME). Batas ukuran: image 5MB, doc 10MB (validator per kategori via helper). Path strategy: `room-{id}/{yyyy}/{mm}/{ulid}-{safe_name}`. Frontend `Pages/Chat/Show.tsx`: tombol 📎 + hidden `<input type="file" multiple>`, preview grid (image thumb / PDF card / DOC card) sebelum kirim, display inline `<img max-h-64>` untuk image, card unduh untuk PDF/doc. `Message` type + `Attachment` type, `MessageSent` echo handler include `attachments[]`. Routes: POST `chat.messages.attachments.store`, GET `chat.attachments.download`. Catatan: hard-delete message cascade hapus row attachment tapi FILE di disk tetap (cleanup via cron — TODO Fase Future). Soft-delete message: row tetap, payload sembunyi (UI), file tetap di disk (audit trail). SVG **diblok** eksplisit (XSS). `php artisan storage:link` tidak dipakai (disk private, semua via controller). Production butuh `post_max_size=20M` di `php.ini` (lihat `deploy/SETUP.md`).
- **Fase 10** — Sistem Notifikasi Multi-Channel (Web, WebSocket & Email/SMTP): migrasi `notifications` table (standard Laravel) + JSON column `notification_preferences` di tabel `users`. Helper `getNotificationPreference(key, default)` di `User` model. 2 Notification classes: `NewCompetitionNotification` (channels: `database`, `broadcast`, `mail`) + `InvitationNotification` (channels: `database`, `broadcast`, `mail`). Listeners & Controllers: `LogNewCompetition` listener dispatch `NewCompetitionNotification` ke user dengan preferensi tingkat yang cocok. `ChatController::inviteMember` dispatch `InvitationNotification` ke student yang diundang. `NotificationController` +4 routes: `GET /notifications` (index), `POST /notifications/{id}/read` (mark single read), `POST /notifications/read-all` (mark all read), `PATCH /notifications/preferences` (update preferences). Frontend React/Inertia: `Pages/Notifications/Index.tsx` (list notifikasi, toggle baca, brutal style), Bell icon + badge count real-time di `AuthenticatedLayout` (Echo notification listener), section `UpdateNotificationPreferencesForm` di `Profile/Edit.tsx`. Scraper: skip `.env` loading di python tests (`APP_ENV=testing`), upgrade `_build_url` di `firecrawl_client.py` agar mengizinkan `/v1/search` dan `/v2/search`.

Test lulus: **167 passed, 504 assertions** (`php artisan test`) + **47 passed** (`scraper/pytest`) per fase ini. Screenshot preview: `docs/screenshots/`. Smoke test E2E: `scripts/smoke_fase9.php` (11 check lulus, semua skenario upload/download/reject/soft-delete). Test admin: `AdminScraperControllerTest` (10 test: RBAC + trigger + cooldown + health). Test poster: `PosterDownloaderTest` (7 test: jpeg/png/webp/gif, SVG ditolak, >5MB ditolak, idempotent). Test notifikasi: `NotificationControllerTest` (7 test: preferences, read/unread status, matching level, invitation).

## 7. TODO & Keputusan yang Belum Diambil
- [ ] Conventional Commits penuh (scope, body, footer) — perlu keputusan tim
- [ ] Strategi branch (`feat/*` `fix/*` `chore/*`) & PR template
- [ ] Strategi migrasi DB awal (pertama kali `php artisan migrate` di env dev)
- [x] Retry policy scraper saat gagal — **done di Fase 5/6**: `ScrapePortalJob` punya `tries=3` + `backoff()=[60, 300, 900]`, 4xx → `$this->fail()` permanent, 5xx/connection → retry. Permanent failure masuk `failed_jobs` table (Redis) + log ke `scraper` channel via `failed()` callback. Inspect dengan `php artisan queue:failed`, retry dengan `php artisan queue:retry <uuid>`.
- [ ] Rate-limit per portal target (scrape terlalu agresif = IP kena blok)
- [ ] Strategi auth channel Reverb (sesi Laravel vs JWT) — rancangan sebut dua-duanya
- [ ] Inisialisasi `composer.json` & `package.json` (saat coding mulai)
- [ ] Setup CI/CD (GitHub Actions?) — belum diputuskan
- [ ] **Fase 9 cleanup** — cron job hapus file orphan di `storage/app/chat/` (kalau message force-delete). Saat ini file tetap (audit trail + safe default).
- [ ] **Fase 9 hardening** — antivirus scan (ClamAV) untuk PDF/DOC. Pilot skip.
- [ ] **Fase 9 image optimization** — auto-resize gambar > 1MB jadi max 1600px. Pilot skip.
- [ ] **Fase 9 quota** — disk usage monitor per user. Pilot skip.

## 8. Catatan untuk Agen Berikutnya
- Jangan mulai dari猜测. Baca `Rancangan Portal Lomba Laravel.md` dulu sebelum usulkan perubahan.
- Jika ada konflik antara dokumen rancangan dan best practice modern, **dokumen rancangan menang** sampai ada diskusi eksplisit untuk meng-update rancangan.
- Jika ingin mengubah salah satu keputusan §3, **buka diskusi dulu**. Jangan langsung commit.
- Jika menemukan hal di §7 yang menghambat, **flag ke user** — jangan putuskan sendiri.

## 8.1 Server Dev Baru: 10.10.1.12 (NyxChamp)

Sejak 2026-07-01, pindah ke server lokal **10.10.1.12** (hostname `NyxChamp`, Proxmox VM Ubuntu 24.04 fresh install, 4GB RAM / 9.8GB disk / 2 CPU). Server ini jadi dev workstation baru, bukan sandbox.

**Stack ter-install lokal** (self-contained):
- PHP 8.4.22 (sury.org repo, alternatif Ubuntu 8.3 default) + extensions: mysql, redis (via predis), bcmath, gd, intl, mbstring, curl, zip, opcache
- Composer 2.10.1
- Node.js 20.20.2 + npm 10.8.2
- MySQL 8.0.46 (bind 127.0.0.1, root: `AsusTerbaik2-nyxchamp-root`, app user `nyxchamp`/`AsusTerbaik2-nyxchamp-app`)
- Redis 7.0.15 (bind 127.0.0.1:6379, REDIS_CLIENT=predis di .env)
- Python 3.12.3 + scraper venv di `/opt/nyxchamp/scraper/.venv`

**Services jalan** (semua di satu mesin, no Docker):
- `php artisan serve` → :8000 (Laravel HTTP)
- `php artisan reverb:start --port=8080` → :8080 (WebSocket)
- `php artisan queue:work --queue=scraping --tries=3 --backoff=60,300,900` (no port)
- `scraper/.venv/bin/uvicorn app.main:app --host=0.0.0.0 --port=8001` (FastAPI scraper)
- Start script: `/opt/nyxchamp/start-services.sh`

**Resource usage runtime**: RAM 851Mi used / 3.2Gi available, disk 29% (6.7GB free). Aman.

**Akses dev**:
- Web: `http://10.10.1.12:8000` (login: `admin@nyxchamp.test`/`guru@nyxchamp.test`/`siswa@nyxchamp.test`, password `password`)
- WebSocket: `ws://10.10.1.12:8080`
- FastAPI docs: `http://10.10.1.12:8001/docs`
- Admin trigger scrape: `http://10.10.1.12:8000/admin`

**Scraper tetap pakai Firecrawl dari 10.10.1.28** (scraper server Proxmox VM existing). SSH via `sshpass` di scraper process, auto-start firecrawl kalau down. Firecrawl auto-stop 3 menit idle di 10.10.1.28 tetap aktif.

**Catatan dev**:
- Gunakan `COMPOSER_ALLOW_SUPERUSER=1` saat install via root
- `npm install` butuh `--legacy-peer-deps` (vite 7 vs plugin-react 4.7.0 conflict)
- Predis sebagai Redis client (`REDIS_CLIENT=predis` di .env) — sury.org tidak punya `php8.4-redis`
- `bootstrap/cache` harus `mkdir` manual setelah clone (gitignored) — sudah di-start-services.sh
- Untuk start Firecrawl otomatis, butuh `apt install sshpass` (sudah ter-install di setup)
