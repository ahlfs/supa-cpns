@php
    /*
     * Task 10 registers the three endpoints below. Until they exist the room
     * still renders — the markup shape is the contract Task 10 fills in, and the
     * same expression emits the real URLs the moment the routes are there, with
     * no edit in this file. Registering stand-in routes here instead would
     * shadow Task 10's handlers, because the first matching route wins dispatch.
     */
    $answerUrl = \Illuminate\Support\Facades\Route::has('tryout.answer') ? route('tryout.answer', $attempt) : '';
    $positionUrl = \Illuminate\Support\Facades\Route::has('tryout.position') ? route('tryout.position', $attempt) : '';
    $finishUrl = \Illuminate\Support\Facades\Route::has('tryout.finish') ? route('tryout.finish', $attempt) : '';

    $total = $questions->count();
    $currentNumber = $questions->search(fn ($question) => $question->id === $current->id) + 1;
@endphp

<x-layouts.exam :title="$attempt->package->title">
    <div
        data-exam
        data-current-question-id="{{ $current->id }}"
        data-answer-url="{{ $answerUrl }}"
        data-position-url="{{ $positionUrl }}"
        data-total="{{ $total }}"
        data-answered="{{ $answered }}"
        data-remaining="{{ $remaining }}"
    >
        {{-- Phone: a big clock and the way to the navigator. --}}
        <header class="sticky top-0 z-30 border-b border-line bg-white px-4 py-3 lg:hidden">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <span data-exam-clock data-state="normal" class="font-heading text-[22px] font-bold text-ink data-[state=warning]:text-warning data-[state=danger]:text-danger">--:--</span>
                    <p class="text-caption text-muted">Soal <span data-exam-number>{{ $currentNumber }}</span> dari {{ $total }}</p>
                </div>
                <button type="button" data-exam-sheet-toggle class="inline-flex min-h-[44px] items-center gap-2 rounded-card border border-line px-4 py-2.5 text-small font-semibold text-ink">
                    <x-icon name="list" class="h-4 w-4" />
                    Nomor soal
                </button>
            </div>
        </header>

        {{-- Desktop: the CAT-shaped strip. --}}
        <header class="hidden border-b border-line bg-white px-6 py-3 lg:block">
            <div class="mx-auto flex max-w-page flex-wrap items-center gap-x-6 gap-y-2 text-small">
                <strong class="font-heading text-body font-bold text-ink">{{ auth()->user()->name }}</strong>
                <span class="text-muted">No. peserta {{ auth()->user()->participant_number }}</span>
                <span class="text-muted">Berakhir {{ $attempt->expires_at->format('H:i') }}</span>
                <span class="text-muted">{{ $total }} soal</span>
                <span class="text-muted">Dijawab <span data-exam-answered>{{ $answered }}</span></span>
                <span class="text-muted">Belum <span data-exam-blank>{{ $total - $answered }}</span></span>
                <span class="ml-auto inline-flex items-center gap-2">
                    <x-icon name="clock" class="h-4 w-4 text-muted" />
                    <span data-exam-clock data-state="normal" class="font-heading text-body font-bold text-ink data-[state=warning]:text-warning data-[state=danger]:text-danger">--:--</span>
                </span>
                <form data-exam-finish method="POST" action="{{ $finishUrl }}">
                    @csrf
                    <button type="submit" data-exam-finish-button @disabled($answered < $total)
                        class="rounded-card bg-brand px-5 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark disabled:opacity-40">
                        Selesai ujian
                    </button>
                </form>
            </div>
        </header>

        <div class="mx-auto grid max-w-page gap-5 px-4 py-5 lg:grid-cols-[320px_minmax(0,1fr)] lg:px-6 lg:py-6">
            @include('exam.partials.navigator', [
                'questions' => $questions,
                'answers' => $answers,
                'currentId' => $current->id,
                'finishUrl' => $finishUrl,
            ])

            <main class="min-w-0">
                @if (session('status'))
                    {{-- "Sesi sebelumnya ditutup dan dinilai sebagai waktu habis." — a
                         restart lands here, so this is where it has to be said. --}}
                    <p class="mb-4 rounded-card border border-warning/30 bg-warning/5 px-4 py-3 text-small text-warning">
                        {{ session('status') }}
                    </p>
                @endif

                @if ($errors->any())
                    <p role="alert" class="mb-4 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
                        {{ $errors->first() }}
                    </p>
                @endif

                <p data-exam-status role="status" aria-live="polite" class="mb-3 h-5 text-small text-muted data-[state=ok]:text-success data-[state=error]:text-danger"></p>

                <div class="space-y-5">
                    @foreach ($questions as $index => $question)
                        @include('exam.partials.item', [
                            'question' => $question,
                            'answer' => $answers->get($question->id),
                            'number' => $index + 1,
                            'visible' => $question->id === $current->id,
                        ])
                    @endforeach
                </div>

                {{-- The phone's bottom bar: which item this is, and the three ways
                     out of it. Above 992px this row is one more control strip
                     under the item. --}}
                <div class="sticky bottom-0 mt-5 border-t border-line bg-surface py-3 lg:static lg:border-0">
                    <p class="mb-2 text-center text-caption font-semibold text-ink">
                        Soal <span data-exam-number>{{ $currentNumber }}</span> dari {{ $total }}
                    </p>

                    <div class="flex items-center justify-between gap-3">
                        <button type="button" data-exam-prev class="inline-flex min-h-[44px] items-center gap-2 rounded-card border border-line bg-white px-4 py-3 text-small font-semibold text-ink">
                            <x-icon name="arrow-left" class="h-4 w-4" />
                            Sebelumnya
                        </button>
                        <button type="button" data-exam-skip class="inline-flex min-h-[44px] items-center gap-2 rounded-card border border-line bg-white px-4 py-3 text-small font-semibold text-muted">
                            <x-icon name="skip" class="h-4 w-4" />
                            Lewati soal ini
                        </button>
                        <button type="button" data-exam-next class="inline-flex min-h-[44px] items-center gap-2 rounded-card bg-brand px-4 py-3 text-small font-semibold text-white">
                            Berikutnya
                            <x-icon name="arrow-right" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </main>
        </div>
    </div>
</x-layouts.exam>
