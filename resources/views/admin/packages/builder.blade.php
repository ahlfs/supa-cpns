<x-layouts.admin :title="$title" :site="$site">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-heading text-h2 font-bold text-ink">{{ $package->title }}</h1>
            <p class="mt-2 text-body text-muted">
                {{ $package->duration_minutes }} menit · skor maks {{ config('cpns.total_max') }} ·
                {{ collect($report)->sum('present') }} dari {{ config('cpns.total_items') }} soal
            </p>
        </div>
        <a href="{{ route('admin.packages.index') }}" class="rounded-card border border-line bg-white px-4 py-2.5 font-heading text-small font-bold text-ink">
            Semua paket
        </a>
    </div>

    @if (session('status'))
        <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <p class="mt-5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">{{ $errors->first() }}</p>
    @endif

    @if ($locked)
        <p class="mt-5 rounded-card border border-warning/30 bg-warning/5 px-4 py-3 text-small text-warning">
            Komposisi paket ini dikunci karena sudah ada percobaan. Teks soal dan pembahasannya tetap bisa
            diubah dari bank soal; untuk susunan yang berbeda, buat paket baru.
        </p>
    @endif

    {{-- The shortfall bar: what is missing, per subtest, at a glance. --}}
    <div class="mt-6 grid gap-3 lg:grid-cols-3">
        @foreach ($report as $subtest)
            <div class="rounded-card border border-line bg-white p-4 shadow-card">
                <div class="flex items-baseline justify-between gap-2">
                    <strong class="font-heading text-body font-bold text-ink">{{ $subtest['label'] }}</strong>
                    <span class="font-heading text-body font-bold {{ $subtest['complete'] ? 'text-success' : 'text-danger' }}">
                        {{ $subtest['present'] }} / {{ $subtest['required'] }}
                    </span>
                </div>
                <div class="mt-3 h-2 rounded-full bg-surface">
                    <div class="h-2 rounded-full {{ $subtest['complete'] ? 'bg-success' : 'bg-danger' }}"
                        style="width: {{ min(100, (int) round($subtest['present'] / max(1, $subtest['required']) * 100)) }}%"></div>
                </div>
                <p class="mt-2 text-caption text-muted">
                    {{ $subtest['complete'] ? 'genap' : 'kurang '.$subtest['missing'].' soal lagi' }}
                </p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        @if ($package->isPublished())
            <form method="POST" action="{{ route('admin.packages.unpublish', $package) }}">
                @csrf
                <button type="submit" class="rounded-card border border-line bg-white px-5 py-3 font-heading text-small font-bold text-ink">Tutup paket</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.packages.publish', $package) }}">
                @csrf
                <button type="submit" @disabled(! $complete)
                    class="rounded-card bg-accent px-5 py-3 font-heading text-small font-bold text-white transition hover:bg-accent-dark disabled:opacity-40">
                    Buka untuk peserta
                </button>
            </form>
            @unless ($complete)
                <p class="self-center text-small text-muted">
                    Tombol ini aktif setelah komposisinya genap. {{ $shortfall }}
                </p>
            @endunless
        @endif
    </div>

    <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Isi paket</h2>
    <div class="mt-4 space-y-2">
        @forelse ($items as $index => $item)
            <div class="flex flex-wrap items-center gap-3 rounded-card border border-line bg-white p-4 shadow-card">
                <span class="w-8 font-heading text-small font-bold text-muted">{{ $index + 1 }}</span>
                <span class="min-w-0 flex-1 text-small text-ink">{{ \Illuminate\Support\Str::limit($item->stem, 120) }}</span>
                <span class="text-caption text-muted">{{ $item->category->label() }} · {{ $item->subcategoryLabel() }}</span>

                @unless ($locked)
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('admin.packages.move', ['package' => $package, 'question' => $item]) }}">
                            @csrf
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="rounded-card border border-line px-2.5 py-1.5 text-small text-muted" aria-label="Naikkan">&uarr;</button>
                        </form>
                        <form method="POST" action="{{ route('admin.packages.move', ['package' => $package, 'question' => $item]) }}">
                            @csrf
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="rounded-card border border-line px-2.5 py-1.5 text-small text-muted" aria-label="Turunkan">&darr;</button>
                        </form>
                        <form method="POST" action="{{ route('admin.packages.detach', ['package' => $package, 'question' => $item]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-card border border-line px-3 py-1.5 text-small text-muted transition hover:border-danger hover:text-danger">Keluarkan</button>
                        </form>
                    </div>
                @endunless
            </div>
        @empty
            <p class="rounded-card border border-line bg-white p-5 text-small text-muted shadow-card">Paket ini masih kosong.</p>
        @endforelse
    </div>

    @unless ($locked)
        <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Tambah dari bank soal</h2>

        <form method="GET" action="{{ route('admin.packages.builder', $package) }}" class="mt-4 grid gap-3 lg:grid-cols-[200px_minmax(0,1fr)_auto]">
            <select name="category" class="rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">
                <option value="">Semua subtes</option>
                @foreach (\App\Enums\QuestionCategory::cases() as $option)
                    <option value="{{ $option->value }}" @selected($category === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari teks soal"
                class="rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">
            <button type="submit" class="rounded-card border border-line bg-white px-4 py-2.5 font-heading text-small font-bold text-ink">Cari</button>
        </form>

        <div class="mt-4 space-y-2">
            @forelse ($candidates as $candidate)
                <div class="flex flex-wrap items-center gap-3 rounded-card border border-line bg-white p-4 shadow-card">
                    <span class="min-w-0 flex-1 text-small text-ink">{{ \Illuminate\Support\Str::limit($candidate->stem, 120) }}</span>
                    <span class="text-caption text-muted">{{ $candidate->category->label() }} · {{ $candidate->subcategoryLabel() }}</span>
                    <form method="POST" action="{{ route('admin.packages.attach', $package) }}">
                        @csrf
                        <input type="hidden" name="question_id" value="{{ $candidate->id }}">
                        <button type="submit" class="rounded-card bg-brand px-4 py-2 font-heading text-small font-bold text-white transition hover:bg-brand-dark">Tambah</button>
                    </form>
                </div>
            @empty
                <p class="rounded-card border border-line bg-white p-5 text-small text-muted shadow-card">
                    Tidak ada soal yang cocok, atau semua yang cocok sudah masuk paket ini.
                </p>
            @endforelse
        </div>

        <div class="mt-4">{{ $candidates->links() }}</div>
    @endunless
</x-layouts.admin>
