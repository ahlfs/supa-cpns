@props(['timing'])

<div class="rounded-card border border-line bg-white p-5 shadow-card lg:p-6">
    <h2 class="font-heading text-h3 font-bold text-ink">Waktu</h2>
    <p class="mt-1 text-small text-muted">Perkiraan, bukan stopwatch.</p>

    <dl class="mt-4 grid grid-cols-2 gap-4 text-small lg:grid-cols-4">
        <div>
            <dt class="text-muted">Terpakai</dt>
            <dd class="font-heading text-body font-bold text-ink">
                {{ intdiv($timing['used_seconds'], 60) }} dari {{ intdiv($timing['budget_seconds'], 60) }} menit
            </dd>
        </div>
        <div>
            <dt class="text-muted">Rata-rata per soal</dt>
            <dd class="font-heading text-body font-bold text-ink">{{ $timing['average_seconds'] }} detik</dd>
        </div>
        <div>
            <dt class="text-muted">Dijawab &lt; {{ $timing['guess_threshold'] }} detik lalu salah</dt>
            <dd class="font-heading text-body font-bold text-ink">{{ $timing['guesses'] }} soal</dd>
        </div>
        <div>
            <dt class="text-muted">Porsi waktu</dt>
            <dd class="font-heading text-body font-bold text-ink">{{ $timing['used_percent'] }}%</dd>
        </div>
    </dl>

    <h3 class="mt-6 font-heading text-body font-bold text-ink">Lima soal paling lama</h3>
    <ol class="mt-3 space-y-2 text-small">
        @forelse ($timing['slowest'] as $index => $row)
            <li class="flex items-baseline gap-3">
                <span class="text-muted">{{ $index + 1 }}.</span>
                <span class="min-w-0 flex-1 truncate text-ink">{{ $row['stem'] }}</span>
                <span class="shrink-0 font-semibold text-ink">{{ $row['seconds'] }} detik</span>
            </li>
        @empty
            <li class="text-muted">Belum ada data waktu.</li>
        @endforelse
    </ol>

    <p class="mt-4 text-caption text-muted">
        Angka waktu adalah perkiraan dari interval antar perpindahan soal, bukan stopwatch, dan satu soal
        paling banyak dihitung {{ intdiv((int) config('cpns.timing_cap_seconds'), 60) }} menit.
        Soal TKP tidak pernah muncul di daftar tebakan karena TKP tidak punya jawaban salah.
    </p>
</div>
