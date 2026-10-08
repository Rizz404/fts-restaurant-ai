# Plan teknis FTS Restaurant AI

Folder ini berisi delapan plan teknis yang dapat dikerjakan AI satu per satu. Setiap plan menentukan dependensi, paket atau skema, endpoint, urutan pekerjaan, pengujian, dan syarat selesai. Status implementasi tiap plan tercantum pada tabel di bawah.

Baca [master plan](000_MASTER_PLAN.md) untuk tujuan produk dan [kontrak teknis bersama](TECHNICAL_CONTRACTS.md) untuk interface yang dipakai antar tahap. [Fondasi UI](UI_FOUNDATION.md) menetapkan tema, template, dan komponen reusable; [UI_COMPONENTS](UI_COMPONENTS.md) mendokumentasikan kit komponen yang diwarisi dari basis. [Daftar gambar visual](VISUAL_ASSETS.md) mencatat gambar slide/karakter yang dibutuhkan (sementara memakai placeholder acak).

## Basis kode dan acuan

Repositori ini diturunkan dari `../fts-corporate-ai` (Laravel 13, Plan 1–8 corporate sudah diimplementasikan di sana) melalui [panduan turunan](../../PANDUAN_TURUNAN_FTS_CORPORATE_AI.md). Konsekuensinya:

- Per 8 Oktober 2026 seluruh kode domain corporate (departemen, tiket HR/IT, `/office/*`, korpus eval, tes) sudah diganti oleh domain restoran; yang diwarisi dan tetap dipakai adalah kit UI, gateway AI, pola draft → konfirmasi → staf, dan infrastruktur operasi. Kode lama yang dibuang dipindahkan ke arsip lokal pengembang, bukan ke repositori.
- Setiap plan memuat bagian "Basis kode yang diwarisi" yang menyebut komponen yang dipakai ulang dan yang diubah. Periksa kondisi kode sebelum mengerjakan; jangan menimpa tanpa membaca.
- [Rencana adaptasi](RESTAURANT_ADAPTATION_PLAN.md) mencatat keputusan penurunan dan status rename; setelah Plan 1 selesai dokumen itu menjadi arsip.
- Acuan domain: `../fts-resturant-menu` (menu QR multi-restoran) dan `../Restaurant-Research-AI-Agent`. Acuan teknis tambahan: [analisis FTS Hotel AI](../../ANALISIS_FTS_HOTEL_AI.md).

## Urutan pengerjaan

| Plan | Dokumen | Hasil | Dependensi | Status |
|---|---|---|---|---|
| 1 | [Setup](PLAN_01_SETUP.md) | Rename identifier warisan, tema restoran, konfigurasi `restaurant`, bersihkan data demo corporate | Master + kontrak | Implemented |
| 2 | [Akun dan akses](PLAN_02_IDENTITY_AND_ACCESS.md) | Akun staf/editor/admin, tim, sesi tamu anonim, throttle, audit | 1 | Implemented |
| 3 | [Pengalaman presentasi](PLAN_03_PRESENTATION_EXPERIENCE.md) | Deck tujuh slide, navigasi, mobile-first, branding, tiga bahasa | 2 | Implemented |
| 4 | [CMS menu dan info](PLAN_04_MENU_AND_INFO_CMS.md) | Menu, alergen, diet, ketersediaan, artikel info, retrieval berfilter | 3 | Implemented |
| 5 | [Percakapan dan AI](PLAN_05_CONVERSATION_AND_AI.md) | Chat tamu tersimpan, jawaban bersumber, guardrail publik, lease, recovery | 4 | Implemented |
| 6 | [Reservasi, pesanan, dan feedback](PLAN_06_RESERVATIONS_ORDERS_FEEDBACK.md) | Draft/konfirmasi, kapasitas slot, antrean staf, pesan, status, idempotensi | 5 | Implemented |
| 7 | [Tool AI dan workflow](PLAN_07_AI_TOOLS_AND_WORKFLOWS.md) | Tool calling, `present_slide`, perjalanan rekomendasi sampai request | 6 | Implemented |
| 8 | [Kualitas dan rilis](PLAN_08_QUALITY_AND_RELEASE.md) | Seluruh pengujian Plan 1–7, CI/lint, aset visual final, evaluasi model, hardening publik, retensi, readiness, staging/deployment artifacts, dan pilot | 7 | Implemented (verifikasi PostgreSQL, provider, dan server menunggu) |

Master plan bernomor 000 merupakan acuan produk, bukan Plan 1. Penyebutan "Plan 1", "Plan 2", dan seterusnya mengacu ke tabel di atas.

## Kebijakan pengujian

Keputusan yang diwarisi dari basis: Plan 1–7 fokus implementasi (dijalankan demikian; satu-satunya pengecualian adalah pemeriksaan sintaks `php -l`, `route:list`, dan setup). Selama tahap tersebut implementer **tidak menulis maupun menjalankan** tes otomatis (PHPUnit, Node test), lint/format check (lint:ui, Pint --test), CI, atau review browser. Perintah yang diperlukan agar aplikasi dapat dipakai tetap boleh dijalankan: composer/npm install, key:generate, migrate, seed, dan npm run build.

Setiap plan ditutup dengan **checklist untuk pengguna**: langkah konkret yang perlu dicoba pengguna dan hal yang perlu diklarifikasi. Checklist ditulis pada catatan hasil implementasi plan dan disampaikan kepada pengguna di akhir pekerjaan.

Semua pengujian (infrastruktur tes, CI, lint UI, katalog skenario Plan 1–7, review UI, evaluasi model) dikerjakan di [Plan 8](PLAN_08_QUALITY_AND_RELEASE.md). Skenario tiap plan tercatat di [katalog pengujian Plan 8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan); tambahkan skenario baru di sana bila sebuah plan menemukan risiko yang perlu diuji. Tes yang diwarisi dari basis (`tests/`) masih bertema corporate dan diganti di Plan 8.

## Instruksi untuk AI saat mengerjakan satu plan

1. Baca README ini, master plan, kontrak bersama, fondasi UI, dokumen plan yang diminta, serta hasil implementasi dependensinya.
2. Periksa kondisi kode dan aturan proyek yang berlaku. Status dokumen tidak menggantikan bukti kode; pastikan prasyarat tahap benar-benar tersedia dan baca bagian "Basis kode yang diwarisi".
3. Implementasikan plan yang diminta dengan interface serta keputusan yang telah ditetapkan. Paket/version API diverifikasi terhadap versi installed/lockfile, bukan ditebak dari framework terbaru.
4. Pertahankan docs ketika mengubah kode warisan; jangan men-scaffold ulang Laravel.
5. Ikuti kebijakan pengujian di atas: Plan 1–7 tidak menulis/menjalankan tes; Plan 8 menjalankan seluruh pengujian dengan database testing terpisah, data sintetis, dan fake model.
6. Catat perubahan, perintah setup yang dijalankan, checklist untuk pengguna, dan kendala pada bagian hasil implementasi plan.
7. Perbarui status plan (Implemented untuk Plan 1–7, Done setelah verifikasi Plan 8) dan tabel ini berdasarkan hasil nyata. Sampaikan hasil, checklist pengguna, dan plan berikutnya kepada pengguna; tahap berikutnya dikerjakan saat diminta.

Jika prasyarat gagal atau interface perlu berubah, jelaskan penyebabnya, kerjakan bagian yang tidak bergantung pada kendala bila ada, dan catat dampak terhadap kontrak serta plan berikutnya. Perubahan keputusan produk harus diselaraskan pada master plan; jangan meninggalkan dua spesifikasi aktif yang bertentangan.

## Contoh instruksi pengguna

```text
Kerjakan Plan 1 di docs/PLAN_01_SETUP.md untuk proyek fts-restaurant-ai.
Baca docs/README.md, docs/000_MASTER_PLAN.md, docs/TECHNICAL_CONTRACTS.md,
dan docs/UI_FOUNDATION.md.
Implementasikan sampai kriteria implementasi terpenuhi tanpa menulis/menjalankan tes,
isi hasil implementasi beserta checklist untuk saya, lalu perbarui status.
Batasi pekerjaan pada Plan 1.
```

Untuk tahap selanjutnya ganti nomor dan nama file, misalnya:

```text
Kerjakan Plan 2 di docs/PLAN_02_IDENTITY_AND_ACCESS.md.
Pastikan hasil Plan 1 memenuhi prasyarat, lalu ikuti kontrak teknis bersama.
```

## Status dan penambahan plan

- Draft: spesifikasi belum lengkap atau belum dikerjakan.
- Ready: spesifikasi siap dikerjakan; implementasi belum selesai.
- In Progress: implementasi sedang berlangsung.
- Implemented: implementasi selesai dan checklist pengguna sudah disampaikan; verifikasi otomatis menunggu Plan 8.
- Blocked: ada prasyarat yang menghambat; penyebab dan input yang dibutuhkan dicatat.
- Done: implementasi selesai dan skenario tahap tersebut lulus verifikasi Plan 8 (untuk Plan 8: seluruh pemeriksaannya selesai).

Gunakan [template teknis](PLAN_TEMPLATE.md) untuk plan tambahan. Nama file `PLAN_NN_NAMA_TAHAP.md`; nomor berikutnya 09. Bila plan perlu disisipkan, tambahkan dependency eksplisit dan ubah urutan tabel tanpa merename file yang sudah dirujuk. Jangan menyimpan credential dalam dokumen.

Deployment hostname/database/provider credentials adalah input environment untuk eksekusi, bukan alasan mengganti stack. Rencana memakai PostgreSQL sesuai master plan; pola presenter deck, gateway chat completions, dan shared action mengikuti basis corporate-ai (yang mengikuti FTS Hotel AI).
