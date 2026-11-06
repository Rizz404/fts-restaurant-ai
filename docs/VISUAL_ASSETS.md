# Daftar gambar visual FTS Restaurant AI

Ilustrasi panggung tamu dibuat sendiri (vektor datar, tanpa aset pihak ketiga) oleh `python scripts/generate-stage-assets.py` dan disimpan di `public/assets/stage/`. URL dibangun oleh `App\Modules\Experience\Support\SceneImages`; view dan JS tidak menulis URL gambar sendiri. Foto hidangan **bukan** bagian repositori: restoran mengunggahnya lewat CMS menu ([Plan 4](PLAN_04_MENU_AND_INFO_CMS.md)).

## Berkas

| Berkas | Dipakai di | Ukuran | Isi |
|---|---|---|---|
| `scenes/{welcome,menu,picks,reservation,order,bookings,info}.svg` | Latar layar penuh tiap ruang (`object-fit: cover`, titik fokus per scene) | 1920×1080 | Ruang makan malam hari: jendela lengkung, lampu gantung, meja bertaplak, papan tulis menu, pass dapur, konter takeaway, meja host, fasad dengan toldo. Tanpa teks terbaca. |
| `character/host-greeting.svg` | Host di Welcome, Booking Saya, Info, dan login staf | 800×1200 | Potongan setengah badan, melambai. Tepi bawah dipotong lurus dan menempel ke bawah layar. |
| `character/host-menu.svg` | Host di Menu dan Pilihan Chef | 800×1200 | Memegang buku menu dan menunjuk. |
| `character/host-notepad.svg` | Host di Reservasi dan Pesanan | 800×1200 | Memegang buku catatan dan pena. |
| `character/host-avatar.svg` | Avatar bulat di balon bicara dan header chat | 256×256 | Wajah host. |
| `restaurant/logo.svg`, `og-image.png`, `icon-192.png`, `icon-512.png` | Merek, pratinjau tautan, manifest | | Dibangun ulang oleh `scripts/generate-visual-assets.py` (logo dan favicon). |

Posisi host (`anchor`), pose, dan titik fokus latar (`focus`, `focusMobile`) tiap scene diatur di `config/restaurant.php` (`scenes.stage`). Gambar dekoratif memakai `alt=""`.

## Mengganti dengan karya final

1. Taruh berkas dengan nama sama di `public/assets/stage/` (SVG, atau ubah `SCENE_IMAGE_EXTENSION=webp|png` di `.env` bila semua berkas satu format).
2. Latar 16:9 tanpa elemen penting di tepi (dipotong `cover`); sisakan sisi kiri lebih tenang untuk teks beranda dan sisi kanan untuk host.
3. Karakter: PNG/WebP/SVG transparan 800×1200, tepi bawah dipotong lurus, kepala di sepertiga atas.
4. Tidak ada teks atau logo di dalam gambar (teks UI diterjemahkan tiga bahasa). Ilustrasi generik; jangan menjanjikan hidangan yang tidak ada di menu.

## Mode placeholder (hanya lokal)

`SCENE_IMAGE_SOURCE=placeholder` memakai foto acak ber-seed dari picsum.photos (tetap per berkas, tanpa data pengguna). Panggung dirancang untuk ilustrasi lokal, jadi gunakan placeholder hanya untuk eksperimen. Production selalu memaksa `local`.

## Lisensi dan sumber

Semua berkas di `public/assets/stage/` dibuat sendiri oleh `scripts/generate-stage-assets.py`. Catat sumber dan lisensi karya pengganti di catatan rilis.
