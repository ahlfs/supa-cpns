@props(['package', 'openAttempt' => null])

<div class="rounded-card border border-line bg-white p-5 shadow-card">
    <strong class="block font-heading text-body font-bold text-ink">{{ $package->title }}</strong>
    <p class="mt-1 text-small text-muted">{{ $package->description }}</p>
    <p class="mt-3 text-caption text-muted">
        {{ config('cpns.total_items') }} soal · {{ $package->duration_minutes }} menit · maks {{ config('cpns.total_max') }}
    </p>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        @if ($openAttempt)
            <form method="POST" action="{{ route('tryout.start', $package) }}">
                @csrf
                <button type="submit" class="rounded-card bg-accent px-5 py-2.5 font-heading text-small font-bold text-white transition hover:bg-accent-dark">
                    Lanjutkan
                </button>
            </form>
            <span class="text-small text-warning">Sisa {{ intdiv($openAttempt->remainingSeconds(), 60) }} menit</span>
            <form method="POST" action="{{ route('tryout.restart', $package) }}">
                @csrf
                <button type="submit" class="rounded-card border border-line px-4 py-2.5 text-small font-semibold text-muted transition hover:border-danger hover:text-danger">
                    Mulai ulang
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('tryout.start', $package) }}">
                @csrf
                <button type="submit" class="rounded-card bg-brand px-5 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
                    Mulai tryout
                </button>
            </form>
        @endif
    </div>
</div>
