# Plan 4 CMS menu dan info serta pencarian berfilter

| Metadata | Nilai |
|---|---|
| ID | 4 |
| Status | Implemented |
| Dependensi | [Plan 3](PLAN_03_PRESENTATION_EXPERIENCE.md), [kontrak teknis](TECHNICAL_CONTRACTS.md) |
| Acuan | [Master](000_MASTER_PLAN.md), `../../fts-resturant-menu` (acuan domain menu) |
| Hasil implementasi | [Catatan hasil](#catatan-hasil-implementasi) (7 Oktober 2026) |

## Hasil yang harus tersedia

Editor/admin dapat membuat dan menerbitkan hidangan tiga bahasa (nama, deskripsi, harga, foto, kategori, tag diet, alergen), mengatur ketersediaan seketika, memverifikasi data alergen, serta mengelola artikel info (jam buka, lokasi, kebijakan, FAQ). Tamu dapat menjelajah, mencari, dan memfilter menu serta membaca info **tanpa AI**. `MenuRetriever` dan `KnowledgeRetriever` siap dipakai Plan 5 dan 7.

## Basis kode yang diwarisi

Dipakai ulang: `Modules/Knowledge` (`CreateArticle`, `SaveDraft`, `CloneRevision`, `PublishRevision`, `ArchiveRevision`, `PublicationRules`, `ArticleRules`, `KnowledgeRetriever`, `TextNormalizer`, `VisibleRevisions`, enum `RevisionStatus`), CMS artikel dengan tab id/en/ja dan indikator kelengkapan, `KnowledgeArticlePolicy`, kolom turunan `search_*`, pola scoring SQL portabel dan escape `LIKE ... ESCAPE '!'`.

Diubah:
- Artikel info: `KnowledgeCategory` menjadi `general|hours|location|policy|faq` (menggantikan general/hr/it/onboarding); `Audience` dan semua jalur departemen dihapus (`knowledge_revision_department`, `owner_department_id`, `knowledge_editor_department`); `VisibleRevisions` hanya memeriksa published + masa berlaku; `KnowledgeRetriever::search(locale, query, category?, limit=5)` tanpa actor. Editor boleh membuat/mengedit draft; hanya admin menerbitkan/mengarsipkan.
- Migration pengetahuan (`…000007`) ditulis ulang oleh plan ini bersama tabel menu.
- Route karyawan `/office/knowledge*` diganti `/info` dan `/info/{slug}`; API `/api/v1/knowledge*` menjadi `/api/v1/info*`.
- `KnowledgeDemoSeeder` diganti `MenuDemoSeeder` dan `InfoDemoSeeder` (berlabel `[DEMO]`).

Baru: modul `App\Modules\Menu` (actions, validasi, `MenuRetriever`, policy, presenter) dan modul pendukung harga (`Money`).

## Skema dan invariant

Skema menu mengikuti [kontrak teknis](TECHNICAL_CONTRACTS.md#menu-info-dan-evidence): `menu_categories` (+ terjemahan), `menu_items`, `menu_item_translations`, `menu_item_allergens`, `menu_item_dietary_tags`. Skema info mengikuti basis (`knowledge_articles`, `knowledge_revisions`, `knowledge_translations`) tanpa tabel/kolom departemen; partial unique index satu `published` dan satu `draft` per artikel tetap.

Invariant menu:
- Kode alergen dan tag diet berasal dari `config/restaurant.php`; kode tidak dikenal ditolak 422. Satu alergen per item maksimal satu relasi (`contains` atau `may_contain`).
- Publikasi mensyaratkan nama (≤120) dan deskripsi (≤1000) tiga locale, harga integer > 0, kategori aktif, dan `allergen_info_verified_at` terisi (daftar alergen boleh kosong bila staf menyatakan hidangan bebas alergen terdaftar). Draft boleh belum lengkap.
- **Verifikasi alergen:** mengubah daftar alergen atau tag diet pada hidangan apa pun (termasuk yang sudah published) mengosongkan `allergen_info_verified_at` dan `allergen_info_verified_by`. Hidangan published tetap tampil, tetapi tanpa klaim alergen/diet sampai diverifikasi ulang oleh admin atau editor lewat aksi eksplisit.
- Status `draft → published → archived`. Hidangan published dapat diedit langsung (harga, deskripsi, foto, ketersediaan) tanpa revisi baru; setiap perubahan harga, ketersediaan, alergen, tag diet, dan verifikasi dicatat audit (`menu.price_changed`, `menu.availability_changed`, `menu.allergens_changed`, `menu.allergen_verified`) berisi nilai lama/baru tanpa data pribadi. Archive menghapus hidangan dari tampilan dan retrieval tetapi mempertahankan record (request lama tetap merujuk).
- Slug ASCII lowercase/hyphen, dibuat dari nama id, unik, maksimal 120, immutable setelah pertama kali diterbitkan.
- Foto: satu per hidangan; upload gambar JPEG/PNG/WebP ≤ 2 MB, rasio dan dimensi minimum 800×600, disimpan sebagai WebP maksimal sisi panjang 1600 px di disk `public` (`menu/{slug}-{hash}.webp`) setelah di-encode ulang dengan GD (menghapus EXIF/GPS dan payload tersisip). Membutuhkan ekstensi PHP `gd` (tambahkan ke daftar kebutuhan README) dan `php artisan storage:link`. Foto bersifat opsional; tanpa foto tampil ikon kategori.

Tag/alias maksimal 50 string masing-masing 1–80; normalisasi NFKC + lowercase. Kolom turunan `search_name/search_description/search_keywords` diisi setiap save.

## Controller dan route

CMS menu (admin dan editor):
- GET/POST `/admin/menu`; GET `/admin/menu/create`; GET `/admin/menu/{item}/edit`; PATCH `/admin/menu/{item}`.
- POST `/admin/menu/{item}/publication` (publish); DELETE path yang sama (archive).
- PATCH `/admin/menu/{item}/availability`: admin, editor, **dan staf** (hidangan habis dicoret cepat dari panel).
- POST `/admin/menu/{item}/allergen-verification`: admin dan editor.
- GET/POST/PATCH `/admin/menu/categories`: tanpa hapus (nonaktifkan lewat update).

CMS info:
- GET/POST `/admin/info`; GET `/admin/info/create`; GET `/admin/info/{article}/edit`; PATCH `/admin/info/{article}/draft`.
- POST `/admin/info/{article}/publication` (admin) dan DELETE (arsip, admin); POST `/admin/info/{article}/revisions` membuat draft dari published; GET `/admin/info/{article}/revisions`.

Tamu (publik):
- GET `/menu?q=&category=&diet[]=&exclude_allergens[]=&max_price=` (dengan tab kategori dan filter), GET `/menu/{slug}`.
- GET `/picks`: hidangan published `is_chef_pick` dan `is_available`.
- GET `/info?q=&category=`, GET `/info/{slug}`.
- API: GET `/api/v1/menu?…` (excerpt singkat), GET `/api/v1/menu/{slug}`, GET `/api/v1/info?q=&category=`, GET `/api/v1/info/{slug}`; semua read-only dan throttled per IP.

Resource tidak tersedia (draft/archived/tidak berlaku) menghasilkan 404. Tidak ada download dokumen.

## Retrieval deterministik

`MenuRetriever::search(locale, query?, filters, limit=8)` mengikuti kontrak: hanya published; query opsional (tanpa query, urut `sort_order` lalu nama); filter category, `dietary_tags[]` (semua harus ada), `exclude_allergens[]`, `max_price`; hasil `is_available` lebih dulu. Skor memakai pola `KnowledgeRetriever`: nama frasa penuh +30, alias +20, deskripsi +10, token berbeda +3/+2/+1, NFKC lowercase, bigram CJK, maksimal 12 token, wildcard LIKE di-escape, binding.

**Aturan alergen:** bila `exclude_allergens` diisi, item yang `contains` **atau** `may_contain` alergen itu dikeluarkan, dan item dengan `allergen_info_verified_at` kosong **juga dikeluarkan**; hasil membawa `unverified_excluded_count`. Tag diet pada hasil hanya dikirim bila item terverifikasi; item belum terverifikasi dikirim dengan `allergen_verified=false` dan tanpa daftar alergen/tag.

`KnowledgeRetriever::search(locale, query, category?, limit=5)` untuk info mengikuti basis tanpa audience. Excerpt UI 300 karakter; evidence internal 2000.

## Presentasi di slide

- Menu: tab kategori, kartu hidangan (foto, nama, harga terformat locale, badge diet, badge "habis"), filter diet dan pengecualian alergen, pencarian. Kartu menampilkan alergen hanya bila terverifikasi; bila tidak, badge "Info alergen belum diverifikasi — tanyakan staf" (terjemahan id/en/ja).
- Detail hidangan: deskripsi, daftar alergen (`contains`/`may_contain`) bila terverifikasi, tingkat pedas, CTA "Tambah ke pesanan" (aktif setelah Plan 6) dan "Tanya AI soal hidangan ini" (Plan 5).
- Picks: grid hidangan chef; slot rekomendasi AI diisi Plan 7.
- Info: daftar artikel per kategori, detail dengan teks plain whitespace-preserving.
- Harga: helper `Money::format(int, currency, locale)` memakai `NumberFormatter` (intl); IDR tanpa desimal.
- Context scene membawa `menu_item_id` yang valid, bukan seluruh isi.

## Langkah pengerjaan

1. Tulis ulang migration pengetahuan dan tambah migration menu; models, factory, enum (`MenuStatus`, `AllergenRelation`, `KnowledgeCategory` baru).
2. Modul Menu: actions `CreateMenuItem`, `UpdateMenuItem`, `PublishMenuItem`, `ArchiveMenuItem`, `SetAvailability`, `VerifyAllergens`, `StoreMenuPhoto`, `SaveCategory`; `MenuItemRules` (draft longgar, publikasi ketat); `MenuItemPolicy`; `MenuRetriever`; `MenuPresenter`. Transaksi singkat dengan audit.
3. Sederhanakan modul Knowledge (tanpa departemen/audience) dan ubah kategori; perbarui policy (editor draft, admin publish).
4. Buat CMS menu dan info (plain textarea tiga bahasa dengan indikator kelengkapan; form hidangan memakai tabs per bahasa, select kategori, checkbox diet, daftar alergen dengan relasi, upload foto). Render teks sebagai escaped text; tanpa editor HTML kaya.
5. Hubungkan slide Menu/Picks/Info dan API publik; perbarui lang dan presenter badge.
6. Seeder demo `[DEMO]` (local/testing): tiga kategori, ±8 hidangan tiga bahasa termasuk satu terverifikasi dengan alergen, satu belum terverifikasi, satu tidak tersedia, dua chef pick; artikel info jam buka, lokasi, kebijakan, FAQ. Bukan konten restoran sungguhan.
7. README: tambah ekstensi `gd`, `storage:link`; perbarui daftar URL dan seeder.

## Kriteria implementasi dan verifikasi pengguna

Implementasi selesai (Implemented) bila skema/indexes, actions, policy, CMS menu dan info, halaman/API publik, retriever menu dan info, upload foto, aturan verifikasi alergen, dan seed demo tersedia sehingga jelajah, filter, dan pembacaan info berfungsi tanpa provider AI. Partial unique index dan scoring ditulis untuk PostgreSQL. Tes otomatis tidak ditulis/dijalankan; skenarionya ada di [katalog Plan 8](PLAN_08_QUALITY_AND_RELEASE.md#katalog-pengujian-per-plan).

Checklist untuk pengguna:
1. Sebagai editor buat hidangan dengan draft belum lengkap; publish harus ditolak sampai tiga bahasa, harga, kategori, dan verifikasi alergen lengkap.
2. Ubah daftar alergen hidangan yang sudah terverifikasi; status verifikasi harus hilang dan kartu menampilkan "belum diverifikasi" sampai diverifikasi ulang.
3. Pada `/menu` aktifkan filter pengecualian alergen; hidangan yang mengandung atau mungkin mengandung alergen itu, dan hidangan belum terverifikasi, tidak muncul.
4. Tandai hidangan "habis" sebagai staf biasa; kartu berubah seketika dan audit mencatatnya.
5. Ubah harga; audit mencatat nilai lama dan baru; tampilan harga mengikuti locale.
6. Upload foto JPEG/PNG/WebP sah, lalu file terlalu besar atau bukan gambar; yang terakhir ditolak. Pastikan foto tersimpan sebagai WebP tanpa EXIF.
7. Sebagai admin terbitkan, edit, arsipkan artikel info; editor tidak dapat menerbitkan.
8. Cari dengan kata Indonesia, Inggris, dan Jepang serta alias; urutan hasil masuk akal. Isi nama/deskripsi dengan tag HTML; harus tampil sebagai teks.
9. Klarifikasi: kategori awal, daftar alergen/diet final, kebijakan verifikasi (siapa yang boleh), sumber foto, dan menu nyata untuk pilot.

## Pemakaian fondasi UI

CMS menyusun `x-layouts.admin` dan `x-ui.tabs/tab-panel` untuk tiga bahasa; gunakan field/input/textarea/select/checkbox, card, table, badge, dan confirm-dialog bersama. Halaman tamu memakai `x-deck-stage` dari Plan 3. Status ke badge tone dipetakan presenter (published→success, draft→neutral, archived→warning; "habis"→danger, "belum diverifikasi"→warning), bukan warna baru di view. Semua warna/font/radius berasal dari UI_FOUNDATION dan theme.css.

## Catatan hasil implementasi

Dikerjakan 7 Oktober 2026. Status: **Implemented**; verifikasi otomatis menunggu Plan 8.

**Perubahan yang dibuat**
- Info: modul `Knowledge` diadaptasi (tanpa audience/departemen; kategori `general|hours|location|policy|faq`; admin/editor menulis, hanya admin publish/arsip; revisi bernomor dengan satu revisi published), `Admin\InfoController`, `InfoArticleRequest`, view `admin/info/*`, halaman publik `/info` dan `/info/{slug}`, API `GET /api/v1/info`.
- Menu: modul `Menu` baru (model `MenuCategory`, `MenuItem`, terjemahan, `MenuItemAllergen`, `MenuItemDietaryTag`; enum `MenuStatus`, `AllergenRelation`; aksi Create/Update/Publish/Archive/SetAvailability/VerifyAllergens/SaveCategory; `MenuPhotos` mengenkode ulang foto ke WebP lewat GD; `Money` memakai `NumberFormatter`). Aturan keselamatan alergen: data alergen hanya dipercaya bila `allergen_info_verified_at` terisi; mengubah alergen pada hidangan published menghapus verifikasi; filter diet/alergen hanya mengembalikan hidangan terverifikasi.
- Retrieval deterministik `MenuRetriever` (skor frasa/token, semua bahasa dicari, tanpa model) dan `KnowledgeRetriever`; `MenuEvidence` mengosongkan alergen/diet untuk hidangan yang belum diverifikasi.
- Halaman: `/menu`, `/menu/{slug}`, `/picks`, kartu hidangan; admin `/admin/menu` (+ kategori); staf hanya mengubah ketersediaan (`MenuItemPolicy`). Seeder `MenuDemoSeeder` dan `InfoDemoSeeder` (sintetis, awalan [DEMO]).

**Penyimpangan keputusan**
- Filter diet mensyaratkan verifikasi alergen (bukan hanya tag), supaya klaim "vegetarian/halal" tidak bersandar pada data yang belum diperiksa staf.
- Foto menu diproses dengan GD (ekstensi `gd` wajib; lihat README).

**Checklist untuk pengguna:** buat kategori dan hidangan di `/admin/menu`, verifikasi alergen lalu publish; ubah alergen hidangan published dan pastikan kartu berubah menjadi "belum diverifikasi"; cari "vegetarian" di `/menu` dan pastikan hidangan tak terverifikasi tidak muncul; tulis artikel jam buka lalu publish sebagai admin.
