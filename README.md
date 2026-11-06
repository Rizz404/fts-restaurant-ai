# FTS Restaurant AI

Website restoran yang AI-first dan berbentuk presentasi untuk satu restoran: tamu (tanpa login) menjelajahi deck slide yang dipandu karakter AI, bertanya soal menu dan info, lalu meneruskan reservasi, pesanan, atau feedback ke staf lewat request yang dikonfirmasi tamu sendiri dengan tombol. Staf (admin, editor, staf front of house, staf dapur) bekerja di `/admin`. Rencana produk dan tahapan ada di [docs/](docs/README.md).

> **Status (7 Oktober 2026):** Plan 1–7 sudah diimplementasikan dan Plan 8 (pengujian, hardening, operasional, evaluasi) dikerjakan sampai batas yang bisa dilakukan tanpa kredensial PostgreSQL, provider AI nyata, dan server target. Lihat [catatan hasil Plan 8](docs/PLAN_08_QUALITY_AND_RELEASE.md#catatan-hasil-implementasi) untuk apa yang sudah diverifikasi dan apa yang belum.

Stack: Laravel 13 (PHP 8.4), PostgreSQL 17, Blade, Tailwind CSS 4, Alpine.js 3, Vite 8.

## Kebutuhan

- PHP 8.4+ dengan ekstensi mbstring, intl, openssl, pdo_pgsql (atau pdo_sqlite untuk iterasi lokal), **gd dengan dukungan WebP dan exif** (foto menu), fileinfo, xml/dom, ctype, curl, tokenizer
- Composer 2
- Node 22.12+ dan npm
- PostgreSQL 17 (Docker via `compose.yaml` atau instalasi native)

## Setup lokal

```bash
composer setup        # install, .env, APP_KEY, migrate, npm ci, build
php artisan storage:link   # foto menu disajikan lewat public/storage
composer dev          # artisan serve + queue:listen + vite
```

Atau langkah manual:

```bash
composer install
cp .env.example .env          # default: SQLite, tanpa password
php artisan key:generate
php artisan migrate
php artisan storage:link
npm ci
npm run build                 # atau npm run dev
php artisan serve
```

### Database

Pengembangan lokal memakai **SQLite** (default di `.env.example`): file `database/database.sqlite` dibuat otomatis oleh `php artisan migrate`, tanpa server dan tanpa password.

Target production dan CI memakai **PostgreSQL 17**. Untuk memakainya secara lokal, ubah di `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=restaurant_ai
DB_USERNAME=restaurant_ai
DB_PASSWORD=...
```

Dengan Docker (`docker compose up -d`, isi `DB_PASSWORD` di `.env` dulu), container membuat database `restaurant_ai` dan `restaurant_ai_test` untuk role `restaurant_ai`; tanpa Docker buat role dan kedua database yang sama di PostgreSQL 17 native. Kode ditulis untuk PostgreSQL: pengujian konkurensi (kunci baris, kursi terakhir) dan indeks unik parsial hanya terbukti di PostgreSQL, bukan di SQLite.

### Akun admin pertama dan data demo

```bash
php artisan db:seed                       # data referensi: role, tim staf, baris pengaturan (idempoten)
php artisan restaurant:create-admin --email=admin@example.com --name="Admin"
```

Password sementara dimasukkan lewat prompt tersembunyi (minimal 12 karakter) dan wajib diganti saat login pertama. Hanya di local/testing:

```bash
php artisan restaurant:demo-accounts                 # admin, editor, front of house, dapur (password acak ditampilkan sekali)
php artisan db:seed --class=LocalDemoSeeder         # akun demo + menu + info + contoh request (password lokal tetap, hanya untuk pratinjau)
php artisan db:seed --class=MenuDemoSeeder          # hanya menu sintetis [DEMO]
php artisan db:seed --class=InfoDemoSeeder          # hanya artikel info sintetis [DEMO]
```

Seeder demo menolak berjalan di environment lain dan datanya sintetis (bukan menu atau kebijakan restoran nyata).

### Asisten AI

Isi di `.env` (jangan commit nilainya):

```dotenv
ASSISTANT_ENABLED=true
LOCAL_LLM_BASE_URL=https://host-provider      # tanpa /v1; endpoint /v1/chat/completions ditambahkan aplikasi
LOCAL_LLM_API_KEY=...
LOCAL_LLM_MODEL=...
ASSISTANT_REASONING_EFFORT=none               # untuk thinking model; kosongkan bila tidak didukung
LOCAL_LLM_CA_BUNDLE=                           # opsional: file CA bila PHP tidak punya CA bundle (cURL error 60)
ASSISTANT_GUEST_DAILY_TURNS=40                # turn AI per sesi tamu per hari
ASSISTANT_DAILY_TURN_CAP=2000                 # turn AI global per hari
```

Tanpa provider (atau `ASSISTANT_ENABLED=false`), chat tetap tampil dengan fallback berisi hidangan/artikel terkait, dan jelajah menu serta form reservasi/pesanan/feedback tetap bekerja. Tool calling aktif secara default; set `ASSISTANT_TOOLS_ENABLED=false` untuk provider tanpa function calling. Asisten hanya menyiapkan draft; request dibuat ketika tamu menekan tombol konfirmasi. Asisten tidak pernah menyatakan alergen atau diet suatu hidangan yang belum diverifikasi staf.

## URL

| URL | Keterangan |
|---|---|
| `/` | Welcome: deck tujuh slide dengan preferensi tamu (diet, alergen, jumlah tamu) |
| `/menu`, `/menu/{slug}`, `/picks` | Menu, detail hidangan, Chef's Picks |
| `/info`, `/info/{slug}` | Informasi restoran (jam buka, lokasi, kebijakan, FAQ) |
| `/reservation`, `/order` | Wizard reservasi (3 langkah) dan pesanan bawa pulang / pre-order |
| `/bookings`, `/bookings/{reference}` | Request milik sesi ini, pencarian referensi + kontak, thread pesan, feedback dan permintaan khusus |
| `/?table=A3` | Nomor meja dari QR (tampilan saja, bukan identitas) |
| `/login`, `/password/change` | Login staf; ganti password (wajib untuk password sementara) |
| `/admin` | Panel staf (admin, editor, staf) |
| `/admin/menu`, `/admin/menu/categories` | CMS menu (admin/editor; staf hanya ketersediaan) |
| `/admin/info` | CMS info (admin/editor menulis, hanya admin menerbitkan) |
| `/admin/requests`, `/admin/requests/{id}` | Antrean request sesuai tim staf (front of house: reservasi, feedback, permintaan khusus; dapur: pesanan) |
| `/admin/users` | Akun staf (admin) |
| `/admin/settings` | Nama brand, teks sambutan, kontak, jam buka, tanggal tutup, kapasitas (admin) |
| `/admin/operations` | Metrik 7 hari, alert pilot, kesiapan (admin) |
| `/api/v1/*` | API JSON internal (envelope di [TECHNICAL_CONTRACTS](docs/TECHNICAL_CONTRACTS.md)); `/api/v1/admin/readiness` untuk admin |
| `/up` | Health check framework |
| `/dev/ui`, `/dev/admin`, `/dev/user`, `/dev/guest` | Galeri dan pratinjau template (hanya local/testing) |

## Scripts

| Perintah | Fungsi |
|---|---|
| `composer setup` | Setup awal non-destruktif |
| `composer dev` | Server, queue listener, dan Vite bersamaan |
| `npm run dev` / `npm run build` | Vite dev server / build produksi |
| `composer test` | PHPUnit (PostgreSQL `restaurant_ai_test` dari `.env.testing`) |
| `php vendor/bin/pint --test` | Cek gaya kode PHP |
| `npm run lint:ui` | Cek konvensi UI (token warna, Alpine tunggal) |
| `npm run test:ui` | Tes Node (API helper, keranjang, wizard, safe-card-renderer, lint UI) |
| `php artisan restaurant:recover-turns` | Pulihkan turn AI yang macet (terjadwal tiap menit) |
| `php artisan restaurant:purge-data [--dry-run]` | Retensi data (terjadwal 02:00 WIB) |
| `python docs/evals/build_corpus.py` lalu `php artisan restaurant:eval` | Bangun dan jalankan evaluasi model ([docs/evals](docs/evals/README.md)) |
| `python scripts/generate-stage-assets.py` | Bangun ulang ilustrasi panggung tamu (latar scene dan host) di `public/assets/stage` |
| `python scripts/generate-visual-assets.py` | Bangun ulang logo dan favicon |

### Pengujian

Salin `.env.testing.example` menjadi `.env.testing`, isi `DB_PASSWORD`, dan buat role `restaurant_ai` serta database `restaurant_ai_test` di PostgreSQL 17. Lalu `composer test`. Tes konkurensi (`--group=postgres`) hanya berjalan di PostgreSQL dan dilewati di SQLite. Iterasi cepat tanpa PostgreSQL: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test` (bukan pengganti run PostgreSQL). CI ada di `.github/workflows/ci.yml`.

### Operasional dan rilis

Runbook production (setup, scheduler, backup/restore termasuk foto menu, retensi, alert, insiden) ada di [docs/OPERATIONS.md](docs/OPERATIONS.md); contoh konfigurasi nginx, supervisor, PHP, env, dan skrip backup ada di [`deploy/`](deploy). Set `TRUSTED_PROXIES` bila ada reverse proxy/CDN agar pembatas laju memakai IP tamu yang benar.

## UI: layout, komponen, dan tema

- Layout: `x-layouts.guest` (login), `x-layouts.admin`, `x-layouts.user` (deck tamu) — semuanya menyusun `x-layouts.base`.
- Komponen: `x-ui.*`, `x-navigation.*`, `x-restaurant-stage`, `x-deck-chat`, `x-request-thread`. Panduan: [docs/UI_COMPONENTS.md](docs/UI_COMPONENTS.md).
- Tema: satu-satunya sumber warna/radius/shadow adalah `resources/css/theme.css` (spesifikasi di [docs/UI_FOUNDATION.md](docs/UI_FOUNDATION.md)).

Aturan dasar: pakai komponen yang ada; tambahkan token hanya di `theme.css`; variant komponen didaftarkan di `App\Support\Ui\ComponentStyles` sebagai class literal penuh; modul Alpine didaftarkan di `resources/js/ui/register.js` dan `Alpine.start()` hanya ada di `resources/js/app.js`; tidak ada `x-html`/`innerHTML` (CSP mengizinkan `unsafe-eval` untuk Alpine, kompensasinya adalah teks selalu lewat `x-text`).

## Struktur penting

```text
app/Http/Middleware/      AssignRequestId, SetLocale, CaptureTableLabel, EnsureGuestSession, RejectHoneypot, SecurityHeaders, …
app/Modules/Identity/     ActorContext, akun staf, tim, sesi tamu anonim (GuestSessions, SessionGrants)
app/Modules/Experience/   SceneCode, BuildScene/SceneData, SceneImages, RestaurantSettings, RestaurantJsonLd
app/Modules/Menu/         CMS menu, verifikasi alergen, MenuRetriever, MenuPhotos (WebP)
app/Modules/Knowledge/    CMS info (revisi), KnowledgeRetriever, VisibleRevisions
app/Modules/Assistant/    gateway chat completions, PromptComposer, AnswerValidator, AllergenClaimGuard, sepuluh tool
app/Modules/Conversation/ percakapan AI: AppendTurn, ExecuteTurn, history, lease, anggaran, recovery
app/Modules/Requests/     draft, konfirmasi idempoten, kapasitas kursi, status, pesan, notifikasi
app/Modules/Operations/   retensi data, metrik operasional, readiness, heartbeat
app/Support/Http/         ApiResponse, ApiException, ApiExceptionRenderer (envelope JSON /api/v1)
app/Support/Ui/           ComponentStyles, FieldState
config/restaurant.php     brand, timezone, locale, mata uang, retensi, slide/aset, reservasi, alergen/diet, keamanan
config/assistant.php      anggaran dan batas AI
routes/web.php            halaman; memuat routes/internal.php (/api/v1) dan routes/dev.php
lang/{id,en,ja}/          ui, deck, menu, info, requests, validation
resources/js/             app.js, ui/*, chat.js, request-wizard.js, request-thread.js, shared/*
docs/                     master plan, kontrak teknis, plan 1–8, evals, OPERATIONS
deploy/                   nginx, supervisor, php.ini, env produksi, backup/restore
```
