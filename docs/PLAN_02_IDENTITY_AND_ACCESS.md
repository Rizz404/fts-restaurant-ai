# Plan 2 Akun staf, sesi tamu, dan hak akses

| Metadata | Nilai |
|---|---|
| ID | 2 |
| Status | Implemented |
| Dependensi | [Plan 1](PLAN_01_SETUP.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Acuan | [Master](000_MASTER_PLAN.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Hasil implementasi | [Catatan hasil](#catatan-hasil-implementasi) (7 Oktober 2026) |

## Hasil yang harus tersedia

- Admin dapat membuat/menonaktifkan akun staf, menetapkan peran (admin/editor/staff) dan penugasan tim (`front_of_house`, `kitchen`). Staf login dengan session; password sementara wajib diganti.
- Tamu tidak login. Server mengenali tamu lewat **sesi tamu** anonim (`guest_sessions`) yang dibuat lazy saat tamu pertama kali menulis sesuatu (turn AI, draft, konfirmasi). Sesi tamu menjadi pemilik percakapan dan request pada plan berikutnya.
- Limiter dan honeypot untuk endpoint tamu tersedia sebagai komponen siap pakai untuk Plan 5 dan 6.
- Hak akses menjadi action/Policy yang dipakai plan berikutnya. Konsep departemen dihapus.

## Basis kode yang diwarisi

Dipakai ulang: `Modules/Identity` (`ActorContext`, `CurrentActor`, `ProvisionUser`, `DisableUser`, `ResetTemporaryPassword`, `ChangePassword`, `UpdateUserAccess`, `WriteAuditEvent`, `AdminGuard` dengan perlindungan admin terakhir, `SessionRevoker`, `AdminNavigation`, `UserAccessRules`), middleware `EnsureAccountIsActive` dan `EnsurePasswordChanged`, halaman login/ganti password/admin users, `CreateAdminCommand`, `DemoAccountsCommand`, tabel `audit_events`.

Diubah: enum `RoleCode` (admin/editor/staff; `employee` dihapus) dan `SupportTeamCode` → `StaffTeamCode` (`front_of_house`, `kitchen`); `support_teams` → `staff_teams`; `UserAccessRules` tanpa departemen dan tanpa editor department; `AdminNavigation` mengikuti menu restoran; `ReferenceSeeder` hanya role dan tim; `LocalDemoSeeder`/`DemoAccountsCommand` diganti akun demo restoran.

Dihapus: `Department`, `Admin/DepartmentController`, `DepartmentPolicy`, view `admin/departments`, kolom `users.department_id`, tabel `knowledge_editor_department` (dibuat di migration pengetahuan, dihapus di Plan 4).

## Skema data

Ditulis ulang pada migration Plan 2 (lihat [peta kepemilikan Plan 1](PLAN_01_SETUP.md#4-peta-kepemilikan-modul-corporate)):
- users: preferred_locale default id, is_active default true, must_change_password default true. Email dinormalisasi lowercase pada semua jalur create/update/login. Tanpa `department_id`.
- roles: id, code unique dengan admin/editor/staff.
- role_user: user_id + role_id unique.
- staff_teams: id, code unique front_of_house/kitchen, name, is_active.
- staff_team_user: staff_team_id + user_id unique; member harus role staff.
- guest_sessions: id, public_id UUID unique, locale, table_label nullable (≤ 20, alfanumerik dan `-`), created_at, last_seen_at, expires_at (default 30 hari setelah last_seen_at). Tanpa kolom PII.
- audit_events: tetap (actor_id nullable, action, subject_type, subject_id string, metadata jsonb, occurred_at). Tidak menyimpan password, transkrip, kontak tamu, atau credential.

Tidak hard delete user yang sudah dirujuk; nonaktifkan. Hindari cascade yang menghapus request/audit. Model/Factory untuk `StaffTeam` dan `GuestSession`.

ReferenceSeeder idempoten membuat tiga role dan dua tim. Nama reference seed bukan data staf produksi.

## Sesi tamu

- `App\Modules\Identity\Guest\GuestSessions`: `current(bool $create = false): ?GuestSession`. Membaca `guest_session_id` dari server session; bila `$create` dan belum ada, membuat baris baru (locale dari session, `table_label` dari session bila ada) dan menyimpan ID-nya di session. Page view biasa tidak membuat baris.
- Middleware `CaptureTableLabel`: pada request GET halaman tamu, parameter `?table=` yang cocok `^[A-Za-z0-9-]{1,20}$` disimpan di session sebagai `table_label`; nilai tidak valid diabaikan. Label meja hanya keterangan, bukan otorisasi.
- Middleware `EnsureGuestSession` untuk endpoint tamu `/api/v1` yang menulis: memanggil `current(create: true)`. Staf yang membuka halaman tamu diperlakukan sebagai tamu biasa; sesi tamu independen dari akun staf dan tidak membawa hak staf.
- `GuestContext` (DTO per request): `guest_session_id`, `public_id`, `locale`, `table_label`. Identitas tidak boleh berasal dari body context atau tool.
- `SessionGrants`: daftar `request_public_id` yang dibuka lewat kode referensi (maksimal 20, di server session), dipakai Plan 6.
- Logout staf atau regenerasi session tidak menghapus baris `guest_sessions`; kedaluwarsa dan purge dikerjakan Plan 8 (`restaurant:purge-data`).

## Autentikasi staf dan endpoint

- GET/POST /login; POST /logout; GET/POST /password/change. `/` bukan lagi redirect: menampilkan halaman sambutan sementara (x-layouts.guest) sampai Plan 3.
- Admin HTML: GET /admin, resource /admin/users kecuali destroy. Perubahan aktif melalui update, bukan delete. Tidak ada resource departemen.
- Admin update user mengatur role dan penugasan tim dalam satu transaksi. Assignment tim tanpa role `staff` ditolak 422.
- POST /admin/users/{user}/reset-password menghasilkan password sementara baru dengan input secret admin, bukan email delivery. Jangan menampilkan password di daftar atau log.
- POST /api/v1/preferences menerima locale id/en/ja: untuk staf menyimpan preferred_locale dan session; untuk tamu menyimpan session dan, bila baris sesi tamu ada, kolom `locale`.

Login memakai Auth::attempt dengan syarat user aktif, regenerasi session setelah sukses, dan limiter kontrak. Password minimal 12 karakter, confirmed, hashing default Laravel.

Middleware EnsureAccountIsActive dan EnsurePasswordChanged tetap melindungi semua halaman/API staf; JSON mengembalikan 403 `password_change_required`. Endpoint tamu tidak memakainya.

## Limiter dan honeypot

Definisikan named limiters di `AppServiceProvider` (nilai di [kontrak teknis](TECHNICAL_CONTRACTS.md#konfigurasi-baseline)): `login` (5/menit per email+IP), `guest-assistant` (8/menit per sesi tamu + 30/menit per IP), `guest-writes` (10/menit per sesi tamu), `guest-lookup` (10/menit per IP), `staff-writes` (20/menit per user). Kunci sesi tamu memakai `public_id`, bukan ID bigint. Respons 429 membawa `Retry-After`.

Honeypot: middleware `RejectHoneypot` memeriksa field tersembunyi (`contact_website`) pada form tamu; bila terisi, tolak 422 `validation_failed` tanpa petunjuk. Komponen `x-ui.honeypot` ditambahkan ke kit UI (dokumentasikan di UI_COMPONENTS).

Batas giliran harian AI dan anggaran global diterapkan di Plan 5; Plan 2 hanya menyediakan identifier sesi tamu.

## Langkah pengerjaan

1. Tulis ulang migration identitas, ubah models dan factory; hapus Department.
2. Ubah enum `RoleCode`, rename `SupportTeamCode` → `StaffTeamCode`; perbarui `ActorContext` (tanpa departemen dan editor scope), `UserAccessRules`, dan action yang menyebut departemen.
3. Implementasikan `GuestSessions`, `GuestContext`, `SessionGrants`, middleware `CaptureTableLabel`, `EnsureGuestSession`, `RejectHoneypot`, dan limiter.
4. Perbarui UserPolicy; hapus DepartmentPolicy. Tidak memakai super-admin before() yang membypass seluruh policy resource.
5. Perbarui routes, controller, Form Request, dan view admin users (tanpa departemen, dengan tim). Semua form POST/PATCH memakai CSRF, error terjemahan, dan old input tanpa password.
6. Perbarui `restaurant:create-admin` (role admin, wajib ganti password; tanpa departemen) dan `restaurant:demo-accounts` (local/testing; akun admin, editor, staf front_of_house, staf kitchen dengan password sementara acak; tolak production).
7. Pertahankan perlindungan admin terakhir (lock baris seluruh admin aktif dalam transaksi) dan audit perubahan role/tim tanpa data sensitif.
8. Reset password/deactivation tetap menghapus session user dan mengirim event `UserSessionsRevoked` (Plan 5 memasang pembatalan lease AI untuk percakapan; percakapan tamu tidak terpengaruh akun staf).
9. Perbarui `AdminNavigation`: Dashboard, Akun; menu Menu/Info/Request/Pengaturan/Operasi tampil nonaktif sampai plan pemiliknya selesai.
10. Perbarui README (reference seed, create-admin) dan catat penghapusan departemen di catatan hasil.

## Kriteria implementasi dan verifikasi pengguna

Implementasi selesai (Implemented) bila migration/model/factory baru, actions, Policy, middleware, login/password/admin users, `GuestSessions` dan middleware pendukung, limiter, honeypot, command create-admin dan demo, perlindungan admin terakhir, serta audit tersedia, dan departemen sudah dihapus dari kode dan UI. Tidak perlu membangun Policy menu/request yang belum memiliki model. Tes otomatis tidak ditulis/dijalankan; skenarionya ada di [katalog Plan 8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan).

Checklist untuk pengguna:
1. `php artisan migrate:fresh --seed`, lalu `restaurant:create-admin`; login dan wajib ganti password sementara.
2. Login dengan email huruf besar/kecil berbeda; coba password salah berulang hingga throttle.
3. Sebagai admin buat staf, tetapkan role dan tim, nonaktifkan, reset password; pastikan sesi lama langsung keluar.
4. Coba hapus role admin dari admin terakhir atau nonaktifkan akunnya; harus ditolak.
5. Login sebagai staf non-admin dan buka `/admin/users`; harus ditolak.
6. Buka `/?table=12` tanpa login; periksa bahwa tidak ada baris `guest_sessions` terbentuk dari page view, dan `table_label` tidak diterima bila berisi karakter aneh.
7. Periksa halaman login/admin dalam id/en/ja serta pesan validasi.
8. Klarifikasi: siapa admin pertama, daftar staf awal per tim, dan kebijakan password.

## Pemakaian template dan komponen

Login/password memakai x-layouts.guest; halaman akun admin memakai x-layouts.admin. Gunakan x-ui.field/input/select/checkbox, button, card, alert, table/pagination; FieldState menghubungkan validasi dengan control. Pasang ability menu pada navigation component yang sudah ada. Label id/en/ja diperluas di `lang/*/ui.php`.

## Catatan hasil implementasi

Dikerjakan 7 Oktober 2026 (bersama Plan 3–7 dalam satu pass karena modul saling terkait). Status: **Implemented**; verifikasi otomatis menunggu Plan 8.

**Perubahan yang dibuat**
- Migration baru menggantikan migration identitas corporate: `…000002_add_identity_columns` (locale, `is_active`, `must_change_password`), `…000004_create_staff_teams_tables` (`staff_teams`, pivot `staff_team_user`), `…000007_create_guest_sessions_table` (`public_id`, `locale`, `table_label`, `last_seen_at`, `expires_at`). Tabel `departments`, `support_teams`, `company_settings`, dan kolom departemen dihapus; migration lama dipindah ke arsip lokal (bukan dihapus).
- Enum `RoleCode` (admin/editor/staff) dan `StaffTeamCode` (`front_of_house`, `kitchen`); model `StaffTeam`, `GuestSession` (+ factory), `User::staffTeams()`. `ActorContext` tanpa departemen dengan `isStaffOf(StaffTeamCode)`; `UserAccessRules`, `ProvisionUser`, `UpdateUserAccess`, `UserAccessRequest`, view `admin/users/*` memakai `staff_teams` (tim hanya boleh diberikan bersama peran staf; staf tanpa tim sah tetapi tidak menangani request apa pun).
- Sesi tamu anonim: `GuestSessions` (stateless; baris dibuat lazy hanya oleh endpoint tulis, cache per request di atribut request), `GuestContext`, `SessionGrants` (grant request di sesi server), `GuestChallenge` + `HoneypotChallenge` (bidang `contact_website`), middleware `CaptureTableLabel` (`?table=` tervalidasi regex, tidak pernah dipercaya sebagai identitas), `EnsureGuestSession`, `RejectHoneypot`, `SetLocale` yang menyinkronkan locale sesi tamu. Komponen `ui/honeypot`.
- `AppServiceProvider`: gate `admin.access`, `operations.view`, `requests.staff`; limiter `guest-session-create` (30/menit per IP), `guest-assistant` (8/menit per sesi + 30/menit per IP), `guest-writes` (10 + 60), `guest-lookup` (10/IP), `public-read` (30/IP), `staff-writes` (20/user). Kunci limiter diturunkan dari `GuestSessions::current()` atau id sesi server, bukan dari urutan middleware.
- Login staf tetap memakai `LoginRequest` dan `/admin` sebagai tujuan; `bootstrap/app.php` mengarahkan pengguna login ke `admin.dashboard`. `AdminNavigation` mengikuti menu restoran dan menonaktifkan item yang rutenya belum ada (`Route::has`). Command `restaurant:create-admin` dan `restaurant:demo-accounts` (admin, editor, front of house, kitchen; password acak ditampilkan sekali), `ReferenceSeeder` (role, tim, baris `restaurant_settings`).

**Perintah setup:** tidak ada selain `php -l` dan `php artisan route:list` untuk memastikan aplikasi dapat memuat route; `migrate` belum dijalankan (kendala password PostgreSQL pada Plan 1).

**Penyimpangan keputusan**
- Throttle login tetap di `LoginRequest` (per email+IP) dan tidak dipindah ke limiter bernama; limiter pembuatan sesi tamu hanya satu (`guest-session-create`, per IP) karena baris sesi dibuat lazy.
- Tabel `staff_team_user` dinamai mengikuti pola pivot Laravel; kode tim disimpan di `staff_teams.code` dan diisi `ReferenceSeeder`.

**Checklist untuk pengguna:** jalankan `php artisan migrate:fresh --seed` dengan `DB_PASSWORD` terisi, lalu `php artisan restaurant:demo-accounts` (atau `db:seed --class=LocalDemoSeeder`). Masuk di `/login` sebagai admin, buat akun staf dengan tim, periksa bahwa staf tanpa tim tidak melihat menu Permintaan, dan buka `/` sebagai tamu (baris `guest_sessions` baru muncul hanya setelah aksi tulis).

**Pekerjaan tersisa:** tes (Plan 8). Password PostgreSQL diperlukan untuk menjalankan migration.
