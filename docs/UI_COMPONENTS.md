# Komponen UI FTS Restaurant AI

Dokumen ini mendeskripsikan kit komponen yang **diwarisi dari FTS Corporate AI** dan benar-benar ada di kode repositori ini. Spesifikasi nilai tema tetap di [UI_FOUNDATION.md](UI_FOUNDATION.md); dokumen ini menjelaskan pemakaian komponen yang tersedia. Semua komponen dapat dilihat di `/dev/ui` (APP_ENV local/testing).

> **Status (7 Oktober 2026):** kit `x-ui.*`, `x-navigation.*`, dan layout berlaku apa adanya; presenter, chat, wizard request, dan thread sudah memakai nama restoran (`x-deck-stage`, `x-deck-chat`, `x-deck-character`, `x-guest-preferences`, `x-request-thread`, `Alpine.data` `deckStage`, `restaurantChat`, `requestWizard`, `requestThread`, `requestLookup`, `notificationBell`). Komponen tambahan restoran: `x-ui.honeypot`, `x-seo`, bagian partial `experience/_request-wizard` dan `experience/_request-lookup`.

## Aturan pakai

1. Pakai layout dan komponen yang ada sebelum membuat yang baru. Fitur menambahkan isi pada slot, bukan menyalin header/sidebar/wrapper.
2. Warna hanya melalui token semantik (`bg-primary`, `text-muted`, `border-border`, …) dari `resources/css/theme.css`. Tidak ada `bg-blue-500`, `bg-[#…]`, `style="color:…"`, atau class warna yang dirakit dari data.
3. Variant/tone/size divalidasi `App\Support\Ui\ComponentStyles`. Nilai tidak dikenal melempar exception di local/testing dan kembali ke default di production. Tambah variant baru di mapping tersebut (class literal penuh), bukan di view.
4. Label diterjemahkan pemanggil (`__('ui.…')`). Komponen tidak menjalankan query DB atau pemeriksaan izin.
5. Teks dinamis dirender escaped (`{{ }}`, `x-text`, `textContent`). Jangan memakai `x-html`/`{!! !!}` untuk pesan, konten model, atau teks staf.
6. Semua komponen meneruskan attribute bag: `class` digabung, atribut lain (`autocomplete`, `maxlength`, `data-*`, `x-model`, `x-on:*`, `aria-*`) diteruskan ke elemen utama.

## Layout

Setiap halaman memakai tepat satu layout teratas. Semua layout menyusun `x-layouts.base`, sehingga `<head>`, CSRF meta, satu `@vite`, skip link, dan toast container hanya ada sekali.

| Komponen | Source | Props | Slot |
|---|---|---|---|
| `x-layouts.base` | `resources/views/components/layouts/base.blade.php` | `title`, `seo` (lihat `x-seo`) | default, `head` |
| `x-layouts.guest` | `…/layouts/guest.blade.php` | `title`, `heading`, `description`, `width` (`md`/`lg`) | default (isi panel), `before` (di atas panel), `head`. Menampilkan `session('status')` sebagai alert info. |
| `x-layouts.admin` | `…/layouts/admin.blade.php` | `title`, `heading`, `navItems`, `breadcrumbs` | default, `actions`, `topbar`, `account` (default: account menu saat login), `navigation` (mengganti `navItems`), `head`. Menampilkan `session('status')` sebagai alert sukses. |
| `x-layouts.user` | `…/layouts/user.blade.php` | `title`, `navItems`, `mode` (`deck`/`room`), `accent`, `scene`, `sceneLabel`, `avatar`, `stage`, `tableLabel`, `seo` | default (konten slide), `flash`, `topbar`, `navigation`, `chat`, `head`. Tamu anonim: tanpa account menu. Dipakai lewat `x-deck-stage`. |

```blade
<x-layouts.admin
    :title="__('ui.nav.accounts')"
    :heading="__('ui.nav.accounts')"
    :nav-items="$navItems"
    :breadcrumbs="[['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')], ['label' => __('ui.nav.accounts')]]"
>
    <x-slot:actions>
        <x-ui.link-button :href="route('admin.users.create')">{{ __('ui.add') }}</x-ui.link-button>
    </x-slot:actions>

    …konten…
</x-layouts.admin>
```

- **Admin:** sidebar 16rem pada ≥1024px; di bawahnya menjadi drawer (`role="dialog"`, `x-trap.inert.noscroll`, Escape/klik luar menutup, fokus kembali ke tombol menu). Nama layout tidak menentukan role; item menu dikirim server.
- **User:** presenter AI ([UI_FOUNDATION §7](UI_FOUNDATION.md)): slide sorter di kiri (tab bar bawah di mobile), top bar dengan command pill, konten (deck di reception, kanvas di ruang lain), dan panel copilot dari kanan (layar penuh di mobile; Escape menutup).
- **Guest:** panel tengah dengan brand dan language switch; dipakai login/ganti password.
- **Halaman admin** menerima `$navItems` otomatis dari view composer (`AppServiceProvider` + `App\Modules\Identity\Presenters\AdminNavigation`) untuk semua view `admin.*`; item disaring berdasarkan kemampuan server.

`navItems` (admin dan user) berbentuk `[['label' => string, 'href' => ?string, 'icon' => ?string, 'active' => bool]]`. Item tanpa `href` tampil nonaktif dengan label "Segera".

### Slide tamu (deck)

`x-deck-stage` (`resources/views/components/deck-stage.blade.php`) adalah satu-satunya cara menyusun halaman tamu. Props: `scene` (`App\Modules\Experience\Data\SceneData` dari `BuildScene`), `seo` (override `title`, `description`, `noindex`, `jsonld`). Welcome menjadi deck presentasi yang dipandu karakter (sambutan + preferensi tamu, satu slide per ruang, slide "Tanya AI"); slide lain menjadi kanvas bernomor dengan presenter di samping dan slot default sebagai isinya. Slot `chat` opsional mengganti panel copilot.

- Gambar slide dan karakter berasal dari `SceneImages` (placeholder ber-seed atau file lokal); karakter/avatar punya fallback bila gagal dimuat (`Alpine.data('sceneImage')`); `x-deck-character` membingkai karakter dalam arch. Gambar dekoratif (`alt=""`).
- Navigasi slide (thumbnail sorter di desktop, tab bar yang bisa digulir di ponsel) memakai `x-navigation.user-menu` lewat `navItems` dari `SceneData::$menu`. Chip nomor meja (`?table=`) hanya tampilan.
- Narrator (Web Speech) dan suara (Web Audio) hanya aktif atas aksi pengguna; subtitle diketik; kendali deck: slide, autoplay, keyboard, swipe (`Alpine.data('deckStage')`).
- Context slide tersedia di `<script type="application/json" id="scene-context">`. Context ini bukan identitas; server tetap memverifikasi.

```blade
<x-deck-stage :scene="$scene" :seo="['title' => $item->name, 'description' => $item->description]">
    <x-ui.card :title="…">…</x-ui.card>
</x-deck-stage>
```

### Komponen domain

| Komponen | Source | Props | Catatan |
|---|---|---|---|
| `x-deck-chat` | `components/deck-chat.blade.php` | `scene` | Isi panel copilot; `Alpine.data('restaurantChat')`, kartu lewat `safe-card-renderer.js` (delapan jenis kartu, lima jenis aksi). Dipasang otomatis oleh `x-deck-stage`. |
| `x-guest-preferences` | `components/guest-preferences.blade.php` | — | Chip diet, alergen yang dihindari, dan jumlah tamu; hanya kode whitelist di localStorage; dikirim sebagai `preferences` pada turn AI. |
| `x-request-thread` | `components/request-thread.blade.php` | `role` (`guest`/`staff`) | Daftar pesan + kotak tulis; harus berada di dalam `x-data="requestThread(...)"` (polling 5 dtk dengan kursor `after_id`, jeda saat tab tersembunyi/offline). Mode staf menambah catatan internal. |
| `x-navigation.notification-bell` | `components/navigation/notification-bell.blade.php` | `variant` | Notifikasi staf dari `/api/v1/notifications`; hanya dipasang untuk staf dengan tim (`@can('requests.staff')`). |
| `x-seo` | `components/seo.blade.php` | `data` | Canonical, hreflang, description, Open Graph, JSON-LD, atau `noindex` untuk halaman privat. |

Wizard request (`experience/_request-wizard`) memakai `Alpine.data('requestWizard')`: draft disimpan di server, kunci konfirmasi stabil per revisi draft, kontak hanya di langkah review. Keranjang pesanan (`Alpine.store('cart')`, kunci `fts-restaurant-stage:cart`) hanya menyimpan id dan jumlah.

## Navigasi

| Komponen | Source | Props | Catatan |
|---|---|---|---|
| `x-navigation.brand` | `components/navigation/brand.blade.php` | `variant` (`light`/`dark`), `href`, `subtitle` (bool), `compact` (hanya tanda) | Nama dari `config('restaurant.brand')` |
| `x-navigation.admin-sidebar` | `…/admin-sidebar.blade.php` | `items` | `aria-current="page"` untuk item aktif |
| `x-navigation.user-menu` | `…/user-menu.blade.php` | `items`, `orientation` (`horizontal`/`vertical`) | Untuk header gelap |
| `x-navigation.language-switch` | `…/language-switch.blade.php` | `variant` (`light`/`dark`) | Link `?lang=xx` pada URL saat ini; `SetLocale` menyimpan pilihan di session |
| `x-navigation.breadcrumbs` | `…/breadcrumbs.blade.php` | `items` `[['label', 'href' => ?]]` | Item terakhir `aria-current="page"` |
| `x-navigation.account-menu` | `…/account-menu.blade.php` | `variant` (`light`/`dark`), `siteHref`, `adminHref` | Dropdown nama/email, link situs tamu/admin (dikirim pemanggil, tidak mengecek izin sendiri), ganti password, logout (form POST) |

## Tombol dan ikon

| Komponen | Props (default) | Perilaku |
|---|---|---|
| `x-ui.button` | `variant` primary/secondary/outline/ghost/danger/ai/glass (`primary`; `ai` = CTA pil bergradient AI, `glass` = tombol pil transparan di top bar), `size` sm/md/lg (`md`), `type` button/submit/reset (`button`), `loading` (false), `disabled` (false) | Native `<button>`. `loading` menambah `disabled`, `aria-busy="true"`, dan spinner; label tetap terbaca. Type tidak dikenal menjadi `button`. |
| `x-ui.link-button` | `href`, `variant`, `size`, `disabled` | `<a>` dengan style yang sama (resolver bersama). `disabled` atau tanpa `href` menjadi `<span role="link" aria-disabled="true">`. |
| `x-ui.icon` | `name` (whitelist `ComponentStyles::ICONS`), `size` sm/md/lg (`md`), `label` (null) | Heroicons outline. Tanpa `label`: `aria-hidden`. Dengan `label`: `role="img"` + `aria-label`. Tambah nama baru ke whitelist sebelum dipakai. |
| `x-ui.spinner` | `size` (`md`), `label` (null) | Dengan `label`: `role="status"` + teks sr-only; tanpa label dekoratif. `motion-reduce` menghentikan animasi. |

```blade
<x-ui.button variant="primary" type="submit">{{ __('ui.save') }}</x-ui.button>
<x-ui.button variant="danger" x-on:click="$dispatch('ui:modal-open', { name: 'delete-user' })">
    <x-ui.icon name="trash" size="sm" /> {{ __('ui.delete') }}
</x-ui.button>
```

Untuk loading dari Alpine, teruskan binding: `x-bind:disabled="submitting" x-bind:aria-busy="submitting"`.

## Form

Kontrak: `x-ui.field` dan control menerima **name dan id yang sama**. ID help/error dihitung `App\Support\Ui\FieldState` (`{id}-help`, `{id}-error`). Control membaca `help`/`error` milik field melalui `@aware`, sehingga `aria-describedby` dan `aria-invalid` otomatis cocok. Pesan error diambil dari error bag Laravel (`$errors`) dengan key dot-notation (`items[0][title]` → `items.0.title`). Di form berulang, beri id unik.

| Komponen | Props (default) | Catatan |
|---|---|---|
| `x-ui.field` | `name`, `id`, `label`, `required` (false), `help` (null), `error` (null, override error bag) | `<label for>`, tanda wajib + teks sr-only, help, error dengan ikon |
| `x-ui.input` | `name`, `id`, `type` (`text`), `value`, `invalid`, `describedby`, `disabled` | `form-input`. Nilai dari `old()` kecuali `type="password"` (tidak pernah mengisi value) |
| `x-ui.textarea` | `name`, `id`, `value`, `rows` (4), `invalid`, `describedby`, `disabled` | `form-textarea`, plain text |
| `x-ui.select` | `name`, `id`, `options`, `value`, `placeholder`, `invalid`, `describedby`, `disabled` | `options`: `[value => label]` atau `[['value' => …, 'label' => …]]` |
| `x-ui.checkbox` | `name`, `id`, `value` (`'1'`), `checked`, `disabled`, `describedby` | Slot = label. Setelah submit gagal, status mengikuti old input; nama array (`roles[]`) tercentang bila `value` ada di daftar old |

`invalid` dan `describedby` dapat diberikan eksplisit bila control dipakai di luar field.

```blade
<x-ui.field name="email" id="login-email" :label="__('ui.email')" required>
    <x-ui.input name="email" id="login-email" type="email" autocomplete="username" required />
</x-ui.field>

<x-ui.field name="preferred_locale" id="user-locale" :label="__('ui.users.preferred_locale')">
    <x-ui.select name="preferred_locale" id="user-locale" :options="$localeOptions" :value="$user->preferred_locale" />
</x-ui.field>
```

## Konten

| Komponen | Props (default) | Slot / catatan |
|---|---|---|
| `x-ui.card` | `padding` sm/md (`md`), `title` (null) | default, `header` (di samping judul), `footer` |
| `x-ui.badge` | `tone` neutral/success/warning/danger/info (`neutral`) | Status domain dipetakan ke tone di presenter domain, bukan di badge |
| `x-ui.alert` | `tone` info/success/warning/danger (`info`), `title`, `dismissible` (false), `role` status/alert (`status`) | Pakai `role="alert"` hanya untuk error mendesak |
| `x-ui.empty-state` | `title`, `description`, `icon` (`inbox`) | `action` |
| `x-ui.skeleton` | `shape` line/card (`line`), `count` (1, maks 12), `label` | Placeholder tanpa angka palsu; `label` menjadikan `role="status"` |

## Tabel dan paginasi

| Komponen | Props | Catatan |
|---|---|---|
| `x-ui.table` | `headers` (string atau `['label', 'sr_only' => bool]`), `caption` | Wrapper `overflow-x-auto`, `<thead>` semantik |
| `x-ui.table.row` | – | `<tr>` dengan hover |
| `x-ui.table.cell` | `header` (false) | `header` → `<th scope="row">` |
| `x-ui.pagination` | `paginator` | Memakai view `resources/views/vendor/pagination/restaurant.blade.php` (ringkasan "Menampilkan x–y dari z", prev/next dengan label, `aria-current` halaman aktif) |

```blade
<x-ui.table :headers="[__('ui.name'), __('ui.status'), ['label' => __('ui.actions'), 'sr_only' => true]]">
    @foreach ($users as $user)
        <x-ui.table.row>
            <x-ui.table.cell header>{{ $user->name }}</x-ui.table.cell>
            <x-ui.table.cell><x-ui.badge :tone="$user->is_active ? 'success' : 'neutral'">…</x-ui.badge></x-ui.table.cell>
            <x-ui.table.cell class="text-right">…</x-ui.table.cell>
        </x-ui.table.row>
    @endforeach
</x-ui.table>
<x-ui.pagination :paginator="$users" class="mt-4" />
```

## Interaksi Alpine

Semua data provider didaftarkan di `resources/js/ui/register.js` sebelum satu-satunya `Alpine.start()` di `resources/js/app.js`. Modul baru didaftarkan di sana; jangan memanggil `Alpine.start()` atau memuat Alpine dari CDN.

| Komponen | Props | Perilaku dan aksesibilitas |
|---|---|---|
| `x-ui.modal` | `name` (unik), `title`, `size` sm/md/lg (`md`) | Slot default dan `footer`. `role="dialog"`, `aria-modal`, `aria-labelledby`; `x-trap.inert.noscroll` mengunci fokus dan mengembalikannya ke pemicu; Escape/klik luar menutup; `x-cloak`. Di dalam slot tersedia `close()`. |
| `x-ui.confirm-dialog` | `name`, `title`, `confirm-label`, `cancel-label`, `variant` primary/danger | Menyusun modal + dua tombol; tombol konfirmasi memancarkan `ui:confirmed`. Tidak melakukan write bisnis. |
| `x-ui.dropdown` | `align` left/right, `trigger-variant` (variant tombol, default `outline`), `id` | Slot `trigger` (isi tombol; atribut slot diteruskan ke tombol) dan item. `aria-haspopup="menu"`, `aria-expanded`; klik luar/Escape/Tab menutup; panah atas/bawah, Home/End. |
| `x-ui.dropdown.item` | `href`, `icon`, `disabled`, `type` (`button`/`submit`) | Dengan `href` → link native `role="menuitem"`; tanpa `href` → tombol yang menutup menu; `type="submit"` mengirim form pembungkus (mis. logout). |
| `x-ui.tabs` | `id`, `items` `[['id', 'label']]`, `selected` | `role="tablist"/"tab"`, `aria-selected`, roving tabindex; ArrowLeft/Right melingkar, Home/End. |
| `x-ui.tab-panel` | `name`, `tabs-id` | `role="tabpanel"`, `aria-labelledby`; panel nonaktif memakai `x-cloak` sampai Alpine aktif. |
| `x-ui.toast-container` | – | Sudah dipasang di base layout. `aria-live="polite"`, maksimum 5 toast, hilang otomatis 6 detik, tombol tutup. Teks saja. |

```blade
<x-ui.button x-on:click="$dispatch('ui:modal-open', { name: 'disable-user' })">…</x-ui.button>

<x-ui.confirm-dialog name="disable-user" :title="__('ui.users.disable_title')" :confirm-label="__('ui.users.disable')" variant="danger">
    {{ __('ui.users.disable_body') }}
</x-ui.confirm-dialog>
```

Untuk submit form setelah konfirmasi, dengarkan event pada form: `x-on:ui:confirmed.window="if ($event.detail.name === 'disable-user') $el.submit()"`. Server tetap memvalidasi dan mengotorisasi.

### Event standar

| Event (window) | Detail | Keterangan |
|---|---|---|
| `ui:modal-open` / `ui:modal-close` | `{name}` | Membuka/menutup modal bernama |
| `ui:confirmed` | `{name}` | Dipancarkan confirm-dialog; pemanggil menjalankan aksi server |
| `ui:toast` | `{message, tone}` | Tone whitelist info/success/warning/danger; `message` teks (dipotong 500 karakter) |
| `ui:auth-required` / `ui:session-expired` | `{requestId}` | Dipancarkan `apiRequest` untuk respons 401/419 |

## JavaScript bersama

- `resources/js/shared/api.js`: `apiRequest(url, {method, body, signal})` hanya untuk same-origin `/api/v1/…`; mengirim `Accept: application/json`, `credentials: 'same-origin'`, header `X-CSRF-TOKEN` dan body JSON untuk mutation. Mengembalikan envelope `{schema_version, data, meta, error}` atau melempar `ApiError {kind, status, code, message, fields, requestId}` dengan `kind` `http`/`network`/`abort`/`invalid_response`/`blocked`. Tidak pernah retry otomatis. `createApiClient({fetchImpl, csrfTokenReader, origin, events})` untuk injeksi.
- `resources/js/shared/dom.js`: `setText`, `textNode`, `createElement(tag, {text, attrs})`, `setSafeAttribute` (allowlist + `data-*`, `href` hanya path relatif/http(s)/mailto), `isSafeUrl`, `allowedToken`.

## Server bersama

- `App\Support\Http\ApiResponse::success($data, $meta, $status)` / `::error($code, $message, $status, $fields)` membangun envelope kontrak dengan `meta.request_id`.
- `App\Support\Http\ApiException($code, $status, $fields)` untuk error domain; pesan diambil dari `lang/*/ui.php` key `errors.{code}`.
- Exception pada `/api/*` dipetakan ke envelope oleh `ApiExceptionRenderer` (401/403/404/409/419/422/429/503/500, tanpa detail internal).
- Middleware: `AssignRequestId` (global, header `X-Request-Id`), `SetLocale` (web; `?lang` → session → default `id`; invalid pada JSON = 422).

## Menambah komponen atau token

1. Pastikan kebutuhan belum bisa dipenuhi props/slot yang ada.
2. Buat anonymous component di `resources/views/components/ui` dengan nama jelas; style variant ke `ComponentStyles` sebagai class literal penuh.
3. Token warna/radius/shadow baru hanya di `resources/css/theme.css` dan dicatat di UI_FOUNDATION.
4. Tambahkan contoh di `/dev/ui` dan dokumentasikan di file ini.
