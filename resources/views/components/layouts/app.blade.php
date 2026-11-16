<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ config('site.indexable') ? 'index, follow' : 'noindex, nofollow' }}">

    <title>{{ $site['brand'] }} — {{ $site['tagline'] }}</title>
    <meta name="description" content="{{ $site['description'] }}">
    <link rel="canonical" href="{{ url('/') }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $site['brand'] }}">
    <meta property="og:description" content="{{ $site['description'] }}">
    <meta property="og:image" content="{{ asset('assets/og-default.png') }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('assets/favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('assets/apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white antialiased">
    <x-partials.header :site="$site" />

    {{-- Sections are keyed by their content file; each owns its own data section. --}}
    <main class="overflow-hidden text-body">
        {{ $slot }}
    </main>

    <x-partials.footer :site="$site" :footer="$footer ?? []" />
    <x-partials.floating-actions :site="$site" :floating="$floating ?? []" />

    @include('partials.analytics')
</body>
</html>
