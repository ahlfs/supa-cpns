<x-layouts.admin :title="$title" :site="$site">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading text-h2 font-bold text-ink">Bank soal</h1>
            <p class="mt-1 text-body text-muted">{{ $questions->total() }} soal cocok dengan filter ini.</p>
        </div>
        <a href="{{ route('admin.questions.create') }}" class="inline-flex items-center gap-2 rounded-card bg-brand px-4 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
            <x-icon name="plus" class="h-4 w-4" />
            Tambah soal
        </a>
    </div>

    @if (session('status'))
        <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">{{ session('status') }}</p>
    @endif

    <form method="GET" action="{{ route('admin.questions.index') }}" class="mt-6 grid gap-3 lg:grid-cols-[200px_220px_minmax(0,1fr)_auto]">
        <select name="category" class="rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">
            <option value="">Semua subtes</option>
            @foreach (\App\Enums\QuestionCategory::cases() as $option)
                <option value="{{ $option->value }}" @selected($category === $option)>{{ $option->label() }}</option>
            @endforeach
        </select>

        <select name="subcategory" class="rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">
            <option value="">Semua submateri</option>
            @foreach (($category?->subcategories() ?? []) as $value => $label)
                <option value="{{ $value }}" @selected($subcategory === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <input type="search" name="q" value="{{ $search }}" placeholder="Cari teks soal"
            class="rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">

        <button type="submit" class="rounded-card border border-line bg-white px-4 py-2.5 font-heading text-small font-bold text-ink">Saring</button>
    </form>

    <div class="mt-5 overflow-x-auto rounded-card border border-line bg-white shadow-card">
        <table class="w-full min-w-[860px] text-left text-small">
            <thead class="bg-surface text-caption uppercase text-muted">
                <tr>
                    <th class="px-4 py-3">Soal</th>
                    <th class="px-4 py-3">Subtes</th>
                    <th class="px-4 py-3">Submateri</th>
                    <th class="px-4 py-3">Dipakai di</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($questions as $question)
                    <tr class="border-t border-line align-top">
                        <td class="max-w-[420px] px-4 py-3 text-ink">{{ \Illuminate\Support\Str::limit($question->stem, 110) }}</td>
                        <td class="px-4 py-3">{{ $question->category->label() }}</td>
                        <td class="px-4 py-3 text-muted">{{ $question->subcategoryLabel() }}</td>
                        {{-- The blast radius, before anyone edits anything. --}}
                        <td class="px-4 py-3" title="{{ $question->packages->pluck('title')->join(', ') }}">
                            @if ($question->packages->isEmpty())
                                <span class="text-muted">belum dipakai</span>
                            @else
                                <span class="font-semibold text-ink">{{ $question->packages->count() }} paket</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="{{ $question->is_active ? 'text-success' : 'text-muted' }}">
                                {{ $question->is_active ? 'aktif' : 'nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.questions.edit', $question) }}" class="inline-flex items-center gap-1.5 font-semibold text-brand hover:text-brand-dark">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                    Ubah
                                </a>
                                <form method="POST" action="{{ route('admin.questions.destroy', $question) }}"
                                    onsubmit="return confirm('Hapus soal ini dari bank? Soal yang masih dipakai paket atau sudah dijawab peserta akan ditolak — keluarkan dulu dari paketnya atau ubah soalnya. Soal yang belum terpakai akan langsung dihapus.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1.5 font-semibold text-muted transition hover:text-danger">
                                        <x-icon name="trash" class="h-4 w-4" />
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-muted">Belum ada soal yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">{{ $questions->links() }}</div>
</x-layouts.admin>
