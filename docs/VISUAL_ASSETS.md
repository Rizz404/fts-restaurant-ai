# Daftar gambar visual FTS Restaurant AI

Status: daftar kebutuhan. Selama pengembangan seluruh gambar slide memakai **placeholder acak** (picsum.photos) agar fokus ada pada desain, animasi, dan transisi. Ilustrasi final dibuat di [Plan 8](PLAN_08_QUALITY_AND_RELEASE.md#aset-visual-final). Foto hidangan **bukan** bagian repositori: restoran mengunggahnya lewat CMS menu ([Plan 4](PLAN_04_MENU_AND_INFO_CMS.md)).

## Cara kerja placeholder

- `.env` lokal: `SCENE_IMAGE_SOURCE=placeholder`. URL dibangun oleh `App\Modules\Experience\Support\SceneImages` dengan seed tetap (`https://picsum.photos/seed/restaurant-{nama}/{lebar}/{tinggi}`), sehingga gambar tidak berubah antar request dan tidak memuat data pengguna. Foto acak tidak bertema makanan; ini keterbatasan yang diterima sampai aset final tersedia.
- Production selalu memaksa `local` (gate rilis: tidak ada gambar remote). Jangan mengubahnya.
- Gambar slide dipotong `object-fit: cover` dengan overlay gelap, dan foto karakter placeholder dipotong mengikuti bingkai arch, sehingga foto acak tetap rapi.

## Beralih ke gambar final

1. Taruh berkas di `public/assets/restaurant/{nama}.{ext}` sesuai tabel.
2. Di `.env`: `SCENE_IMAGE_SOURCE=local` dan `SCENE_IMAGE_EXTENSION=webp` (atau `png`/`svg`).
3. Tidak ada perubahan kode. Untuk menggeser titik fokus crop, atur `object-position` pada `.thumb img` atau `.canvas-head img`.

## Daftar gambar

| Nama berkas | Dipakai di | Ukuran | Format | Isi / arahan |
|---|---|---|---|---|
| `welcome.{ext}` | Thumbnail slide 01 di sorter dan latar sambutan | 1600×900 (16:9) | WebP/JPG, ≤ 300 KB | Suasana restoran yang hangat dan mengundang: interior atau meja tersaji dari kejauhan. Tampil kecil di thumbnail, komposisi bebas. |
| `menu.{ext}` | Thumbnail sorter + header kanvas Menu | 1600×900 | WebP/JPG | Flat-lay atau barisan hidangan khas dengan cahaya alami; tanpa teks. |
| `picks.{ext}` | Thumbnail sorter + header kanvas Chef's Picks | 1600×900 | WebP/JPG | Tangan chef menyajikan hidangan, atau satu hidangan unggulan dengan latar dapur yang kabur. |
| `reservation.{ext}` | Thumbnail sorter + header kanvas Reservasi | 1600×900 | WebP/JPG | Meja kosong tertata rapi dengan lilin/lampu hangat; nuansa menunggu tamu. |
| `order.{ext}` | Thumbnail sorter + header kanvas Pesanan | 1600×900 | WebP/JPG | Kemasan takeaway atau nampan hidangan siap diambil di konter. |
| `bookings.{ext}` | Thumbnail sorter + header kanvas Booking Saya | 1600×900 | WebP/JPG | Konter host dengan buku reservasi atau papan nama meja; nuansa rapi dan ramah. |
| `info.{ext}` | Thumbnail sorter + header kanvas Info | 1600×900 | WebP/JPG | Fasad atau pintu masuk restoran saat senja, papan nama tanpa teks terbaca. |
| `assistant.{ext}` | **Karakter host utama**: besar di arch pada slide Welcome dan presenter ruang; kecil sebagai avatar subtitle, bubble, dan header copilot; di panel showcase login staf | 1200×1600 (3:4) minimum | **PNG/WebP transparan** (final); foto persegi untuk placeholder | Setengah badan, menghadap penonton, senyum ramah, mengenakan seragam atau celemek restoran, satu tangan terbuka seperti mempersilakan. Wajah di 30% bagian atas (avatar dipotong dengan `object-position: top`). Kepala boleh menembus tepi atas arch. Tepi bawah dipotong lurus. |
| `category-{kode}.svg` | Ikon kategori menu sebagai fallback tanpa foto hidangan | vektor 128×128 | SVG | Satu ikon sederhana per kategori (mis. makanan utama, minuman, pencuci mulut); gaya konsisten dengan palet token. |
| `logo.svg` | Monogram merek | vektor | SVG | Dipakai referensi; tanda di UI berupa ikon percikan (sparkles) di atas gradient token, bukan berkas gambar. Dibuat ulang oleh `scripts/generate-visual-assets.py` dengan palet restoran. |
| `og-image.{ext}` | Pratinjau tautan (Open Graph) | 1200×630 | JPG/PNG | Cover Welcome + nama restoran; satu per locale bila teks disematkan (hindari teks di dalam gambar; nama ditampilkan oleh meta). |

Ikon aplikasi (`favicon.svg`, `favicon.ico`, `apple-touch-icon.png`, ikon 192/512) dibangun ulang bersama logo.

## Panduan komposisi (agar teks tetap terbaca)

- Header kanvas selalu mendapat overlay espresso gelap (kiri paling gelap) sehingga teks putih terbaca; hindari area putih murni di kiri. Thumbnail sorter juga digelapkan di bawah.
- Warna dominan: terracotta, krem hangat, herb green, dan sentuhan saffron agar selaras dengan token tema (`primary`, `herb`, `saffron`, `chili`). Hindari warna dingin yang bertabrakan dengan palet hangat.
- Satu gaya visual untuk ketujuh slide (fotografi hangat atau ilustrasi flat yang konsisten).
- Tidak ada teks atau logo di dalam gambar (teks UI diterjemahkan 3 bahasa).
- Hindari gambar hidangan yang menjanjikan sesuatu yang tidak ada di menu; ilustrasi harus generik. Foto hidangan sungguhan hanya dari CMS.

## Lisensi dan sumber

Catat sumber dan lisensi tiap berkas final (buatan sendiri, dibuat dengan alat generatif dengan lisensi yang jelas, atau stok berlisensi) di catatan hasil Plan 8. Jangan memakai gambar bermerek pihak lain atau gambar tanpa lisensi jelas.

## Contoh prompt pembuatan gambar

- Cover slide: "Warm inviting restaurant interior at golden hour, terracotta and cream tones, soft daylight, shallow depth of field, no people, no text, 16:9".
- Karakter: "Half-body portrait of a friendly restaurant host in a dark espresso apron, smiling, one hand open in a welcoming presenting gesture, facing camera, soft studio light, transparent background, clean illustration style".
- Ikon kategori: "Minimal flat icon of a rice bowl, single color #B4472B on transparent background, rounded line style, 128x128".
