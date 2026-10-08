# Kontrak teknis bersama FTS Restaurant AI

Status: Ready sebagai spesifikasi dan sudah diimplementasikan (Plan 1–7); penyimpangan kecil yang diputuskan saat implementasi dicatat di bagian akhir dokumen. Pengujian otomatis dijalankan di Plan 8.
Tanggal: 6 Oktober 2026 (diperbarui 7 Oktober 2026).
Acuan: [master plan](000_MASTER_PLAN.md). Dibaca bersama setiap plan teknis.

## Stack dan batas arsitektur

Laravel 13, PHP target 8.4, PostgreSQL 17, Blade, JavaScript ES modules dengan Alpine.js 3, Tailwind CSS 4, Vite 8, dan PHPUnit 12.5 (tidak berubah dari basis). Aplikasi satu restoran dan server-rendered multi-page seperti FTS Hotel AI dan Corporate AI.

Gunakan namespace `App\Modules\Identity`, `Menu`, `Knowledge` (artikel info), `Conversation`, `Assistant`, `Requests`, `Experience`, dan `Operations` untuk action, query, DTO, dan kontrak. Models tetap di `app/Models`; controller, Form Request, resource, middleware, dan Policy mengikuti folder standar Laravel. Tidak membuat repository generik untuk setiap model. Modul `Support` basis menjadi `Requests`.

Semua ID database internal memakai bigint. Guest session, conversation, AI turn, request draft, dan request juga memiliki `public_id` UUID unik sebagai ID API. Hidangan dan artikel info menggunakan slug pada halaman dan ID bigint pada tool internal. UUID bukan pengganti pemeriksaan izin. Timestamp disimpan UTC dan dikirim ISO 8601; tampilan menggunakan timezone restoran `Asia/Jakarta` (dapat dikonfigurasi). Harga disimpan sebagai bilangan bulat satuan terkecil mata uang (default IDR tanpa desimal).

## Fondasi UI bersama

[UI_FOUNDATION.md](UI_FOUNDATION.md) menetapkan template guest/admin/user, token theme.css, props komponen Blade, bootstrap Alpine tunggal, presenter deck, dan event/helper bersama. Semua plan memakai layout/komponen tersebut; perubahan warna ditambahkan di sumber tema, bukan per halaman.

Alpine mengatur state tampilan; action/Policy server tetap menentukan izin dan efek bisnis. CSS/JS dibangun sekali melalui Vite pada layout base, bukan bundle berbeda per role. Perintah npm run lint:ui dijalankan di Plan 8.

## HTTP dan locale

Halaman tamu (tanpa login): `/` (Welcome), `/menu`, `/menu/{slug}`, `/picks`, `/reservation`, `/order`, `/bookings`, `/bookings/{reference}`, `/info`, `/info/{slug}`. Halaman staf: `/login`, `/password/change`, `/admin/*`. Nama route bernama: `experience.welcome`, `experience.menu`, `experience.picks`, `experience.reservation`, `experience.order`, `experience.bookings`, `experience.info`. Route `/office/*` basis dihapus.

Endpoint JSON berawalan `/api/v1`, didefinisikan dalam `routes/internal.php` yang dimuat oleh grup di `routes/web.php`. Endpoint tamu memakai web session + CSRF + middleware sesi tamu; endpoint staf memakai auth, akun aktif, dan password sementara sudah diganti. Request JavaScript memakai `Accept: application/json`, credentials same-origin, serta header CSRF.

Locale sah: `id`, `en`, `ja`. Urutan pilihan: query `lang` yang valid → session locale → default `id`. Nilai locale tidak valid menghasilkan 422 di JSON; halaman mengabaikannya dan memakai session/default. Simpan perubahan pilihan dalam session; input pesan AI membawa locale aktif secara eksplisit. Locale percakapan adalah locale terakhir.

Respons sukses JSON:

```json
{
  "schema_version": 1,
  "data": {},
  "meta": {"request_id": "uuid"},
  "error": null
}
```

Untuk daftar, `data` adalah array dan `meta` memuat cursor/pagination. Error memakai `data: null`, serta `error: {code, message, fields}`; fields kosong kecuali validasi. `code` selalu snake_case huruf kecil (mis. `validation_failed`, `turn_in_progress`, `slot_full`). Status: 401 belum login (staf), 403 kemampuan tidak ada, 404 resource milik sesi/tim lain, 409 konflik/revisi/idempotensi/slot penuh, 419 CSRF, 422 input, 429 limiter, 503 dependensi yang diperlukan tidak tersedia. Pesan diterjemahkan. Error tidak menyertakan provider body atau secret.

Cursor riwayat memakai `after_id` ID bigint pesan, order ID ascending, maksimal 50 pesan per request, dan `meta.next_after_id`. Daftar request memakai pagination 20, order updated_at descending lalu ID descending.

## Identity dan scope

**Staf.** Peran dapat lebih dari satu: `admin`, `editor`, `staff`. Anggota staf memiliki penugasan ke tim `front_of_house` dan/atau `kitchen`. Tidak ada departemen. Editor mengelola menu dan artikel info tanpa pembatasan departemen.

**Tamu.** Tamu bukan `User`. Tabel `guest_sessions`: id, public_id UUID, locale, table_label nullable, created_at, last_seen_at, expires_at. Server session menyimpan `guest_session_id`; baris dibuat **lazy** pada penulisan pertama (turn AI, draft, atau konfirmasi), bukan pada setiap page view. Masa aktif default 30 hari tidak aktif; cookie session `SESSION_LIFETIME` default 720 menit. Tamu tidak pernah menjadi sumber identitas staf atau role.

Baca artikel info dan menu terpublikasi terbuka bagi siapa pun. Pengelolaan menu/info melalui admin: admin mengelola semuanya dan menerbitkan artikel info; editor mengelola menu dan draft artikel info. Admin tanpa penugasan tim tidak memperoleh akses request.

Baca conversation hanya untuk sesi tamu pemilik. Baca request hanya untuk sesi pemilik, sesi yang telah membuka request lewat kode referensi + kontak (grant tersimpan di server session, maksimal 20), atau staf aktif yang ditugaskan ke tim request. Catatan internal hanya untuk staf tim, tidak untuk tamu atau model. Kontak tamu (nama, telepon, email) hanya dapat dibaca pemilik dan staf tim; tidak masuk prompt, log, atau metrik.

## Menu, info, dan evidence

**Menu.**
- `menu_categories`: id, code unik, sort_order, is_active; terjemahan `menu_category_translations` (category_id, locale, name).
- `menu_items`: id, slug unik, category_id, status draft/published/archived, price (integer), currency, photo_path nullable, is_available default true, is_chef_pick default false, spice_level nullable 0–3, sort_order, allergen_info_verified_at nullable, allergen_info_verified_by nullable, created_by, timestamps.
- `menu_item_translations`: item_id, locale id/en/ja, name max 120, description max 1000, aliases jsonb, kolom turunan search_name/search_description/search_keywords. Unique(item_id, locale).
- `menu_item_allergens`: item_id, allergen_code, relation `contains|may_contain`. Kode alergen tetap di config (14 alergen utama + `buckwheat`), label diterjemahkan.
- `menu_item_dietary_tags`: item_id, tag (`halal`, `vegetarian`, `vegan`, `pork_free`, `alcohol_free`).
- Publikasi mensyaratkan nama dan deskripsi tiga locale, harga > 0, kategori aktif, dan keputusan alergen eksplisit (daftar alergen boleh kosong bila `allergen_info_verified_at` diisi). Mengubah daftar alergen atau tag diet mengosongkan `allergen_info_verified_at` sampai staf memverifikasi ulang.
- Perubahan harga, ketersediaan, dan alergen dicatat audit (nilai lama dan baru, tanpa data pribadi).

**Info.** Artikel dan revisi mengikuti basis (`knowledge_articles`, `knowledge_revisions`, `knowledge_translations`) dengan perubahan: kategori `general|hours|location|policy|faq`; audience selalu umum (tabel departemen dan kolom `owner_department_id` dihapus); revisi published/archived immutable; satu published per artikel; publikasi berlaku langsung.

**Retriever.**
- `MenuRetriever::search(locale, query?, filters, limit=8)`; filters: `category`, `dietary_tags[]` (semua harus cocok), `exclude_allergens[]`, `max_price`. Hanya item published. Hasil mengurutkan item `is_available` lebih dulu. Skor sama dengan `KnowledgeRetriever` (nama frasa penuh +30, alias +20, deskripsi +10, token +3/+2/+1, NFKC, bigram CJK, maksimal 12 token). Jika `exclude_allergens` diisi, item dengan `allergen_info_verified_at` kosong **dikecualikan** dan jumlahnya dilaporkan di hasil agar AI dapat menyatakan bahwa sebagian hidangan belum dapat dinilai.
- `KnowledgeRetriever::search(locale, query, category?, limit=5)` untuk info (tanpa actor/audience).
- Evidence menu (`MenuEvidence`): item_id, locale, slug, name, description, price, currency, category_code, dietary_tags, allergens, allergen_verified (bool), is_available, photo_url, source_path server. Evidence info sama dengan basis (revision_id, version, title, excerpt ≤ 2000, effective dates, source path).

Evidence hasil model hanya boleh menunjuk item/revision yang benar-benar diberikan tool atau retrieval pada turn itu. Server memverifikasi ulang status (published, tidak diarsipkan) sebelum respons disimpan. Jika tidak ada evidence untuk pertanyaan menu atau info, hasilnya fallback tanpa klaim faktual restoran. Referensi sumber saja bukan bukti jawabannya benar; evaluasi model menilai kesesuaian isi.

Riwayat dengan sumber yang sudah tidak tersedia tidak dikirim ke model; pada history UI, turn yang terkait diganti pesan baku. Jangan membersihkan klaim dengan regex. Riwayat dengan evidence usang (harga/ketersediaan berubah) ditandai usang dan dikeluarkan dari prompt.

## Kontrak turn dan model

Input turn: `client_turn_id` UUID, `message` 1–2000 karakter, `locale`, dan context terbatas pada `scene`, `menu_item_id` nullable, `request_public_id` nullable, dan `preferences` nullable (`dietary_tags[]`, `exclude_allergens[]`, `party_size`; hanya nilai whitelist config, diaktifkan Plan 7). scene: welcome, menu, picks, reservation, order, bookings, info. Draft request dan kontak tamu tidak berada dalam scene context.

`ModelGateway::complete(ChatCompletionRequest, TurnDeadline)` menghasilkan `ChatCompletionResult` dengan text, tool_calls, finish_reason, usage yang tersedia; implementasi `CompatibleChatCompletionsGateway` serta fake untuk tes (tidak berubah dari basis).

Output model berupa JSON ketat: `{"text": "...", "source_menu_item_ids": [], "source_revision_ids": []}`. Setiap klaim tentang menu memerlukan minimal satu menu item dari evidence turn; setiap klaim tentang restoran memerlukan minimal satu revision. Server membangun kartu dari data kanonik, bukan dari teks model.

Data respons turn:

```json
{
  "turn_id": "uuid",
  "status": "completed",
  "message": {"id": 12, "role": "assistant", "text": "..."},
  "sources": [],
  "ui": [],
  "actions": [],
  "operation": null
}
```

Status terminal completed/failed; processing hanya pada respons polling. Failed menyimpan pesan fallback dan kode error pada turn; tidak menghapus pesan tamu. `operation` hanya berisi referensi draft/request hasil server; tidak boleh berupa kode executable.

UI type yang diperbolehkan: `menu_item_card`, `menu_list`, `source_card`, `availability_slots`, `request_draft`, `request_list`, `request_detail`, `request_confirmation`. Action type: `open_scene`, `open_menu_item`, `open_article`, `open_request`, `review_request_draft`. Client menggunakan named path hasil server atau ID resource, tidak mengeksekusi target arbitrary dari model. `present_slide` menghasilkan action `open_scene` (slide dari whitelist `SceneCode`).

## Request dan perubahan status

Jenis dan tim: `reservation`, `feedback`, `special_request` → tim `front_of_house`; `order` → tim `kitchen`. Jenis dan kategori tetap di enum/config; tidak editable dari CMS v1.

Draft: type, payload sesuai jenis, kontak, table_label nullable, revision integer mulai 1, expires_at 15 menit, guest_session_id, conversation nullable. Edit draft menaikkan revision dan menghasilkan fingerprint baru.

Payload per jenis:
- `reservation`: `date` (YYYY-MM-DD, timezone restoran), `slot` (HH:MM, harus slot sah), `party_size` 1–`max_party_size`, `notes` ≤ 500, `seating_preference` nullable (`indoor|outdoor|any`).
- `order`: `fulfilment` (`pickup|dine_in_preorder`), `requested_time` (HH:MM, dalam jam buka dan ≥ lead time), `items[]` (`menu_item_id`, `quantity` 1–20, `note` ≤ 200; maksimal 20 baris), `notes` ≤ 500. Harga satuan dan total dihitung server dan dibekukan saat konfirmasi.
- `feedback`: `kind` (`compliment|complaint|suggestion`), `subject` ≤ 160, `body` ≤ 5000, `related_reference` nullable.
- `special_request`: `subject` ≤ 160, `body` ≤ 2000, `date` nullable.

Kontak: `contact_name` 1–80 dan `contact_phone` (digit, +, spasi, minimal 6 digit) wajib untuk reservasi, pesanan, dan permintaan khusus; opsional untuk feedback. `contact_email` opsional.

Status dan actor:
- pending → confirmed: staf tim; reservasi tidak memeriksa kapasitas ulang (kursi sudah ditahan sejak `pending`), pesanan memeriksa ketersediaan hidangan (409 `item_unavailable`).
- pending → declined: staf tim; wajib alasan yang terlihat tamu.
- confirmed → completed: staf tim.
- confirmed → no_show: staf tim; hanya reservasi.
- pending|confirmed → cancelled: tamu pemilik atau staf tim; staf wajib alasan.
- completed, declined, cancelled, no_show adalah terminal. Tamu mengajukan perubahan dengan membatalkan lalu membuat request baru (tanpa edit pasca-konfirmasi di v1).

Pesan publik dapat dikirim tamu pemilik dan staf selama request belum terminal. Catatan internal hanya staf. State transition, assignment, kapasitas, dan audit terjadi dalam transaksi singkat dengan row lock; tidak ada HTTP model di dalam transaksi.

Kapasitas reservasi: `reservation_slot_usage` (date, slot_start, booked_seats; unique date+slot_start). Konfirmasi draft reservasi mengunci baris slot (`lockForUpdate`, dibuat dengan upsert), memastikan `booked_seats + party_size <= slot_capacity`, lalu menambah `booked_seats`. Request `pending` dan `confirmed` menghitung kursi; transisi ke `declined`, `cancelled`, `no_show`, atau `completed` melepaskan kursi pada transaksi yang sama. Slot penuh menghasilkan 409 `slot_full`.

Idempotency key berupa UUID untuk konfirmasi draft, pesan request, pembatalan, dan transisi. Unik per aktor (guest_session atau user) + operation type + key. Fingerprint adalah SHA-256 JSON canonical dengan key terurut dan nilai tervalidasi, termasuk aktor/resource/revision yang relevan tetapi tanpa idempotency key. Payload identik mengembalikan hasil pertama; key sama dengan payload berbeda menghasilkan 409. Tabel receipt menyimpan fingerprint dan resource result, tidak seluruh respons sensitif.

Referensi request `RST-YYYYMMDD-XXXXXXXX` (random suffix, unique, retry collision maksimal tiga kali). Referensi hanya label; membuka request lewat referensi memerlukan kontak yang cocok (telepon dinormalisasi ke digit, atau email lowercase) dan dibatasi limiter.

## Konfigurasi baseline

`config/restaurant.php`: brand FTS Restaurant AI, timezone Asia/Jakarta, supported locales, default locale, mata uang, retention, asset slide, `reservation` (slot_interval_minutes 30, slot_capacity 20, max_party_size 10, min_lead_minutes 60, max_advance_days 30; jam buka per hari disimpan di pengaturan restoran), dan daftar kode alergen/diet.
`config/assistant.php`: enabled, deadline 60 detik, connect timeout 5 detik, max completion calls 7, max tool rounds 6, max tool calls per response 5, max total invocations 12, history maksimal 20 pesan dan 12000 karakter, evidence menu maksimal 8 item dan info maksimal 5 kutipan masing-masing 2000 karakter, max_tokens 2048, temperature 0.3, ditambah batas tamu (di bawah).
`config/services.php`: LOCAL_LLM_BASE_URL, LOCAL_LLM_API_KEY, LOCAL_LLM_MODEL, dan opsional LOCAL_LLM_CA_BUNDLE. Kemampuan provider opsional seperti `reasoning_effort` diatur lewat `ASSISTANT_REASONING_EFFORT`.

Base URL wajib berupa origin/base path tanpa suffix `/v1` atau `/chat/completions`; endpoint ditambahkan tepat sekali. HTTPS wajib di production; localhost HTTP diperbolehkan local/testing.

Limit default:
- Login staf 5/menit per email-normalized+IP.
- AI tamu: 8/menit per sesi tamu, 30/menit per IP sebagai backstop, 40 giliran per sesi per hari (`ASSISTANT_GUEST_DAILY_TURNS`), 2000 giliran per hari global (`ASSISTANT_DAILY_TURN_CAP`); melewati batas menghasilkan fallback `ai_budget_exhausted` (jelajah dan form tetap berfungsi), bukan error halaman.
- Penulisan request tamu 10/menit per sesi; pencarian lewat kode referensi 10/menit per IP dan 5 kegagalan berturut-turut per referensi mengunci pencarian 15 menit.
- Penulisan staf 20/menit per user.
- Honeypot field tersembunyi pada form tamu; isian honeypot menolak dengan 422 tanpa petunjuk.
- Budget concurrent AI satu per percakapan; lease percakapan 75 detik, deadline 60 detik, client timeout 70 detik. Provider POST tidak otomatis di-retry pada v1; retry tamu memakai client_turn_id yang sama.

## Verifikasi dan referensi

Sesuai [kebijakan pengujian](README.md#kebijakan-pengujian), Plan 1–7 hanya implementasi dan menutup tahap dengan checklist untuk pengguna. Plan 8 membangun dan menjalankan pemeriksaan dari root proyek: `php artisan test --compact`, `vendor/bin/pint --test`, `npm run lint:ui`, `npm run test:ui`, dan `npm run build`. Windows dapat menjalankan Pint melalui `php vendor/bin/pint --test`. Feature/integration memakai database PostgreSQL testing terpisah; jangan menunjuk database development/production. Provider nyata tidak dipanggil CI.

Referensi framework: [routing](https://laravel.com/framework/docs/13.x/routing), [authorization](https://laravel.com/framework/docs/13.x/authorization), [HTTP client](https://laravel.com/framework/docs/13.x/http-client), [locking/transaksi](https://laravel.com/framework/docs/13.x/queries#pessimistic-locking), [rate limiting](https://laravel.com/framework/docs/13.x/rate-limiting). Referensi menjelaskan primitives; kontrak di atas adalah keputusan proyek.

## Penyimpangan yang diputuskan saat implementasi (7 Oktober 2026)

Dicatat agar kontrak dan kode tidak berbeda diam-diam. Alasan lengkap ada di catatan hasil plan terkait.

| Topik | Kontrak awal | Implementasi | Plan |
|---|---|---|---|
| Throttle login | Limiter bernama | Tetap di `LoginRequest` (per email + IP, 5 percobaan) | 2 |
| Pembuatan sesi tamu | Limiter per IP sebelum baris dibuat | Satu limiter `guest-session-create` (30/menit per IP) pada endpoint tulis; baris dibuat lazy; lookup sukses juga membuat baris agar grant tersimpan | 2, 6 |
| Staf tanpa tim | Tidak disebut | Sah; tidak menangani request apa pun. Tim hanya boleh diberikan bersama peran staf | 2 |
| `reserved_slot`, `slot_start` | Jam | String `HH:MM` zona restoran | 6 |
| `request_drafts.guest_session_id` | Wajib | Nullable (null saat sesi tamu dipurge) | 6 |
| Hidangan habis saat konfirmasi | 409 `item_unavailable` | Sama; pemeriksaan dipindah sebelum validasi umum supaya tidak menjadi 422 | 6 |
| Filter diet | Cocok tag | Mensyaratkan verifikasi alergen, seperti filter alergen | 4 |
| Skenario eval | 12 menu + 30 total | Tabel menjumlahkan 34; menu dipangkas menjadi 8 agar 30 per bahasa (90 total) | 8 |
| CSP | `script-src 'self'` | `script-src 'self' 'unsafe-eval'` (build Alpine standar), `style-src-attr 'unsafe-inline'` untuk custom property slide; tanpa `x-html` | 8 |
