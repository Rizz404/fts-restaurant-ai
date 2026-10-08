# Rencana Adaptasi: FTS Restaurant AI

Tanggal: 6 Oktober 2026.
Status: ARSIP. Seluruh langkah (A–D) selesai: rename (Plan 1) 6 Oktober 2026, kode domain (Plan 2–7) dan hardening/pengujian (Plan 8) 7 Oktober 2026. Master plan menjadi acuan utama.
Prosedur umum: [PANDUAN_TURUNAN_FTS_CORPORATE_AI.md](../../PANDUAN_TURUNAN_FTS_CORPORATE_AI.md). Dokumen ini hanya memuat keputusan dan isi khusus restoran.
Basis kode: `../../fts-corporate-ai` (tidak diubah).
Acuan domain: `../../fts-resturant-menu` (SaaS menu QR multi-restoran) dan `../../Restaurant-Research-AI-Agent`.

## 1. Tujuan dan keputusan

FTS Restaurant AI adalah website restoran yang **AI-first dan berbentuk presentasi**, dibangun dari base code FTS Corporate AI agar tidak mulai dari nol.

Keputusan yang sudah ditetapkan pemilik proyek:
- Pengguna utama: **tamu/pelanggan restoran** (publik, tanpa akun wajib). Staf memakai panel admin.
- **Satu restoran per deployment**; tanpa multi-tenant.
- UI: **deck presentasi dipandu AI di semua ruang**, dengan copilot chat. Ini memperluas presenter deck yang di corporate-ai hanya ada di Reception.
- Lingkup tahap ini: duplikat, rename identitas, dan tulis ulang `docs/` menjadi plan restoran. Kode domain dikerjakan per plan sesudahnya.

Di luar v1: pembayaran, integrasi POS, penetapan meja, WhatsApp/email otomatis, akun tamu, multi-restoran, impor menu dari `fts-resturant-menu` (dicatat sebagai kandidat integrasi).

## 2. Status pekerjaan

| Langkah | Status |
|---|---|
| A. Salinan ke `D:\kodingan\shared\fts-restaurant-ai` | **Selesai** (6 Oktober 2026; 420 file, ±1,9 MB; jumlah file per direktori cocok dengan sumber; tanpa `.env`, `.git`, `vendor`, `node_modules`, SQLite) |
| B. Rename identitas proyek | **Selesai** (6 Oktober 2026): paket, `APP_NAME`/brand default, `restaurant_ai*`/`restaurant_pgdata`, path dan nama proses di `deploy/`, domain contoh nginx, manifest dan logo, prefix localStorage, README (tanpa password demo), komentar gateway |
| C. Tulis ulang `docs/` | **Selesai** (6 Oktober 2026): master plan, README indeks, kontrak teknis, UI_FOUNDATION, Plan 1–8, VISUAL_ASSETS, OPERATIONS, UI_COMPONENTS, `evals/README.md`; korpus/hasil eval corporate dan tiga file plan lama dihapus |
| D. Rename identifier kode dan implementasi domain | Plan 1–8, nanti (status semua plan: Draft) |

Pemeriksaan statis yang sudah dijalankan: tidak ada `corporate_ai`/`corporate-ai`/"Corporate AI" tersisa di kode aplikasi, config, deploy, CI, dan tes (sisa "corporate" hanya identifier yang memang ditunda, tercatat di [Plan 1 §3](PLAN_01_SETUP.md#3-rename-identifier-warisan)); sisa nama lama di `docs/` hanya pada tabel delta, rujukan ke basis, dan daftar "dihapus"; 129 tautan relatif beserta anchor valid; setiap plan memuat metadata, "Basis kode yang diwarisi", kriteria, checklist, dan catatan hasil "Belum dikerjakan". Tes dan aplikasi tidak dijalankan.

## 3. Rename identitas (Langkah B)

| Dari | Ke |
|---|---|
| `fts/corporate-ai` | `fts/restaurant-ai` |
| "FTS Corporate AI" | "FTS Restaurant AI" (nama pendek tetap "FTS"; manifest "FTS Restaurant") |
| `corporate_ai`, `corporate_ai_test`, `corporate_pgdata` | `restaurant_ai`, `restaurant_ai_test`, `restaurant_pgdata` |
| `corporate-ai` (path, proses supervisor, backup) | `restaurant-ai` |
| localStorage `fts-chat:`, `fts-office:` | `fts-restaurant-chat:`, `fts-restaurant-stage:` |

Daftar file lengkap: bagian 5 panduan umum. Khusus proyek ini ikut diganti: `README.md` (hapus password demo `LocalDemo#2026` dan rujukan HR/IT), `app/Modules/Assistant/Gateway/CompatibleChatCompletionsGateway.php` (komentar "FTS Hotel").

**Ditunda ke Plan 1:** `config/corporate.php` → `config/restaurant.php`; command `corporate:*` → `restaurant:*`; `public/assets/corporate/` → `public/assets/restaurant/`; `vendor/pagination/corporate`; `BuildsCorporateData` → `BuildsRestaurantData`; prompt `corporate-v1/v2` → `restaurant-v1`; route `/office/*` dan `office.*` → ruang deck baru. Sampai itu selesai, aplikasi masih berperilaku seperti corporate.

## 4. Pemetaan konsep

| Corporate-ai | Restaurant-ai |
|---|---|
| Karyawan login, akun dibuat admin | **Tamu anonim** (session bertanda tangan; nama dan kontak diminta hanya saat konfirmasi); staf login |
| Virtual office 5 ruang | **Deck 6 slide-ruang**: Welcome, Menu, Chef's Picks, Reservasi, Pesanan, Booking Saya (+ slide Info: jam, lokasi, kebijakan) |
| Knowledge CMS (SOP, audience per departemen) | **Menu & Info CMS**: kategori, hidangan, harga, foto, tag diet, **alergen**, ketersediaan; revisi dan terjemahan id/en/ja; artikel info (jam buka, kebijakan, FAQ) |
| Retriever artikel | Retriever menu + info dengan filter: diet, alergen, harga, kategori |
| Tiket HR/IT (draft → konfirmasi → balasan staf) | **Request**: reservasi, pesanan (pre-order/takeaway), feedback/komplain, permintaan khusus; draft AI → konfirmasi tamu → konfirmasi/penolakan staf |
| `open → in_progress → resolved → closed` | `pending → confirmed → completed`, atau `declined`/`cancelled`; reservasi menambah `no_show` |
| `CORP-YYYYMMDD-XXXXXXXX` | `RST-YYYYMMDD-XXXXXXXX` |
| Tim hr/it | `front_of_house` (reservasi, feedback), `kitchen` (pesanan) |
| Role employee/admin/editor/staff | `guest` (implisit), `staff`, `editor` (menu/info), `admin` |
| Tool: search_knowledge, get_article, list_my_tickets, get_ticket, prepare_ticket | `search_menu`, `get_menu_item`, `search_info`, `check_availability`, `prepare_reservation`, `prepare_order`, `prepare_feedback`, `get_request_status`, **`present_slide`** |
| Prompt `corporate-v*` | `restaurant-v1` |
| Notifikasi in-app karyawan | Halaman status per kode referensi dengan polling; kanal luar di luar v1 |
| `Department` | dihapus (tidak ada padanan) |

Dipertahankan tanpa perubahan mekanisme: UI foundation, presenter/office-stage dan narrator, `ModelGateway` dan adapter OpenAI-compatible, `PromptComposer`/`AnswerValidator`, lease/recorder/recovery turn, tool registry/executor, `operation_receipts` dan `RunsIdempotently`, audit, API envelope, Operations, deploy dan CI.

## 5. Cakupan per plan

| Plan | Nama file baru | Isi utama |
|---|---|---|
| 1 | `PLAN_01_SETUP.md` | Setup dari base, rename tuntas (butir "ditunda"), tema warna hangat restoran, tanpa bootstrap Laravel baru |
| 2 | `PLAN_02_IDENTITY_AND_ACCESS.md` | Staf/editor/admin dengan login; sesi tamu anonim bertanda tangan; throttle; audit; tanpa departemen |
| 3 | `PLAN_03_PRESENTATION_EXPERIENCE.md` (dari `…_VIRTUAL_OFFICE`) | Deck engine dan model slide, navigasi antar slide, narasi, mobile-first portrait, aset visual restoran |
| 4 | `PLAN_04_MENU_AND_INFO_CMS.md` (dari `…_KNOWLEDGE_CMS`) | Skema menu/alergen/diet/ketersediaan, revisi, terjemahan, retrieval berfilter |
| 5 | `PLAN_05_CONVERSATION_AND_AI.md` | Chat tamu, grounding menu/info, guardrail publik, anggaran token, keputusan hosting model |
| 6 | `PLAN_06_RESERVATIONS_ORDERS_FEEDBACK.md` (dari `…_HR_IT_TICKETS`) | Skema request, kapasitas slot reservasi, panel staf, status, halaman status tamu |
| 7 | `PLAN_07_AI_TOOLS_AND_WORKFLOWS.md` | Registry tool baru, `present_slide`, alur rekomendasi → draft → konfirmasi, idempotensi |
| 8 | `PLAN_08_QUALITY_AND_RELEASE.md` | Katalog tes per plan (kelas bertema restoran), eval, hardening publik, CI, rollout |

Slide dan isinya (Plan 3, dipakai juga oleh `present_slide` di Plan 7):

| Slide | Isi | Tool terkait |
|---|---|---|
| Welcome | sapaan host AI, pilih bahasa, preferensi diet/jumlah tamu | `present_slide` |
| Menu | kartu hidangan per kategori, foto, harga, tag alergen | `search_menu`, `get_menu_item` |
| Chef's Picks | rekomendasi AI berdasarkan preferensi | `search_menu` |
| Reservasi | tanggal, jam, jumlah tamu, slot tersedia | `check_availability`, `prepare_reservation` |
| Pesanan | keranjang pre-order/takeaway | `prepare_order` |
| Booking Saya | status request via kode referensi | `get_request_status` |
| Info | jam buka, lokasi, kebijakan, FAQ | `search_info` |

## 6. Keputusan desain khusus restoran

1. **Mobile-first.** Tamu membuka lewat QR di meja; deck harus nyaman di layar portrait. Masuk `UI_FOUNDATION §7` dan Plan 3.
2. **Alergen dan diet.** Jawaban hanya dari data menu terstruktur. Bila data tidak ada, fallback "tanyakan ke staf"; AI tidak pernah menyatakan sebuah hidangan aman. Masuk Plan 4, 5, dan korpus eval.
3. **Reservasi.** Konfigurasi jam buka, interval slot, kapasitas per slot, ukuran grup maksimum, lead time. Tanpa penetapan meja di v1. `check_availability` hanya membaca; reservasi tetap menunggu konfirmasi staf.
4. **Pesanan tanpa pembayaran.** Request pesanan dikonfirmasi staf; tanpa payment gateway dan POS.
5. **PII tamu minimal.** Nama dan kontak opsional; retensi purge lebih pendek dari basis.
6. **Endpoint AI publik.** Hardening sesuai bagian 10 panduan umum, dirinci di Plan 5, 7, dan 8. `present_slide` hanya mengubah tampilan, tidak mengubah data.
7. **Hosting model AI.** LM Studio lokal tidak cocok untuk situs publik; adapter OpenAI-compatible tetap dipakai, endpoint produksi diputuskan di Plan 5/8 (keputusan terbuka).

## 7. Penulisan ulang docs (Langkah C)

| File | Tindakan |
|---|---|
| `000_MASTER_PLAN.md` | Tulis ulang: "Restaurant AI — Pengalaman Tamu Berbasis AI"; memuat tabel bagian 4 sebagai "Basis kode dan delta" |
| `README.md` | Indeks plan, kebijakan pengujian, instruksi AI; rujukan ke basis, panduan umum, dan `fts-resturant-menu` |
| `PLAN_TEMPLATE.md` | Tetap |
| `PLAN_01`–`PLAN_08` | Tulis ulang sesuai bagian 5; ganti nama file Plan 3, 4, 6; perbarui semua tautan; reset "Catatan hasil implementasi" |
| `TECHNICAL_CONTRACTS.md` | Guest scope, request/status, kontrak tool baru, `present_slide`, konfigurasi baseline |
| `UI_FOUNDATION.md` | §2 token tema restoran; §7 presenter diperluas ke semua ruang dan mobile |
| `UI_COMPONENTS.md` | Tetap; sesuaikan brand/token |
| `VISUAL_ASSETS.md` | Daftar aset dan prompt gambar bertema restoran (Welcome, Menu, Chef, Reservasi, Pesanan, Booking, karakter host) |
| `OPERATIONS.md` | Path/DB/command target restoran; command ditandai "target setelah Plan 1" |
| `evals/` | Buang korpus, artikel, hasil corporate; `README.md` baru yang mendesain korpus: menu, alergen, info, reservasi, pesanan, no_answer, forbidden, injection; id/en/ja; korpus aktual dibuat di Plan 8 |
| `RESTAURANT_ADAPTATION_PLAN.md` | Dokumen ini; setelah Langkah C selesai dipindah statusnya menjadi arsip dan master plan menjadi acuan utama |

Urutan menulis: master plan → `README.md` → `TECHNICAL_CONTRACTS` → `UI_FOUNDATION` → Plan 01–08 → `VISUAL_ASSETS` → `OPERATIONS` → `evals/`.

## 8. Verifikasi (statis)

Mengikuti bagian 11 panduan umum, ditambah:
- `Grep -i corporate` di `fts-restaurant-ai`: hanya identifier yang ditunda (tercatat di `PLAN_01`), tabel delta, dan rujukan ke basis.
- Grep `HR|IT Helpdesk|karyawan|SOP|CORP-|LocalDemo|Department` di `docs/` dan `README.md`: nol di luar tabel delta.
- `corporate_ai` tidak tersisa di compose, env example, CI, init SQL, dan test guard; `restaurant_ai` selaras di semuanya.
- Tautan relatif `.md` di `docs/` valid, termasuk tiga file plan yang diganti nama dan tautan ke panduan umum.

Tidak ada commit, push, atau `git init`. Tes dan aplikasi tidak dijalankan.

## 9. Keputusan yang diambil saat menulis docs (mohon ditinjau)

Selain keputusan pemilik di bagian 1, hal berikut saya tetapkan di docs sebagai usulan dan dapat diubah sebelum Plan 1 dikerjakan:

1. **Tamu bukan `User`:** sesi tamu lazy (`guest_sessions`, dibuat saat tulis pertama, bukan tiap page view); request tamu dibuka kembali lewat kode referensi + kontak, dengan limiter dan penguncian (Plan 2, 6).
2. **Kontak tamu tidak lewat AI:** tool `prepare_*` tidak menerima nama/telepon; kontak diisi di langkah review UI sehingga tidak masuk prompt/log/penyedia model (Plan 7).
3. **Keselamatan alergen:** flag `allergen_info_verified_at`; mengubah alergen/diet mengosongkan verifikasi; filter pengecualian alergen juga membuang hidangan belum terverifikasi; AI tidak pernah menyatakan "aman" (Plan 4, 5, 7, korpus eval).
4. **Menu tidak berrevisi immutable** (harga/ketersediaan berlaku seketika, diaudit), berbeda dari artikel info yang tetap berrevisi (Plan 4).
5. **Kapasitas reservasi** dihitung per slot dengan baris `reservation_slot_usage` dan row lock; kursi ditahan sejak `pending` (Plan 6).
6. **Model bernama `GuestRequest`** (bukan `Request`) agar tidak bentrok dengan kelas HTTP Laravel; modul `Support` → `Requests` (Plan 6).
7. **Anggaran AI publik:** 40 giliran/sesi/hari dan 2000 global/hari sebagai default, dengan fallback `ai_budget_exhausted` (Plan 5).
8. **Retensi lebih pendek dari basis:** percakapan 30 hari, request 180 hari setelah terminal, sesi tamu 30 hari tidak aktif (Plan 8).
9. **Migration corporate ditulis ulang** oleh plan pemiliknya (tanpa `ALTER`) karena belum ada database produksi; pengembang memakai `migrate:fresh` lokal (Plan 1 §4).
10. **CSP dan Alpine:** build standar Alpine memerlukan `'unsafe-eval'`; Plan 8 harus memutuskan migrasi ke `@alpinejs/csp` atau menerima `'unsafe-eval'` dengan alasan tercatat.
11. **`present_slide` tidak memindahkan deck otomatis**, hanya menawarkan tombol (aksesibilitas).
12. **Upload foto menu** disertakan di v1 (re-encode WebP dengan GD, tanpa EXIF); butuh ekstensi `gd` dan `storage:link` (Plan 4).
13. **Palet restoran** (terracotta/espresso/herb/saffron, danger crimson) adalah proposal; kontras diverifikasi di Plan 8.

## 10. Keputusan terbuka

- Penyedia dan endpoint model AI untuk produksi publik.
- Perlu tidaknya CAPTCHA pihak ketiga atau cukup honeypot dan rate limit.
- Apakah menu awal diimpor dari `fts-resturant-menu` atau diisi lewat CMS.
- Sumber aset foto hidangan dan karakter host.
