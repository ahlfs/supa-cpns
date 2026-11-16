@props(['questions', 'answers', 'currentId', 'finishUrl'])

{{--
    One navigator, two shapes. Above 992px it is a sticky column with its own
    scroll; below it, it is a bottom sheet that slides up over the question.

    The slide is a `translate-y-full` -> `translate-y-0` pair driven by
    `data-open`, and the desktop override sets the *same* value (`translate-y-0`)
    that the open state does, so whichever of the two rules wins, the result is
    identical. That is why this does not need an `!important` or a second copy of
    the markup.

    Finish lives inside this panel on purpose: on a phone it sits behind a button,
    so nobody ends the exam at item 60 while reaching for "next".

    Both copies of Finish obey one rule — closed while anything is still blank —
    and each one stands next to a live count of the blanks, so the closed button
    says what it is waiting for instead of being a dead end. The rule is in the
    markup (`@disabled`) *and* in the module, so it holds before the script loads.

    Cells are 44px tall (h-11) on both faces: on a phone this grid is tapped with
    a thumb, and a mis-tap here re-opens the wrong item mid-exam.
--}}
@php($blank = $questions->filter(fn ($question): bool => $answers->get($question->id)?->selected_option_id === null)->count())
<aside
    data-exam-sheet
    data-open="false"
    class="fixed inset-x-0 bottom-0 z-40 max-h-[70vh] translate-y-full overflow-y-auto rounded-t-card-lg border-t border-line bg-white p-4 shadow-card-hover transition-transform duration-200 data-[open=true]:translate-y-0 lg:sticky lg:top-4 lg:z-auto lg:max-h-[calc(100vh-2rem)] lg:translate-y-0 lg:rounded-card lg:border lg:shadow-card"
>
    <div class="flex items-center justify-between gap-3">
        <h2 class="font-heading text-body font-bold text-ink">Nomor soal</h2>
        <button type="button" data-exam-sheet-toggle class="flex h-11 w-11 items-center justify-center rounded-card text-muted hover:bg-surface lg:hidden">
            <x-icon name="chevron-down" class="h-5 w-5" />
            <span class="sr-only">Tutup daftar nomor</span>
        </button>
    </div>

    <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-caption text-muted">
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-success"></span> sudah dijawab
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-danger"></span> belum dijawab
        </span>
        <span class="inline-flex items-center gap-1.5">
            <x-icon name="flag" class="h-3.5 w-3.5" /> ragu-ragu
        </span>
    </p>

    <div class="mt-4 grid grid-cols-5 gap-2 lg:grid-cols-6">
        @foreach ($questions as $index => $question)
            @php($answer = $answers->get($question->id))
            @php($isAnswered = $answer?->selected_option_id !== null)
            <button
                type="button"
                data-exam-cell
                data-question-id="{{ $question->id }}"
                data-status="{{ $isAnswered ? 'answered' : 'blank' }}"
                data-flag="{{ $answer?->is_flagged ? 'true' : 'false' }}"
                data-current="{{ $currentId === $question->id ? 'true' : 'false' }}"
                class="relative flex h-11 items-center justify-center rounded-card border border-danger bg-danger/5 font-heading text-small font-semibold text-danger transition data-[status=answered]:border-success data-[status=answered]:bg-success/10 data-[status=answered]:text-success data-[current=true]:ring-2 data-[current=true]:ring-brand"
            >
                {{ $index + 1 }}
                <span data-exam-cell-flag class="{{ $answer?->is_flagged ? '' : 'hidden' }} absolute top-1 right-1 text-warning">
                    <x-icon name="flag" class="h-3 w-3" />
                </span>
            </button>
        @endforeach
    </div>

    {{-- The phone's copy of the save/refusal line: the module's answer and the
         server's refusal both land here, where the tapped button is. --}}
    <p data-exam-status class="mt-4 mb-1 h-5 text-small text-muted data-[state=ok]:text-success data-[state=error]:text-danger"></p>

    <form data-exam-finish method="POST" action="{{ $finishUrl }}" class="mt-1 lg:hidden">
        @csrf
        <button type="submit" data-exam-finish-button @disabled($blank > 0) class="w-full rounded-card bg-brand px-5 py-3 font-heading text-body font-bold text-white transition hover:bg-brand-dark disabled:opacity-50">
            Selesai ujian
        </button>
        {{-- The promise below is the module's rule, spoken out loud: the button
             stays closed while anything is blank, and the count that closes it is
             written next to it. --}}
        <p class="mt-2 text-caption text-muted">
            Tombol ini baru bisa dipakai kalau semua soal sudah dijawab.
            Belum dijawab: <span data-exam-blank>{{ $blank }}</span>.
        </p>
    </form>
</aside>
