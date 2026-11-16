@php($thresholds = collect(\App\Enums\QuestionCategory::cases())
    ->map(fn ($category) => $category->label().' '.$category->threshold())
    ->implode(', '))

<x-layouts.app :site="$site" :footer="$footer" :floating="$floating">
    <x-ui.app-page section="tryout">
        <div class="mx-auto max-w-page">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-heading text-h2 font-bold text-ink">Tryout SKD</h1>
                    <p class="mt-2 text-body text-muted">
                        {{ config('cpns.total_items') }} soal, {{ config('cpns.duration_minutes') }} menit, skor maksimal {{ config('cpns.total_max') }}.
                        Ambang batasnya tiga-tiganya berdiri sendiri: {{ $thresholds }}.
                    </p>
                    {{-- The one sitting each package allows runs to the clock, so this
                         page is the place a participant can see it still running and
                         pick it back up. --}}
                    <p class="mt-2 text-small text-muted">
                        Keluar dari halaman ini tidak menghapus sesi — waktunya tetap berjalan sampai kamu menekan
                        "Selesai ujian" atau waktu habis.
                    </p>
                </div>

                <a href="{{ route('dashboard') }}" class="rounded-card border border-line bg-white px-4 py-2.5 font-heading text-small font-bold text-ink transition hover:border-brand hover:text-brand">
                    Kembali ke dashboard
                </a>
            </div>

            @if (session('status'))
                <p class="mt-5 rounded-card border border-warning/30 bg-warning/5 px-4 py-3 text-small text-warning">
                    {{ session('status') }}
                </p>
            @endif

            <div class="mt-8 grid gap-4 lg:grid-cols-2">
                @forelse ($packages as $package)
                    @include('exam.partials.package-card', [
                        'package' => $package,
                        'openAttempt' => $openAttempts->get($package->id),
                    ])
                @empty
                    <p class="text-body text-muted">Belum ada paket tryout yang dibuka.</p>
                @endforelse
            </div>
        </div>
    </x-ui.app-page>
</x-layouts.app>
