# Plan 6 Reservasi, pesanan, feedback, dan panel staf

| Metadata | Nilai |
|---|---|
| ID | 6 |
| Status | Implemented |
| Dependensi | [Plan 5](PLAN_05_CONVERSATION_AND_AI.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Acuan | [Master](000_MASTER_PLAN.md) |
| Hasil implementasi | [Catatan hasil](#catatan-hasil-implementasi) (7 Oktober 2026) |

## Hasil yang harus tersedia

Tamu mengajukan reservasi, pesanan (takeaway atau pre-order dine-in), feedback, atau permintaan khusus lewat form dengan review/konfirmasi, melihat status dan membalas staf, dan membatalkan request yang belum terminal. Staf menangani antrean timnya (konfirmasi, tolak, assign, balas, selesai). Kapasitas slot reservasi ditegakkan tanpa overbooking. Seluruh alur bekerja tanpa AI; Plan 7 hanya memasang tool AI pada action yang sudah tersedia.

## Basis kode yang diwarisi

Dipakai ulang: pola draft → konfirmasi idempoten (`PrepareTicketDraft`, `UpdateTicketDraft`, `ConfirmTicketDraft`), `RunsIdempotently` + `Receipts`, `TransitionTicket`/`AssignTicket`/`AppendTicketMessage`/`ReadTicketHistory` sebagai kerangka, `TicketReference`, `TicketNotifier` + notifikasi database, `TicketAccess`, presenter, wizard (`ticketWizard`), `x-ticket-thread` (polling 5 s, cursor `after_id`, pause saat hidden/offline), panel staf di layout admin, `x-navigation.notification-bell`.

Diubah:
- Modul `Support` → `Requests` (`App\Modules\Requests`). **Model bernama `GuestRequest`** (tabel `guest_requests`) karena `Request` bentrok dengan kelas HTTP Laravel; `Ticket*` → `GuestRequest*`, `TicketDraft` → `RequestDraft`, `TicketMessage` → `RequestMessage`, `TicketEvent` → `RequestEvent`.
- `TicketStatus` → `RequestStatus` (pending, confirmed, completed, declined, cancelled, no_show); tone badge: pending→info, confirmed→success, completed→neutral, declined→danger, cancelled→warning, no_show→warning.
- `TicketCategory` dihapus; diganti `RequestType` (reservation, order, feedback, special_request) dengan pemetaan tim di enum.
- Pemohon bukan `User` tetapi `GuestContext`; `TicketAccess` → `RequestAccess` (sesi pemilik, grant referensi, atau staf aktif tim).
- `operation_receipts`: kolom `actor_user_id` menjadi `actor_type` (`guest_session|user`) + `actor_id`.
- Referensi `CORP-` → `RST-YYYYMMDD-XXXXXXXX`.
- Notifikasi hanya untuk staf; tamu memakai polling halaman status.
- `…000009_create_ticket_tables` ditulis ulang oleh plan ini; `/office/requests*`, wizard tiket, antrean `/admin/tickets` diganti.

Dihapus: kategori HR/IT, nama tim `hr`/`it`, notifikasi untuk pemohon, endpoint `/api/v1/tickets*`.

## Skema data

- request_drafts: id, public_id UUID unique, guest_session_id, conversation_id nullable, originating_turn_id nullable (unique bila non-null; FK `ai_turns` null-on-delete, dipakai Plan 7), type, team_id, payload jsonb, contact_name, contact_phone, contact_email nullable, table_label nullable, revision integer default 1, payload_fingerprint string 64, expires_at, consumed_request_id nullable, timestamps.
- guest_requests: id, public_id UUID unique, reference string unique, type, team_id, status default pending, version integer default 1, guest_session_id nullable (**null-on-delete**: request dipertahankan walau sesi dipurge), contact_name, contact_phone, contact_phone_digits (terindeks), contact_email nullable (lowercase), table_label nullable, locale, payload jsonb, pricing jsonb nullable (order: baris dengan harga satuan beku dan total), reserved_date date nullable, reserved_slot time nullable, party_size nullable, assignee_id nullable, draft_id unique FK request_drafts (restrict), conversation_id nullable (null-on-delete), confirmed_at, terminal_at nullable, timestamps. Index (team_id, status, updated_at), (type, reserved_date, reserved_slot).
- request_messages: id, request_id, author_type guest|staff, author_user_id nullable, visibility public|internal (internal hanya staf), text max 5000, created_at; index (request_id, id).
- request_events: id, request_id, actor_type guest|staff|system, actor_user_id nullable, event_code, previous_status nullable, next_status nullable, metadata jsonb, created_at. Tidak menyimpan isi pesan.
- operation_receipts: id, actor_type, actor_id, operation_type, idempotency_key UUID, fingerprint string 64, resource_type, resource_public_id string, created_at. Unique(actor_type, actor_id, operation_type, idempotency_key).
- reservation_slot_usage: id, date, slot_start (time), booked_seats integer, timestamps; unique(date, slot_start).
- closed_dates: id, date unique, reason nullable, created_by.
- restaurant_settings (tambahan kolom, Plan 3): opening_hours jsonb (kunci `mon..sun` berisi daftar `{open, close}` HH:MM, timezone restoran), slot_capacity, max_party_size, min_lead_minutes, max_advance_days (semua nullable; null memakai default `config/restaurant.php`).
- notifications: tabel notifikasi Laravel (UUID id, notifiable morph `User`, type, data, read_at) untuk staf.

Circular draft/request FK dibuat terpisah setelah kedua tabel ada; `guest_requests.draft_id` unique menjamin satu request per draft (restrict-on-delete); `request_drafts.consumed_request_id` null-on-delete. Pada purge, hapus request lebih dulu, baru draft. Request/pesan tidak hard delete lewat UI.

Referensi `RST-YYYYMMDD-XXXXXXXX` dibuat server dengan random suffix dan unique constraint; retry collision maksimal tiga kali. Referensi hanya label, bukan identifier otorisasi.

## Form, API, dan panel

Tamu (semua di `/api/v1`, sesi tamu lazy, CSRF):
- GET `/api/v1/availability?date=&party_size=`: daftar slot `{slot, available_seats, can_book}` untuk tanggal; read-only; throttle 30/menit per IP.
- POST `/api/v1/request-drafts` (type, payload, kontak, `conversation_public_id` opsional milik sesi); limiter `guest-writes` + honeypot.
- GET/PATCH `/api/v1/request-drafts/{draft:public_id}` (edit membawa `expected_revision`).
- POST `/api/v1/request-drafts/{draft}/confirm` dengan `expected_revision` dan `idempotency_key`.
- GET `/api/v1/requests` (milik sesi + grant), GET `/api/v1/requests/{request}`.
- POST `/api/v1/requests/lookup` (`reference` + `contact`): bila cocok, menambahkan grant ke sesi (maksimal 20) dan mengembalikan request. Limiter `guest-lookup`; 5 kegagalan berturut-turut untuk satu referensi mengunci pencarian 15 menit; respons gagal selalu seragam (tidak membocorkan keberadaan referensi).
- GET/POST `/api/v1/requests/{request}/messages` (tamu hanya pesan public; POST membawa text dan `idempotency_key`).
- POST `/api/v1/requests/{request}/cancel` (alasan opsional, `idempotency_key`); hanya status pending/confirmed.

Halaman tamu: `/reservation` (wizard 3 langkah: tanggal+jumlah tamu → slot → kontak+review/konfirmasi), `/order` (keranjang → fulfilment, jam, kontak → review/konfirmasi), `/bookings` (daftar request sesi, form pencarian referensi, tombol "Butuh bantuan lain?" untuk feedback/permintaan khusus), `/bookings/{reference}` (detail; tanpa akses menampilkan form pencarian, bukan 404 yang membocorkan).

Staf:
- GET `/admin/requests` (filter tipe/status/tanggal/milik saya; tampilan "Hari ini" dengan ringkasan kursi per slot) dan `/admin/requests/{request}` memakai keanggotaan tim, bukan role admin.
- PATCH `/api/v1/staff/requests/{request}/assignment` (`assignee_id`, `expected_version`, `idempotency_key`).
- PATCH `/api/v1/staff/requests/{request}/status` (target, `expected_version`, `reason` bila perlu, `idempotency_key`).
- GET/POST `/api/v1/staff/requests/{request}/messages` (visibility public/internal).
- GET `/api/v1/notifications`, PATCH `/api/v1/notifications/{notification}/read` hanya pemilik.
- Pengaturan di `/admin/settings` (admin): jam buka per hari, tanggal tutup, kapasitas slot, ukuran grup maksimum, lead time, jarak maksimum ke depan.

API tamu mengembalikan pesan public saja dan hanya request yang dimiliki/di-grant. API staf memerlukan role staff serta keanggotaan tim saat ini. Admin tanpa keanggotaan tim mendapat 403 menu staf dan 404 request. Request orang lain atau tim lain → 404.

## Action dan transaksi

Buat `PrepareRequestDraft`, `UpdateRequestDraft`, `ConfirmRequestDraft`, `AssignRequest`, `TransitionRequest`, `CancelRequest`, `AppendRequestMessage`, `ReadRequestHistory`, `LookupRequest`, `CheckAvailability`. Validator domain (`RequestDraftRules` per jenis) dipakai Form Request dan tool Plan 7; aktor selalu `GuestContext`/user session, bukan id di body.

Validasi per jenis ([kontrak payload](TECHNICAL_CONTRACTS.md#request-dan-perubahan-status)):
- Reservasi: tanggal di jendela `min_lead_minutes`..`max_advance_days`, bukan `closed_dates`, slot jatuh pada interval dan dalam jam buka, `party_size` ≤ `max_party_size`.
- Pesanan: semua `menu_item_id` published dan `is_available` saat draft dan saat konfirmasi, kuantitas 1–20, maksimal 20 baris, jam diminta dalam jam buka dan ≥ lead time. Harga satuan dan total dihitung server (`OrderPricing`, harga dari DB, bukan dari klien atau model) dan dibekukan di `pricing` saat konfirmasi; total ditampilkan di review.
- Kontak: nama dan telepon wajib untuk reservasi/pesanan/permintaan khusus; `contact_phone_digits` dinormalisasi untuk pencarian.

Konfirmasi draft:
1. Periksa receipt aktor/type/key; identik → hasil pertama; payload berbeda → 409.
2. Lock draft; periksa pemilik (guest_session), `expected_revision`, `expires_at`, fingerprint.
3. Bila draft sudah consumed, kembalikan request yang sama bagi pemilik untuk revision/fingerprint identik; jangan buat request kedua walau key berbeda.
4. Reservasi: upsert baris `reservation_slot_usage` untuk (date, slot) lalu `lockForUpdate`; bila `booked_seats + party_size > slot_capacity` → 409 `slot_full` (draft tetap, tamu dapat memilih slot lain).
5. Insert request `pending`, set `consumed_request_id`, receipt, event, `booked_seats += party_size` (reservasi), dan notifikasi staf dalam satu transaksi; 201 pertama dan 200 pada replay.
6. Draft kedaluwarsa yang belum consumed ditolak 409 `draft_expired`.

Transisi dan penahanan kursi:
- Matriks status mengikuti kontrak. Reservasi menahan kursi sejak `pending`; konfirmasi staf **tidak** memeriksa kapasitas ulang. Pesanan memeriksa ketersediaan hidangan saat `pending → confirmed`: bila ada yang habis → 409 `item_unavailable` dengan daftar item agar staf menolak atau membalas tamu.
- Transisi ke `declined`, `cancelled`, `no_show`, `completed` melepaskan kursi dalam transaksi yang sama (`booked_seats -= party_size`, tidak di bawah 0).
- `declined` dan pembatalan staf wajib alasan (disimpan sebagai pesan public agar tamu tahu). Pembatalan tamu boleh tanpa alasan. Ubah `version` saat status atau assignment berubah; pesan tidak menaikkan version tetapi mengunci request untuk mencegah balasan setelah terminal.
- Assignment hanya user aktif staf tim request.

Notifikasi (database, sinkron di transaksi yang sama, hanya staf): request baru → semua staf aktif tim; pesan tamu → assignee atau tim bila belum assigned; pembatalan tamu → assignee atau tim. Data notification hanya `public_id`, `event_code`; tidak menyimpan kontak atau isi pesan. Receipt replay tidak menciptakan event/pesan/notifikasi lagi. Lock dan transaksi untuk perlombaan, bukan sekadar validasi awal. [Pessimistic locking Laravel](https://laravel.com/framework/docs/13.x/queries#pessimistic-locking).

## Frontend

- Wizard reservasi/pesanan/feedback mengikuti `ticketWizard` (didaftarkan sebagai Alpine.data sebelum startup tunggal). Draft disimpan server; browser hanya menyimpan `public_id` draft non-rahasia dan keranjang pesanan (`fts-restaurant-stage:cart`: hanya `menu_item_id` dan jumlah; harga selalu dihitung ulang server). Kontak tidak disimpan di storage.
- Langkah pilih slot memanggil `/api/v1/availability`; slot penuh tampil nonaktif; setelah `slot_full` daftar slot dimuat ulang.
- Halaman status memakai `x-request-thread` (turunan `x-ticket-thread`): status, pesan public, form balas, tombol batalkan; polling 5 s hanya saat halaman terlihat/online dengan `after_id` dan dedup id. Catatan internal tidak pernah masuk renderer tamu.
- Staf: antrean dan detail di layout admin, filter tim/status/tanggal, tampilan "Hari ini" (kursi terpakai per slot), aksi konfirmasi/tolak/selesai/no-show/assign, badge notifikasi di header admin.
- Slide Reservasi, Pesanan, dan Booking Saya (Plan 3) berpindah dari empty state ke wizard/daftar; tombol "Tambah ke pesanan" di kartu menu (Plan 4) aktif.

## Kriteria implementasi dan verifikasi pengguna

Implementasi selesai (Implemented) bila skema, actions dengan transaksi/row lock/receipt, kapasitas slot, API tamu dan staf, notifikasi staf, wizard, halaman Booking Saya, panel staf, pengaturan jam buka/kapasitas, dan polling tersedia, dan semua modul tiket HR/IT sudah hilang. Tes otomatis (termasuk concurrency PostgreSQL) tidak ditulis/dijalankan; skenarionya ada di [katalog Plan 8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan).

Checklist untuk pengguna:
1. Sebagai tamu (tanpa login) buat reservasi lewat wizard; review lalu konfirmasi. Klik konfirmasi dua kali cepat atau refresh saat submit; harus tetap satu request.
2. Penuhi kapasitas satu slot (turunkan kapasitas di pengaturan); reservasi berikutnya ke slot itu ditolak dengan pesan slot penuh dan slot lain tetap bisa dipilih.
3. Buat pesanan dengan hidangan yang lalu ditandai "habis" oleh staf sebelum konfirmasi; konfirmasi atau konfirmasi staf harus ditolak dengan pesan jelas.
4. Biarkan draft >15 menit lalu konfirmasi; harus ditolak.
5. Sebagai staf front_of_house lihat antrean, assign, balas publik, tulis catatan internal, konfirmasi, tandai selesai/no-show. Staf kitchen tidak boleh melihat reservasi.
6. Sebagai tamu lihat balasan staf muncul dalam ±5 detik tanpa refresh; catatan internal tidak terlihat. Batalkan request; kursi kembali tersedia.
7. Buka `/bookings/{reference}` dari browser lain: harus meminta kode referensi + kontak; kontak salah berulang kali mengunci pencarian sementara.
8. Admin tanpa penugasan tim tidak bisa membuka request tamu.
9. Cek badge notifikasi staf untuk request baru, pesan tamu, dan pembatalan.
10. Klarifikasi: jam buka, kapasitas slot dan ukuran grup maksimum, kebijakan jam pesanan, dan siapa staf awal tiap tim.

[Database notifications Laravel](https://laravel.com/framework/docs/13.x/notifications#database-notifications) menjadi acuan kanal.

## Pemakaian fondasi UI

Antrean/detail staf menyusun `x-layouts.admin`; halaman tamu memakai `x-deck-stage`/`x-layouts.user`. Form memakai field/control, card, button, modal/confirm-dialog, table, badge, empty-state, dan toast yang sudah ada. Wizard didaftarkan sebagai Alpine.data sebelum startup tunggal. Status ke badge tone dipetakan presenter Requests. Konfirmasi UI tetap memanggil action server dengan revision/idempotency; event `ui:confirmed` tidak mengotorisasi operasi.

## Catatan hasil implementasi

Dikerjakan 7 Oktober 2026. Status: **Implemented**; verifikasi otomatis (termasuk uji konkurensi PostgreSQL) menunggu Plan 8.

**Perubahan yang dibuat**
- Skema `…000011`: `request_drafts`, `guest_requests` (+ `reserved_date/reserved_slot/party_size`, `pricing` beku, `version`), `request_messages`, `request_events`, `operation_receipts`, `reservation_slot_usage`, `closed_dates`, `notifications`. Model `RequestDraft`, `GuestRequest`, `RequestMessage`, `RequestEvent`, `OperationReceipt`, `ReservationSlotUsage`, `ClosedDate`.
- Modul `Requests`: enum `RequestType` (reservation, order, feedback, special_request; tim penangan), `RequestStatus` (pending → confirmed → completed; declined, cancelled, no_show), `MessageVisibility`; `Support` (`Receipts`, `RequestReference` RST-YYYYMMDD-XXXXXXXX, `RequestAccess`, `OpeningSchedule`, `OrderPricing`, `RequestNotifier`, `SeatLedger`, `WizardLabels`); `RequestDraftRules` (aturan yang sama untuk form, API, dan tool AI); aksi `PrepareRequestDraft` (kedaluwarsa 15 menit), `UpdateRequestDraft` (`expected_revision`), `ConfirmRequestDraft` (kunci baris, kapasitas kursi, harga beku, idempotensi receipt), `TransitionRequest` (versi + alasan wajib saat tolak/batal), `CancelRequest`, `AssignRequest`, `AppendRequestMessage`, `ReadRequestHistory`, `LookupRequest` (referensi + kontak, terkunci per referensi), `CheckAvailability`.
- API (`routes/internal.php`): availability, request-drafts (POST/PATCH/GET/confirm), requests (daftar, detail, lookup, pesan, batal), staf (assignment, status, pesan), notifikasi. Halaman: `/reservation`, `/order`, `/bookings`, `/bookings/{reference}`; panel staf `/admin/requests` (+ "Hari ini" untuk front of house) dibatasi keanggotaan tim, bukan peran admin.
- Frontend: `request-wizard.js` (reservasi 3 langkah, pesanan, feedback/permintaan khusus; draft di server), `request-thread.js` (polling 5 dtk dengan kursor `after_id`, jeda saat tab tersembunyi/offline; pencarian booking), `notifications.js` (bel staf), `shared/cart.js` (keranjang `fts-restaurant-stage:cart`, hanya id dan jumlah), komponen `request-thread`, view `experience/{reservation,order,bookings,bookings-show}`, `admin/requests/{index,show}`, `lang/{id,en,ja}/requests.php`.
- `LocalDemoSeeder`: akun demo (password lokal tetap, hanya di seeder, tidak didokumentasikan), menu, info, dan contoh request.

**Penyimpangan keputusan**
- `reserved_slot` dan `slot_start` disimpan sebagai string `HH:MM`; `request_drafts.guest_session_id` boleh null (null saat sesi tamu dipurge).
- Notifikasi staf hanya menyimpan public id request dan kode kejadian; label dan tautan dibuat saat dibaca, jadi tidak ada teks tamu di tabel notifikasi.

**Checklist untuk pengguna:** buat reservasi di `/reservation` (pilih tanggal, jam, isi kontak, konfirmasi); lihat `pending` di `/bookings`; masuk sebagai staf front of house, konfirmasi di `/admin/requests`; periksa tamu melihat status berubah dalam ±5 detik; buat pesanan dari `/menu` (tombol "Tambah ke pesanan") dan pastikan hanya tim dapur yang melihatnya; coba slot penuh dan hidangan habis.
