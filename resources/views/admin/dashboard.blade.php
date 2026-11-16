<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">Ringkasan</h1>
    <p class="mt-2 text-body text-muted">Keadaan bank soal dan paket tryout hari ini.</p>

    <div class="mt-6 grid gap-4 lg:grid-cols-4">
        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <p class="text-caption font-semibold tracking-wide text-muted uppercase">Total soal</p>
            <p class="mt-2 font-heading text-[30px] leading-none font-bold text-ink">{{ $questionTotal }}</p>
            <ul class="mt-3 space-y-1 text-small text-muted">
                @foreach ($questionsPerCategory as $label => $count)
                    <li class="flex justify-between gap-3"><span>{{ $label }}</span><span class="font-semibold text-ink">{{ $count }}</span></li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <p class="text-caption font-semibold tracking-wide text-muted uppercase">Paket</p>
            <p class="mt-2 font-heading text-[30px] leading-none font-bold text-ink">{{ $draftPackages + $publishedPackages }}</p>
            <p class="mt-3 text-small text-muted">{{ $draftPackages }} draft · {{ $publishedPackages }} published</p>
        </div>

        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <p class="text-caption font-semibold tracking-wide text-muted uppercase">Peserta</p>
            <p class="mt-2 font-heading text-[30px] leading-none font-bold text-ink">{{ $participantCount }}</p>
            <p class="mt-3 text-small text-muted">{{ $attemptCount }} percobaan tercatat</p>
        </div>

        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <p class="text-caption font-semibold tracking-wide text-muted uppercase">Paket belum lengkap</p>
            <p class="mt-2 font-heading text-[30px] leading-none font-bold text-ink">{{ $incomplete->count() }}</p>
            <p class="mt-3 text-small text-muted">Paket yang belum genap {{ config('cpns.total_items') }} soal tidak bisa dibuka peserta.</p>
        </div>
    </div>

    <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Yang masih kurang</h2>
    <div class="mt-4 space-y-3">
        @forelse ($incomplete as $row)
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <strong class="font-heading text-body font-bold text-ink">{{ $row['package']->title }}</strong>
                        <p class="text-small text-muted">{{ $row['shortfall'] }}</p>
                    </div>
                    {{-- The row carries the package, and the builder is where the
                         shortfall this card reports is actually closed. --}}
                    <a href="{{ route('admin.packages.builder', $row['package']) }}"
                        class="rounded-card bg-brand px-4 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
                        Susun paket
                    </a>
                </div>

                <ul class="mt-4 grid gap-2 text-small lg:grid-cols-3">
                    @foreach ($row['report'] as $subtest)
                        <li class="rounded-card bg-surface px-3.5 py-2.5">
                            <span class="font-semibold text-ink">{{ $subtest['label'] }}</span>
                            <span class="{{ $subtest['complete'] ? 'text-success' : 'text-danger' }}">
                                {{ $subtest['present'] }} / {{ $subtest['required'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="rounded-card border border-line bg-white p-5 text-small text-muted shadow-card">
                Semua paket sudah genap. Tidak ada yang perlu dilengkapi.
            </p>
        @endforelse
    </div>
</x-layouts.admin>
