# Fondasi UI Tailwind dan Alpine FTS Restaurant AI

Status: Ready sebagai spesifikasi dan sudah diimplementasikan: kit komponen dari basis, tema restoran ([Plan 1](PLAN_01_SETUP.md)), presenter deck ([Plan 3](PLAN_03_PRESENTATION_EXPERIENCE.md)), serta chip, stepper, dan wizard ([Plan 6](PLAN_06_RESERVATIONS_ORDERS_FEEDBACK.md)). Review visual di browser belum dilakukan (lihat catatan Plan 8).
Tanggal: 6 Oktober 2026. Dipakai oleh seluruh tahap.
Alpine.js sudah menjadi bagian stack basis; dokumen ini menetapkan nilai tema dan pengalaman restoran.

## 1. Layout dan pembagian tanggung jawab

Template dibuat internal dengan Blade, Tailwind, Alpine, dan Heroicons. Tidak mengambil template dashboard eksternal yang membawa sistem CSS/component kedua.

| Blade component | Tanggung jawab dan slot |
|---|---|
| x-layouts.base | html/head/body, lang, title, meta CSRF, satu Vite bundle, skip link, toast container; slot utama dan head tambahan |
| x-layouts.guest | Halaman login staf: form di kartu, panel showcase (karakter host + prompt nyata dari slide restoran) di layar lebar, brand + language switch, footer; title, heading, description, slot utama |
| x-layouts.admin | Sidebar, mobile drawer, topbar, breadcrumb, judul/actions, main content; title, navigation, breadcrumbs, actions, slot utama |
| x-layouts.user | Presenter tamu (bagian 7): slide sorter (tab bar bawah di mobile), top bar dengan command pill "Tanya AI", konten (deck/kanvas), panel copilot; title, mode, accent, scene, sceneLabel, avatar, stage, navigation, topbar, flash, slot utama, chat. Dipakai tamu tanpa login |

Semua layout selain base menyusun x-layouts.base. Masing-masing halaman memasang satu layout teratas, sehingga head/Vite/Alpine tidak terduplikasi. Admin dipakai juga editor/staf sesuai menu yang diotorisasi server; nama layout tidak menentukan role (layout user dipakai tamu anonim).

Frame template dan preview sintetis diwarisi dari basis. Plan 1 mengganti tema ke palet restoran. Plan 2 memasang login staf, sesi tamu, dan pembatasan akses. Plan 3 menyusun deck-stage (turunan office-stage basis) di dalam layout user, gambar slide (placeholder acak sampai Plan 8 menyediakan ilustrasi final), narrator, dan transisi. Layout user tidak diganti menjadi dashboard administratif.

Desktop admin: sidebar 16rem, topbar 4rem, konten utama berjarak konsisten. Pada lebar <1024 sidebar menjadi drawer; tombol pembuka mempunyai aria-expanded, drawer dapat ditutup dengan Escape/outside click dan focus kembali ke pemicu. User mengikuti mobile-first dengan slot stage yang dapat dikembangkan Plan 3. Breakpoint Tailwind bawaan dipertahankan.

Default tema light. Halaman admin memakai sidebar secondary dan panel surface; user serta guest memakai token sama. Semua menu bisnis yang route-nya belum tersedia ditampilkan sebagai preview nonaktif; jangan membuat endpoint domain kosong hanya untuk memenuhi navigation.

## 2. Sumber tunggal tema

Setelah implementasi, `resources/css/theme.css` menjadi sumber nilai visual. Dokumen ini adalah spesifikasi awalnya. Tema tidak diduplikasi ke config PHP, JavaScript, inline style, atau stylesheet per halaman.

`resources/css/app.css`:

```css
@import 'tailwindcss';
@import './theme.css';
@import './base.css';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';

@plugin '@tailwindcss/forms' {
    strategy: 'class';
}
```

theme.css mendefinisikan nilai dasar melalui `@theme static` agar seluruh token tersedia; alias on-primary/focus didefinisikan dalam blok `@theme inline` yang merujuk token dasar. Reset hanya namespace warna melalui `--color-*: initial`; spacing/breakpoint/utility layout bawaan tetap tersedia. Dengan demikian fitur memakai semantic color utility, bukan palette default red/blue/gray.

| Token warna | Nilai awal | Pemakaian |
|---|---|---|
| primary | #B4472B | CTA utama dan pilihan aktif (terracotta) |
| primary-hover | #963820 | Hover CTA utama |
| secondary | #2B1D16 | Slide sorter, sidebar admin, permukaan gelap (espresso) |
| secondary-hover | #3D2A20 | Hover di permukaan secondary |
| accent | #3F7D58 | Aksen/link bila diperlukan (herb green) |
| canvas | #FBF6EF | Latar aplikasi (krem hangat) |
| surface | #FFFFFF | Panel dan input |
| surface-muted | #F2E9DD | Area sekunder/readonly |
| text | #2B1D16 | Teks utama |
| muted | #6B5A4E | Teks pendukung |
| border | #E6D9C8 | Garis pemisah |
| success | #15803D | Indikasi sukses |
| success-soft | #F0FDF4 | Latar sukses |
| warning | #B45309 | Indikasi perhatian |
| warning-soft | #FFFBEB | Latar warning |
| danger | #9F1239 | Error/destructive action (crimson, dibedakan dari primary) |
| danger-hover | #881337 | Hover destructive action |
| danger-soft | #FFF1F2 | Latar error |
| info | #1D4ED8 | Informasi |
| info-soft | #EFF6FF | Latar informasi |
| on-primary | alias var(--color-surface) | Teks pada primary/secondary/danger |
| focus | alias var(--color-primary) | Focus ring |
| transparent | transparent | Permukaan transparan |
| current | currentColor | SVG mengikuti warna teks |
| overlay | rgb(43 29 22 / 0.55) | Backdrop modal |
| saffron | #E9A23B | Aksen slide, gradient AI (bukan untuk teks); menggantikan `iris` |
| herb | #5FA777 | Aksen slide, gradient AI (bukan untuk teks); menggantikan `mint` |
| chili | #E5624B | Aksen slide, gradient AI (bukan untuk teks); menggantikan `coral` |
| ink-soft | #D8C8B8 | Teks sekunder di atas permukaan secondary (sorter, sidebar) |
| online | #34D399 | Titik status online asisten |

Nilai di atas adalah proposal awal palet restoran (hangat, terinspirasi bahan makanan); kontras teks terhadap latar harus ≥ 4.5:1 dan diverifikasi di Plan 8. Danger sengaja dibuat crimson agar tidak tertukar dengan primary terracotta. Nama variable `--color-primary` menghasilkan utility `bg-primary`, `text-primary`, dan seterusnya. Literal tabel hanya ditulis ke theme.css. Contoh consumer: `bg-surface text-text border-border`, `bg-primary hover:bg-primary-hover text-on-primary`, `focus-visible:ring-focus`.

Token tambahan:
- `--font-sans`: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans JP", sans-serif. Font tidak harus diunduh dari jaringan.
- `--font-display`: "Segoe UI Variable Display", "SF Pro Display", system-ui, ... (sans, tracking rapat). Dipakai untuk judul hero/ruang, kartu login, dan halaman admin (utility `font-display`).
- `--font-mono`: ui-monospace, "SF Mono", "Cascadia Code", ... untuk label teknis kecil (eyebrow, kbd, breadcrumb, kode bahasa).
- `--radius-control: 0.75rem`, `--radius-card: 1.25rem`, `--radius-dialog: 1.25rem`.
- `--shadow-card`, `--shadow-overlay`: bayangan lembut berwarna espresso; `--shadow-glow`: cahaya hangat (primary) untuk elemen AI/aktif. `--accent` (properti terdaftar di theme.css) adalah aksen slide/ruang yang berganti.
- `--ease-cine: cubic-bezier(0.22, 0.8, 0.24, 1)` (utility `ease-cine`): kurva standar semua animasi masuk dan transisi scene.
- `--text-page-title: 1.5rem` dengan line-height 2rem; section-title 1.125rem/1.75rem; body 0.875rem/1.5rem; caption 0.75rem/1rem.
- `--spacing` tetap 0.25rem. Gunakan gap-2/3/4/6/8; panel p-4 mobile dan md:p-6 desktop.
- Motion normal maksimum200ms untuk kontrol; animasi masuk/keluar scene mengikuti bagian 7. Class motion-reduce dan `prefers-reduced-motion` menghilangkan animasi yang tidak perlu.

base.css hanya memuat base/layout rules lintas halaman: x-cloak, focus/skip link, reduced motion, view transition, default body, dan kelas dasar kontrol bila perlu. `presentation.css` memuat selector bertingkup untuk presenter AI (bagian 7). Warna di base.css merujuk token, bukan literal baru. Style scene khusus tetap dapat dibuat dengan selector terlingkup dan variable tema.

Ilustrasi final (dibuat Plan 8) di public/assets/restaurant dapat memakai literal fill karena stylesheet luar tidak diwarisi oleh gambar img. Warna visual utamanya mengikuti palette ini; ilustrasi tidak menjadi sumber warna UI baru. Asset library/vendor dan binary tidak masuk pemeriksaan source color.

## 3. Kontrak komponen reusable

Komponen anonymous Blade di `resources/views/components/ui`, layout di components/layouts, dan navigasi di components/navigation. Props tampilan tervalidasi; unknown variant tidak membuat nama class dinamis, melainkan fallback default atau error yang jelas pada development.

| Komponen | Props/slot utama | Perilaku |
|---|---|---|
| x-ui.button | variant primary/secondary/outline/ghost/danger, size sm/md/lg, type default button, loading, disabled; slot label | Native button, loading menonaktifkan submit ulang, aria-busy, spinner dan label tetap terbaca |
| x-ui.link-button | href server, variant/size sama button; label | Anchor untuk navigasi; memakai resolver style button yang sama |
| x-ui.icon | name whitelist, size sm/md/lg, label optional | Heroicons outline; dekoratif aria-hidden, icon bermakna mempunyai label |
| x-ui.field | name, id wajib, label, required, help, error optional; slot control | Label for=id, help/error ID stabil, menampilkan validation error dari error bag |
| x-ui.input | name, id, type default text, value, invalid, describedby, disabled | form-input, escaped value; password tidak menggunakan old value |
| x-ui.textarea | name, id, value, rows, invalid, describedby | form-textarea; isi plain text |
| x-ui.select | name, id, options value/label, value, invalid, describedby | Native form-select; pilihan diterjemahkan oleh pemanggil |
| x-ui.checkbox | name, id, checked, disabled; slot label | Native form-checkbox; keyboard dan label association |
| x-ui.card | padding sm/md, title optional; header/footer/slot utama | Surface/border/radius/shadow seragam |
| x-ui.badge | tone neutral/success/warning/danger/info; label | Warna status dan typography konsisten |
| x-ui.alert | tone info/success/warning/danger, dismissible; title/slot utama | role status; error mendesak boleh role alert secara eksplisit |
| x-ui.modal | name unik, title, size sm/md/lg; slot utama/footer | Alpine modal, x-trap.inert.noscroll, Escape, focus restore, x-cloak |
| x-ui.confirm-dialog | name, title, confirm-label, variant; slot deskripsi | Menyusun modal dan button; emit ui:confirmed, tidak melakukan write bisnis |
| x-ui.dropdown | align left/right; trigger dan slot item | Open/close Alpine, outside/Escape, keyboard focus; menu navigasi memakai links native |
| x-ui.tabs + x-ui.tab-panel | tabs id, items id/label; panel name/tabs-id | Tablist/tabpanel, aria-selected, ArrowLeft/Right/Home/End; state Alpine lokal |
| x-ui.table + row/cell | headers, caption optional; slot rows | Wrapper overflow-x, semantic table/th/td, style header/cell sama |
| x-ui.pagination | paginator Laravel | View pagination restaurant memakai link-button/tokens yang sama |
| x-ui.empty-state | title, description, icon optional; slot action | Empty state terjemahan, tidak memalsukan data |
| x-ui.spinner | size, label | Loading status accessible, dekoratif jika label induk ada |
| x-ui.skeleton | bentuk line/card, count | Placeholder tanpa fake angka; motion-reduce |
| x-ui.toast-container | tanpa data bisnis; store ui.toasts | Toast text-only, aria-live polite, dismiss, maximum5 toast |

Kontrak field explicit: caller memberikan id yang sama ke field/control; help dan error memakai suffix -help/-error. Control receives invalid dan describedby dari halaman/view presenter yang memakai helper bersama, bukan perhitungan berulang yang berbeda. Implementasikan helper `FieldState` untuk error/help IDs dari name/id/error bag. Di form berulang, pemanggil memberi id unik.

Semua komponen meneruskan attributes yang sah melalui attribute bag, termasuk autocomplete, wire-free Alpine x-model/x-on, maxlength, dan data attributes. Default input name/value tidak berasal dari model AI. Layout/control tidak mengandung query DB atau pemeriksaan izin bisnis terselubung.

Style button/size/tone berada dalam `App\Support\Ui\ComponentStyles` berisi mapping class literal penuh; button dan link-button berbagi mapping. Mapping status tiket/publikasi ke badge tone berada dalam presenter domain, bukan di komponen badge.

Jangan menyusun `bg-${tone}-500` atau class Tailwind dari data API; class variant harus statis agar masuk build. Warna literal tidak diizinkan melalui props seperti color="#...". Slot berisi markup Blade terpercaya; model/pesan/teks staf dirender escaped/x-text/textContent, tidak melalui x-html.

## 4. Alpine dan JavaScript bersama

Install Alpine melalui npm/Vite. Core, Focus, dan Collapse dipasang pada rilis3 yang sama dan di-lock. Daftarkan plugins, data providers, dan store sebelum satu Alpine.start() di resources/js/app.js. Jangan menambahkan CDN Alpine atau start kedua pada halaman/admin.

Struktur:
- resources/js/ui/register.js mendaftarkan data providers.
- resources/js/ui/admin-shell.js, user-shell.js, modal.js, dropdown.js, tabs.js, toasts.js.
- resources/js/shared/api.js untuk fetch/envelope/error/CSRF.
- resources/js/shared/dom.js untuk safe DOM dan lookup token/type yang diizinkan.
- Modul domain chat.js/office-stage.js dan lainnya ditambahkan pada plan masing-masing; tidak mengganti bootstrap Alpine.

Alpine menyimpan state tampilan: drawer, dropdown, tabs, modal, loading, toast. API client/action server tetap menentukan autentikasi, izin, revision, dan idempotensi. Alpine.store('ui') tidak memuat transcript, role sebagai otorisasi, PII, atau credential.

Event terstandar:
- ui:modal-open / ui:modal-close: detail {name}.
- ui:confirmed: detail {name}; pemanggil domain menyiapkan konfirmasi server yang sah.
- ui:toast: detail {message, tone}; tone whitelist dan message text, tidak HTML.
- ui:auth-required / ui:session-expired: dipancarkan API client untuk penanganan session.

`apiRequest(url, {method='GET', body, signal})` hanya same-origin API proyek. Membaca meta CSRF, credentials same-origin, Accept JSON; body JSON dan X-CSRF-TOKEN untuk mutation. Mengembalikan envelope atau melempar typed ApiError {status,code,message,fields,requestId}. Bedakan fetch network error/abort dari HTTP error; tidak auto-retry write. Gunakan factory createApiClient({fetchImpl, csrfTokenReader, origin}) untuk injection pada tes Node; apiRequest adalah instance default untuk browser, dengan interface pemanggil yang tetap sama.

Shared component menjaga focus management dan lifecycle; gunakan init()/destroy() untuk menghapus listener/timer saat elemen dilepas. State persistent hanya pilihan UI nonpersonal yang memang disetujui, tidak otomatis memakai Alpine Persist.

## 5. Gallery dan aturan reuse

Plan 1 menyediakan /dev/ui, /dev/admin, /dev/user, /dev/guest pada APP_ENV local/testing; production404. Halaman berisi data sintetis, tidak memerlukan user database, dan tidak melakukan write bisnis. Memakai kontrol nyata untuk drawer/modal/dropdown/tabs/toast; form demo hanya menampilkan hasil lokal sebagai preview.

Gallery menampilkan seluruh variant tombol, validation input/help/error, badge/alert tones, table pagination demo, empty/loading states, dan dialog. Penggantian token di theme.css harus mengubah semua preview admin/user/guest dengan build yang sama.

Plan berikutnya:
1. Pilih layout dan komponen yang sudah tersedia.
2. Tambahkan action/presenter/domain UI pada slot, bukan menyalin wrapper/header/sidebar.
3. Jika kebutuhan benar-benar baru, perluas komponen/props atau tambahkan komponen bersama yang bernama jelas; dokumentasikan di UI_COMPONENTS.md setelah implementasi.
4. Jika token baru diperlukan, tambahkan pada theme.css dan spesifikasi ini; jangan memberi warna lokal diam-diam.
5. Bila komponen lintas fitur berubah, cantumkan peninjauan gallery di checklist pengguna; pengujiannya dilakukan Plan8.

## 6. Pemeriksaan konsistensi UI

Plan 8 menjalankan scripts/check-ui-conventions.mjs dan npm script `lint:ui` (diwarisi dari basis; diperbarui bila token berganti nama), hanya memakai Node built-ins, untuk seluruh kode Plan 1–7 (lihat [kebijakan pengujian](README.md#kebijakan-pengujian)). Ini lint source, bukan bukti seluruh perilaku/accessibility benar.

Scan resources/views, resources/js, resources/css milik aplikasi. Kecualikan hanya theme.css, asset/vendor/generated dan fixtures tes yang disengaja. Pemeriksaan:
- Literal hex/rgb/hsl/oklch sebagai warna UI di luar theme.css.
- Palette Tailwind langsung seperti bg-blue-500, text-gray-700, border-red-500, bg-white/text-black; gunakan token semantik.
- Arbitrary color seperti bg-[#...] serta inline style color/background literal.
- CDN Alpine atau Alpine.start() di luar bootstrap resmi.
- Contoh class variant dinamis yang membangun nama warna dari payload.

Jangan melarang arbitrary dimensions atau background image yang sah. Jika static scan mempunyai false positive, gunakan pengecualian kecil dengan alasan dan path/line spesifik; bukan mengecualikan seluruh modul. Jangan scan docs karena dokumen ini sengaja memuat contoh warna terlarang.

Gallery review pada375x812,768x1024,1440x900: keyboard/focus, drawer, modal, outside click, Escape, validation errors, contrast body text minimal4.5:1, reduced motion, serta no flash x-cloak. Review ini dilakukan pengguna lewat checklist tiap plan dan diformalkan di Plan8; catat manual review terpisah dari tes backend.

## 7. Presenter AI: deck presentasi, animasi, transisi

Konsep: karakter AI (host restoran) memperkenalkan restoran seperti presentasi, dan **semua ruang adalah slide**. Welcome adalah deck bertuntun; Menu, Chef's Picks, Reservasi, Pesanan, Booking Saya, dan Info adalah slide kanvas yang dapat dimasuki. Warna hanya dari token (bagian 2); selector berada di `resources/css/presentation.css` dan dipakai oleh `x-layouts.user` (tamu tanpa login) dan `x-layouts.guest` (login staf). Komponen: `x-deck-stage`, `x-deck-chat`, `x-deck-character` (turunan `x-office-*` basis, lihat [Plan 3](PLAN_03_PRESENTATION_EXPERIENCE.md)).

**Karakter:** potret berbingkai lengkung (arch) dengan lingkaran cahaya beraksen, podium bercahaya, dan dua ikon melayang. Foto placeholder acak dipotong mengikuti arch; ilustrasi final berlatar transparan berdiri di depan arch dan boleh menembus tepi atasnya (deteksi lewat `data-placeholder`). Karakter bergerak pelan (melayang), "berbicara" saat teks diketik atau suara dibacakan, dan berpindah sisi kiri/kanan antar slide.

**Welcome = deck (desktop ≥1024px, 100dvh tanpa scroll):** kiri adalah slide sorter (thumbnail bernomor dengan gambar slide, seperti panel PowerPoint); tengah adalah stage; bawah adalah bar subtitle. Slide 01 sambutan (nama restoran, tagline, chip preferensi tamu: diet, alergen yang dihindari, jumlah orang; agenda berupa tautan ke slide; tombol Mulai tur); slide 02–07 satu per ruang (angka besar berkontur, judul, ringkasan, poin, tombol "Buka …" dan "Tanya AI soal ini"); slide terakhir "Tanya AI" berisi command bar dan chip prompt. Konten dan karakter bergantian sisi per slide. Chip preferensi disimpan di sisi klien dan dikirim sebagai `preferences` pada context turn AI (nilai whitelist; lihat [kontrak teknis](TECHNICAL_CONTRACTS.md#kontrak-turn-dan-model)); tidak berisi data pribadi.

**Bar subtitle:** narasi slide yang diketik (titik berpikir lalu huruf demi huruf, tombol Lewati), titik navigasi slide, tombol suara (Web Speech), putar otomatis (bar progres mengisi selama jeda), sebelumnya, dan berikutnya. Teks lengkap tersedia untuk pembaca layar lewat region `aria-live` yang berganti per slide.

**Navigasi deck:** tombol, thumbnail, titik, keyboard (← → PageUp PageDown Home End), geser horizontal di layar sentuh (hanya di Welcome, agar tidak mengganggu scroll vertikal di slide lain), dan hash `#slide-N`. Ctrl/Cmd+K atau `/` melompat ke slide "Tanya AI" dan memfokuskan command bar; di halaman lain membuka copilot. `present_slide` (Plan 7) hanya menawarkan tombol navigasi; deck tidak berpindah otomatis.

**Aksen per slide/ruang:** `--accent` (properti terdaftar, bisa dianimasikan) berganti saffron/herb/chili/primary; latar, arch, angka, titik navigasi, dan bar progres ikut berubah warna dengan transisi halus.

**Ruang (selain Welcome):** slide canvas. Kiri, presenter yang menempel (karakter kecil + bubble sambutan yang diketik, chip prompt kontekstual, tombol Lewati/Dengarkan); kanan, kanvas bernomor dengan header sampul dan isi (kartu hidangan, wizard reservasi/pesanan, daftar request, artikel info).

**Mobile-first (<768px):** tamu terutama memakai ponsel portrait lewat QR di meja, jadi layout ponsel adalah desain utama, bukan turunan desktop.
- Slide sorter menjadi tab bar bawah dengan tujuh item (ikon + label ringkas, target sentuh ≥ 44px, scroll-snap horizontal bila sempit), memperhitungkan `env(safe-area-inset-bottom)`.
- Presenter menjadi bubble ringkas yang dapat diciutkan; karakter kecil sebagai avatar.
- Konten satu kolom dengan scroll normal; tinggi memakai `100dvh`; tidak ada interaksi hover-only.
- Copilot membuka layar penuh; command bar dan input tetap terlihat saat keyboard virtual muncul (pantau `visualViewport`).
- Chip "Meja {label}" di header bila ada `table_label`.
- Rentang 768–1023px menyesuaikan dua panel tanpa memotong tombol.

**Copilot (chat):** panel dari kanan (layar penuh di mobile) dengan chip konteks slide aktif; dibuka dari command pill "Tanya AI" di top bar, chip prompt, atau `chat:ask`. Kartu hasil (hidangan, slot, draft, request) dirender `safe-card-renderer` dari template Blade. Escape menutup dan fokus kembali ke command pill.

**Animasi:** isi slide masuk bertahap dari arah navigasi (`slide-in-next` / `slide-in-prev`), slide lama keluar ke arah sebaliknya, karakter muncul dengan pop, gradient judul mengalir, cincin arch berdenyut. Semua memakai `--ease-cine` dan fill `backwards` agar tidak menyisakan transform.

**Transisi antar-halaman:** klik tautan ke route deck (`/`, `/menu`, `/picks`, `/reservation`, `/order`, `/bookings`, `/info`) menambah `.is-leaving` (konten memudar naik, 300 ms) lalu navigasi. Browser dengan cross-document View Transitions menjaga top bar, sorter, dan latar tetap diam sementara konten utama bergeser.

**Aksesibilitas dan motion:** `prefers-reduced-motion` mematikan animasi, ketikan, dan view transition (slide berganti seketika). Seluruh kontrol adalah elemen native yang dapat difokus; slide non-aktif disembunyikan (`display: none`). Panel copilot tertutup memakai `visibility`. Tanpa JavaScript, slide pertama dan tautan agenda tetap dapat dipakai, dan form reservasi/pesanan tetap dapat diakses sebagai halaman biasa.

**Gambar:** URL slide dan karakter dibangun `SceneImages`. Gambar slide dipakai di thumbnail sorter dan header kanvas; karakter dipakai besar di arch dan kecil sebagai avatar. Foto hidangan berasal dari CMS menu (bukan `SceneImages`). Daftar dan placeholder acak: [VISUAL_ASSETS.md](VISUAL_ASSETS.md).

## Referensi

[Theme Tailwind](https://tailwindcss.com/docs/theme) mendukung namespace warna dan semantic utilities. [Alpine installation](https://alpinejs.dev/essentials/installation) mendukung module bootstrap; [Focus](https://alpinejs.dev/plugins/focus) dan [Collapse](https://alpinejs.dev/plugins/collapse) menangani interaksi UI. [Blade components](https://laravel.com/framework/docs/13.x/blade#components) dan [Heroicons](https://github.com/driesvints/blade-heroicons) menjadi acuan rendering reusable.
