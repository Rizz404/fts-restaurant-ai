{{--
    Head metadata for search and sharing. Public pages get a description, canonical URL (without query), Open Graph
    tags, hreflang alternates for each locale, and optional JSON-LD. Private pages (staff area, sign-in, booking
    status) get noindex. $data keys: description, noindex, jsonld (array), title (page title for sharing).
--}}
@props([
    'data' => [],
])

@php
    $noindex = (bool) ($data['noindex'] ?? false);
    $description = isset($data['description']) ? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $data['description']))), 200) : null;
    $title = $data['title'] ?? config('restaurant.brand.name');
    $canonical = url()->current();
    $locales = config('restaurant.locales');
    $ogLocales = ['id' => 'id_ID', 'en' => 'en_US', 'ja' => 'ja_JP'];
@endphp

@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@else
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($locales as $locale)
        <link rel="alternate" hreflang="{{ $locale }}" href="{{ $canonical }}?lang={{ $locale }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $canonical }}">
    @if ($description)
        <meta name="description" content="{{ $description }}">
        <meta property="og:description" content="{{ $description }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('restaurant.brand.name') }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ $ogLocales[app()->getLocale()] ?? 'id_ID' }}">
    <meta property="og:image" content="{{ asset('assets/restaurant/og-image.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    @if (! empty($data['jsonld']))
        <script type="application/ld+json">{!! json_encode($data['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    @endif
@endif
