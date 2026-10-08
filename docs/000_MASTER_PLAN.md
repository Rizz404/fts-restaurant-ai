# Restaurant AI — Pengalaman Tamu Berbasis AI

Tanggal: 6 Oktober 2026.
Status: acuan perencanaan; Plan 1–7 sudah diimplementasikan dan Plan 8 dikerjakan sejauh dapat dilakukan tanpa PostgreSQL/provider/server target (7 Oktober 2026, lihat [docs/README.md](README.md)).
ID plan: 000.
Dependensi: tidak ada.

## 1. Tujuan dan batas versi pertama

Membangun website untuk **satu restoran**, dapat diakses publik tanpa login (tamu biasanya masuk lewat QR di meja atau tautan di media sosial). Pengalaman utamanya **AI-first dan berbentuk presentasi**: karakter AI memandu tamu melewati deck slide (Welcome, Menu, Chef's Picks, Reservasi, Pesanan, Booking Saya, Info), menjawab pertanyaan tentang menu dan restoran dengan sumber resmi, lalu meneruskan reservasi, pesanan, atau feedback ke staf lewat request yang dikonfirmasi tamu.

Proyek ini diturunkan dari [FTS Corporate AI](../../fts-corporate-ai) agar tidak mulai dari nol; prosedur umum penurunan ada di [panduan turunan](../../PANDUAN_TURUNAN_FTS_CORPORATE_AI.md). Pola gateway AI, presenter deck, dan alur draft → konfirmasi → staf mengikuti basis tersebut. Acuan domain menu ada di `../../fts-resturant-menu` (SaaS menu QR).

**Alur utama:** tamu membuka deck → memilih bahasa → menjelajah atau bertanya → membaca jawaban beserta sumber (kartu menu atau artikel info) → meminta reservasi/pesanan/feedback → meninjau dan mengonfirmasi draft → mendapat kode referensi → staf mengonfirmasi atau menolak → tamu melihat status di Booking Saya.

Keputusan yang sudah ditetapkan:

- Satu restoran per deployment; tanpa multi-tenant.
- Tamu tidak memiliki akun. Identitas tamu berupa sesi anonim; nama dan kontak hanya diminta saat konfirmasi request.
- Staf, editor, dan admin memiliki akun yang dibuat admin dan login di `/login`.
- Menu dan informasi restoran dikelola lewat CMS (admin/editor); ketersediaan menu dapat diubah seketika.
- Bahasa UI, konten resmi, dan respons AI: Indonesia, Inggris, Jepang.
- Koneksi AI mengikuti adapter kompatibel chat completions yang sama dengan FTS Hotel AI dan Corporate AI.
- Semua ruang berbentuk slide yang dinarasikan karakter AI, dengan copilot chat di samping.

Versi pertama tidak mencakup pembayaran, integrasi POS, penetapan nomor meja, notifikasi WhatsApp/email otomatis, akun tamu, program loyalitas, multi-restoran, atau upload dokumen. Impor menu dari `fts-resturant-menu` dicatat sebagai kandidat integrasi, bukan bagian v1.

**Catatan penamaan:** folder proyek `fts-restaurant-ai`; nama produk "FTS Restaurant AI". Identifier kode warisan `corporate` (konfigurasi, command, aset) sudah diganti pada Plan 1; yang tersisa (route `/office/*`, model/modul corporate) diganti plan pemiliknya.

## 2. Pengalaman pengguna dan fitur

### Deck presentasi

| Slide (ruang) | Fungsi |
|---|---|
| Welcome | Sambutan host AI, pilihan bahasa, preferensi tamu (diet, jumlah orang), agenda menuju slide lain |
| Menu | Jelajah kategori dan hidangan: foto, harga, deskripsi, tag diet, alergen, status tersedia |
| Chef's Picks | Pilihan chef dan rekomendasi AI berdasarkan preferensi tamu |
| Reservasi | Pilih tanggal, jam, jumlah tamu; lihat slot tersedia; ajukan reservasi |
| Pesanan | Keranjang pre-order atau takeaway; ajukan pesanan |
| Booking Saya | Daftar request tamu di sesi ini, pencarian lewat kode referensi, status, dan percakapan dengan staf |
| Info | Jam buka, lokasi, kebijakan, FAQ |

Gunakan presenter deck dua dimensi dari basis: slide sorter, bar subtitle yang diketik, karakter AI yang berpindah sisi, transisi antar halaman, dan copilot chat dari kanan. Implementasi memakai Blade dan JavaScript; tidak memerlukan mesin 3D. Selama pengembangan, gambar ruang dan karakter memakai placeholder; ilustrasi, foto, karakter, dan logo final dibuat di akhir (Plan 8).

**Mobile-first.** Tamu terutama memakai ponsel portrait. Pada layar kecil sorter menjadi tab bar bawah, presenter menjadi bubble ringkas, dan copilot membuka layar penuh. Parameter `?table=` pada tautan QR disimpan sebagai label meja (alfanumerik 1–20 karakter) dan hanya menjadi keterangan pada request, bukan otorisasi. Semua fungsi dapat dipakai lewat keyboard. Animasi mengikuti reduced motion; narasi memakai browser speech synthesis dan hanya berjalan setelah diaktifkan tamu.

### Menu dan AI

- AI memahami slide, hidangan, dan request yang sedang dibuka lewat konteks terstruktur yang diverifikasi server.
- Jawaban tentang menu memakai data menu terstruktur; jawaban tentang restoran memakai artikel info terpublikasi. Tampilkan kartu hidangan atau kartu sumber artikel.
- **Alergen dan diet:** AI hanya menyebut alergen atau tag diet dari data terstruktur yang sudah ditandai terverifikasi oleh staf. Bila data belum diverifikasi atau tidak ada, AI menjawab bahwa informasinya belum tersedia dan menyarankan bertanya ke staf. AI tidak pernah menyatakan suatu hidangan aman bagi alergi tertentu.
- Bila sumber tidak ditemukan atau pertanyaan ambigu, AI meminta klarifikasi atau menawarkan request ke staf.
- Bahasa respons mengikuti pilihan bahasa aktif; perubahan berlaku pada giliran berikutnya.
- Pencarian menu dan info memakai pencocokan Unicode deterministik (nama, alias/kata kunci, deskripsi) dalam tiga bahasa dengan filter kategori, tag diet, pengecualian alergen, dan harga maksimum; maksimal delapan hasil menu dan lima artikel info. Embedding tidak digunakan pada v1.
- AI dapat menggerakkan deck lewat tool `present_slide` (hanya membuka slide yang diizinkan server; tidak mengubah data).

### Request dan bantuan staf

Request memiliki jenis, status, pemohon (sesi tamu), kontak, tim penerima, staf penanggung jawab, serta riwayat pesan dan perubahan.

| Jenis | Isi | Tim penerima |
|---|---|---|
| Reservasi | tanggal, jam slot, jumlah tamu, catatan, preferensi tempat duduk (opsional) | front_of_house |
| Pesanan | takeaway atau pre-order dine-in, jam diminta, daftar hidangan dan jumlah, catatan | kitchen |
| Feedback | pujian, keluhan, atau saran; kode referensi terkait (opsional) | front_of_house |
| Permintaan khusus | mis. perayaan atau kebutuhan khusus; subjek dan isi | front_of_house |

- AI menyiapkan draft request; tamu meninjau ringkasan dan menekan tombol konfirmasi sebelum request dibuat. Form biasa dan alur AI memakai validasi serta action domain yang sama.
- Status: `pending → confirmed → completed`, atau `declined`, `cancelled`; reservasi dapat menjadi `no_show` dari `confirmed`. Staf menolak dengan alasan yang dilihat tamu.
- Tamu dapat membatalkan request yang belum terminal dan membalas lewat halaman status; staf menangani konfirmasi, penolakan, assignment, dan balasan.
- Tamu menemukan request lewat sesi yang sama atau lewat kode referensi ditambah kontak yang dimasukkan saat konfirmasi.
- Notifikasi in-app hanya untuk staf (request baru, pesan tamu, pembatalan). Tamu melihat status lewat halaman status dengan polling lima detik. WhatsApp dan email ditunda.

## 3. Fondasi teknis, data, dan hak akses

### Arsitektur

Gunakan **monolit modular Laravel 13**, PHP 8.4, PostgreSQL, Blade, JavaScript ES modules dengan Alpine.js 3, Tailwind CSS 4, dan Vite 8 (stack basis tidak berubah). Kunci dependensi melalui lockfile.

Pisahkan tanggung jawab menjadi Identity, Menu, Knowledge (artikel info), Conversation, Assistant, Requests, Experience, dan Operations. Controller menangani HTTP; Form Request menangani input; Policy menangani izin; action domain menangani publikasi, pembuatan request, dan transisi status. [Dokumentasi authorization](https://laravel.com/framework/docs/13.x/authorization).

Seluruh tahap mengikuti [fondasi UI](UI_FOUNDATION.md); Alpine menangani interaksi ringan, sementara action/Policy server tetap menangani aturan bisnis.

### Basis kode dan delta

| Corporate-ai | Restaurant-ai |
|---|---|
| Karyawan login, akun dibuat admin | Tamu anonim (sesi bertanda server); staf login |
| Virtual office 5 ruang (`/office/*`) | Deck 7 slide (`/`, `/menu`, `/picks`, `/reservation`, `/order`, `/bookings`, `/info`) |
| Knowledge CMS (SOP, audience per departemen) | Menu CMS (alergen, diet, ketersediaan) + Info CMS (artikel revisi, audience selalu umum) |
| Retriever artikel | `MenuRetriever` berfilter + `KnowledgeRetriever` untuk info |
| Tiket HR/IT, draft → konfirmasi → balasan staf | Request reservasi/pesanan/feedback/permintaan khusus, draft → konfirmasi → konfirmasi staf |
| `open → in_progress → resolved → closed` | `pending → confirmed → completed`, `declined`, `cancelled`, `no_show` |
| `CORP-YYYYMMDD-XXXXXXXX` | `RST-YYYYMMDD-XXXXXXXX` |
| Tim hr/it | Tim `front_of_house`, `kitchen` |
| Role employee/admin/editor/staff, departemen | Role staff/editor/admin; tanpa departemen |
| Tool: search_knowledge, get_article, list_my_tickets, get_ticket, prepare_ticket | search_menu, get_menu_item, search_info, check_availability, list_my_requests, get_request_status, prepare_reservation, prepare_order, prepare_feedback, present_slide |
| Prompt `corporate-v1/v2` | `restaurant-v1` |
| Notifikasi in-app karyawan | Notifikasi in-app staf; status tamu lewat halaman status |
| Retensi: percakapan 90 hari, tiket 365 hari | Percakapan 30 hari, request 180 hari setelah terminal, sesi tamu 30 hari tidak aktif |

Mekanisme yang dipertahankan: UI foundation, presenter/office-stage dan narrator, `ModelGateway` dan adapter OpenAI-compatible, `PromptComposer`/`AnswerValidator`, lease/recorder/recovery turn, tool registry/executor, `operation_receipts` dan `RunsIdempotently`, audit, envelope API, Operations, deploy dan CI.

### Data dan akses

Entitas utama: pengguna staf, peran, tim, sesi tamu, kategori menu, hidangan beserta terjemahan/alergen/tag diet, artikel info beserta revisi/terjemahannya, pengaturan restoran (jam buka, kapasitas slot, tanggal tutup), percakapan, pesan, AI turn, draft request, request, pesan request, event request, notifikasi staf, dan audit perubahan.

| Peran | Hak akses |
|---|---|
| Tamu (sesi anonim) | Menu dan info terpublikasi, percakapan sendiri, request sendiri atau yang dibuka lewat kode referensi + kontak |
| Staf | Antrean dan request tim yang ditugaskan (front_of_house dan/atau kitchen); dapat mengubah ketersediaan menu |
| Editor | Mengelola menu dan artikel info (draft, ketersediaan, verifikasi alergen) |
| Admin | Mengelola akun, peran, tim, pengaturan restoran (brand, jam buka, kapasitas), publikasi artikel info, dan operasi |

Satu akun dapat memiliki beberapa peran. Peran admin tidak otomatis memberi akses ke request atau percakapan tamu; akses request memerlukan penugasan sebagai staf tim terkait.

Menu: hidangan berstatus `draft → published → archived`; publikasi mensyaratkan nama dan deskripsi tiga bahasa, harga, kategori, dan keputusan eksplisit tentang alergen. Perubahan harga dan ketersediaan berlaku seketika dan dicatat audit (tidak ada revisi immutable per edit, berbeda dari artikel info). Artikel info memakai alur `draft → published → archived` dengan revisi immutable seperti basis.

Izin diperiksa pada halaman, endpoint, tool, dan pengambilan sumber. Riwayat atau sumber yang sudah tidak berlaku (hidangan diarsipkan, artikel diarsipkan) tidak diteruskan ke model pada giliran berikutnya.

### Kontrak chat dan tool

Endpoint JSON same-origin memakai session dan CSRF, dengan awalan `/api/v1`. Sediakan operasi untuk percakapan, pesan, history, pencarian menu dan info, ketersediaan slot, draft request, konfirmasi request, pesan request, dan status request.

Respons chat memuat `schema_version`, `turn_id`, pesan, referensi sumber, kartu UI, tindakan yang tersedia, dan error terstruktur. Kartu serta tujuan navigasi dibuat server dari tipe yang diizinkan; model tidak mengirim HTML atau URL bebas.

Tool awal:

- `search_menu`, `get_menu_item`, `search_info`, `check_availability`
- `list_my_requests`, `get_request_status`
- `prepare_reservation`, `prepare_order`, `prepare_feedback`
- `present_slide`

Pembuatan request final berlangsung melalui endpoint konfirmasi, bukan keputusan model. Draft terikat pada sesi tamu, payload, revisi, dan masa berlaku 15 menit. Konfirmasi memakai idempotency key agar retry mengembalikan request yang sama.

### Integrasi AI

Pertahankan setting `LOCAL_LLM_BASE_URL`, `LOCAL_LLM_API_KEY`, dan `LOCAL_LLM_MODEL`, dengan endpoint `/v1/chat/completions`. Nama setting mengikuti proyek basis dan tidak menentukan lokasi hosting model. **Endpoint produksi untuk situs publik belum ditentukan** (keputusan terbuka di Plan 5 dan 8); LM Studio lokal tidak cocok untuk beban publik.

- Jangan mengirim parameter khusus seperti `reasoning_effort` kecuali diaktifkan melalui konfigurasi kemampuan provider.
- Default budget satu giliran: 60 detik, maksimal enam putaran tool, satu giliran aktif per percakapan.
- Batasi history dan kutipan sumber yang dikirim; kontak tamu dan catatan internal staf tidak masuk prompt.
- Tangani timeout, respons malformed, dan tool tidak dikenal dengan fallback yang jelas.
- **Endpoint publik:** rate limit per sesi dan IP, batas giliran harian per sesi dan global, honeypot, batas panjang input, tool read-only secara default (hanya `prepare_*` menghasilkan draft, dan draft bukan request). Dirinci di Plan 5, 7, dan 8.
- Kegagalan AI tetap memungkinkan jelajah menu, pencarian info, form request, dan balasan staf.
- Render teks, sumber, dan pesan staf secara aman; jangan menyisipkan konten dinamis melalui HTML bebas.

## 4. Tahapan implementasi

Tahapan produk dipecah menjadi delapan plan teknis yang dapat dikerjakan AI satu per satu. Urutan, kontrak interface, dan statusnya tersedia di [indeks plan](README.md) dan [kontrak teknis bersama](TECHNICAL_CONTRACTS.md). Kode basis sudah ada di repositori ini; tiap plan memuat bagian "Basis kode yang diwarisi" yang menyebut apa yang dipakai ulang dan apa yang diubah.

1. **[Plan 1 Setup](PLAN_01_SETUP.md):** menuntaskan rename identifier warisan, tema restoran, dan konfigurasi dari basis; tanpa scaffold Laravel baru.
2. **[Plan 2 Akun dan akses](PLAN_02_IDENTITY_AND_ACCESS.md):** akun staf/editor/admin, tim, sesi tamu anonim, throttle.
3. **[Plan 3 Pengalaman presentasi](PLAN_03_PRESENTATION_EXPERIENCE.md):** deck engine, tujuh slide, navigasi, mobile-first, branding, dan tiga bahasa.
4. **[Plan 4 CMS menu dan info](PLAN_04_MENU_AND_INFO_CMS.md):** menu, alergen, diet, ketersediaan, artikel info, dan pencarian berfilter.
5. **[Plan 5 Percakapan dan AI](PLAN_05_CONVERSATION_AND_AI.md):** history, gateway model, jawaban bersumber, guardrail publik, lease, dan recovery.
6. **[Plan 6 Reservasi, pesanan, dan feedback](PLAN_06_RESERVATIONS_ORDERS_FEEDBACK.md):** draft/konfirmasi, kapasitas slot, antrean staf, pesan, status, dan idempotensi.
7. **[Plan 7 Tool AI dan workflow](PLAN_07_AI_TOOLS_AND_WORKFLOWS.md):** tool calling, `present_slide`, dan alur rekomendasi menuju request.
8. **[Plan 8 Kualitas dan rilis](PLAN_08_QUALITY_AND_RELEASE.md):** seluruh pengujian dan CI, aset visual final, evaluasi, hardening publik, retensi, monitoring, staging/deployment artifacts, dan pilot.

Status Ready pada dokumen berarti spesifikasi siap dikerjakan, bukan aplikasi restoran telah dibuat. Plan teknis tidak mengubah batas produk versi pertama.

Default deployment adalah Linux dengan Nginx/PHP-FPM, PostgreSQL, worker queue database, scheduler, dan HTTPS. Gunakan staging terpisah, `APP_DEBUG=false`, secret di environment deployment, backup harian, serta uji pemulihan.

Pantau latency/error AI, pemakaian anggaran giliran, pencarian tanpa hasil, request ganda, request pending terlama, dan penolakan limiter. Log operasional menggunakan ID korelasi tanpa menyimpan kredensial, kontak tamu, atau isi prompt mentah.

## 5. Pengujian dan kriteria selesai

Plan 1–7 fokus implementasi dan ditutup dengan checklist untuk pengguna; seluruh pengujian di bawah dibangun dan dijalankan pada Plan 8 (lihat [kebijakan pengujian](README.md#kebijakan-pengujian)).

- **Akses:** tamu tidak dapat membaca request atau percakapan sesi lain lewat halaman, API, tool, atau manipulasi ID/kode referensi; staf hanya melihat request timnya; area admin menolak tamu.
- **Menu:** draft dan arsip tidak tampil; hidangan tidak tersedia tidak dapat dipesan; alergen hanya disebut bila terverifikasi; filter pengecualian alergen tidak mengembalikan hidangan yang belum terverifikasi.
- **AI:** uji pertanyaan tanpa sumber, ambigu, prompt injection, permintaan klaim alergen, tool invalid, timeout, batas iterasi, dan batas anggaran menggunakan fake provider.
- **Request:** submit ulang atau dua konfirmasi bersamaan menghasilkan satu request; slot penuh ditolak; dua reservasi bersamaan tidak melebihi kapasitas; transisi status ilegal ditolak; balasan staf terbaca tamu.
- **Frontend:** uji teks berbahaya, perpindahan slide, konteks lama, pergantian bahasa, keyboard, mobile portrait, dan reduced motion.
- **Database:** jalankan pengujian transaksi dan concurrency pada PostgreSQL.
- **Evaluasi model nyata:** gunakan sedikitnya 30 skenario per bahasa. Target pilot: minimal 90% jawaban sesuai sumber; seluruh klaim alergen tanpa sumber, akses terlarang, dan tindakan tanpa konfirmasi harus ditolak.
- **CI:** lint/format check, tes backend, build frontend, dan audit dependensi harus lulus.

Default retensi: percakapan AI 30 hari sejak aktivitas terakhir, request 180 hari setelah terminal (kontak tamu ikut dihapus), sesi tamu 30 hari tidak aktif, dan audit 365 hari; seluruhnya dapat dikonfigurasi.

Produk dinyatakan siap ketika tamu dapat menyelesaikan alur **pertanyaan menu → kartu sumber → draft reservasi/pesanan → konfirmasi → konfirmasi staf → status terlihat**, dalam tiga bahasa, tanpa request ganda, tanpa klaim alergen tanpa sumber, dan dengan endpoint AI publik yang terlindungi.
