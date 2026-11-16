@props(['question', 'category', 'action', 'method'])

@php
    $options = $question?->options->keyBy('label') ?? collect();
    $key = $question?->options->firstWhere('is_correct', true)?->label;
    // The weight ceiling is the blueprint's per-item maximum, read from the enum,
    // never a literal 5 in the markup.
    $weightCeiling = $category->perItemMaximum();
@endphp

@if ($errors->any())
    <ul class="mt-5 space-y-1 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="{{ $action }}" class="mt-6 space-y-5">
    @csrf
    @method($method)

    <input type="hidden" name="category" value="{{ $category->value }}">

    <div class="grid gap-4 rounded-card border border-line bg-white p-5 shadow-card lg:grid-cols-2">
        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Submateri</span>
            <select name="subcategory" class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
                @foreach ($category->subcategories() as $value => $label)
                    <option value="{{ $value }}" @selected(old('subcategory', $question?->subcategory) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Status</span>
            <select name="is_active" class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
                <option value="1" @selected(old('is_active', $question?->is_active ?? true))>Aktif</option>
                <option value="0" @selected(! old('is_active', $question?->is_active ?? true))>Nonaktif (tidak dipakai paket baru)</option>
            </select>
        </label>

        <label class="block lg:col-span-2">
            <span class="mb-1.5 block text-small font-semibold text-ink">Teks soal</span>
            <textarea name="stem" rows="4" required
                class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">{{ old('stem', $question?->stem) }}</textarea>
        </label>
    </div>

    <div class="space-y-3 rounded-card border border-line bg-white p-5 shadow-card">
        <p class="text-small font-semibold text-ink">
            Lima opsi
            @if ($category->requiresKey())
                <span class="font-normal text-muted">— pilih satu kunci jawaban</span>
            @else
                <span class="font-normal text-muted">— beri bobot 1 sampai {{ $weightCeiling }}, masing-masing dipakai sekali</span>
            @endif
        </p>

        @foreach (\App\Models\QuestionOption::LETTERS as $letter)
            @php $score = old("options.{$letter}.score", $options->get($letter)?->score); @endphp

            <div class="grid gap-3 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-start">
                @if ($category->requiresKey())
                    <label class="flex items-center gap-2 rounded-card border border-line px-3.5 py-3 lg:self-stretch">
                        <input type="radio" name="key" value="{{ $letter }}" @checked(old('key', $key) === $letter) @required($letter === 'A')>
                        <span class="font-heading text-small font-bold text-ink">{{ $letter }}</span>
                        <span class="text-caption text-muted">kunci</span>
                    </label>
                @else
                    <span class="flex items-center justify-center rounded-card border border-line px-3.5 py-3 font-heading text-small font-bold text-muted">{{ $letter }}</span>
                @endif

                <input type="text" name="options[{{ $letter }}][body]" required
                    value="{{ old("options.{$letter}.body", $options->get($letter)?->body) }}"
                    placeholder="Isi opsi {{ $letter }}"
                    class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">

                @unless ($category->requiresKey())
                    <label class="flex items-center gap-2">
                        <span class="text-caption text-muted">Bobot</span>
                        {{-- The empty first option is deliberate: an untouched form must not look
                             like the admin chose a weight, and the server refuses a missing one. --}}
                        <select name="options[{{ $letter }}][score]" required class="rounded-card border border-line px-3 py-2.5 text-small text-ink">
                            <option value="" @selected($score === null)>Pilih bobot</option>
                            @foreach (range(1, $weightCeiling) as $optionScore)
                                <option value="{{ $optionScore }}" @selected((int) $score === $optionScore)>{{ $optionScore }}</option>
                            @endforeach
                        </select>
                    </label>
                @endunless
            </div>
        @endforeach
    </div>

    <label class="block rounded-card border border-line bg-white p-5 shadow-card">
        <span class="mb-1.5 block text-small font-semibold text-ink">Pembahasan</span>
        <span class="mb-2 block text-caption text-muted">Muncul di halaman analisa begitu peserta menyelesaikan ujian.</span>
        <textarea name="explanation" rows="3"
            class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">{{ old('explanation', $question?->explanation) }}</textarea>
    </label>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="rounded-card bg-brand px-5 py-3 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
            Simpan soal
        </button>
        <a href="{{ route('admin.questions.index') }}" class="rounded-card border border-line bg-white px-5 py-3 font-heading text-small font-bold text-ink">
            Batal
        </a>
    </div>
</form>
