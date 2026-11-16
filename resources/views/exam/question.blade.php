{{--
    One item of a finished sitting, and the conversation about it.

    Its own page rather than a box inside the review list, for two reasons. A
    conversation needs room — on a phone, a nested panel inside a row of a
    110-row list has none, and the answer is the thing the participant came for.
    And the list is 696 kB of markup before anything is added to it; 110 collapsed
    forms would have been another 597 kB on every result page load.

    The whole exchange is a plain form post. There is no fetch here: the page is
    one item and one thread, so there is nothing to preserve by staying put, and
    the server stays the only thing that decides what a turn looks like.
--}}
<x-layouts.app :site="$site" :footer="$footer" :floating="$floating">
    <x-ui.app-page section="question">
        <div class="mx-auto max-w-page space-y-6">
            <a
                href="{{ route('hasil.show', ['attempt' => $attempt, 'filter' => $filter, 'buka' => $number]).'#soal-'.$number }}"
                class="inline-flex min-h-[44px] items-center gap-2 text-small font-semibold text-brand hover:text-brand-dark"
            >
                <x-icon name="arrow-left" class="h-4 w-4 shrink-0" />
                Kembali ke pembahasan
            </a>

            <header>
                <p class="text-caption font-semibold tracking-wide text-muted uppercase">
                    Soal {{ $number }} · {{ $question->category->label() }} · {{ $question->subcategoryLabel() }}
                </p>
                <h1 class="mt-1 font-heading text-h2 font-bold text-ink">Tanya {{ config('ai.bot_name') }} tentang soal ini</h1>
            </header>

            <section class="rounded-card border border-line bg-white p-5 shadow-card lg:p-6">
                <h2 class="text-body font-semibold text-ink">{!! nl2br(e($question->stem)) !!}</h2>

                <ul class="mt-3 space-y-2">
                    @foreach ($question->options as $option)
                        <x-exam.question-option
                            :option="$option"
                            :selected="$answer?->selected_option_id === $option->id"
                            :requires-key="$question->category->requiresKey()"
                            :frozen-correct="$answer?->is_correct"
                        />
                    @endforeach
                </ul>

                @if ($question->explanation)
                    <p class="mt-3 rounded-card bg-surface px-4 py-3 text-small text-body">
                        <strong class="font-semibold text-ink">Pembahasan:</strong> {{ $question->explanation }}
                    </p>
                @endif
            </section>

            <section class="rounded-card border border-line bg-white p-5 shadow-card lg:p-6">
                <h2 class="font-heading text-h3 font-bold text-ink">Percakapan</h2>

                @if (session('error'))
                    <p class="mt-3 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
                        {{ session('error') }}
                    </p>
                @endif

                <div data-chat-thread class="mt-4 space-y-3">
                    @forelse ($messages as $turn)
                        <x-exam.chat-bubble
                            :role="$turn->role->value"
                            :paragraphs="\App\Support\AnalysisText::paragraphs($turn->content)"
                        />
                    @empty
                        <p class="text-small text-muted">
                            Belum ada yang ditanyakan. Tanyakan bagian dari soal ini yang belum jelas — misalnya kenapa
                            pilihanmu belum tepat, atau apa yang sebenarnya diuji soal ini.
                        </p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('hasil.tanya', ['attempt' => $attempt, 'question' => $question, 'filter' => $filter]) }}" data-chat-form class="mt-5">
                    @csrf

                    {{-- A rejected message comes back here rather than vanishing:
                         pressing Kirim on an empty box is the one validation failure
                         reachable by hand, and a page that silently does nothing
                         reads as a broken feature. --}}
                    @error('message')
                        <p class="mt-3 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">{{ $message }}</p>
                    @enderror

                    <label for="pesan" class="text-small font-semibold text-ink">Pertanyaanmu</label>
                    <textarea
                        id="pesan"
                        name="message"
                        rows="3"
                        maxlength="{{ \App\Services\Ai\QuestionTutor::MAX_MESSAGE_CHARS }}"
                        data-chat-input
                        placeholder="Contoh: kenapa jawabanku salah?"
                        class="mt-1.5 w-full rounded-card border border-field bg-white px-3.5 py-2.5 text-small text-ink placeholder:text-muted focus:outline-2 focus:outline-offset-1 focus:outline-brand"
                    >{{ old('message') }}</textarea>

                    {{-- Submitting buttons rather than boxes that need scripting to
                         be filled in: pressing one sends its own text as the
                         question, and the browser is not asked to run anything. --}}
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($suggestions as $suggestion)
                            <button
                                type="submit"
                                name="saran"
                                value="{{ $suggestion }}"
                                class="min-h-[40px] rounded-card border border-line bg-surface px-3 py-2 text-small text-body transition hover:border-brand hover:text-brand"
                            >{{ $suggestion }}</button>
                        @endforeach
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            data-chat-submit
                            class="inline-flex min-h-[44px] items-center gap-2 rounded-card bg-brand px-5 py-3 font-heading text-small font-bold text-white transition hover:bg-brand-dark focus:outline-2 focus:outline-offset-2 focus:outline-brand"
                        >
                            <x-icon name="send" class="h-4 w-4" />
                            Kirim
                        </button>

                        <span data-chat-status hidden class="inline-flex items-center gap-2 text-small text-muted">
                            <x-icon name="clock" class="h-4 w-4" />
                            Sedang menyusun jawaban. Bisa sampai satu menit, jangan tutup halaman ini.
                        </span>

                        <span class="text-caption text-muted">Enter mengirim, Shift+Enter membuat baris baru.</span>
                    </div>
                </form>

                <p class="mt-4 rounded-card border border-line bg-surface px-4 py-3 text-caption text-muted">
                    Jawaban ditulis model bahasa dan hanya membahas soal ini serta opsinya — bukan penilaian resmi, dan
                    tidak mengubah skormu. Yang dikirim ke model: teks soal ini, kelima opsinya, pilihanmu, pembahasannya,
                    dan pertanyaanmu.
                </p>
            </section>
        </div>
    </x-ui.app-page>
</x-layouts.app>
