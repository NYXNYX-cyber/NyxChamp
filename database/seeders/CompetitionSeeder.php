<?php

namespace Database\Seeders;

use App\Models\Competition;
use Illuminate\Database\Seeder;

/**
 * Seed kompetisi dengan portofolio tetap (read-only demo).
 *
 * Empat kompetisi sengaja dibuat mewakili keempat tingkat
 * (kabupaten, provinsi, nasional, internasional) dengan deadline
 * yang berbeda — sebagian sudah lewat, sebagian masih buka — untuk
 * menguji filter & badge "pendaftaran masih buka" di UI.
 */
class CompetitionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $items = [
            [
                'title' => 'Lomba Poster Digital Pelestarian Lingkungan 2026',
                'organizer' => 'Dinas Lingkungan Hidup Kabupaten Bogor',
                'description' => "Kompetisi desain poster digital untuk pelajar SMA/sederajat se-Kabupaten Bogor. Tema tahun ini: **“Sungai Bersih, Kota Sehat.”**\n\nKarya dinilai oleh juri dari komunitas desainer grafis independen. Pemenang utama mendapatkan tropi, piagam, dan uang pembinaan Rp 3.000.000.",
                'registration_deadline' => $now->copy()->addDays(14)->toDateString(),
                'level' => Competition::LEVEL_KABUPATEN,
                'registration_fee' => 0,
                'source_url' => 'https://kompetisi.co.id/lomba-poster-lingkungan-2026',
            ],
            [
                'title' => 'Hackathon Edukasi Jawa Barat 2026',
                'organizer' => 'Dinas Pendidikan Provinsi Jawa Barat',
                'description' => "Hackathon 48 jam untuk tim 3-5 mahasiswa aktif D3/D4/S1 di Jawa Barat. Bangun prototipe aplikasi edukasi dalam waktu 2 hari. Topik bebas di bidang teknologi pendidikan.\n\nFasilitas: konsumsi, akses coworking, mentoring dari praktisi industri. Total hadiah Rp 25.000.000 untuk 3 pemenang.",
                'registration_deadline' => $now->copy()->addDays(21)->toDateString(),
                'level' => Competition::LEVEL_PROVINSI,
                'registration_fee' => 50000,
                'source_url' => 'https://lombahub.com/',
            ],
            [
                'title' => 'Olimpiade Matematika Nasional Tingkat SMA 2026',
                'organizer' => 'Kemendikbud',
                'description' => "Olimpiade sains nasional bidang matematika untuk siswa SMA/sederajat. Seleksi melalui tiga tahap: kabupaten, provinsi, dan nasional.\n\nPeserta yang lolos ke tingkat nasional akan direkomendasikan untuk program pembinaan menuju olimpiade internasional. Soal terdiri dari analisis, aljabar, kombinatorika, dan teori bilangan.",
                'registration_deadline' => $now->copy()->addDays(45)->toDateString(),
                'level' => Competition::LEVEL_NASIONAL,
                'registration_fee' => 0,
                'source_url' => 'https://ajangjuara.com/olimpiade-matematika-nasional-2026',
            ],
            [
                'title' => 'ASEAN Youth Science Competition 2026',
                'organizer' => 'ASEAN Foundation',
                'description' => "Kompetisi sains pelajar tingkat ASEAN. Setiap negara anggota mengirimkan delegasi terbaik. Kategori: riset terapan, inovasi lingkungan, dan bioteknologi.\n\nDelegasi Indonesia akan diseleksi dari pemenang olimpiade nasional + seleksi berkas. Akomodasi, transportasi, dan konsumsi ditanggung panitia.",
                'registration_deadline' => $now->copy()->addDays(60)->toDateString(),
                'level' => Competition::LEVEL_INTERNASIONAL,
                'registration_fee' => 0,
                'source_url' => 'https://sejutacita.id/asean-youth-science-2026',
            ],
            [
                'title' => 'Lomba Esai Kebangsaan untuk Mahasiswa',
                'organizer' => 'Universitas Indonesia',
                'description' => "Lomba esai nasional untuk mahasiswa aktif D4/S1. Tema: **“Peran Generasi Z dalam Menjaga Persatuan Bangsa.”** Panjang esai 1500-2500 kata.\n\nKarya terbaik akan dimuat di jurnal kampus dan menerima uang pembinaan total Rp 10.000.000.",
                'registration_deadline' => $now->copy()->addDays(7)->toDateString(),
                'level' => Competition::LEVEL_NASIONAL,
                'registration_fee' => 25000,
                'source_url' => 'https://luarkampus.id/',
            ],
            [
                'title' => 'Kompetisi Short Video Pelajar Indonesia 2026',
                'organizer' => 'Mozilla Indonesia',
                'description' => "Lomba video pendek (maks 90 detik) untuk pelajar SMP dan SMA. Tema: literasi digital dan keamanan internet. Platform: Instagram Reels atau TikTok.\n\nPenilaian: orisinalitas, pesan, dan kualitas produksi. Total hadiah Rp 15.000.000.",
                'registration_deadline' => $now->copy()->subDays(5)->toDateString(),
                'level' => Competition::LEVEL_NASIONAL,
                'registration_fee' => 0,
                'source_url' => 'https://lombahub.com/',
            ],
            [
                'title' => 'Web Design Competition Sumatera Barat 2026',
                'organizer' => 'Universitas Andalas',
                'description' => "Kompetisi desain web statis untuk siswa SMA dan mahasiswa se-Sumatera Barat. Buat landing page bertema budaya Minangkabau.\n\nSubmission: HTML, CSS, dan aset gambar. Penilaian: estetika, aksesibilitas, dan kesesuaian tema. Hadiah utama Rp 5.000.000.",
                'registration_deadline' => $now->copy()->addDays(30)->toDateString(),
                'level' => Competition::LEVEL_PROVINSI,
                'registration_fee' => 75000,
                'source_url' => 'https://kompetisi.co.id/web-design-sumbar-2026',
            ],
            [
                'title' => 'International Physics Olympiad 2026 (IPhO)',
                'organizer' => 'Ikatan Alumni Olympiade Sains',
                'description' => "Kompetisi fisika internasional tahunan. Indonesia mengirimkan 8 delegasi pilihan dari hasil olimpiade nasional.\n\nPeserta internasional akan mendapat akomodasi dan transportasi lokal selama 10 hari. Bahasa pengantar: Inggris.",
                'registration_deadline' => $now->copy()->addDays(90)->toDateString(),
                'level' => Competition::LEVEL_INTERNASIONAL,
                'registration_fee' => 0,
                'source_url' => 'https://ajangjuara.com/iph-2026',
            ],
        ];

        foreach ($items as $item) {
            Competition::firstOrCreate(
                ['hash_md5' => \App\Services\CompetitionHash::compute($item['title'], $item['registration_deadline'])],
                $item,
            );
        }
    }
}
