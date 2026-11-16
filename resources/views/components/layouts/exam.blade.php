{{--
    The exam room deliberately does NOT reuse the marketing header and footer.

    Those carry a mega menu and site-wide links, and one stray click during a
    100-minute timed exam costs the participant something that cannot be bought
    back. Same design tokens, so it still looks like the same product.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Tryout' }} — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-body text-body antialiased">
    {{ $slot }}
</body>
</html>
