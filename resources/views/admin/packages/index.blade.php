<x-layouts.admin :title="$title" :site="$site">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading text-h2 font-bold text-ink">Paket tryout</h1>
            <p class="mt-1 text-body text-muted">Paket yang masih kurang tidak bisa dibuka peserta.</p>
        </div>
        <a href="{{ route('admin.packages.create') }}" class="inline-flex items-center gap-2 rounded-card bg-brand px-4 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
            <x-icon name="plus" class="h-4 w-4" />
            Paket baru
        </a>
    </div>

    @if (session('status'))
        <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">{{ session('status') }}</p>
    @endif

    <div class="mt-6 space-y-3">
        @forelse ($packages as $row)
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <strong class="font-heading text-body font-bold text-ink">{{ $row['package']->title }}</strong>
                        <p class="text-small text-muted">
                            {{ $row['package']->questions_count }} soal ·
                            {{ $row['package']->duration_minutes }} menit ·
                            {{ $row['package']->attempts_count }} percobaan
                        </p>
                        <p class="mt-1 text-small {{ $row['complete'] ? 'text-success' : 'text-danger' }}">
                            {{ $row['complete'] ? 'Komposisi genap' : $row['shortfall'] }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-card px-3 py-1.5 text-caption font-semibold {{ $row['package']->isPublished() ? 'bg-success/10 text-success' : 'bg-surface text-muted' }}">
                            {{ $row['package']->isPublished() ? 'published' : 'draft' }}
                        </span>
                        <a href="{{ route('admin.packages.builder', $row['package']) }}" class="rounded-card bg-brand px-4 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
                            Susun
                        </a>
                        <a href="{{ route('admin.packages.edit', $row['package']) }}" class="rounded-card border border-line px-4 py-2.5 font-heading text-small font-bold text-ink">
                            Ubah
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <p class="rounded-card border border-line bg-white p-5 text-small text-muted shadow-card">
                Belum ada paket tryout. Buat satu, lalu susun {{ config('cpns.total_items') }} soalnya.
            </p>
        @endforelse
    </div>
</x-layouts.admin>
