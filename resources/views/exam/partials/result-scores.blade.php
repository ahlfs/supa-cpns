@props(['report', 'subtests'])

<div class="grid gap-4 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
    <div class="rounded-card border border-line bg-white p-6 text-center shadow-card">
        <p class="text-caption font-semibold tracking-wide text-muted uppercase">Skor total</p>
        <p class="mt-2 font-heading text-[48px] leading-none font-bold text-ink">{{ $report->total() }}</p>
        <p class="mt-1 text-small text-muted">dari {{ $report->maximum() }}</p>
        <p class="mt-4 inline-block rounded-card px-4 py-2 font-heading text-small font-bold {{ $report->passed() ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}">
            {{ $report->passed() ? 'Lolos ambang batas' : 'Belum lolos ambang batas' }}
        </p>
        <p class="mt-4 text-caption text-muted">
            Ambang batasnya berdiri sendiri per subtes, bukan dijumlah. Skor total yang tinggi
            masih bisa gugur kalau satu subtes di bawah garisnya.
        </p>
    </div>

    <div class="grid gap-4">
        @foreach ($subtests as $subtest)
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <strong class="font-heading text-body font-bold text-ink">
                        {{ $subtest['label'] }}
                        <span class="font-body text-caption font-normal text-muted">{{ $subtest['name'] }}</span>
                    </strong>
                    <span class="font-heading text-body font-bold {{ $subtest['passed'] ? 'text-success' : 'text-danger' }}">
                        {{ $subtest['score'] }} / {{ $subtest['max'] }}
                    </span>
                </div>

                {{-- The tick sits exactly on the threshold, so "how far short" is visible without arithmetic. --}}
                <div class="relative mt-4 h-3 rounded-full bg-surface">
                    <div class="h-3 rounded-full {{ $subtest['passed'] ? 'bg-success' : 'bg-danger' }}" style="width: {{ min(100, $subtest['percent']) }}%"></div>
                    <span class="absolute -top-1 h-5 w-0.5 bg-ink" style="left: {{ $subtest['threshold_percent'] }}%" title="Ambang batas {{ $subtest['threshold'] }}"></span>
                </div>

                <p class="mt-2 text-caption text-muted">
                    Ambang {{ $subtest['threshold'] }}
                    ({{ $subtest['passed'] ? 'lolos' : 'kurang '.$subtest['shortfall'].' poin' }})
                </p>

                <dl class="mt-4 grid grid-cols-3 gap-3 text-small">
                    @if ($subtest['has_key'])
                        <div><dt class="text-muted">Benar</dt><dd class="font-semibold text-success">{{ $subtest['correct'] }}</dd></div>
                        <div><dt class="text-muted">Salah</dt><dd class="font-semibold text-danger">{{ $subtest['wrong'] }}</dd></div>
                    @else
                        <div><dt class="text-muted">Terjawab</dt><dd class="font-semibold text-ink">{{ $subtest['correct'] }}</dd></div>
                        <div><dt class="text-muted">Rata-rata bobot</dt><dd class="font-semibold text-ink">{{ $subtest['average_weight'] }}</dd></div>
                    @endif
                    <div><dt class="text-muted">Kosong</dt><dd class="font-semibold text-muted">{{ $subtest['blank'] }}</dd></div>
                </dl>

                @unless ($subtest['has_key'])
                    <p class="mt-3 text-caption text-muted">
                        TKP tidak punya jawaban salah: yang dinilai kesesuaiannya dengan nilai ASN, jadi
                        opsi terlemah pun bernilai 1 dan mengosongkan bernilai 0.
                    </p>
                @endunless
            </div>
        @endforeach
    </div>
</div>
