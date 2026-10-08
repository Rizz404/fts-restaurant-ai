# Plan N Nama tahap teknis

Gunakan template ini untuk `PLAN_NN_NAMA_TAHAP.md`. Isi keputusan nyata, bukan sekadar nama fitur, sebelum status Ready.

| Metadata | Nilai |
|---|---|
| ID | N |
| Status | Draft |
| Dependensi | Tautan plan yang harus selesai |
| Acuan | [Master](000_MASTER_PLAN.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Hasil implementasi | Belum dikerjakan |

## Hasil yang harus tersedia

Sebutkan perilaku yang dapat dipakai dan diverifikasi setelah tahap selesai, serta batas tahap.

## Basis kode yang diwarisi

Untuk proyek turunan: sebutkan komponen basis yang dipakai ulang, yang diubah, dan yang dihapus (path konkret). Hapus bagian ini bila plan tidak memakai kode warisan.

## Teknologi dan konfigurasi

Tetapkan paket/constraint, setting environment/config, alasan perubahan terhadap baseline, dan input deployment bila relevan. Untuk UI, rujuk [fondasi UI](UI_FOUNDATION.md), sebutkan layout/komponen yang dipakai dan extension yang diperlukan. Warna berasal dari theme.css. Jangan menyimpan secret.

## Data dan interface

Tetapkan model/migration, field/constraint/index yang diperlukan, actor/permission, route, request/response, dan kontrak antar modul. Referensikan kontrak bersama daripada mendefinisikan bentuk berbeda.

## Langkah pengerjaan

Susun urutan pekerjaan dengan nama module/action/controller/UI yang relevan, validasi, transaksi/idempotensi, recovery, dan kondisi gagal. Pilih keputusan penting sekarang agar AI implementer tidak perlu menebak.

## Kriteria implementasi dan verifikasi pengguna

Ikuti [kebijakan pengujian](README.md#kebijakan-pengujian): tahap implementasi tidak menulis/menjalankan tes, lint, atau CI. Tetapkan:
- Kriteria implementasi selesai (Implemented): komponen, route, dan perilaku yang harus tersedia.
- Checklist untuk pengguna: langkah konkret yang dicoba pengguna dan hal yang perlu diklarifikasi.
- Skenario tes otomatis/manual sukses-gagal, ditambahkan ke [katalog pengujian Plan8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan). Bedakan fake integration, model nyata, serta deployment.

## Catatan hasil implementasi

Belum dikerjakan. Saat mengimplementasikan, isi:
- Perubahan yang benar-benar dibuat.
- Perintah setup yang dijalankan (install, migrate, build).
- Checklist untuk pengguna.
- Penyimpangan keputusan beserta alasan.
- Pekerjaan yang tersisa atau prasyarat yang menghambat.

Perbarui status (Implemented, lalu Done setelah verifikasi Plan8) dan indeks hanya sesuai bukti implementasi.
