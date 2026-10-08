{{-- The only document shell: one Vite bundle, CSRF meta, skip link, toast container. --}}
@props([
    'title' => null,
    'seo' => [],
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.config('restaurant.brand.name') : config('restaurant.brand.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <x-seo :data="$seo" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body {{ $attributes->class('min-h-dvh font-sans text-body antialiased') }}>
    <a href="#main-content" class="skip-link">{{ __('ui.skip_to_content') }}</a>

    {{ $slot }}

    <x-ui.toast-container />
</body>
</html>
