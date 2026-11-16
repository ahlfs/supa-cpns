<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">Impor soal</h1>
    <p class="mt-2 max-w-[70ch] text-body text-muted">
        Tempel blok berkolom atau unggah CSV. Semuanya lewat pratinjau dulu: kalau ada satu baris yang
        salah, tidak ada satu pun yang masuk.
    </p>

    @if (session('status'))
        <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <p class="mt-5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('admin.import.preview') }}" class="mt-6 space-y-4 rounded-card border border-line bg-white p-5 shadow-card">
        @csrf

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Tempel di sini</span>
            <textarea name="payload" rows="8" class="w-full rounded-card border border-line px-3.5 py-2.5 font-mono text-caption text-ink">{{ $payload }}</textarea>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Atau unggah berkas CSV</span>
            <input type="file" name="file" accept=".csv,.txt" class="text-small text-ink">
        </label>

        <button type="submit" class="rounded-card border border-line bg-white px-5 py-2.5 font-heading text-small font-bold text-ink">
            Pratinjau
        </button>
    </form>

    @if ($rows !== null)
        <div class="mt-6 rounded-card border border-line bg-white p-5 shadow-card">
            <h2 class="font-heading text-h3 font-bold text-ink">Pratinjau</h2>
            <p class="mt-1 text-small text-muted">
                {{ count($rows) }} baris siap diimpor · {{ count($importErrors) }} baris bermasalah
            </p>

            {{-- The per-row reasons, next to the line they belong to. The framework's
                 own validation bag is a different thing and stays in $errors. --}}
            @if ($importErrors !== [])
                <ul class="mt-4 space-y-1.5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
                    @foreach ($importErrors as $line => $message)
                        <li>Baris {{ $line }}: {{ $message }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($rows !== [])
                <ol class="mt-4 space-y-3 text-small">
                    @foreach (array_slice($rows, 0, 10) as $row)
                        <li class="border-t border-line pt-3 first:border-0 first:pt-0">
                            <span class="text-caption font-semibold text-muted uppercase">
                                {{ strtoupper($row['category']) }} · {{ $row['subcategory'] }}
                            </span>
                            <p class="mt-1 text-ink">{{ \Illuminate\Support\Str::limit($row['stem'], 140) }}</p>
                            <p class="mt-1 text-caption text-muted">
                                @foreach ($row['options'] as $letter => $option)
                                    {{ $letter }}{{ isset($option['score']) ? ':'.$option['score'] : '' }}{{ ($row['key'] ?? null) === $letter ? '*' : '' }}
                                @endforeach
                            </p>
                        </li>
                    @endforeach
                </ol>

                @if (count($rows) > 10)
                    <p class="mt-3 text-caption text-muted">… dan {{ count($rows) - 10 }} baris lain.</p>
                @endif

                <form method="POST" action="{{ route('admin.import.commit') }}" class="mt-5">
                    @csrf
                    <input type="hidden" name="payload" value="{{ $payload }}">
                    <button type="submit" @disabled($importErrors !== [])
                        class="rounded-card bg-brand px-5 py-3 font-heading text-small font-bold text-white transition hover:bg-brand-dark disabled:opacity-40">
                        Impor {{ count($rows) }} soal
                    </button>
                    @if ($importErrors !== [])
                        <p class="mt-2 text-caption text-danger">Tombol ini aktif setelah semua baris bersih.</p>
                    @endif
                </form>
            @endif
        </div>
    @endif
</x-layouts.admin>
