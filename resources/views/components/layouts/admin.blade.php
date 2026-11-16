@props(['title' => 'Admin', 'site' => []])

{{--
    The third layout in the application (marketing, exam, admin). It shares the
    token block with the other two, so it is recognisably the same product, but it
    has no marketing footer and keeps its own left sidebar.

    Nav items are a plain array here because they are presentation, not data: there
    is no second place that needs to know what the admin menu contains.
--}}
@php
    $nav = [
        ['route' => 'admin.dashboard', 'label' => 'Ringkasan', 'icon' => 'grid', 'pattern' => 'admin.dashboard'],
        ['route' => 'admin.questions.index', 'label' => 'Bank soal', 'icon' => 'file-text', 'pattern' => 'admin.questions.*'],
        ['route' => 'admin.packages.index', 'label' => 'Paket tryout', 'icon' => 'layers', 'pattern' => 'admin.packages.*'],
        ['route' => 'admin.import.index', 'label' => 'Impor soal', 'icon' => 'upload', 'pattern' => 'admin.import.*'],
        ['route' => 'admin.users.index', 'label' => 'Pengguna', 'icon' => 'users', 'pattern' => 'admin.users.*'],
    ];

    // The menu grows as each admin screen lands (Tasks 13–16). A route name that
    // does not exist yet would make route() throw while rendering the sidebar of
    // every admin page, so entries whose screen is not built yet are dropped here
    // rather than referenced blindly.
    $nav = collect($nav)
        ->filter(fn (array $item): bool => Route::has($item['route']))
        ->values()
        ->all();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} — {{ $site['brand'] ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-body text-body antialiased">
    <div class="lg:flex">
        <aside class="border-b border-line bg-white lg:min-h-screen lg:w-[260px] lg:shrink-0 lg:border-r lg:border-b-0">
            <div class="flex items-center justify-between gap-3 px-5 py-4">
                <span class="font-heading text-body font-bold text-ink">{{ $site['brand'] ?? config('app.name') }}</span>
                <span class="rounded-card bg-brand/10 px-2.5 py-1 text-caption font-semibold text-brand">Admin</span>
            </div>

            <nav class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-col lg:overflow-visible lg:px-3 lg:pb-6">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                        @class([
                            'inline-flex shrink-0 items-center gap-2.5 rounded-card px-3.5 py-2.5 text-small font-semibold transition',
                            'bg-brand/10 text-brand' => request()->routeIs($item['pattern']),
                            'text-muted hover:bg-surface hover:text-ink' => ! request()->routeIs($item['pattern']),
                        ])>
                        <x-icon :name="$item['icon']" class="h-4 w-4" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-line px-5 py-4 lg:border-t-0">
                {{-- Rendered at every width, not just desktop: the panel is now
                     reachable from the header on a phone, so it has to offer a
                     way back out at that width too. --}}
                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center gap-2 text-small font-semibold text-muted transition hover:text-brand">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Lihat situs peserta
                </a>

                <p class="mt-3 text-caption text-muted">{{ auth()->user()->name }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 text-small font-semibold text-muted transition hover:text-danger">
                        <x-icon name="logout" class="h-4 w-4" />
                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        <main class="min-w-0 flex-1 px-5 py-6 lg:px-8 lg:py-8">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
