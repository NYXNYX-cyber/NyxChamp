-- MySQL init script. Dijalankan sekali saat container pertama kali start.
-- Lihat https://hub.docker.com/_/mysql untuk detail.
--
-- Untuk tweak collation / charset, edit docker-compose.yml command args.
-- Untuk seed data atau user tambahan, tambahkan di sini.

-- Pastikan utf8mb4 jadi default untuk konsistensi dengan Laravel default.
ALTER DATABASE nyxchamp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
