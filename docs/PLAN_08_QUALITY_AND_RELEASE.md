# Plan 8 Evaluasi, hardening publik, dan persiapan rilis

| Metadata | Nilai |
|---|---|
| ID | 8 |
| Status | Implemented |
| Dependensi | [Plan 7](PLAN_07_AI_TOOLS_AND_WORKFLOWS.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Acuan | [Master](000_MASTER_PLAN.md), [OPERATIONS](OPERATIONS.md), [evals](evals/README.md) |
| Hasil implementasi | [Catatan hasil](#catatan-hasil-implementasi) (8 Oktober 2026) |

## Hasil yang harus tersedia

Seluruh kemampuan v1 diverifikasi, memiliki retensi/recovery, metrik aman, hardening untuk situs publik, serta paket deployment staging/production yang dapat digunakan. Sesuai [kebijakan pengujian](README.md#kebijakan-pengujian), plan ini adalah satu-satunya tahap yang membangun dan menjalankan pengujian: infrastruktur tes, lint UI, CI, seluruh skenario tes Plan 1–7, review UI, dan evaluasi model. Plan 1–7 berstatus Implemented sampai lulus verifikasi di sini, lalu menjadi Done. Menjalankan plan ini membuat aplikasi siap rilis; publikasi nyata tidak dilakukan otomatis tanpa instruksi deployment dan target yang tersedia.

## Basis kode yang diwarisi

Dipakai ulang: `Modules/Operations` (`OperationsMetrics`, `PurgeExpiredData`, `Heartbeat`, `Readiness`), `/admin/operations`, `/api/v1/admin/readiness`, `deploy/*` (nginx, supervisor, PHP ini, env, backup/restore), `docs/OPERATIONS.md`, `.github/workflows/ci.yml`, `scripts/check-ui-conventions.mjs`, `scripts/generate-visual-assets.py`, `TestDatabaseGuard`, trait `TalksToAssistant`, worker `tests/Support/concurrent-action.php`, `tests/js/*` (api-client, safe-card-renderer, ui-conventions), `EvaluateAssistantCommand`.

Diubah: seluruh tes `tests/Feature|Unit` bertema corporate diganti sesuai katalog di bawah; `BuildsRestaurantData`; `restaurant:eval`, `restaurant:purge-data`, `restaurant:recover-turns`; metrik operasi; korpus eval; aset visual; dokumen OPERATIONS.

Dihapus: korpus, artikel, dan hasil eval corporate (sudah dibersihkan dari `docs/evals`), tes HR/IT.

## Konfigurasi dan artefak operasional

Tambahkan/perbarui:
- `docs/OPERATIONS.md`: setup production, create-admin, backup/restore, queue/scheduler, recovery, insiden (kerangka warisan diperbarui untuk restoran).
- `deploy/nginx.conf.example`: root public, PHP-FPM, hanya index.php sebagai entry PHP, HTTPS template, larang dotfiles/env, fastcgi timeout 80 s, **header keamanan** (lihat Hardening publik), batas ukuran body 3 MB (foto menu), kompresi untuk aset statis.
- `deploy/supervisor.conf.example`, `deploy/php-production.ini.example` (PHP 8.4, ekstensi `gd` dan `intl` aktif), `deploy/env.production.example`, `deploy/scripts/backup.sh` dan `restore-staging.sh` (PostgreSQL custom-format; secret dari environment tanpa password CLI/log; restore hanya ke staging/testing).
- `php artisan storage:link` pada langkah rilis; direktori `storage/app/public/menu` ikut backup (foto menu) dengan jadwal sama dengan dump database.
- `docs/evals/`: korpus sintetis 90 skenario dan hasil bertanggal; bukan data restoran nyata.

Input deployment yang harus tersedia: hostname/domain, server Linux dengan PHP 8.4/PostgreSQL 17, kredensial database, APP_KEY, URL/model/key provider **yang dapat dijangkau publik** (keputusan terbuka Plan 5), TLS, daftar proxy tepercaya (CDN/reverse proxy), dan lokasi backup private. Ini input environment, bukan keputusan arsitektur yang boleh diganti AI. Tanpa input tersebut, selesaikan artefak/runbook dan catat deployment belum dilakukan.

`SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `same_site=lax`, `APP_ENV=production`, `APP_DEBUG=false`, log retention terkonfigurasi. Provider HTTPS wajib. Database hanya dapat diakses host aplikasi/worker. Jangan menyalin compose dev untuk hosting production.

## Hardening situs publik

Situs ini terbuka tanpa login, sehingga bagian ini menjadi gate rilis.

1. **IP klien yang benar.** Konfigurasikan `TrustProxies` untuk proxy/CDN yang dipakai; verifikasi limiter `guest-assistant`, `guest-lookup`, dan login memakai IP klien nyata, bukan IP proxy (kesalahan umum yang membuat satu IP memblokir semua tamu atau sebaliknya).
2. **Header keamanan** (middleware atau nginx): `Content-Security-Policy` ketat (`default-src 'self'`; `style-src 'self'`; `img-src 'self' data:`; `connect-src 'self'`; `frame-ancestors 'none'`; tanpa `unsafe-inline` untuk script; sumber gambar remote tidak diizinkan di production). **Catatan Alpine:** build standar Alpine 3 mengevaluasi ekspresi dengan `new Function`, sehingga `script-src 'self'` saja akan merusak UI dan memerlukan `'unsafe-eval'`. Putuskan di plan ini salah satu: (a) migrasi ke build `@alpinejs/csp` (ekspresi inline dibatasi; modul `Alpine.data` yang sudah ada tetap dipakai, ekspresi kompleks di template dipindah ke method) sehingga `script-src 'self'` cukup, atau (b) terima `'unsafe-eval'` dengan alasan tercatat dan kompensasi (escaping ketat, tanpa `x-html`). Skrip JSON konteks (`<script type="application/json">`) bukan skrip eksekusi dan tetap diizinkan. Tambahan header: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (matikan kamera/mikrofon/geolokasi), `Strict-Transport-Security` di HTTPS.
3. **Anggaran AI.** Verifikasi `ASSISTANT_GUEST_DAILY_TURNS` dan `ASSISTANT_DAILY_TURN_CAP` bekerja, alert pemakaian ≥ 80% kapasitas global, dan fallback `ai_budget_exhausted` tidak mematikan jelajah/form.
4. **Anti-penyalahgunaan.** Uji honeypot, limiter, penguncian pencarian referensi, dan pembuatan sesi tamu lazy (page view massal tidak membuat baris DB). Keputusan CAPTCHA pihak ketiga dicatat; jika dipasang, lewat `GuestChallenge` Plan 5.
5. **Privasi.** Kontak tamu tidak ada di log, metrik, prompt, notifikasi, atau URL; purge retensi menghapus kontak bersama request; halaman status tidak membocorkan keberadaan referensi.
6. **Crawler dan SEO.** `public/robots.txt` mengizinkan `/`, `/menu`, `/info`; melarang `/api`, `/admin`, `/bookings`, `/login`. Halaman menu dan info mempunyai `<title>`, meta description, canonical, dan Open Graph per locale; Welcome/Info menyertakan JSON-LD `Restaurant` dari pengaturan restoran (nama, jam buka, tautan kontak) bila tersedia. Tautan `hreflang` untuk id/en/ja.
7. **Aset.** Production memakai `SCENE_IMAGE_SOURCE=local`; tidak ada gambar/font/script remote; foto menu dari disk `public` hasil re-encode.

## Recovery dan retensi

Implementasikan command:
- `restaurant:recover-turns`: tandai processing melewati lease 75 s sebagai failed `turn_interrupted`, tulis fallback sekali, lepaskan lease milik turn dengan pemeriksaan ownership. Jadwalkan setiap menit.
- `restaurant:purge-data --dry-run`: tampilkan jumlah record yang memenuhi expiry tanpa isi personal.
- `restaurant:purge-data`: tiap hari 02:00 Asia/Jakarta dengan withoutOverlapping, onOneServer, batch 200 dan transaksi pendek.

Retensi baseline: percakapan 30 hari sejak `last_activity_at` (turn/pesan/evidence/invocation ikut); request dan pesan 180 hari sejak `terminal_at` (kontak tamu ikut terhapus); draft belum consumed 24 jam setelah `expires_at`; consumed draft mengikuti umur request; sesi tamu 30 hari sejak `last_seen_at` (percakapan ikut terhapus, request tidak, `guest_session_id` menjadi null); baris `reservation_slot_usage` lebih tua dari 60 hari; `assistant_usage` 90 hari; receipt terkait request ikut request, receipt lain 365 hari; audit 365 hari. Jangan hapus request `pending`/`confirmed` berdasarkan tanggal dibuat.

Urutan penghapusan request tertutup: request (messages, events, receipts, notifikasi) → draft (hindari FK sirkular) → baru sesi tamu. FK conversation/turn pada request/draft null-on-delete. Uji cleanup terhadap FK sirkular draft-request, bukan hanya mengasumsikan cascade. Tidak membuat fitur penghapusan staf otomatis.

## Observabilitas dan readiness

Log terstruktur: request_id, turn_id internal, guest_session public_id atau user ID, prompt/schema version, provider/model alias, latency, result code, tool name/status, loop count, usage jika tersedia, operation reference. Jangan log API key, bearer header, provider body, prompt penuh, transkrip, kontak tamu, atau isi pesan request.

`/admin/operations` (admin) dengan metrik 7 hari: completed/failed turn, p50/p95 latency, timeout, `ai_budget_exhausted`, hit limiter, tool validation failures, pencarian menu tanpa hasil, request per jenis dan status, pending terlama, pemakaian kursi vs kapasitas hari ini. Agregat hanya hitungan; tanpa akses isi request lewat metrik.

`/api/v1/admin/readiness`: DB/cache, setting provider, heartbeat scheduler/worker, ketersediaan `storage/app/public`, dan status `storage:link`. Tidak memanggil model pada setiap health request. `/up` tetap health dasar; AI tidak tersedia tidak mematikan jelajah menu/info maupun form request.

Alert pilot: error AI >10% dari minimal 20 turn dalam 1 jam; p95 turn >55 s dari minimal 20 turn; request `pending` tanpa penanganan >2 jam pada jam buka (ambang dapat dikonfigurasi); pemakaian anggaran global ≥ 80%. Runbook menjelaskan pemeriksaan; tidak mengirim pesan eksternal karena kanal itu belum diintegrasikan.

## Evaluasi kualitas dan skenario end-to-end

Korpus 30 skenario per bahasa (id/en/ja), dibangun dari menu dan artikel info sintetis berlabel `[EVAL]`:

| Kategori | Jumlah | Contoh |
|---|---|---|
| menu | 12 | rekomendasi, harga, deskripsi, ketersediaan, filter diet |
| info | 4 | jam buka, lokasi, kebijakan |
| allergen | 4 | hidangan terverifikasi, belum diverifikasi, pengecualian alergen, "apakah aman untuk alergi X" |
| no_answer | 4 | di luar topik/ambigu/tidak ada di menu |
| forbidden | 5 | prompt injection, minta status request orang lain, minta diskon/ubah harga, minta konfirmasi/ubah status lewat teks, minta nasihat medis |
| availability_status | 3 | cek slot, status request sendiri |
| draft | 2 | reservasi dan pesanan sampai draft (tanpa konfirmasi) |

Expected result setiap skenario memuat sumber/aktor sah, jawaban atau fallback yang diharapkan, serta efek domain yang boleh/tidak boleh terjadi. Target rilis: ≥27/30 benar di setiap bahasa; **semua** skenario allergen dan forbidden lulus (nol klaim alergen/aman tanpa data terverifikasi, nol hidangan belum terverifikasi lolos filter pengecualian); nol request dibuat tanpa klik konfirmasi; nol duplikasi efek saat retry; harga pada jawaban sama dengan DB. Detail dan perintah di [evals/README.md](evals/README.md).

Jalankan evaluasi model nyata terpisah dari CI. Catat provider/model alias, prompt version (`restaurant-v1`/`v2`), tanggal, usage bila tersedia, hasil, dan contoh kegagalan tanpa secret. Bandingkan minimal satu model instruksi standar dengan kandidat produksi (khususnya bila memakai model berlabel *uncensored*). Jangan mempromosikan hasil fake menjadi akurasi model. Jika credential/provider belum tersedia, tandai belum dijalankan dan jangan menyatakan pilot lulus.

## Aset visual final

Plan 3–7 memakai gambar placeholder acak dan brand mark teks. Tahap ini menyelesaikan aset visual sebelum rilis ([VISUAL_ASSETS.md](VISUAL_ASSETS.md)):
- Ilustrasi/foto tujuh slide dan karakter host di `public/assets/restaurant/` (SVG atau gambar teroptimasi), konsisten dengan palet [UI_FOUNDATION](UI_FOUNDATION.md). Sumber/lisensi tiap aset dicatat; tidak memakai aset bermerek pihak lain.
- Logo/brand mark untuk `x-navigation.brand`, favicon, ikon aplikasi, dan `og-image`.
- Foto hidangan disediakan restoran melalui CMS (bukan bagian repo); ikon kategori sebagai fallback tanpa foto.
- Gaya ilustrasi, karakter, dan logo dikonfirmasi pengguna lebih dulu, lalu `SCENE_IMAGE_SOURCE=local` diaktifkan; view/JS tidak berubah karena memakai resolver Plan 3.
- Gate rilis: production tidak memakai mode placeholder atau URL remote; fallback gambar tetap berfungsi; teks di atas gambar memenuhi kontras ≥ 4.5:1.

## Infrastruktur pengujian dan CI

Kerjakan sebelum menjalankan katalog:
- phpunit.xml/env testing memakai PostgreSQL 17 `restaurant_ai_test`; `.env.testing` tidak dilacak. Base TestCase menolak reset database bila nama database tidak berakhiran `_test`. SQLite bukan baseline tes; bila dipakai untuk iterasi cepat hanya `:memory:`/`*_test.sqlite` dan tidak menggantikan run PostgreSQL.
- Pastikan role/database PostgreSQL lokal tersedia; jalankan migrate pada PostgreSQL dan catat perbedaan perilaku.
- Composer script `test` (config:clear lalu artisan test) dan npm `test:ui` (Node built-in test runner).
- `scripts/check-ui-conventions.mjs` dan `npm run lint:ui` sesuai [UI_FOUNDATION §6](UI_FOUNDATION.md#6-pemeriksaan-konsistensi-ui); hanya Node built-ins.
- GitHub Actions: PostgreSQL 17, PHP 8.4 (ekstensi `gd`, `intl`), Node 22: `composer validate --strict`, composer install dari lock, PHPUnit, `php vendor/bin/pint --test`, npm ci, lint:ui, test:ui, build, composer audit, `npm audit --omit=dev`.
- HTTP model memakai fake dengan URL tepat dan `preventStrayRequests`; waktu di-freeze untuk tes expiry dan slot. Provider nyata tidak dipanggil CI.

## Katalog pengujian per plan

Setiap plan baru Done bila skenarionya lulus atau penyimpangannya dicatat. Interaksi browser yang tidak diotomasi dicatat sebagai review manual.

### Plan 1 setup dan fondasi UI
- SetupSmokeTest: `/`, `/up`, koneksi DB testing terpisah, locale query/session/default, header request_id, tidak ada rujukan config `corporate`.
- UiPreviewTest: `/dev/*` 200 di local/testing dan 404 di production; label id/en/ja; escaping konten sintetis; satu bundle/CSRF meta/toast region per halaman.
- UiComponentContractTest: button, link-button, field/FieldState, escaping prop/slot, variant tidak dikenal gagal di local/testing.
- ApiEnvelopeTest: sukses dan 401/403/404/409/419/422/429/503/500 dengan kode error terjemahan, request_id, `Retry-After` pada 429, tanpa bocor detail internal.
- API helper (Node): same-origin, CSRF, envelope, 401/419 event, 422 fields, 429, tanpa retry write.
- Review manual gallery 375×812, 768×1024, 1440×900: keyboard/focus, drawer, modal, dropdown, tabs, toast, kontras palet restoran ≥ 4.5:1, reduced motion.

### Plan 2 akun staf dan sesi tamu
- IdentityAccessTest: login/logout, email case, akun nonaktif, password change wajib (halaman + JSON 403), throttling, admin/non-admin, assignment role/tim invalid, perlindungan admin terakhir, audit tanpa password, staf dengan dua tim.
- Reset password/deactivation menutup sesi; ganti password menjaga sesi saat ini; perlindungan admin terakhir dengan dua koneksi PostgreSQL bersamaan.
- GuestSessionTest: page view tidak membuat baris; sesi dibuat lazy saat penulisan pertama; `?table=` valid/tidak valid; staf yang membuka halaman tamu tidak membawa hak staf; sesi tamu tidak bisa mengakses `/admin`.
- GuestLimiterTest: limiter per sesi dan IP, `Retry-After`, honeypot menolak 422, kunci pencarian referensi.
- `restaurant:create-admin` dan `restaurant:demo-accounts` (menolak production); menu admin per role.

### Plan 3 pengalaman presentasi
- ExperienceSceneTest: tujuh route publik, locale valid/default/invalid, label tiga bahasa, `table_label`, context sesuai slide, `/admin` mengalihkan ke login.
- Resolver gambar: placeholder dengan seed tetap dan tanpa data pengguna; local menunjuk `public/assets/restaurant`; production menolak placeholder.
- RestaurantSettingsTest: hanya admin, validasi panjang/locale, escaping, whitelist `contact_links` (WhatsApp digit, handle Instagram, host Maps allowlist), audit.
- Review manual 375×812, 768×1024, 1440×900: tab bar bawah, bubble presenter, copilot layar penuh, keyboard, kontras, fallback gambar, reduced motion, narrator tanpa autoplay.

### Plan 4 CMS menu dan info
- MenuPublicationTest: draft incomplete, publikasi lengkap, publish bersamaan, archive, izin editor/admin/staf (ketersediaan), slug terkunci.
- **AllergenSafetyTest**: mengubah alergen/tag diet mengosongkan verifikasi; kartu tanpa klaim bila belum terverifikasi; `exclude_allergens` mengeluarkan `contains`, `may_contain`, dan item belum terverifikasi dengan `unverified_excluded_count`; API publik tidak membocorkan alergen item belum terverifikasi.
- MenuRetrievalTest: query id/en/ja, alias, NFKC, bigram CJK, ranking dan tie-break, filter diet/kategori/harga, item habis berurutan terakhir, wildcard LIKE di-escape, draft/arsip tidak muncul.
- MenuPhotoUploadTest: tipe/ukuran/dimensi sah dan tidak sah, re-encode WebP tanpa EXIF, path tersimpan.
- InfoKnowledgeTest: publikasi/arsip revisi, hanya admin yang menerbitkan, kategori, API `/api/v1/info`, payload XSS tampil sebagai teks.
- Audit harga/ketersediaan/alergen; format harga per locale.

### Plan 5 percakapan dan AI
- ConversationOwnershipTest, ConversationRetryTest, ModelGatewayTest, GroundedAnswerTest, ContextLifecycleTest, AssistantBudgetTest.
- Kasus: malformed JSON, finish length, timeout, 429/500 provider, tool call saat registry kosong, source palsu, no source, locale switch, hidangan diarsipkan di tengah percakapan, dua tab, stale lease, duplikat submit, response hilang, anggaran harian per sesi dan global habis (tanpa memanggil model), honeypot terisi. Dedup/lease diuji di PostgreSQL.
- Gateway: endpoint/model/format request sesuai provider; `reasoning_effort` hanya bila dikonfigurasi; base URL dengan `/v1` atau HTTP di production ditolak; `LOCAL_LLM_CA_BUNDLE`; balasan deterministik tanpa model; history `stale`/`unavailable`; `chat.js` (retry key sama, polling 202, 404 membuang ID) dan `safe-card-renderer`.
- Prompt: klaim alergen tanpa data terverifikasi diganti fallback oleh validator/evaluasi; teks tamu berisi instruksi tidak mengubah aturan.
- Smoke provider nyata opt-in dengan data sintetis; tanpa credential dicatat belum dijalankan.

### Plan 6 reservasi, pesanan, dan feedback
- RequestDraftTest, RequestWorkflowTest, StaffRequestAccessTest, RequestLookupTest, RequestNotificationTest, ReservationCapacityTest.
- Kasus: type/payload invalid per jenis, slot di luar jam buka/tanggal tutup/lead time, party_size melebihi batas, draft expired/edited, konfirmasi ganda dengan key sama maupun berbeda, konfirmasi bersamaan, version stale, transisi ilegal, staf tim lain, isolasi catatan internal, replay pesan, balasan setelah terminal, pembatalan tamu, kehilangan respons, pesanan dengan hidangan habis (409 `item_unavailable`), harga order dibekukan dan tidak mengikuti perubahan harga berikutnya.
- **ReservationCapacityTest** (PostgreSQL, dua proses/koneksi nyata tanpa transaksi pembungkus): dua konfirmasi bersamaan pada sisa kursi terakhir hanya satu berhasil (`slot_full` untuk lainnya); pembatalan/penolakan/no-show melepaskan kursi; `booked_seats` tidak negatif; satu efek event/notifikasi.
- RequestLookupTest: referensi + kontak benar menambah grant; salah menghasilkan respons seragam; penguncian setelah 5 kegagalan; grant maksimal 20; request orang lain tidak dapat dibuka lewat ID/UUID.
- Notifikasi staf tidak memuat kontak/isi pesan; path sesuai akses; `x-request-thread` berhenti polling saat tab tersembunyi/offline; limiter `guest-writes`.
- Manual: alur tamu → staf → tamu termasuk polling 5 detik.

### Plan 7 tool AI dan workflow
- ToolRegistryTest: semua schema, unknown tool/key, JSON rusak, enum/range invalid, scope sesi, tidak ada argumen kontak, penolakan side effect.
- ToolLoopTest: protokol `tool_call_id`, limit ronde/call/invocation, deadline, provider tanpa tool (`tool_capability_unavailable`).
- PreferenceFilterTest: server menggabungkan preferensi alergen/diet ke `search_menu` walau model menghilangkannya; nilai preferensi di luar whitelist 422.
- RestaurantJourneyTest: pertanyaan menu → kartu → minta reservasi → draft sekali → isi kontak di review → konfirmasi tombol → request `pending` → staf mengonfirmasi → tamu melihat status. Replay turn, provider gagal setelah draft, draft berbeda dalam satu turn, `slot_full` saat konfirmasi, XSS seluruh kartu. Fake sequence membuktikan wire format; manual journey tiga bahasa.
- "ya" di chat tidak membuat request; `present_slide` tidak memindahkan deck otomatis.

### Hardening publik (Plan 8 sendiri)
- Header CSP/keamanan hadir dan tidak memblokir aset atau Alpine sendiri (sesuai keputusan build Alpine di Hardening butir 2); tidak ada sumber remote di production.
- Limiter memakai IP klien nyata di belakang proxy tepercaya.
- `robots.txt`, canonical/hreflang, JSON-LD valid; `/bookings`, `/admin`, `/api` tidak diindeks.
- Purge: sesi tamu kedaluwarsa menghapus percakapan tetapi mempertahankan request; kontak dihapus bersama request tertutup lewat retensi.

## Checklist verifikasi teknis

1. Bangun infrastruktur di atas, tulis tes dari katalog, jalankan seluruh PHPUnit terhadap PostgreSQL testing (termasuk concurrency terpisah), composer validate, Pint check, `npm run lint:ui`, `npm run test:ui`, build, composer audit, dan audit npm runtime. Temuan audit ditriase berdasarkan paket/jalur aktual.
2. Fresh install pada database staging kosong dengan migrate + reference seed (bukan `migrate:fresh` pada database berisi data); buat admin lewat command secret prompt; `storage:link`.
3. Restore backup ke staging dan ulangi journey menu → draft → konfirmasi → konfirmasi staf → status dalam tiga bahasa, termasuk isolasi tim dan hidangan diarsipkan.
4. Verifikasi `/dev/*` 404 di production, aset final aktif tanpa placeholder remote, header keamanan, mobile/desktop, keyboard, reduced motion, CSRF/session expiry, XSS kartu/teks staf, offline/poll recovery, no-provider fallback, dan anggaran AI.
5. Build/config/route cache pada staging, restart worker bila ada, uji scheduler dan retensi dry-run sebelum purge dijadwalkan.
6. Pilot dengan akun perwakilan admin/editor/staf front_of_house/staf kitchen dan data sintetis dulu; lalu menu nyata dengan alergen terverifikasi oleh restoran. Catat masalah dan perbaikannya.

## Kriteria selesai dan rollout

Artefak deployment, runbook, tes recovery/retensi, katalog pengujian Plan 1–7, suite CI lokal, UI review, dan gate hardening publik harus selesai; status Plan 1–7 diperbarui ke Done sesuai hasilnya. Pilot/production hanya dicatat lulus bila benar-benar dijalankan pada target.

Rollout: staging → pilot terbatas (mis. satu cabang/jam makan tertentu, QR di beberapa meja) → perluasan setelah quality gate lulus. Rollback aplikasi memakai release sebelumnya; migration data memakai forward fix. Backup sebelum migrasi production. Jangan otomatis membuat commit, push, atau publish dari instruksi menulis plan.

[Deployment Laravel](https://laravel.com/framework/docs/13.x/deployment) menjelaskan kebutuhan server serta caching; nilai timeout, jadwal, dan gate di atas adalah keputusan proyek.

## Catatan hasil implementasi

Dikerjakan 7–8 Oktober 2026. Status: **Implemented**, dengan batas verifikasi yang dijelaskan di "Kendala". Plan 1–7 tetap **Implemented** (belum Done) karena run PostgreSQL, evaluasi provider nyata, dan review perangkat nyata belum bisa dilakukan.

**Artefak yang dibuat**
- Operasi: `Operations/Queries/OperationsMetrics` (turn, latensi p50/p95, timeout, anggaran, hit limiter, validasi tool, request per jenis/status, pending terlama, kursi vs kapasitas, empat alert), `Support/RateLimitHits` (hitung 429 per hari, tanpa identitas), `Support/Readiness` (+ cek storage dan `storage:link`), `Retention/PurgeExpiredData` (ditulis ulang untuk skema restoran; urutan request → draft → percakapan → sesi, batch 200), `/admin/operations`, `GET /api/v1/admin/readiness` (503 hanya bila cek wajib gagal), command `restaurant:purge-data` dan `restaurant:recover-turns`, `OPS_PENDING_ALERT_MINUTES`.
- Hardening: middleware `SecurityHeaders` (CSP, nosniff, Referrer-Policy, Permissions-Policy, X-Frame-Options, HSTS hanya di HTTPS), `TRUSTED_PROXIES` untuk IP klien, `robots.txt`, komponen `x-seo` (canonical, hreflang, description, Open Graph, `noindex` untuk halaman privat), `RestaurantJsonLd` (Welcome dan Info), `og-image.png`.
- Evaluasi: `docs/evals/build_corpus.py` → `menu.json` (24 hidangan), `info.json` (6 artikel), `corpus.json` (90 skenario); `restaurant:eval` ditulis ulang (sesi tamu baru per skenario, pemeriksaan sumber/fakta/fallback/efek domain, laporan JSON + Markdown).
- Deploy: `deploy/nginx.conf.example` (body 3 MB, kompresi, header aset), `php-production.ini.example` (gd/intl/exif, upload 3 MB), `env.production.example` (`TRUSTED_PROXIES`, `SECURITY_*`, `OPS_PENDING_ALERT_MINUTES`), `backup.sh`/`restore-staging.sh` (foto menu ikut), `OPERATIONS.md`, README, `UI_COMPONENTS.md`, catatan penyimpangan di `TECHNICAL_CONTRACTS.md`.
- Pengujian: `tests/` ditulis ulang untuk restoran (di luar pengujian generik yang dipertahankan): Identity, GuestSession, ExperienceScene, RestaurantSettings, MenuPublication, AllergenSafety, MenuRetrieval, MenuPhotoUpload, InfoKnowledge, Conversation, RequestDraft, RequestWorkflow, StaffRequestAccess, RequestLookup, RequestNotification, ReservationCapacity, ToolRegistry, ToolLoop, RestaurantJourney, Hardening, Operations, Retention, EvaluateCommand, BladeConventions, PostgresConcurrency (+ worker `tests/Support/concurrent-action.php`); JS: api-client, safe-card-renderer, cart, request-wizard, ui-conventions. `phpunit.xml` memakai `restaurant_ai_test` dan memory 512M; CI memasang `gd` dan `exif`.

**Hasil pemeriksaan (8 Oktober 2026)**

| Pemeriksaan | Hasil |
|---|---|
| PHPUnit pada **SQLite `:memory:`** | 361 tes, 2143 assertion, lulus; 8 dilewati (grup `postgres`, hanya jalan di PostgreSQL) |
| PHPUnit pada PostgreSQL 17 | **Belum dijalankan** (password role `restaurant_ai` tidak tersedia) |
| Tes konkurensi | Worker diuji berjalan pada SQLite file hanya untuk memastikan skripnya benar; kegagalan di sana adalah "database is locked" milik SQLite. Bukti konkurensi baru sah setelah run PostgreSQL |
| `pint --test`, `npm run lint:ui` (112 berkas), `npm run test:ui` (49 tes), `npm run build` | Lulus |
| `composer validate --strict`, `composer audit`, `npm audit --omit=dev` | Lulus (lock diperbarui hash-nya saja; versi paket tidak berubah) |
| Review browser (Chrome desktop, satu lebar sempit ~390 px, akun sintetis, SQLite lokal) | Dilakukan: Welcome, Menu, keranjang, Pesanan, wizard Reservasi sampai konfirmasi, halaman Booking dan thread, panel chat (tanpa provider), login staf, antrean dan detail request, kirim pesan, ubah status, polling tamu, Operasional, form Menu dan Pengaturan |

**Temuan dari pengujian dan browser yang sudah diperbaiki**
1. Hidangan habis saat konfirmasi pesanan menghasilkan 422, bukan 409 `item_unavailable` (validasi umum mendahului) → pemeriksaan dipindah.
2. Lookup referensi + kontak yang berhasil tidak membuat baris sesi tamu, sehingga halaman/API tetap menolak request yang baru dibuka → lookup sukses membuat baris (gagal tetap tidak).
3. `OperationsMetrics` error karena `type`/`status` sudah enum (halaman `/admin/operations` akan 500).
4. `SessionGrants` melempar di luar HTTP (command `restaurant:eval`, console) → kosong bila tidak ada sesi.
5. `@js(...)` di dalam tag komponen (`<x-ui.button …>`) tidak dikompilasi Blade: tombol status staf dan tombol "Tambah ke pesanan" tidak bekerja di browser → diganti `{{ Js::from(...) }}`; dijaga oleh `BladeConventionsTest`.
6. Thumbnail slide desktop berukuran 6×4 px (tombol tidak melebar) → `width: 100%`.
7. Label slot "Penuh" juga muncul untuk slot yang hanya melewati batas lead time → label "Tidak tersedia" terpisah.
8. `composer.lock` tidak sinkron dengan `composer.json` sejak Plan 1 → `composer update --lock`.

**Penyimpangan keputusan**
- **CSP:** opsi (b) dipilih. Build Alpine standar membutuhkan `'unsafe-eval'`; migrasi ke `@alpinejs/csp` memaksa semua ekspresi inline UI dipindah ke method dan tidak bisa diverifikasi tanpa review browser menyeluruh. Kompensasi: tanpa `x-html`/`innerHTML` (diuji), semua teks lewat `x-text`, tidak ada sumber remote. `style-src-attr 'unsafe-inline'` diperlukan untuk custom property slide.
- **Skenario eval:** tabel awal menjumlah 34, bukan 30; menu dipangkas dari 12 menjadi 8.
- **CAPTCHA:** tidak dipasang (honeypot, limiter, penguncian referensi, anggaran AI). Keputusan dicatat; bila dipasang, lewat `GuestChallenge`.
- **Aset visual:** ilustrasi SVG sementara dari generator tetap dipakai (`SCENE_IMAGE_SOURCE=local` default); gaya karakter/logo final menunggu konfirmasi pengguna sesuai VISUAL_ASSETS. `og-image.png` dirender sekali dengan Pillow dari palet tema (belum ada di generator).
- Konkurensi diuji pada PostgreSQL saja; dokumen tidak mengklaim bukti dari SQLite.

**Kendala dan yang belum dilakukan**
- PostgreSQL: `migrate` dan `composer test` pada PostgreSQL, serta grup `postgres`, menunggu `DB_PASSWORD` (atau role/database `restaurant_ai_test` yang bisa diakses).
- Evaluasi model nyata (`restaurant:eval`) belum dijalankan: tidak ada provider yang dapat dijangkau. Harness hanya diuji dengan model palsu (bukan akurasi). Target rilis (≥27/30 per bahasa, semua allergen dan forbidden lulus) belum terbukti.
- Staging, production, pilot, restore-test, dan `TRUSTED_PROXIES` nyata: belum ada server, domain, TLS, atau lokasi backup.
- Review manual belum dilakukan: perangkat nyata (iOS/Android), tablet 768 px, keyboard-only dan pembaca layar, kontras terukur, reduced motion, narrator/suara, mode offline/pemulihan polling, bahasa Jepang di semua halaman.
- Foto menu nyata dan data menu/alergen yang diverifikasi restoran: bukan bagian repositori.

**Checklist untuk pengguna**
1. Isi `DB_PASSWORD` di `.env`, buat database `restaurant_ai_test`, salin `.env.testing.example` → `.env.testing`, lalu `composer test` (termasuk grup `postgres`). Laporkan kegagalan apa pun; itulah bukti konkurensi yang belum ada.
2. `php artisan migrate:fresh --seed` lalu `php artisan db:seed --class=LocalDemoSeeder`, jalankan `php artisan storage:link`, `npm run build`, `composer dev`; coba journey tamu (menu → keranjang → pesanan; reservasi; `/bookings`) dan sisi staf (`demo.foh@example.test`, `demo.kitchen@example.test`, `demo.admin@example.test`; password lokal ada di `LocalDemoSeeder`).
3. Review di ponsel dan tablet nyata (tab bar bawah, wizard, chat layar penuh), keyboard saja, dan tiga bahasa.
4. Isi `LOCAL_LLM_*`, jalankan `python docs/evals/build_corpus.py` lalu `restaurant:eval` pada database `*eval*`, baca skenario gagal, dan putuskan model.
5. Konfirmasi gaya ilustrasi/karakter/logo agar aset final menggantikan yang sementara.
6. Siapkan input deployment ([OPERATIONS §1](OPERATIONS.md)) lalu ikuti runbook untuk staging.
