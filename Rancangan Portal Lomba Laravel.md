# **Rancangan Sistem dan Desain UI/UX Platform Agregator Kompetisi Nasional Berbasis Multi-Agent AI, Laravel Reverb, dan Estetika Neo-Brutalisme**

## **Lanskap Kompetisi di Indonesia dan Strategi Agregasi Data Berkala**

Informasi mengenai kompetisi akademik dan non-akademik di Indonesia saat ini tersebar di berbagai platform digital dengan segmentasi yang sangat terfragmentasi1. Portal informasi seperti Kompetisi.co.id berfokus pada kurasi prestasi nasional yang terhubung langsung dengan Sistem Informasi Manajemen Talenta (SIMT) Puspresnas Kemendikdasmen1. Di sisi lain, platform mandiri seperti Ikutlomba.id, Ajangjuara.com, Sejutacita.id, Infolomba.id, dan Lombahub.com melayani berbagai kategori mulai dari esai, desain grafis, pemrograman, hingga olimpiade sains untuk tingkat sekolah dasar hingga perguruan tinggi2. Fragmentasi ini menyulitkan pelajar, mahasiswa, dan guru pembimbing untuk mendapatkan informasi yang komprehensif dan terbarui secara cepat.  
Untuk mengatasi kendala tersebut, platform agregator ini dirancang untuk mengotomatisasi pengumpulan data dari berbagai sumber eksternal secara berkala6. Pengumpulan data secara mendalam (*deep crawling*) dijadwalkan berlangsung seminggu sekali7. Penjadwalan ini dipilih secara sengaja untuk mengoptimalkan keseimbangan antara pembaruan informasi dan efisiensi konsumsi sumber daya komputasi peladen9.  
Dengan memusatkan siklus penelusuran pada hari dan jam dengan lalu lintas rendah, sistem dapat menghindari pemblokiran IP oleh dinding pertahanan portal target8. Standardisasi format data merupakan keluaran utama dari proses penelusuran ini, mengingat setiap situs target memiliki struktur penulisan tanggal tenggat waktu, persyaratan, dan kategori yang berbeda-beda5.  
Target portal utama yang menjadi fokus pemindaian berkala ini dirinci pada tabel berikut:

| Nama Portal Target | Karakteristik Informasi & Kategori | Target Segmentasi Pengguna | Validasi & Pengakuan Prestasi |
| :---- | :---- | :---- | :---- |
| Kompetisi.co.id | Lomba terkurasi, beasiswa, seni, sains1 | Pelajar & Mahasiswa1 | SIMT Puspresnas Kemendikdasmen1 |
| Ikutlomba.id | Desain, pemrograman, karya tulis, rencana bisnis2 | Pelajar, Mahasiswa, Umum2 | Sertifikat Otomatis Platform2 |
| Ajangjuara.com | Olimpiade sains nasional, beasiswa pendidikan4 | Pelajar Nusantara (SD-SMA)4 | Terdaftar SIMT Puspresnas4 |
| Sejutacita.id | Webinar, magang, beasiswa, perlombaan3 | Mahasiswa & Siswa SMA3 | Penghargaan Aplikasi Terbaik Google3 |
| Luarkampus.id | Pertukaran pelajar, konferensi, kompetisi inovasi10 | Mahasiswa & Siswa Menengah10 | Institusi Penyelenggara Resmi10 |
| Lombahub.com | Adzan, cipta puisi, debat, robotika, esports5 | SD, SMP, SMA, Mahasiswa, Umum5 | Penyelenggara Mandiri & Kampus5 |

## **Arsitektur Multi-Agent AI Web Scraper Paralel**

Arsitektur penelusuran data pada platform ini didesain menggunakan pendekatan *microservices* terpisah guna menjaga performa aplikasi utama Laravel agar tetap responsif bagi pengguna akhir12. Komponen penelusuran diimplementasikan sebagai layanan berbasis Python yang mengeksekusi agen pengikis (*scraper agent*) secara paralel di dalam klaster kontainer14.  
Pemicuan penelusuran dilakukan oleh *Laravel Scheduler* menggunakan antrean *Redis*14. Penjadwal Laravel akan memasukkan tugas penelusuran (*scraping jobs*) ke dalam antrean Redis14. Pekerja (*worker*) paralel kemudian mengambil tugas tersebut dan mengirimkan permintaan asinkron ke layanan pengikis berbasis Python14.  
Waktu total eksekusi penelusuran paralel untuk seluruh target dapat dimodelkan secara matematis. Jika ![][image1] merupakan jumlah total portal target, ![][image2] merupakan rata-rata kedalaman penelusuran (*crawl depth*) per portal7, ![][image3] merupakan jumlah agen AI yang berjalan secara paralel, ![][image4] merupakan waktu rata-rata pengikisan satu halaman web8, dan ![][image5] merupakan waktu rata-rata pemrosesan skema oleh LLM7, maka total waktu eksekusi ![][image6] dirumuskan sebagai:  
![][image7]  
Model ini membuktikan bahwa dengan meningkatkan jumlah agen paralel ![][image3], waktu penelusuran total dapat ditekan secara signifikan meskipun kedalaman penelusuran ![][image2] dikonfigurasi secara mendalam (*deep crawling*)7.  
Aktivitas penelusuran ini memanfaatkan teknologi *Crawl4AI* dan *Firecrawl* yang dikonfigurasi dengan parameter khusus untuk menghindari sistem deteksi bot7. Agen pengikis memanfaatkan teknik rotasi Agen Pengguna (*User-Agent*), manipulasi sidik jari peramban (*browser fingerprinting*), serta simulasi gerakan manusia seperti pengguliran halaman dan penundaan acak (*stealth browser parameters*)7.  
Perbandingan kapabilitas teknologi pengikisan berbasis AI yang digunakan di dalam sistem dianalisis pada tabel berikut:

| Kriteria Teknologi | Crawl4AI Engine | Firecrawl API | Apify AI Scraper |
| :---- | :---- | :---- | :---- |
| **Metode Penelusuran** | Breadth-First (BFS) / Depth-First (DFS)7 | Penelusuran Peta URL & Eksplorasi Mandiri8 | Traversing Berorientasi Skor Halaman7 |
| **Format Keluaran** | Markdown Bersih / JSON Terstruktur7 | Markdown Teroptimasi LLM / Tangkapan Layar8 | JSON CSS & Transformasi Skema XPath7 |
| **Kelebihan Utama** | Penanganan memori dinamis dan bypass pop-up7 | Kecepatan latensi P95 hingga 3,4 detik8 | Integrasi siap pakai dengan n8n, Make, dan Zapier7 |
| **Metode Ekstraksi** | LLMExtractionStrategy / CSS Json7 | Ekstraksi Prompt AI Tanpa URL Awal8 | Pola Skema Selektor CSS Kustom7 |
| **Penanganan JS** | Integrasi Playwright Headless7 | Pengelolaan Proxy Berputar Otomatis8 | Emulator Peramban Skala Industri7 |

Proses pembersihan data mentah dijalankan langsung oleh agen AI setelah dokumen HTML diubah menjadi Markdown7. Agen LLM (menggunakan GPT-4o-mini melalui pustaka *Prism* di Laravel atau integrasi mikro Python) menerima konten Markdown, mengekstrak entitas penting sesuai skema target, dan memvalidasi kebenaran informasi6.  
Proses ini menyaring konten yang tidak relevan seperti iklan, tautan navigasi peladen, dan teks hak cipta, sehingga hanya menghasilkan data bersih berformat JSON yang siap dimasukkan ke dalam database utama7.

## **Arsitektur Database Relasional**

Data yang telah diekstraksi dan divalidasi oleh agen AI kemudian disimpan ke dalam database MySQL18. Desain database dirancang menggunakan normalisasi tingkat ketiga (3NF) untuk menjamin integritas referensial dan mencegah duplikasi data kompetisi yang diambil dari berbagai sumber berbeda6.  
Setiap entitas database beserta tipe data dan tujuannya dirancang secara terstruktur demi mendukung performa query yang cepat pada skala data besar.

### **Tabel Identitas Pengguna: users**

Menyimpan data otentikasi dan profil pengguna, memisahkan hak akses antara siswa, guru pendamping, dan administrator sistem20.

| Nama Kolom | Tipe Data | Atribut | Deskripsi |
| :---- | :---- | :---- | :---- |
| id | bigint | PRIMARY KEY, AUTO\_INCREMENT | Pengidentifikasi unik untuk setiap akun pengguna |
| name | varchar(255) | NOT NULL | Nama lengkap pengguna22 |
| email | varchar(255) | UNIQUE, NOT NULL | Alamat surel aktif untuk login dan notifikasi20 |
| password | varchar(255) | NOT NULL | Hash kata sandi aman pengguna12 |
| role | enum('student', 'teacher', 'admin') | DEFAULT 'student' | Hak akses sistem untuk membatasi fitur kolaborasi20 |
| institution | varchar(255) | NULL | Nama sekolah atau universitas asal pengguna |
| created\_at | timestamp | NULL | Waktu pembuatan baris data |
| updated\_at | timestamp | NULL | Waktu pembaruan baris data |

### **Tabel Data Kompetisi: competitions**

Menampung data teragregasi hasil ekstraksi agen AI scraper6. Kolom hash\_md5 digunakan sebagai kunci unik untuk mencegah penyimpanan ulang data kompetisi yang sama dari portal sumber yang berbeda.

| Nama Kolom | Tipe Data | Atribut | Deskripsi |
| :---- | :---- | :---- | :---- |
| id | bigint | PRIMARY KEY, AUTO\_INCREMENT | Pengidentifikasi unik data kompetisi |
| title | varchar(255) | NOT NULL | Judul resmi kegiatan kompetisi6 |
| slug | varchar(255) | UNIQUE, NOT NULL | Slug teks ramah URL untuk optimasi SEO |
| organizer | varchar(255) | NOT NULL | Nama institusi penyelenggara kompetisi4 |
| description | text | NOT NULL | Deskripsi lengkap, syarat, dan ketentuan perlombaan |
| registration\_deadline | date | NOT NULL | Tanggal batas akhir pendaftaran peserta5 |
| level | enum('kabupaten', 'provinsi', 'nasional', 'internasional') | NOT NULL | Cakupan wilayah pelaksanaan kompetisi4 |
| registration\_fee | decimal(10,2) | DEFAULT 0.00 | Biaya pendaftaran (bernilai 0 jika gratis)1 |
| source\_url | text | NOT NULL | Tautan asli menuju halaman sumber kompetisi6 |
| hash\_md5 | varchar(32) | UNIQUE, NOT NULL | MD5 hash dari judul dan tanggal untuk menghindari duplikasi |
| created\_at | timestamp | NULL | Waktu penyimpanan data |
| updated\_at | timestamp | NULL | Waktu modifikasi data terakhir |

### **Tabel Ruang Obrolan: chat\_rooms**

Mewakili entitas grup obrolan. Grup obrolan dapat dikaitkan langsung dengan kompetisi tertentu atau dibuat secara mandiri oleh guru/murid untuk keperluan diskusi kelompok koordinasi20.

| Nama Kolom | Tipe Data | Atribut | Deskripsi |
| :---- | :---- | :---- | :---- |
| id | bigint | PRIMARY KEY, AUTO\_INCREMENT | Pengidentifikasi unik ruang obrolan |
| competition\_id | bigint | FOREIGN KEY, NULLABLE | Relasi ke kompetisi terkait (jika ada) |
| name | varchar(255) | NOT NULL | Nama grup diskusi atau koordinasi |
| is\_group | boolean | DEFAULT false | Penanda tipe obrolan kelompok (grup) atau personal (1-on-1) |
| created\_by | bigint | FOREIGN KEY, NOT NULL | Pengguna yang menginisiasi pembuatan grup |
| created\_at | timestamp | NULL | Waktu pembuatan ruang obrolan |

### **Tabel Anggota Ruang Obrolan: chat\_room\_members**

Menangani relasi banyak-ke-banyak (*many-to-many*) antara pengguna dan ruang obrolan, mengontrol siapa saja yang berhak mengakses pesan di dalam ruang privat22.

| Nama Kolom | Tipe Data | Atribut | Deskripsi |
| :---- | :---- | :---- | :---- |
| chat\_room\_id | bigint | FOREIGN KEY, NOT NULL | Referensi ke ID ruang obrolan terkait |
| user\_id | bigint | FOREIGN KEY, NOT NULL | Referensi ke ID pengguna yang bergabung |
| joined\_at | timestamp | DEFAULT CURRENT\_TIMESTAMP | Waktu pertama kali bergabung ke dalam grup |

### **Tabel Pesan Obrolan: chat\_messages**

Menyimpan riwayat seluruh pesan yang dikirimkan dalam sistem obrolan real-time22.

| Nama Kolom | Tipe Data | Atribut | Deskripsi |
| :---- | :---- | :---- | :---- |
| id | bigint | PRIMARY KEY, AUTO\_INCREMENT | Pengidentifikasi unik baris pesan22 |
| chat\_room\_id | bigint | FOREIGN KEY, NOT NULL | Referensi ke ruang obrolan tempat pesan dikirim |
| sender\_id | bigint | FOREIGN KEY, NOT NULL | Referensi ke pengguna yang mengirim pesan22 |
| message\_text | text | NOT NULL | Konten pesan teks yang dikirimkan22 |
| created\_at | timestamp | NULL | Waktu pengiriman pesan22 |

## **Sistem Chat Kolaboratif Real-Time Guru-Murid**

Sistem kolaborasi antara guru dan murid dibangun menggunakan teknologi *Laravel Reverb* sebagai peladen WebSocket utama yang bekerja secara asinkron25. Interaksi kolaboratif ini mencakup diskusi terbuka mengenai perlombaan tertentu, pembentukan grup bimbingan oleh guru, serta pengiriman materi persiapan lomba20.

\+--------------------------------------------------------------------------+  
|                            USER INTERFACE (React)                        |  
|  \- Laravel Echo Client                                                   |  
|  \- Component Listens to Private & Presence Channels        |  
\+---------------------+---------------------+------------------------------+  
                      | Send Message (Inertia POST) \[cite: 17, 29\]  
                      v  
\+---------------------+---------------------+------------------------------+  
|                            BACKEND (Laravel)                             |  
|  \- Receives request & Saves message to MySQL              |  
|  \- Dispatches MessageSent Broadcast Event \[cite: 17, 22\]                 |  
\+---------------------+---------------------+------------------------------+  
                      | Push to Queue Redis  
                      v  
\+---------------------+---------------------+------------------------------+  
|                         LARAVEL REVERB SERVER                            |  
|  \- Broadcaster pushes message through active WebSockets  |  
\+---------------------+---------------------+------------------------------+  
                      | WebSocket Message Event (WSS)  
                      v  
\+---------------------+---------------------+------------------------------+  
|                         RECIPIENT CLIENTS                                |  
|  \- Re-renders chat component instantaneously with updated state \[cite: 30\] |  
\+--------------------------------------------------------------------------+

### **Mekanisme Saluran Komunikasi (Channels)**

Sistem memisahkan jalur transmisi pesan menggunakan tiga tipe saluran yang didukung oleh Laravel Echo27:

* **Saluran Publik (Public Channels)**: Digunakan untuk menyiarkan informasi umum, seperti munculnya kompetisi baru yang baru saja selesai diidentifikasi oleh agen pengikis28.  
* **Saluran Privat (Private Channels)**: Digunakan untuk ruang obrolan bimbingan tertutup antara guru dan siswa22. Otorisasi masuk saluran divalidasi oleh backend melalui token JWT atau sesi autentikasi Laravel14.  
* **Saluran Kehadiran (Presence Channels)**: Digunakan di dalam grup koordinasi aktif untuk mendeteksi status pengguna (sedang online, offline, atau sedang mengetik pesan) secara real-time guna memperkuat interaksi kolaboratif27.

### **Alur Kolaborasi Guru dan Siswa**

Fitur obrolan ini dirancang khusus untuk mendukung proses pendampingan akademik20. Guru dapat bertindak sebagai inisiator grup dengan menekan tombol *"Buat Grup Bimbingan"* pada halaman detail kompetisi yang tertera di web20. Sistem kemudian secara otomatis membuat entitas chat\_rooms baru yang terikat dengan ID kompetisi tersebut20.  
Guru dapat membagikan kode akses unik grup atau mengundang akun siswa secara langsung berdasarkan email20. Setelah siswa bergabung, saluran bimbingan privat aktif, memungkinkan diskusi materi, koordinasi pendaftaran, dan pembagian tugas bimbingan berjalan secara lancar tanpa perlu berpindah ke aplikasi pesan instan pihak ketiga2.

## **Desain UI/UX: Paradigma Neo-Brutalisme Eksklusif**

Guna memberikan identitas visual yang unik dan sepenuhnya membedakan diri dari tren aplikasi modern yang cenderung monoton dengan dominasi gradasi biru/ungu (disebut sebagai ciri khas tren A), platform ini menerapkan estetika desain **Neo-Brutalisme**31. Desain ini menonjolkan kesan berani, jujur, dan berkarakter kuat melalui penolakan terhadap kehalusan grafis standar industri34.

### **Konsep Estetika Neo-Brutalisme**

Paradigma Neo-Brutalisme didasarkan pada penegasan elemen fisik mentah tanpa manipulasi kelembutan bayangan atau gradasi warna32. Hal ini tercermin dari kontras yang tajam, pemakaian garis batas hitam tebal pada setiap komponen, tata letak bergaya kotak bento, serta peniadaan efek blur pada bayangan32. Penerapan gaya ini memberikan kesan bahwa platform ini sangat menghargai fungsi informasi di atas dekorasi estetika yang berlebihan34.  
Aturan dan komponen desain Neo-Brutalisme diimplementasikan dengan parameter ketat berikut:

| Parameter Gaya | Pendekatan Konvensional (Tren A) | Pendekatan Neo-Brutalisme Platform |
| :---- | :---- | :---- |
| **Garis Batas (Borders)** | Tipis (1px), berwarna abu-abu pudar, atau tanpa border | Tebal (3px \- 4px) berwarna hitam pekat murni (\#000000)32 |
| **Bayangan (Shadows)** | Lembut, menyebar luas dengan blur Gaussian tinggi | Bayangan keras blok warna hitam murni tanpa efek blur sama sekali32 |
| **Gradasi Warna** | Gradasi linear ungu ke biru, transparansi kaca | Warna datar (*flat solid*) dengan saturasi dan kontras tinggi32 |
| **Radius Sudut** | Sangat membulat (*highly rounded* hingga 16px ke atas) | Sudut tajam bersiku (*rounded-none*) atau radius sudut minimal (2px \- 4px)32 |
| **Tipografi** | Inter, Sans-Serif netral, ukuran seimbang | Tipografi ekspresif, kontras ekstrem antara teks display dan isi32 |
| **Transparansi** | Efek Glassmorphism transparan / kabur | Warna solid, buram murni (*opaque*) tanpa transparansi32 |

### **Skema Palet Warna Neo-Brutalisme**

Penggunaan warna dirancang untuk mengejutkan mata pengguna sekaligus menjaga keterbacaan tingkat tinggi33. Tidak ada warna gradasi yang digunakan di seluruh antarmuka32. Palet warna disusun dengan kode HEX khusus untuk menjamin kontras tinggi yang ramah aksesibilitas (WCAG AAA)31:

* **Cream/Beige Organik (\#F5F0E6)**: Digunakan sebagai warna latar belakang utama halaman web32. Warna ini dipilih untuk memberikan kesan hangat, mirip kertas mentah atau semen ringan, menggantikan warna putih klinis atau hitam pekat yang biasa ditemui pada aplikasi modern32.  
* **Solid Black (\#000000)**: Digunakan untuk semua teks utama, ikon, garis batas komponen (*borders*), dan bayangan blok keras32.  
* **Neon Accent Pink (\#FF4081)**: Memberikan aksen mencolok pada tombol tindakan utama, kategori lomba desain, dan penanda penting lainnya32.  
* **Neon Accent Yellow (\#FFEB3B)**: Digunakan khusus untuk label tingkat kompetisi (seperti "Tingkat Nasional" atau "Internasional") serta badge sorotan utama kompetisi4.  
* **Hyperlink Blue (\#0000EE)**: Mengadopsi warna biru murni peramban klasik era awal internet untuk merepresentasikan tautan rujukan eksternal, menegaskan kejujuran material HTML platform34.  
* **Neo Emerald Green (\#4CAF50)**: Digunakan sebagai indikator status pendaftaran kompetisi yang masih terbuka lebar32.

### **Pilihan Tipografi Unik**

Sistem tipografi menggunakan kombinasi huruf yang memberikan kesan hibrida antara gaya cetak klasik dan fungsionalitas digital retro32:

* **Syne (Display Font)**: Font ini digunakan secara eksklusif untuk judul besar, hero banner, dan logo utama platform37. Karakteristik Syne yang melebar secara radikal ketika ketebalannya ditingkatkan memberikan identitas yang sangat mencolok dan tidak dapat disamai oleh font web standar37.  
* **Space Grotesk (Headers & UI Elements)**: Font sans-serif geometris fungsional ini diaplikasikan pada judul kartu kompetisi, navigasi menu, dan tajuk obrolan32. Font ini mempertahankan kejelasan pembacaan meskipun disandingkan dengan garis batas tebal32.  
* **JetBrains Mono (Metadata & Chat Teks)**: Menggunakan jenis huruf monospace untuk seluruh teks deskripsi detail kompetisi, tenggat waktu pendaftaran, biaya, dan area balon pesan obrolan32. Karakter monospace memperkuat konsep digital mentah dan memberikan presisi visual yang rapi saat membaca data numerik32.

### **Komponen Interaksi Mikro (Micro-interactions)**

Interaksi tombol dalam estetika Neo-Brutalisme berfokus pada sensasi mekanis yang nyata32. Saat pengguna mengarahkan kursor (*hover*) pada kartu kompetisi atau tombol aksi, komponen tersebut akan bergeser ke kiri atas sebesar 3 piksel secara instan, membuat bayangan blok hitam di bawahnya tampak memanjang32. Ketika tombol ditekan (*active*), elemen bergeser ke kanan bawah sebesar 2 piksel dan bayangan akan menyusut, mensimulasikan penekanan saklar fisik secara mekanis32.

## **Analisis Tech Stack dan Peta Aliran Sistem**

Pemilihan komponen teknologi (*tech stack*) didasarkan pada kecepatan pengembangan, efisiensi eksekusi asinkron, serta skalabilitas jangka panjang untuk melayani ribuan pengguna aktif18. Platform ini memprioritaskan kerangka kerja Laravel sebagai jangkar backend utama22.  
Komposisi teknologi yang direkomendasikan untuk platform agregator kompetisi ini dirinci pada tabel berikut:

| Bagian Sistem | Teknologi Terpilih | Peran Fungsional Utama | Dasar Pertimbangan Teknis |
| :---- | :---- | :---- | :---- |
| **Backend Core** | Laravel 11/12/1322 | Pemrosesan logika bisnis, manajemen antrean, otentikasi pengguna9 | Penyediaan ekosistem lengkap (Eloquent ORM, Scheduler, Event Broadcasting)6. |
| **WebSocket Server** | Laravel Reverb25 | Penanganan koneksi soket dua arah dengan latensi rendah22 | Berjalan secara native di ekosistem PHP tanpa ketergantungan node eksternal26. |
| **Frontend Framework** | React.js via Inertia.js21 | Render antarmuka dinamis dan interaksi komponen SPA21 | Integrasi data langsung dari backend tanpa memelihara REST API terpisah21. |
| **Styling Engine** | Tailwind CSS | Penyusun komponen gaya visual Neo-Brutalisme | Kemudahan penulisan kelas utilitas untuk border, bayangan keras, dan warna solid31. |
| **Cache & Queue Broker** | Redis Key-Value Store14 | Manajemen antrean tugas paralel pengikis dan status Reverb14 | Kecepatan tinggi dalam menangani I/O data antrean bervolume tinggi14. |
| **Transactional DB** | MySQL \>= 8.018 | Penyimpanan utama data akun, ruang obrolan, dan kompetisi | Keandalan transaksi relasional terstruktur dan ketersediaan indeks komposit18. |
| **Scraper Agent Run** | Python (FastAPI Engine)14 | Menjalankan proses pengikisan dinamis berbasis multi-agent7 | Ketersediaan pustaka manipulasi peramban modern berkinerja tinggi7. |
| **AI Scraping Libs** | Firecrawl8 & Crawl4AI7 | Pemindaian mendalam dan ekstraksi struktur Markdown7 | Bypass proteksi bot tingkat lanjut dan penghasil markdown siap-LLM7. |
| **LLM Processor** | OpenAI GPT-4o-mini API17 | Ekstraksi entitas dari teks tidak terstruktur menjadi JSON terstandardisasi6 | Pemrosesan bahasa alami yang akurat untuk konversi tanggal dan kategori7. |

### **Aliran Data Sistem End-to-End**

Siklus aliran data berjalan secara berkesinambungan melalui tahapan berikut untuk memastikan data tersaji secara akurat dan real-time kepada pengguna akhir:

\[Target Portal\]   
       │   
       ▼ (Penelusuran Mendalam Mingguan via Crawl4AI/Firecrawl)  
\[Scraper Agent (Python)\]   
       │   
       ▼ (Ekstraksi Entitas & Normalisasi via LLM API)  
\[Standardized JSON Payload\]   
       │   
       ▼ (Laravel Queue Worker memasukkan data ke Database)  
\[MySQL Database\]   
       │   
       ├─► \[Inertia.js memuat data ke UI Klien\]  
       │   
       └─► \[Otomatisasi Pembuatan Ruang Obrolan Kompetisi Baru\]  
                 │   
                 ▼ (Guru membuat grup koordinasi bimbingan siswa)  
           \[Private WebSocket Connection (Reverb)\]  
                 │   
                 ▼ (Pesan kolaborasi terkirim secara real-time) \[cite: 22, 27\]  
           \[Interactive Chat Room (React UI)\] \[cite: 17, 29\]

Tahapan operasional ini berjalan secara otomatis:

1. **Siklus Ingesti Data**: Penjadwal Laravel memicu proses pengerjaan antrean setiap akhir pekan9. Agen Python aktif secara paralel melakukan pemindaian mendalam ke seluruh target portal kompetisi di Indonesia2.  
2. **Siklus Pembersihan & Pemetaan**: Konten halaman diubah menjadi Markdown oleh Firecrawl/Crawl4AI7. LLM mengekstrak informasi penting dan mengembalikannya ke Laravel dalam bentuk payload JSON yang bersih dan sesuai skema6.  
3. **Siklus Penyimpanan**: Laravel memproses payload tersebut, mengecek integritas data menggunakan hash MD5, dan menyimpannya ke MySQL6. Jika terdeteksi kompetisi baru, sistem membuat entitas grup obrolan publik yang melekat pada kompetisi tersebut20.  
4. **Siklus Distribusi Real-Time**: Ketika pengguna (guru atau siswa) mengakses platform, Inertia.js menampilkan antarmuka berbasis komponen React dengan gaya visual Neo-Brutalisme yang berani21. Begitu pesan dikirimkan di dalam saluran bimbingan, Laravel Reverb langsung memancarkan payload ke seluruh perangkat anggota yang tergabung dalam hitungan milidetik, mewujudkan kolaborasi persiapan lomba yang dinamis dan berlatensi rendah26.

#### **Karya yang dikutip**

1. Beranda \- Kompetisi.co.id | Kompetisi Pelajar Nasional Terkurasi, [https://home.kompetisi.co.id/](https://home.kompetisi.co.id/)  
2. Ikutlomba.id — Cari & Ikuti Lomba Online Se-Indonesia, [https://www.ikutlomba.id/](https://www.ikutlomba.id/)  
3. Aplikasi Informasi Lomba, Event, Beasiswa, Webinar no 1 di Indonesia Untuk Mahasiswa dan SMA, [https://sejutacita.id/](https://sejutacita.id/)  
4. Ajang Prestasi Terkurasi PUSPRESNAS untuk Para Juara\!: AJANGJUARA.COM, [https://ajangjuara.com/](https://ajangjuara.com/)  
5. LombaHub \- Info Lomba Terbaru 2026, [https://lombahub.com/](https://lombahub.com/)  
6. Web Scraping With Laravel: A Step-by-Step Guide \- Bright Data, [https://brightdata.com/blog/web-data/web-scraping-with-laravel](https://brightdata.com/blog/web-data/web-scraping-with-laravel)  
7. AI Web Scraper \- Crawl4AI for LLMs, AI Agents & Automation \- Apify, [https://apify.com/raizen/ai-web-scraper](https://apify.com/raizen/ai-web-scraper)  
8. GitHub \- firecrawl/firecrawl: The API to search, scrape, and interact with the web at scale., [https://github.com/firecrawl/firecrawl](https://github.com/firecrawl/firecrawl)  
9. Web Scraping with Laravel 13: A Complete Production-Grade Guide \- Medium, [https://medium.com/@mohammed213123123123123/web-scraping-with-laravel-13-a-complete-production-grade-guide-8be492b5ab58](https://medium.com/@mohammed213123123123123/web-scraping-with-laravel-13-a-complete-production-grade-guide-8be492b5ab58)  
10. Kalender Event \- Luarkampus, [https://luarkampus.id/events](https://luarkampus.id/events)  
11. Info Lomba 2026 Terbaru, [https://www.kabarlomba.com/](https://www.kabarlomba.com/)  
12. How to Build Microservices with Laravel \- OneUptime, [https://oneuptime.com/blog/post/2026-02-02-laravel-microservices/view](https://oneuptime.com/blog/post/2026-02-02-laravel-microservices/view)  
13. A Deep Dive into Laravel Microservices Architecture \- EvinceDev, [https://evincedev.com/blog/laravel-microservices-architecture-detailed-guide/](https://evincedev.com/blog/laravel-microservices-architecture-detailed-guide/)  
14. Advanced Microservices Architecture in Laravel: High-Level Design, Dependency Injection, Repository Patterns, and Real-World Implementation \- Medium, [https://medium.com/@harryespant/advanced-microservices-architecture-in-laravel-high-level-design-dependency-injection-repository-0e787a944e7f](https://medium.com/@harryespant/advanced-microservices-architecture-in-laravel-high-level-design-dependency-injection-repository-0e787a944e7f)  
15. Microservices using Laravel \- Laracasts, [https://laracasts.com/discuss/channels/general-discussion/microservices-using-laravel](https://laracasts.com/discuss/channels/general-discussion/microservices-using-laravel)  
16. Laravel Web Scraper: How to Build One with PHP \- Oxylabs, [https://oxylabs.io/blog/laravel-web-scraping](https://oxylabs.io/blog/laravel-web-scraping)  
17. Creating a Chatbot with Laravel Inertia, Echo, Reverb, and Prism | by Soipo \- Medium, [https://medium.com/@soipo/creating-a-chatbot-with-laravel-inertia-echo-reverb-and-prism-59c598685a8e](https://medium.com/@soipo/creating-a-chatbot-with-laravel-inertia-echo-reverb-and-prism-59c598685a8e)  
18. boolfalse/laravel-reverb-react-chat: Build Real-Time Chat App with Laravel Reverb \- GitHub, [https://github.com/boolfalse/laravel-reverb-react-chat](https://github.com/boolfalse/laravel-reverb-react-chat)  
19. How to Build a Real-Time Chat App with Laravel Reverb \- freeCodeCamp, [https://www.freecodecamp.org/news/laravel-reverb-realtime-chat-app/](https://www.freecodecamp.org/news/laravel-reverb-realtime-chat-app/)  
20. Laravel Internal Live-Chat with Reverb and Livewire, [https://laraveldaily.com/project-examples/laravel-internal-live-chat-with-reverb-and-livewire](https://laraveldaily.com/project-examples/laravel-internal-live-chat-with-reverb-and-livewire)  
21. Nertiakit is a powerful and minimalistic starter kit for Laravel SaaS applications, built with Laravel, Inertia.js, ShadCN, and Tailwind CSS. It provides a fully functional authentication system, role-based access control, and a modern UI, making it easy to kickstart your next project without repetitive setup. · GitHub, [https://github.com/RyderAsKing/NertiaKit](https://github.com/RyderAsKing/NertiaKit)  
22. Adding Real Time Chat to Laravel Using Reverb & Vue, [https://laravel-news.com/index.php/laravel-real-time-chat](https://laravel-news.com/index.php/laravel-real-time-chat)  
23. Info Lomba Terbaru & Event Nasional | Media Partner Terpercaya Sejak 2015, [https://infolomba.id/](https://infolomba.id/)  
24. Build a Real-Time Chat with Laravel Reverb | by Abd Alrzaq Najieb | Medium, [https://medium.com/@jmalj6564/build-a-real-time-chat-with-laravel-reverb-abbbcbdedf44](https://medium.com/@jmalj6564/build-a-real-time-chat-with-laravel-reverb-abbbcbdedf44)  
25. Real-Time Chat Implementation with Laravel Reverb and Vue 3 | by Emre Ensar Çapcı, [https://medium.com/@emreensr/real-time-chat-implementation-with-laravel-reverb-and-vue-3-03a16cf593ef](https://medium.com/@emreensr/real-time-chat-implementation-with-laravel-reverb-and-vue-3-03a16cf593ef)  
26. Laravel Reverb | Laravel 13.x \- The clean stack for Artisans and agents, [https://laravel.com/docs/13.x/reverb](https://laravel.com/docs/13.x/reverb)  
27. Building Real-Time Collaborative Dashboards with Laravel Reverb and React, [https://smarttechdevs.in/blog/laravel-reverb-react-realtime-collaborative-saas](https://smarttechdevs.in/blog/laravel-reverb-react-realtime-collaborative-saas)  
28. Build Real-Time Notifications in Laravel \+ Inertia.js \+ React with Reverb \- DEV Community, [https://dev.to/balwant\_chaudhary/build-real-time-notifications-in-laravel-inertiajs-react-with-reverb-1oj9](https://dev.to/balwant_chaudhary/build-real-time-notifications-in-laravel-inertiajs-react-with-reverb-1oj9)  
29. Designing your app \- Base44 Support Documentation, [https://docs.base44.com/Building-your-app/Design](https://docs.base44.com/Building-your-app/Design)  
30. neobrutalist-web-designer | Skills M... \- LobeHub, [https://lobehub.com/skills/erichowens-some\_claude\_skills-neobrutalist-web-designer](https://lobehub.com/skills/erichowens-some_claude_skills-neobrutalist-web-designer)  
31. All you need to know about color palettes in web design \- Icons8, [https://icons8.com/blog/articles/all-you-need-to-know-about-color-palettes-in-web-design/](https://icons8.com/blog/articles/all-you-need-to-know-about-color-palettes-in-web-design/)  
32. What is the Brutalist Design Art Style? Origins, traits, and design inspiration \- Kittl Blog, [https://www.kittl.com/blogs/brutalist-design-art-stl/](https://www.kittl.com/blogs/brutalist-design-art-stl/)  
33. 2026 Design Trend Report: The Visual Language of the Agentic Era \- Lovart, [https://www.lovart.ai/blog/resource-2027-design-trend-report](https://www.lovart.ai/blog/resource-2027-design-trend-report)  
34. Dynamic Branding in NeoBrutalism: A Bold Identity for a Startup · Kristina Volchek, [https://kristi.digital/shots/dynamic-branding-neobrutalism-bold-identity-startup](https://kristi.digital/shots/dynamic-branding-neobrutalism-bold-identity-startup)  
35. Aesthetic Design Prompt Index | PDF | Page Layout | Typefaces, [https://www.scribd.com/document/972642895/The-Master-Prompt-Index-11-Styles](https://www.scribd.com/document/972642895/The-Master-Prompt-Index-11-Styles)  
36. Page 3 | Flat duotone color scheme Vectors \- Download Free High, [https://www.magnific.com/vectors/flat-duotone-color-scheme/3](https://www.magnific.com/vectors/flat-duotone-color-scheme/3)

[image1]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABMAAAAaCAYAAABVX2cEAAABDElEQVR4XmNgGAXDFzgD8S0gfg/E/4H4IKo0GJwF4n8MEPlvQDwbVRoTbAHiewwQDZZociCQDcTLgJgJXQIdsALxGSCOYIAYthZVGgymMEB8QRDYAPFkIGYG4vtA/BeIVVBUQCxjRxPDChqB2A/KzmWAuG4aQppBCoi3I/HxggNAzAtlcwHxGwZIQItAxeKBuAjKxgv4GCCGIYMmBojr6qD85UCsi5DGDfyBuB5NTBSIvwPxKyDmBuLLqNK4ASiWrNEFgWA6A8R1c4B4EZocTnAOiFnQBRkgsQmKVZCBMWhyWIEdEJ9GF0QCoPQGMkwCXQIZuAHxAwZEFnkCxPbICqDAnAGSlUbBKBhQAADIFjDhxd8YOAAAAABJRU5ErkJggg==>

[image2]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAsAAAAZCAYAAADnstS2AAAAvUlEQVR4XmNgGAXEAScgfgzE/4H4BJocViDKAFHciS6BDUQxQBQ7o0tgA4uB+CsQs6NLsAFxLRDvBuJNQNwNxK+BeBuyIhDgAuJDQLyTAWHKbAaIE/JhimBgMhD/AWIFJLEyBohiDSQxhiCoYDmSGAcQfwfiWUhiYBDAAFHshiQG8j1ILASIbYA4CyYhzgAxJRLKlwXiKwwQxXJAPB+I1aFyYOAOxEeAeB0QLwJibSDeA8T7gbgKSd0ooAMAAAv0IqpWzryXAAAAAElFTkSuQmCC>

[image3]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAaCAYAAAC+aNwHAAAA00lEQVR4Xu2SPwuBURTGnyQDm8nCrpTFQtltBoNR+Ra+hEkpxeATvKPJSlb5t7MoshBFPHW83Pf04h0N91e/5Tznnnu7HcDyfxTolB7onW7pgs7piq5pi8bdA59wIAPSqp6lJzpWdQ8huqcbHTxZQoZndOCSgzT0dUBi9EKvNKGyFw3IgJoOSB2SdXRgMoQ0JY1amFbojnZpxMg8ROkZ8lEDwxFt0+K71Z8S5PaeDoLShAyo6iAoM3pDgEXxIwW5faKDX+Qh6+qu7xHykrLZZLF84wGIty178gkRTgAAAABJRU5ErkJggg==>

[image4]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAA8AAAAaCAYAAABozQZiAAAA1klEQVR4Xu3RLw8BYRzA8Udg82fzAhSBFyDZzBuQSYrpisRsokRjI5imKDabItokJAVdFwSzGd9zT3juCXfPJeW+22d+9/yOY4QI+l8ZVPVD01a46IcmRfDARF+YVMQHZX3hVgtn3PGWsyWt3OPZFgf90KQ4XujrC5NKwv691qvvBsJ+ckJfmHTETs4hrBGW1zEM0cVI2H+poxvGcu6gpuyaaMv5hKSy+1XHFQvhfKNVA09skNd2rkWRRUXYH7x3rt2zvu5SzjnMlZ1nBczQwxQp5zrIV1+0+SM5BEof6QAAAABJRU5ErkJggg==>

[image5]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAaCAYAAAC+aNwHAAABAUlEQVR4Xu3SMUtCURTA8SNRS23WIhgNfgF3VweHcGiqRRyaGholR0EwclMwv0BDY9AQQUPQlnMmDi3tRkIgRP0v9+LTs7yjIDT0hx8e7r0PfFdF/vt7ZXCkFxfpBq960doGxrjUG9Zy+MGB3oirgj5G+A6zszdzxtQjnvWitU1McKE3rBXEv7/7XKqm+G+wpTes9fAU5gRusY59XKGGBto4C+fmekcnzFWUwnyOIl7E35PrC8kwTytjgGuJHnbtoB64tsXfVWp6wtAD8mF2f7K3aCu+NXxiV/zd3ONw7kRMWQzRRUv8qy7UiUSXu1TuJzzWi9ZO8YE7pNWeKXeBq+8XAaAqtoEgJaYAAAAASUVORK5CYII=>

[image6]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACsAAAAaCAYAAAAue6XIAAACCUlEQVR4Xu2WP0iVURjG30grRXAoNIfsJloE4uLgYAZCNbgEKSQObQ22CIGD0BDln2hQkAbRBoUIimhxMZJ0EaTI/lCDmihOQThEpFlgPg/vSx5e7r16ufFdh+8HPzznOe8n33e+851zRWJiYmJCrsK/8Cech5/gd8u+wc/wC9yEv2GDXpYbJuBdeDjIJkVv9kyQnYR/YEmQRcoxOOOyI3ADrrqcfPBBlNyAbS67IDqrD13Oh3jpskipgIdcdk/0ZrmWQ/Jglctyzlu4JbpE9jVHRW90zg8ENIq+kb2w19pTcEoy/CZaRJfAfT8Q8BGe92EKMqntgsM+TMeQ6M1e8gMGZ577cbjNpSKTWvICtvowHUvwFyzwA+AmnIUr8BFMWH4CjsBe+AzWpqmtEd1lui3vtDwf/oDHrb8r/NI5q9MuDxmAt4M+Z4+n21nrN8FX1va1ZaJ7d7n1+TBXrF0velKmhf/gHXwPv4reLI9VXsg88a9SeQMvBv0++DToN8MFa/vafvjc2gfgGiy1/i34wNr/hSLRk41/OaNci5yd9qBmEI5Zja99DTusrhouWs7Dhsc7H7TQxrPmnOxsaTw4DsIn8JplXLvLoq85WS1/g1wOssfwuuhvjnXR6+7YeNYUi65Hbmv8UEil6KvtgaPwtOXJautEH44ZZ5j7Kl8/GRfdtrjfxsTERM020tBomV19GrQAAAAASUVORK5CYII=>

[image7]: <data:image/png;base64,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>