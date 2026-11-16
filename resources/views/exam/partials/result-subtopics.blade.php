@props(['subtopics'])

<div class="rounded-card border border-line bg-white p-5 shadow-card lg:p-6">
    <h2 class="font-heading text-h3 font-bold text-ink">Per submateri</h2>
    <p class="mt-1 text-small text-muted">Diurutkan dari yang paling lemah, jadi yang paling perlu dibaca ada di atas.</p>

    <div class="mt-5 space-y-3">
        @forelse ($subtopics as $row)
            <div>
                <div class="flex flex-wrap items-baseline justify-between gap-2 text-small">
                    <span class="font-semibold text-ink">{{ $row['label'] }} <span class="text-caption font-normal text-muted">{{ $row['category'] }}</span></span>
                    <span class="text-muted">
                        benar {{ $row['correct'] }} · salah {{ $row['wrong'] }} · kosong {{ $row['blank'] }} · {{ $row['score'] }} poin
                    </span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-surface">
                    <div class="h-2 rounded-full {{ $row['ratio'] >= 70 ? 'bg-success' : ($row['ratio'] >= 40 ? 'bg-warning' : 'bg-danger') }}" style="width: {{ min(100, $row['ratio']) }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-small text-muted">Belum ada rincian submateri untuk percobaan ini.</p>
        @endforelse
    </div>
</div>
