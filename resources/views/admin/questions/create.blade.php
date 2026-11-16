<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">Tambah soal</h1>

    @if ($category === null)
        <p class="mt-2 text-body text-muted">Pilih subtesnya dulu — bentuk formnya berbeda antara TWK/TIU dan TKP.</p>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            @foreach (\App\Enums\QuestionCategory::cases() as $option)
                <a href="{{ route('admin.questions.create', ['category' => $option->value]) }}"
                    class="rounded-card border border-line bg-white p-5 shadow-card transition hover:border-brand">
                    <strong class="font-heading text-body font-bold text-ink">{{ $option->label() }}</strong>
                    <p class="mt-1 text-small text-muted">{{ $option->name() }}</p>
                    <p class="mt-3 text-caption text-muted">
                        {{ $option->itemCount() }} soal per paket ·
                        {{ $option->requiresKey() ? 'satu kunci jawaban' : 'bobot 1-'.$option->perItemMaximum().' per opsi' }}
                    </p>
                </a>
            @endforeach
        </div>
    @else
        @include('admin.questions.form', [
            'question' => null,
            'category' => $category,
            'action' => route('admin.questions.store'),
            'method' => 'POST',
        ])
    @endif
</x-layouts.admin>
