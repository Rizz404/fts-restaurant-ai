# Runbook operasional FTS Restaurant AI

Untuk tim yang memasang dan menjalankan aplikasi di staging/production. Keputusan arsitektur ada di [kontrak teknis](TECHNICAL_CONTRACTS.md); dokumen ini berisi langkah kerja. Artefak contoh ada di folder [`deploy/`](../deploy).

> **Status (7 Oktober 2026):** runbook ini mencerminkan kode Plan 1–8. Langkah di server target (bagian 2–4 dan 7) belum pernah dijalankan karena belum ada server staging/production, kredensial PostgreSQL, dan provider AI yang bisa dijangkau; artefak dan perintahnya sudah ada dan diuji sejauh mungkin secara lokal (lihat [catatan hasil Plan 8](PLAN_08_QUALITY_AND_RELEASE.md#catatan-hasil-implementasi)).

## 1. Input yang harus tersedia

Hostname/domain dan sertifikat TLS, server Linux dengan PHP 8.4 (FPM + CLI, ekstensi `gd` dan `intl`) dan nginx, PostgreSQL 17 yang hanya dapat diakses host aplikasi/worker, `APP_KEY`, URL/model/API key provider AI yang dapat dijangkau dari server (HTTPS; keputusan hosting model ada di Plan 5), daftar IP/CIDR proxy atau CDN tepercaya untuk `TRUSTED_PROXIES`, dan lokasi backup privat. Tanpa input ini deployment belum bisa dilakukan; jangan mengganti stack.

## 2. Setup production (release pertama)

1. Struktur: `/var/www/restaurant-ai/releases/<tanggal>`, `/var/www/restaurant-ai/shared/{.env,storage}`, symlink `current` → release aktif. `storage` dan `.env` di `shared` di-symlink ke setiap release.
2. `.env` dari [`deploy/env.production.example`](../deploy/env.production.example) (mode 0600). Wajib: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `LOG_STACK=daily` + `LOG_DAILY_DAYS`, base URL provider HTTPS tanpa `/v1`, `ASSISTANT_GUEST_DAILY_TURNS` dan `ASSISTANT_DAILY_TURN_CAP` sesuai anggaran, `TRUSTED_PROXIES` (kosong berarti tidak ada proxy tepercaya; isi bila ada reverse proxy/CDN supaya pembatas laju memakai IP tamu yang benar), `SECURITY_CSP=true`, dan `SECURITY_HSTS=false` bila nginx yang mengirim `Strict-Transport-Security` (contoh nginx melakukannya).
3. PHP: pasang [`deploy/php-production.ini.example`](../deploy/php-production.ini.example) untuk FPM dan CLI (memory 256M, max_execution_time 75, upload 3 MB, display_errors Off). Ekstensi `gd` (dengan WebP), `exif`, `intl`, dan `pdo_pgsql` wajib; periksa `php -r "var_dump(gd_info()['WebP Support']);"`.
4. nginx: [`deploy/nginx.conf.example`](../deploy/nginx.conf.example) (root `public`, hanya `index.php`, dotfiles ditolak, `fastcgi_read_timeout 80s`, body maksimal 3 MB untuk foto menu, kompresi aset). Aplikasi sendiri mengirim header keamanan (Content-Security-Policy dengan `script-src 'self' 'unsafe-eval'` karena Alpine 3, nosniff, Referrer-Policy, Permissions-Policy, X-Frame-Options); nginx hanya menambah HSTS dan header aset statis.
5. Di direktori release:
   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   npm ci && npm run build
   php artisan migrate --force
   php artisan db:seed --class=Database\\Seeders\\ReferenceSeeder --force
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
   Jangan menjalankan `migrate:fresh`, `restaurant:demo-accounts`, atau seeder demo di production (keduanya menolak production).
6. Admin pertama (password lewat prompt tersembunyi, wajib diganti saat login pertama):
   ```bash
   php artisan restaurant:create-admin --email=admin@restoran.tld --name="Admin"
   ```
7. Ganti symlink `current`, reload PHP-FPM (opcache `validate_timestamps=0`), lalu `php artisan queue:restart` bila worker dipakai.
8. Periksa `/up` (200) dan `/api/v1/admin/readiness` sebagai admin, lalu `/admin/operations`.

## 3. Scheduler dan worker

Cron (user aplikasi):

```cron
* * * * * cd /var/www/restaurant-ai/current && php artisan schedule:run >> /dev/null 2>&1
```

Jadwal: `restaurant:recover-turns` setiap menit (juga menulis heartbeat scheduler) dan `restaurant:purge-data` pukul 02:00 Asia/Jakarta (`withoutOverlapping`, `onOneServer`).

Worker: notifikasi v1 ditulis sinkron sehingga worker opsional. Bila job antrean dipakai, pasang [`deploy/supervisor.conf.example`](../deploy/supervisor.conf.example) (`queue:work --sleep=3 --tries=3 --timeout=90`), set `QUEUE_WORKER_REQUIRED=true`, dan restart worker setiap release.

## 4. Backup dan restore

- Backup harian dengan [`deploy/scripts/backup.sh`](../deploy/scripts/backup.sh): `pg_dump` format custom, diverifikasi dengan `pg_restore --list`, checksum SHA-256, retensi `BACKUP_KEEP` (default 14). Kredensial dari `PGPASSFILE` (0600) atau peer auth; skrip menolak `PGPASSWORD`.
- Foto menu (`storage/app/public/menu`) ikut dicadangkan oleh skrip yang sama bila `PHOTOS_DIR` diisi (arsip `tar.gz` bertanggal + checksum, retensi sama) karena tidak ada di database.
- Restore hanya ke database staging/testing dengan [`deploy/scripts/restore-staging.sh`](../deploy/scripts/restore-staging.sh); nama target wajib berakhiran `_staging` atau `_test`. Foto dipulihkan dengan `RESTORE_PHOTOS_ARCHIVE` dan `RESTORE_PHOTOS_PARENT` (path harus mengandung `staging` atau `test`).
- Latihan restore minimal sebelum pilot dan setiap kuartal: restore ke staging → `php artisan migrate --force` → pulihkan foto menu → ulangi journey (bagian 7).
- Lakukan backup tepat sebelum setiap migrasi production.

## 5. Retensi data

| Data | Umur | Catatan |
|---|---|---|
| Percakapan (turn, pesan, evidence, tool invocation) | 30 hari sejak aktivitas terakhir | Request tetap ada; tautan percakapan menjadi null |
| Request tertutup (completed/declined/cancelled/no_show) beserta pesan, event, draft, receipt, notifikasi, **kontak tamu** | 180 hari sejak `terminal_at` | Request `pending`/`confirmed` tidak pernah dihapus berdasar umur |
| Draft belum dikonfirmasi | 24 jam setelah `expires_at` | |
| Sesi tamu | 30 hari sejak `last_seen_at` | Percakapan ikut terhapus; request tetap dengan `guest_session_id` null |
| Baris kapasitas slot (`reservation_slot_usage`) | 60 hari | |
| Pemakaian anggaran AI (`assistant_usage`) | 90 hari | |
| Audit | 365 hari | |
| Receipt lain | 365 hari | |

Sebelum mengaktifkan jadwal di lingkungan baru: `php artisan restaurant:purge-data --dry-run` (hanya jumlah, tanpa data pribadi). Akun staf tidak dihapus otomatis.

## 6. Observabilitas dan alert pilot

Metrik batas laju dihitung dari respons 429 (hanya angka per hari, tanpa IP atau jalur). Log terstruktur (`assistant.turn`, `assistant.tool`, `retention.purge`) memuat request_id, turn ID internal, ID sesi tamu (public) atau user, prompt version, provider/model alias, latensi, hasil, counter loop/tool, dan usage. Log tidak memuat API key, header bearer, body provider, prompt lengkap, transkrip, kontak tamu, atau isi pesan request.

`/admin/operations` (admin saja) menampilkan metrik 7 hari dan empat alert:

| Alert | Ambang | Pemeriksaan |
|---|---|---|
| Error AI | >10% dari minimal 20 turn dalam 1 jam | `/api/v1/admin/readiness` bagian assistant; log `assistant.turn` per `result` (mis. `ai_timeout`, `ai_rate_limited`, `tool_capability_unavailable`); status provider; bila provider mati, set `ASSISTANT_ENABLED=false` sementara (jelajah menu, info, dan form request tetap jalan) |
| Latensi p95 | >55 dtk dari minimal 20 turn dalam 1 jam | Beban provider/model, `tool_rounds` di usage turn, jaringan ke provider |
| Request pending tanpa penanganan | pending >2 jam pada jam buka (dapat dikonfigurasi) | Hubungi penanggung jawab tim front_of_house/kitchen; assign dan tangani lewat `/admin/requests` |
| Anggaran AI | pemakaian global ≥ 80% dari `ASSISTANT_DAILY_TURN_CAP` | Periksa lonjakan trafik atau penyalahgunaan (hit limiter, sesi baru per IP); naikkan kapasitas atau turunkan `ASSISTANT_GUEST_DAILY_TURNS`; fallback `ai_budget_exhausted` tidak mematikan jelajah/form |

Alert tidak dikirim ke kanal eksternal (belum diintegrasikan); admin memeriksa halaman operasional setiap hari kerja selama pilot.

## 7. Verifikasi staging dan journey

1. Fresh install pada database staging kosong (bagian 2), bukan `migrate:fresh` pada database berisi data.
2. Restore backup ke staging lalu jalankan journey dalam id/en/ja: tanya menu → kartu hidangan → minta reservasi → draft → isi kontak di review → konfirmasi tombol → request pending → staf mengonfirmasi → tamu melihat status. Periksa isolasi tim, slot penuh, dan sumber yang dicabut (hidangan/artikel diarsipkan → riwayat diganti pesan baku).
3. `/dev/*` harus 404, gambar slide dari `/assets/restaurant/*` (tanpa URL remote), header keamanan aktif, foto menu terlayani dari `/storage/menu/*`, `robots.txt` benar, dan readiness `ready: true`.
4. `config:cache`/`route:cache` aktif, scheduler berjalan (heartbeat "OK"), `restaurant:purge-data --dry-run` sesuai harapan.

## 8. Insiden

| Gejala | Langkah |
|---|---|
| AI lambat/gagal | Cek readiness dan provider; turn macet dipulihkan otomatis setiap menit (`turn_interrupted`). Matikan sementara dengan `ASSISTANT_ENABLED=false` lalu `php artisan config:cache`. |
| Provider tidak mendukung tool | Turn gagal `tool_capability_unavailable`; set `ASSISTANT_TOOLS_ENABLED=false` (chat kembali menjawab dari retrieval, request lewat formulir). |
| Akun staf disalahgunakan | Nonaktifkan atau reset password dari `/admin/users`; sesi lama dibatalkan. |
| Trafik/penyalahgunaan endpoint tamu | Periksa hit limiter dan pemakaian anggaran di `/admin/operations`; turunkan `ASSISTANT_GUEST_DAILY_TURNS`, set `ASSISTANT_ENABLED=false` bila perlu, blokir IP di nginx/CDN; periksa bahwa limiter memakai IP klien nyata (`TrustProxies`). |
| Data alergen salah dilaporkan | Tarik verifikasi hidangan terkait dari `/admin/menu` (hidangan tetap tampil tanpa klaim alergen), perbaiki data, verifikasi ulang; cek audit `menu.allergens_changed`. |
| Data perlu dipulihkan | Restore ke staging, ekstrak data yang dibutuhkan; production dipulihkan dengan forward fix, bukan rollback yang menghapus data. |
| Release bermasalah | Arahkan `current` ke release sebelumnya, reload PHP-FPM, `queue:restart`. Migrasi data memakai forward fix. |

Rollout: staging → pilot terbatas (akun admin/editor/staf front_of_house/staf kitchen, data sintetis dulu, lalu QR di beberapa meja) → perluasan akses setelah quality gate (bagian 7 dan [evaluasi model](evals/README.md)) lulus.
