# Plan 3 Pengalaman presentasi, tujuh slide, dan tiga bahasa

> **Revisi tampilan tamu (6 November 2026):** deck slide, slide sorter, dan panel copilot diganti **panggung layar penuh** mengikuti pola cafe-ai ([PANDUAN_TURUNAN_CAFE_AI](../../PANDUAN_TURUNAN_CAFE_AI.md)): latar ilustrasi per ruang, host berpotongan karakter, balon bicara dengan narasi diketik, **dock ikon bulat** sebagai navigasi, isi halaman sebagai lembar kertas, dan chat melayang. `x-deck-stage` menjadi `x-restaurant-stage`, `deck-stage.js` menjadi `stage.js` (`Alpine.data('stageNarration')`), CSS panggung ada di `resources/css/stage/*`. Backend, rute `experience.*`, kontrak API, dan tiga bahasa tidak berubah. Rincian: [UI_FOUNDATION bagian 7](UI_FOUNDATION.md#7-panggung-tamu-ruang-ilustrasi-host-ai-dock-ikon). Bagian di bawah menjelaskan rancangan awal deck dan dipertahankan sebagai riwayat.

| Metadata | Nilai |
|---|---|
| ID | 3 |
| Status | Implemented |
| Dependensi | [Plan 2](PLAN_02_IDENTITY_AND_ACCESS.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Acuan | [Master](000_MASTER_PLAN.md), [UI_FOUNDATION §7](UI_FOUNDATION.md#7-presenter-ai-deck-presentasi-animasi-transisi) |
| Hasil implementasi | [Catatan hasil](#catatan-hasil-implementasi) (7 Oktober 2026) |

## Hasil yang harus tersedia

Tamu (tanpa login) dapat membuka tujuh slide-ruang: Welcome, Menu, Chef's Picks, Reservasi, Pesanan, Booking Saya, dan Info, sebagai deck presentasi yang dipandu karakter AI, dengan slide sorter, subtitle yang diketik, navigasi antar slide, dan slot copilot chat. Tampilan **mobile-first** karena tamu terutama memakai ponsel portrait lewat QR di meja.

Pada tahap ini isi domain (menu, slot, request) belum ada; slide menampilkan empty state dan CTA nonaktif sampai plan pemiliknya selesai (Menu/Picks/Info: Plan 4; chat: Plan 5; Reservasi/Pesanan/Booking Saya: Plan 6). Jangan menampilkan data contoh sebagai menu atau kebijakan resmi.

**Gambar placeholder:** gambar slide dan karakter memakai placeholder acak dulu (mekanisme basis). Ilustrasi, foto hidangan, karakter, dan logo final dikerjakan di [Plan 8](PLAN_08_QUALITY_AND_RELEASE.md#aset-visual-final). Plan 3 cukup memastikan aset dapat diganti tanpa mengubah view/JS.

## Basis kode yang diwarisi

Dipakai ulang: presenter deck dari Reception corporate-ai (`x-office-stage`: slide sorter, bar subtitle, karakter dalam arch, aksen per slide, copilot dari kanan), `office-stage.js` (`officeStage`: narrator, suara, transisi keluar, kendali deck, hash `#slide-N`, keyboard, swipe), `narrator.js`, `sound.js`, `shared/navigation.js`, `Modules/Experience` (`BuildScene`, `SceneData`, `SceneImages`, `SceneCode`), `presentation.css`, resolver gambar placeholder/local dengan fallback, formulir pengaturan admin dengan tab per bahasa.

Diubah:
- Penamaan: `x-office-stage` → `x-deck-stage`, `x-office-chat` → `x-deck-chat`, `x-office-character` → `x-deck-character`; `office-stage.js` → `deck-stage.js` (`Alpine.data('deckStage')`); view `resources/views/office/*` → `resources/views/experience/*`; folder aset `office`/`corporate` → `restaurant` (Plan 1).
- `SceneCode`: welcome, menu, picks, reservation, order, bookings, info (menggantikan reception/knowledge/hr/it/requests) beserta route name `experience.*`, ikon, dan label tiga bahasa.
- Route `/office/*` dihapus; semua halaman tamu publik (tanpa middleware auth/akun). Transisi keluar berlaku untuk link same-origin ke route deck.
- Layout: Welcome tetap berbentuk deck (sorter kiri, stage tengah, subtitle bawah); slide lain tetap berbentuk kanvas bernomor dengan presenter menempel. Perbaikan mobile di bagian "Mobile-first".
- `CompanySetting`/`company_settings` → `RestaurantSetting`/`restaurant_settings` (migration ditulis ulang), `Admin/CompanySettingsController` → `Admin/RestaurantSettingsController`, `ApplyCompanySettings` → `ApplyRestaurantSettings`.

Dihapus: ruang HR/IT/knowledge/requests warisan (`office/hr`, `office/it`, `_service-desk`), CTA tiket HR/IT.

## UI dan struktur

Buat/ubah:
- `resources/views/components/deck-stage.blade.php`: komposisi slide di dalam `x-layouts.user` (layout tamu; nama layout tidak menentukan role). Mengisi backdrop, karakter, navigasi, konten, copilot, header dan language switch.
- `resources/views/experience/{welcome,menu,picks,reservation,order,bookings,info}.blade.php`.
- `resources/js/deck-stage.js`, `narrator.js`, `sound.js`, `shared/navigation.js`.
- `App\Modules\Experience\Queries\BuildScene` dan DTO `SceneData` (scene code, locale, title, intro, backdrop path, character path, menu slide, topik saran, `table_label`).
- Resolver aset slide di `config/restaurant.php` (`scenes`): setiap slide dan karakter assistant memiliki URL gambar yang dibangun server oleh `SceneImages`; view/JS tidak menulis URL gambar sendiri.

### Slide dan isinya

| Slide | Route | Isi pada Plan 3 | Diisi plan |
|---|---|---|---|
| Welcome (deck) | `/` | Sambutan dari teks brand, pilihan bahasa, agenda berupa tautan ke slide, tombol Mulai tur, slide "Tanya AI" dengan command bar dan chip prompt, chip label meja bila ada | — |
| Menu | `/menu` | Empty state "menu segera tersedia", CTA nonaktif | Plan 4 |
| Chef's Picks | `/picks` | Empty state | Plan 4 (data), Plan 7 (rekomendasi AI) |
| Reservasi | `/reservation` | Empty state, CTA nonaktif | Plan 6 |
| Pesanan | `/order` | Empty state | Plan 6 |
| Booking Saya | `/bookings` | Empty state | Plan 6 |
| Info | `/info` | Tautan kontak (telepon, WhatsApp, Instagram, Maps) dari pengaturan restoran; empty state artikel | Plan 4 (artikel) |

Slot chat menunjukkan bahwa fitur tersedia setelah Plan 5; tidak membuat jawaban AI pura-pura. Link `/admin` dan `/login` staf tidak tampil di navigasi tamu; staf membuka `/login` langsung.

### Mobile-first

- Layar <768: slide sorter menjadi tab bar bawah (tujuh ikon + label ringkas, target sentuh ≥ 44px), presenter menjadi bubble ringkas di atas konten, karakter kecil sebagai avatar, copilot membuka layar penuh, konten scroll normal tanpa overflow horizontal.
- 768–1023: dua panel menyesuaikan tanpa memotong tombol.
- ≥1024: deck penuh 100dvh seperti basis.
- Geser horizontal berpindah slide di Welcome; di slide lain geser tidak mengganggu scroll vertikal. Semua fungsi dapat dipakai lewat keyboard; reduced motion mematikan animasi dan ketikan.
- Label meja: chip "Meja {label}" di header bila `table_label` ada di session; hanya tampilan.

### Gambar placeholder sementara

- Mode default `SCENE_IMAGE_SOURCE=placeholder`: URL gambar acak dari layanan placeholder (default `https://picsum.photos`) dengan **seed tetap** per slide/karakter (mis. `/seed/restaurant-menu/1600/900`) agar gambar tidak berganti setiap refresh. `SCENE_PLACEHOLDER_BASE_URL` dapat diganti.
- Mode `local`: file di `public/assets/restaurant/{scene}.{ext}` dan `assistant.{ext}`; dipakai Plan 8 tanpa mengubah view/JS.
- Gambar dekoratif (`alt=""`), lazy, dan bukan syarat fungsi: bila gagal dimuat tampilkan fallback latar `bg-surface-muted` dan ikon karakter. Foto acak bukan foto makanan; keterbatasan ini diterima sampai Plan 8.
- Placeholder remote hanya untuk pengembangan. Production selalu `local` (gate Plan 8). Tidak mengirim data pengguna atau query dalam URL gambar.

Warna UI memakai token semantik di theme.css. Agar teks terbaca di atas foto, panel konten memakai surface solid/semi-solid dan overlay `bg-overlay`.

## Route dan konten

GET `/` → welcome; `/menu`, `/picks`, `/reservation`, `/order`, `/bookings`, `/info` → slide masing-masing; semua publik. Named routes `experience.welcome`, `experience.menu`, `experience.picks`, `experience.reservation`, `experience.order`, `experience.bookings`, `experience.info`. Parameter `?lang=` dan `?table=` didukung di semua halaman. `/up` tetap health framework; `/dev/*` hanya local/testing.

Intro tiap slide (untuk subtitle dan narasi) berasal dari `lang/{id,en,ja}/ui.php`; intro Welcome memakai teks sambutan restoran bila diisi.

## Branding dan pengaturan restoran

`restaurant_settings` singleton id=1: brand_name, welcome_text jsonb (id/en/ja), contact_links jsonb, updated_by, timestamps. Kolom jam buka, tanggal tutup, dan kapasitas ditambahkan Plan 6 (migration yang sama bila dikerjakan berurutan; satu pemilik per kolom).

`contact_links` hanya berisi kunci whitelist:
- `phone`: digit, `+`, spasi, minimal 6 digit; ditampilkan sebagai tautan `tel:`.
- `whatsapp`: hanya digit (kode negara); server membangun `https://wa.me/{digits}`.
- `instagram`: handle `[A-Za-z0-9._]{1,30}`; server membangun `https://www.instagram.com/{handle}`.
- `maps_url`: `https://` dengan host di allowlist (`maps.google.com`, `www.google.com`, `maps.app.goo.gl`, `goo.gl`); selain itu ditolak.

GET/PATCH `/admin/settings` hanya admin, nama brand 1–160 dan welcome text tiap locale maksimum 1000, tanpa HTML, JavaScript, atau URL bebas. Upload logo/gambar tidak disediakan. Perubahan dicatat audit. `BuildScene` mengambil pengaturan satu kali per request dan tidak menulis.

## Langkah pengerjaan

1. Perbarui `SceneCode`, config scenes, `lang/*/ui.php` (label slide, intro, topik saran) untuk tiga bahasa.
2. Rename komponen/JS/view sesuai tabel "Diubah"; hapus ruang HR/IT/knowledge/requests.
3. Susun `deck-stage` pada layout tamu; daftarkan `deckStage` sebagai Alpine.data sebelum startup tunggal; gunakan escaped Blade/x-text/textContent untuk teks dinamis.
4. Implementasikan tab bar bawah mobile, bubble presenter ringkas, dan chip label meja; periksa deck di tiga ukuran viewport.
5. Pasang resolver gambar placeholder/local dengan fallback; header/menu tetap dapat dipakai saat gambar tidak tersedia.
6. Pertahankan transisi: klik link same-origin yang valid → animasi keluar maksimal 250ms → navigasi server. Tanpa JS link tetap berfungsi; hormati modifier click, target blank, dan reduced motion.
7. Scene mengeluarkan context JSON melalui escaping Blade yang aman; JS tidak membaca data arbitrer sebagai identitas.
8. Language switch mempertahankan route dan query (`table`).
9. Narrator dari intro slide (id-ID/en-US/ja-JP), tanpa autoplay; efek suara hanya setelah toggle. Preferensi suara di localStorage (`fts-restaurant-stage:sound`); tidak menyimpan pesan atau PII.
10. Migration `restaurant_settings` dan form admin dengan pembatasan admin.

## Kriteria implementasi dan verifikasi pengguna

Implementasi selesai (Implemented) bila tujuh route deck publik, `deck-stage`, `BuildScene`/`SceneData`, resolver gambar placeholder/local dengan fallback, transisi/narrator/sound, tab bar mobile, label tiga bahasa, dan pengaturan restoran tersedia, dan ruang corporate sudah hilang. Ilustrasi final tidak termasuk. Tes otomatis tidak ditulis/dijalankan; skenarionya ada di [katalog Plan 8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan).

Checklist untuk pengguna:
1. Tanpa login buka ketujuh slide; `/admin` mengalihkan ke `/login`.
2. Ganti bahasa id/en/ja pada setiap slide; route dan `?table=` tetap.
3. Periksa 375×812, 768×1024, 1440×900: tab bar bawah, bubble presenter, copilot layar penuh, tanpa overflow horizontal, teks terbaca di atas gambar.
4. Navigasi keyboard saja; aktifkan reduced motion di OS dan pastikan animasi berkurang.
5. Aktifkan narrator dan suara secara manual; tidak ada autoplay.
6. Blokir domain placeholder lalu muat ulang; fallback tampil dan semua fungsi tetap jalan; gambar tidak berganti saat refresh.
7. Ubah brand, sambutan, dan tautan kontak di `/admin/settings`; isi dengan teks HTML dan URL tidak sah; harus tampil sebagai teks/ditolak.
8. Klarifikasi: teks sambutan resmi, tautan kontak restoran, dan preferensi gaya ilustrasi untuk Plan 8.

## Catatan hasil implementasi

Dikerjakan 7 Oktober 2026. Status: **Implemented**; verifikasi otomatis dan review browser menunggu Plan 8.

**Perubahan yang dibuat**
- Modul `Experience`: enum `SceneCode` (welcome, menu, picks, reservation, order, bookings, info; route `experience.*`, ikon, aksen, label via `lang/*/deck.php`), `SceneData`, `BuildScene`, `ContactLinks`, `RestaurantSettings` (cache + `forget()`), `SceneImages`. Route tamu di `routes/web.php`; `/office/*` dan `OfficeController` dihapus.
- Pengaturan restoran: model `RestaurantSetting` (jam buka per hari, kapasitas slot, ukuran rombongan, lead time, jendela hari, kontak whitelist, tautan), `ApplyRestaurantSettings`, `RestaurantSettingsPolicy`, `Admin\RestaurantSettingsController` + `RestaurantSettingsRequest` (+ tanggal tutup) + view `/admin/settings`.
- Frontend: komponen `deck-stage`, `deck-character`, `guest-preferences`, `layouts/user` (chip nomor meja), `navigation/user-menu` (tab bar ponsel), JS `deck-stage.js` (kunci penyimpanan `fts-restaurant-stage:sound`), `shared/preferences.js` (chip diet/alergen/jumlah tamu, hanya kode whitelist di localStorage), `shared/navigation.js`; `lang/{id,en,ja}/deck.php`. Tab bar ponsel sekarang juga tampil pada mode deck dan dapat di-scroll; thumbnail slide tetap di sisi desktop (`resources/css/presentation.css`, blok "Restaurant").
- Aset: `scripts/generate-visual-assets.py` membuat SVG tujuh slide, asisten, dan logo; gambar placeholder tetap sementara sampai Plan 8.

**Perintah setup:** `npm run build` (lihat catatan Plan 8 untuk build akhir).

**Penyimpangan keputusan**
- Kunci penyimpanan JS memakai awalan `fts-restaurant-stage:` (sound, cart); preferensi tamu hanya berisi kode whitelist.
- Slide Welcome adalah satu-satunya deck bertumpuk; slide lain berupa kanvas bernomor dengan presenter di samping.

**Checklist untuk pengguna:** buka `/` di ponsel dan desktop; jelajahi tujuh slide dengan keyboard/geser; pilih chip preferensi; ubah bahasa id/en/ja; buka `/admin/settings` untuk mengubah jam buka dan kontak.
