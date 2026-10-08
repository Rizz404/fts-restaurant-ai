# Plan 1 Setup dari basis, rename identifier, dan tema restoran

| Metadata | Nilai |
|---|---|
| ID | 1 |
| Status | Implemented |
| Diperbarui | 6 Oktober 2026 |
| Dependensi | [Master](000_MASTER_PLAN.md), [kontrak teknis](TECHNICAL_CONTRACTS.md), [fondasi UI](UI_FOUNDATION.md) |
| Acuan | [Panduan turunan](../../PANDUAN_TURUNAN_FTS_CORPORATE_AI.md), [rencana adaptasi](RESTAURANT_ADAPTATION_PLAN.md) |
| Hasil implementasi | [Catatan hasil](#catatan-hasil-implementasi) (6 Oktober 2026) |

## 1. Hasil akhir tahap

Repositori ini sudah berisi aplikasi Laravel hasil salinan `fts-corporate-ai` dengan identitas proyek bernama restoran (paket, `APP_NAME`, nama database, path deploy). Plan 1 menuntaskan yang tertunda dan menyiapkan fondasi sebelum domain restoran dibuat:
- Semua identifier warisan `corporate` diganti (konfigurasi, command, aset, paginasi, helper tes, komponen JS).
- `config/restaurant.php` menggantikan `config/corporate.php` dengan kunci restoran.
- Tema restoran (palet hangat) di `theme.css` dan spesifikasi [UI_FOUNDATION §2](UI_FOUNDATION.md).
- Aplikasi tetap dapat boot, migrate, dan build; route dan halaman corporate yang belum diganti tetap berjalan sampai plan pemiliknya mengganti.
- Peta kepemilikan penghapusan modul corporate tercatat (bagian 4), supaya tidak ada modul menggantung.

Sesuai [kebijakan pengujian](README.md#kebijakan-pengujian), Plan 1 tidak menulis/menjalankan tes otomatis, lint, CI, atau review browser. Tes warisan di `tests/` masih bertema corporate dan diganti di Plan 8.

Plan 1 tidak membuat fitur restoran. Akun staf dan sesi tamu di Plan 2, deck di Plan 3, menu dan info di Plan 4, AI di Plan 5, request di Plan 6.

## 2. Basis kode yang diwarisi

Sudah tersedia (dipakai ulang tanpa perubahan mekanisme): `composer.json`/`package.json` dan lockfile; Laravel 13, Tailwind 4, Alpine 3, Vite 8; layout `base/guest/admin/user`; kit `x-ui.*` dan `x-navigation.*`; `ComponentStyles`, `FieldState`; `shared/api.js`, `shared/dom.js`; middleware `AssignRequestId`, `SetLocale`; envelope API; `compose.yaml` PostgreSQL 17; `deploy/*`; `.github/workflows/ci.yml`; `scripts/check-ui-conventions.mjs`; `scripts/generate-visual-assets.py`. Lihat [UI_COMPONENTS.md](UI_COMPONENTS.md) untuk kit komponen.

Sudah diganti (Langkah rename identitas, 6 Oktober 2026): nama paket `fts/restaurant-ai`, `APP_NAME`, brand default, `restaurant_ai`/`restaurant_ai_test`/`restaurant_pgdata`, path `restaurant-ai` di `deploy/`, prefix localStorage `fts-restaurant-chat:` dan `fts-restaurant-stage:`, manifest dan logo, README.

Belum diganti: bagian 3.

## 3. Rename identifier warisan

| Dari | Ke | Catatan |
|---|---|---|
| `config/corporate.php`, `config('corporate.*')` (±35 pemakai di view, app, tes) | `config/restaurant.php`, `config('restaurant.*')` | Kunci baru mengikuti [kontrak teknis](TECHNICAL_CONTRACTS.md#konfigurasi-baseline); kunci HR/IT/departemen dihapus |
| `corporate:create-admin`, `corporate:demo-accounts`, `corporate:recover-turns`, `corporate:purge-data`, `corporate:eval` | `restaurant:*` | Perbarui `routes/console.php`, `Heartbeat`, seeder, README, docs/OPERATIONS; isi `demo-accounts` diganti Plan 2, `eval` diganti Plan 8 |
| `public/assets/corporate/` | `public/assets/restaurant/` | `SceneImages`, `config`, `scripts/generate-visual-assets.py`; seed placeholder `corporate-{scene}` → `restaurant-{scene}` |
| `resources/views/vendor/pagination/corporate.blade.php` | `restaurant.blade.php` | Perbarui `x-ui.pagination` |
| `tests/Support/BuildsCorporateData.php` | `BuildsRestaurantData.php` | Perbarui `use` di tes; isi diganti Plan 8 |
| `Alpine.data('corporateChat')` | `restaurantChat` | `resources/js/chat.js`, `ui/register.js`, `x-office-chat` |
| `corporate-v1`, `corporate-v2` (ID prompt) | `restaurant-v1` | Isi prompt diganti Plan 5; ID baru dipakai sejak Plan 5 |
| Komentar/teks "Corporate", "kantor", "karyawan" di kode | Netral atau restoran | Hanya yang bukan bagian domain yang diganti plan lain |

Tidak diganti di Plan 1 (diganti plan pemilik): route `/office/*` dan `office.*`, `x-office-stage`/`x-office-chat`/`x-office-character`, `office-stage.js` (Plan 3); `CompanySetting` (Plan 3); `Department`, `SupportTeam`, `RoleCode` (Plan 2); `Knowledge*` bercorak korporat (Plan 4); `Ticket*` dan `Modules/Support` (Plan 6); `Assistant/Tools/*` (Plan 7).

Setelah rename, `Grep -i corporate` pada kode (di luar `docs/` dan `database/migrations` yang diganti plan masing-masing) harus bersih kecuali komentar yang menyebut basis.

## 4. Peta kepemilikan modul corporate

Repositori ini belum memiliki database produksi, sehingga migration corporate **ditulis ulang langsung oleh plan pemiliknya** (bukan migration `ALTER` tambahan). Pengembang menjalankan `php artisan migrate:fresh --seed` pada database lokal setelah tiap plan. Aplikasi harus tetap boot di akhir setiap plan.

| Migration/modul warisan | Pemilik | Tindakan |
|---|---|---|
| `…000001_create_departments_table`, `Department`, `Admin/DepartmentController`, `DepartmentPolicy` | Plan 2 | Dihapus |
| `…000002_add_identity_columns_to_users_table`, `…000003_create_roles_tables`, `…000004_create_support_teams_tables` | Plan 2 | Ditulis ulang: role admin/editor/staff, `staff_teams` (`front_of_house`, `kitchen`), `guest_sessions`; kolom `department_id` dihapus |
| `…000005_create_audit_events_table` | — | Dipertahankan |
| `…000006_create_company_settings_table`, `CompanySetting`, `Admin/CompanySettingsController` | Plan 3 | Menjadi `restaurant_settings` (brand, sambutan, jam buka, kapasitas) |
| `…000007_create_knowledge_tables`, `Modules/Knowledge` | Plan 4 | Ditulis ulang: info (tanpa departemen) + tabel menu |
| `…000008_create_conversation_tables`, `…000010_add_outcome_to_ai_turns_table`, `Modules/Conversation`, `Assistant` | Plan 5 | Ditulis ulang: pemilik `guest_session_id`, outcome digabung |
| `…000009_create_ticket_tables`, `Modules/Support`, `Ticket*` | Plan 6 | Ditulis ulang menjadi `requests`; `Modules/Support` menjadi `Modules/Requests` |
| `LocalDemoSeeder`, `DemoAccountsCommand`, `KnowledgeDemoSeeder`, `ReferenceSeeder` | Plan 2 (akun, tim), Plan 4 (menu/info), Plan 6 (request contoh) | Diganti data demo restoran; password demo tetap tidak didokumentasikan |
| Tes warisan (`tests/Feature/*`, `tests/Unit/*`) | Plan 8 | Diganti katalog restoran |

Selama transisi, route `/login`, `/admin/*`, dan `/office/*` warisan boleh tetap hidup; plan pemilik menghapus bagiannya bersamaan dengan penggantinya.

## 5. Konfigurasi restoran dan environment

`config/restaurant.php` (menggantikan `corporate.php`), kunci awal:
- `brand` (`name` "FTS Restaurant AI", `short_name`), `timezone` (Asia/Jakarta), `locales` (id/en/ja beserta nama native), `default_locale` (id), `currency` (IDR).
- `retention`: conversation_days 30, request_days 180 (setelah terminal), guest_session_days 30, audit_days 365, draft_grace_hours 24, receipt_days 365.
- `scenes`: sumber gambar (`SCENE_IMAGE_SOURCE`, `SCENE_PLACEHOLDER_BASE_URL`, `SCENE_IMAGE_EXTENSION`) dan daftar slide: welcome, menu, picks, reservation, order, bookings, info.
- `reservation`: slot_interval_minutes, slot_capacity, max_party_size, min_lead_minutes, max_advance_days (nilai awal di kontrak teknis).
- `allergens` (14 alergen utama + buckwheat) dan `dietary_tags` (halal, vegetarian, vegan, pork_free, alcohol_free).

`config/assistant.php` ditambah `guest.daily_turns` (40) dan `daily_turn_cap` (2000) dari `ASSISTANT_GUEST_DAILY_TURNS` dan `ASSISTANT_DAILY_TURN_CAP`.

`.env.example`: pertahankan isi hasil rename identitas; tambahkan `SESSION_LIFETIME=720`, `ASSISTANT_GUEST_DAILY_TURNS=40`, `ASSISTANT_DAILY_TURN_CAP=2000`. `deploy/env.production.example` diselaraskan. Jangan menyimpan secret; `LOCAL_LLM_*` tetap kosong.

## 6. Tema restoran

[UI_FOUNDATION §2](UI_FOUNDATION.md) menetapkan palet restoran (terracotta, espresso, herb, saffron). Implementasi:
1. Ganti nilai token di `resources/css/theme.css` sesuai tabel; nama token semantik (`primary`, `secondary`, `canvas`, …) tetap. Tiga token aksen slide diganti nama: `iris` → `saffron`, `mint` → `herb`, `coral` → `chili`; perbarui `presentation.css`, view, dan `ComponentStyles` yang memakainya.
2. Perbarui `scripts/generate-visual-assets.py` agar logo dan ilustrasi sementara memakai palet baru (aset final di Plan 8).
3. Tidak menambah palet Tailwind default atau literal warna di luar `theme.css`.

Nilai awal adalah proposal; kontras teks terhadap latar harus ≥ 4.5:1 dan diverifikasi di Plan 8.

## 7. Kriteria implementasi dan verifikasi pengguna

Langkah setup yang boleh dijalankan implementer agar aplikasi dapat dipakai (bukan tes): `composer install`, `npm install`, `key:generate`, `migrate:fresh`, `npm run build`.

Implementasi Plan 1 dianggap selesai (Implemented) bila:
- Seluruh identifier pada bagian 3 sudah diganti dan tidak ada rujukan tersisa ke nama lama pada kode aplikasi.
- `config/restaurant.php`, kunci assistant tambahan, dan `.env.example` tersedia.
- Tema restoran aktif di `theme.css` dan tiga layout (`/dev/ui`, `/dev/admin`, `/dev/user`, `/dev/guest`).
- Peta kepemilikan bagian 4 tercatat di catatan hasil dengan keadaan nyata repositori.

Checklist untuk pengguna:
1. `composer setup` atau `php artisan migrate:fresh` + `npm run build` berhasil; `php artisan serve` membuka `/up` dan halaman login.
2. Buka `/dev/ui`, `/dev/admin`, `/dev/user`, `/dev/guest`; palet restoran tampil konsisten, ganti bahasa id/en/ja.
3. `php artisan list` menampilkan `restaurant:*` dan tidak ada `corporate:*`.
4. Jalankan pencarian teks "corporate" pada kode aplikasi; hasilnya hanya komentar yang menyebut basis.
5. Klarifikasi: nama brand dan nama pendek, preferensi palet (terracotta/espresso atau lain), zona waktu, dan mata uang.

Skenario tes (smoke, preview, kontrak komponen, envelope, API helper, lint UI, CI) tercatat di [Plan 8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan).

## 8. Referensi dan catatan implementasi

[Instalasi Laravel](https://laravel.com/framework/docs/13.x/installation), [Tailwind theme](https://tailwindcss.com/docs/theme), [Alpine module setup](https://alpinejs.dev/essentials/installation), [Blade components](https://laravel.com/framework/docs/13.x/blade#components).

### Catatan hasil implementasi

Dikerjakan 6 Oktober 2026. Status: **Implemented** (verifikasi otomatis menunggu Plan 8). Satu langkah setup tertunda: `migrate:fresh` (lihat "Kendala").

**Perubahan yang dibuat**
- `config/corporate.php` → `config/restaurant.php` dengan kunci §5: `brand`, `timezone`, `locales`/`locale_names`/`default_locale`, `currency` (IDR), `retention` (`conversation_days` 30, `request_days` 180, `guest_session_days` 30, `audit_days` 365, `draft_grace_hours` 24, `receipt_days` 365), `scenes` (+ `slides`: welcome, menu, picks, reservation, order, bookings, info), `reservation`, `allergens` (14 + buckwheat), `dietary_tags`, `operations`. Kunci HR/IT dihapus. Semua `config('corporate.*')` di app, view, route, dan tes diganti `config('restaurant.*')`. Variabel env retensi dinamai ulang: `RETENTION_CONVERSATION_DAYS`, `RETENTION_REQUEST_DAYS`, `RETENTION_GUEST_SESSION_DAYS`, `RETENTION_AUDIT_DAYS`, `RETENTION_DRAFT_GRACE_HOURS`, `RETENTION_RECEIPT_DAYS`; `PurgeExpiredData` memakai `conversation_days`/`request_days` (tabel tiket basis masih dipurge dengan `request_days` sampai Plan 6).
- `config/assistant.php`: `guest.daily_turns` (`ASSISTANT_GUEST_DAILY_TURNS`, 40) dan `daily_turn_cap` (`ASSISTANT_DAILY_TURN_CAP`, 2000); `prompt_version` menjadi `restaurant-v1`.
- `.env.example` dan `deploy/env.production.example`: `SESSION_LIFETIME=720`, kunci anggaran AI, kunci retensi baru. Tidak ada secret; `LOCAL_LLM_*` tetap kosong.
- Command `corporate:*` → `restaurant:create-admin|demo-accounts|recover-turns|purge-data|eval` (signature, `routes/console.php`, komentar, seeder, tes, README, OPERATIONS).
- `public/assets/corporate/` → `public/assets/restaurant/`; `SceneImages` (jalur aset dan seed placeholder `restaurant-{scene}`), `site.webmanifest`, `scripts/generate-visual-assets.py`.
- `vendor/pagination/corporate.blade.php` → `restaurant.blade.php` (`x-ui.pagination`); `tests/Support/BuildsCorporateData.php` → `BuildsRestaurantData.php` (`use` di tes diperbarui); `Alpine.data('corporateChat')` → `restaurantChat` (`chat.js`, `ui/register.js`, `x-office-chat`).
- `PromptComposer`: konstanta `VERSION = 'restaurant-v1'`; `VERSION_TOOLS` dihapus (tidak dipakai). Teks prompt tetap warisan sampai Plan 5.
- Tema: `theme.css` memakai palet [UI_FOUNDATION §2](UI_FOUNDATION.md) (termasuk bayangan, overlay, `--accent` awal); token aksen `iris`/`mint`/`coral` → `saffron`/`herb`/`chili` di `presentation.css`, `ComponentStyles`, `office-stage.js`, dan view yang memakainya.
- Aset: generator SVG memakai palet restoran dan dijalankan ulang (SVG ruang masih gambar kantor sementara; diganti Plan 8). Ikon PWA `icon-192.png`/`icon-512.png` dirender ulang dengan warna baru lewat skrip sekali pakai (Pillow) yang tidak disimpan di repositori; `favicon.svg` dan `site.webmanifest` ikut diperbarui.
- Docs: README proyek, `docs/OPERATIONS.md`, `UI_COMPONENTS.md`, `UI_FOUNDATION.md`, master plan, Plan 5, README indeks.

**Perintah setup yang dijalankan:** `composer install` (sesuai `composer.lock`, versi tidak berubah), `npm ci` (tanpa perubahan lockfile), `cp .env.example .env`, `php artisan key:generate`, `npm run build` (berhasil; CSS berisi token `saffron`/`herb`/`chili`). `php artisan list` menampilkan `restaurant:*` tanpa `corporate:*`.

**Penyimpangan keputusan**
- ID prompt: tabel §3 menulis `corporate-v1`, `corporate-v2` → `restaurant-v1`. Keduanya dilebur menjadi satu ID `restaurant-v1` (varian tanpa dan dengan tools sama-sama memakainya). Plan 7 memperkenalkan `restaurant-v2` untuk prompt dengan tools.
- Sisa teks "karyawan/HR/IT/kantor" pada `lang/*`, data pratinjau `/dev/*` (departemen contoh), `CompanySetting`, `RoleCode::Employee`, dan seeder tidak diubah karena dimiliki plan lain (peta di bawah).
- Tes warisan hanya diubah secara mekanis (nama config, command, helper, ID prompt) agar tidak merujuk nama lama; tidak ditulis ulang dan tidak dijalankan.

**Kendala:** `php artisan migrate:fresh` belum dijalankan karena PostgreSQL lokal meminta password role `restaurant_ai` (`DB_PASSWORD` di `.env` kosong) dan kredensial itu tidak tersedia bagi implementer. Server aktif di `127.0.0.1:5432`. Isi `DB_PASSWORD` di `.env`, lalu jalankan `php artisan migrate:fresh`.

**Keadaan nyata peta kepemilikan §4 (setelah Plan 1)**

| Migration/modul | Keadaan | Pemilik |
|---|---|---|
| `…000001_create_departments_table`, `Department`, `Admin/DepartmentController`, `/admin/departments` | Masih ada | Plan 2 menghapus |
| `…000002`/`…000003`/`…000004` (kolom identitas, role, `support_teams`), `RoleCode`, `SupportTeamCode` | Masih ada (role employee/staff/editor/admin, tim hr/it) | Plan 2 menulis ulang |
| `…000005_create_audit_events_table`, `AuditEvent` | Dipertahankan | — |
| `…000006_create_company_settings_table`, `CompanySetting`, `ApplyCompanySettings`, `Admin/CompanySettingsController` | Masih ada | Plan 3 → `restaurant_settings` |
| `…000007_create_knowledge_tables`, `Modules/Knowledge`, `Knowledge*` | Masih ada (audience departemen) | Plan 4 menulis ulang |
| `…000008_create_conversation_tables`, `…000010_add_outcome_to_ai_turns_table`, `Modules/Conversation`, `Modules/Assistant` | Masih ada (pemilik `user_id`) | Plan 5 menulis ulang |
| `…000009_create_ticket_tables`, `Modules/Support`, `Ticket*`, `Admin/TicketController` | Masih ada (referensi `CORP-`) | Plan 6 → `Modules/Requests` |
| Route `/office/*`, `OfficeController`, `x-office-*`, `office-stage.js`, `views/office/*`, `SceneCode` | Masih ada (lima ruang kantor) | Plan 3 |
| `LocalDemoSeeder`, `DemoAccountsCommand` (isi), `KnowledgeDemoSeeder`, `ReferenceSeeder` | Masih berisi data corporate | Plan 2 (akun, tim), 4 (menu/info), 6 (request) |
| `EvaluateAssistantCommand` (isi), `tests/Feature/*`, `tests/Unit/*` | Isi corporate | Plan 8 |

**Checklist untuk pengguna**
1. Isi `DB_PASSWORD` di `.env` (role `restaurant_ai`), lalu `php artisan migrate:fresh` dan `npm run build`; `php artisan serve` membuka `/up` dan halaman login. (`composer setup` juga bisa untuk instalasi baru.)
2. Buka `/dev/ui`, `/dev/admin`, `/dev/user`, `/dev/guest` (APP_ENV=local): palet terracotta/espresso tampil konsisten; ganti bahasa id/en/ja. Data contoh di halaman itu masih bertema kantor.
3. `php artisan list`: ada `restaurant:*`, tidak ada `corporate:*` (sudah dicek implementer).
4. Cari teks "corporate" pada kode aplikasi: sisanya hanya `README.md` (rujukan ke basis); komentar di `docs/` sengaja menyebut basis (sudah dicek implementer).
5. Klarifikasi: nama brand dan nama pendek ("FTS Restaurant AI"/"FTS"), preferensi palet (terracotta/espresso atau lain), zona waktu (Asia/Jakarta), mata uang (IDR). Ilustrasi ruang masih gambar kantor sementara.

Pencatatan identitas proyek (6 Oktober 2026): salinan dari `fts-corporate-ai` dibuat tanpa `.env`, `.git`, `vendor`, `node_modules`, dan SQLite; rename identitas dilakukan pada paket, `APP_NAME`, database, path deploy, manifest/logo, prefix localStorage, dan README (lihat [rencana adaptasi](RESTAURANT_ADAPTATION_PLAN.md)).
