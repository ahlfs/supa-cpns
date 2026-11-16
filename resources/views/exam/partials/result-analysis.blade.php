{{--
    The conversation about this sitting's analysis, between the score cards and the
    per-subtopic breakdown: the breakdown answers "where did the points go", this
    answers "what do I do about it" — and then whatever the participant still wants
    to know.

    It reads as a conversation because it is one. The written analysis is the
    assistant's opening turn, on the left, named, printed by the same component the
    item thread uses; the request that produced it is the participant's turn on the
    right; and everything asked afterwards follows in order. Before an analysis
    exists there is no conversation yet — just what it will be made of, and the one
    button that spends the money.

    Three states, and the first one is the important one — when the gateway is not
    configured at all the section renders NOTHING, not a disabled box. A dead button
    invites a support question; absent markup answers it.

    The content is the one place in this application that prints text a machine wrote.
    It is escaped like any other untrusted string (`{{ }}` and never `{!! !!}`), and
    `App\Support\AnalysisText` reduces it to trimmed paragraphs first — the model
    answers in markdown with deep indentation of its own, and neither belongs on a
    page that renders no markup. Nothing the model returns can become markup here.
--}}
@php
    $aiConfigured = (bool) config('ai.enabled')
        && is_string(config('ai.api_key'))
        && trim(config('ai.api_key')) !== '';

    // The table belongs to this feature's own migration. Both checks are needed:
    // without the first there is no gateway to call, and without the second a
    // checkout whose code arrived before `migrate` ran would turn every result
    // page into an error. One introspection query against a page that already
    // makes a dozen is a fair price for that.
    $aiReady = $aiConfigured && \Illuminate\Support\Facades\Schema::hasTable('attempt_analyses');

    $analysis = $aiReady ? $attempt->analysis : null;
    $paragraphs = $analysis === null ? [] : \App\Support\AnalysisText::paragraphs($analysis->content);

    // Asking back needs its own table, and the service is the thing that knows
    // what "ready" means for it. Asking it here rather than repeating the checks
    // is what keeps the box from appearing when the answer under it could never
    // be stored.
    $chatReady = $aiReady && app(\App\Services\Ai\AttemptTutor::class)->isReady();
    $turns = $chatReady && $analysis !== null ? $analysis->messages()->get() : collect();
@endphp

@if ($aiReady)
    <section id="analisa" data-analysis class="scroll-mt-24 rounded-card border border-line bg-white p-5 shadow-card lg:p-6">
        <div class="flex items-start gap-3">
            <span class="mt-0.5 shrink-0 text-brand"><x-icon name="message-circle" class="h-5 w-5" /></span>
            <div class="min-w-0 flex-1">
                <h2 class="font-heading text-h3 font-bold text-ink">Analisa AI</h2>

                @if (session('error'))
                    <p class="mt-3 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
                        {{ session('error') }}
                    </p>
                @endif

                @if ($analysis === null)
                    <p class="mt-2 text-body text-muted">
                        Analisa ini dibaca dari angka di halaman ini — skor tiap subtes, submateri terlemah, dan
                        waktu pengerjaan — lalu ditulis ulang oleh model bahasa sebagai saran belajar. Sekali dibuat,
                        hasilnya disimpan untuk percobaan ini dan tidak dihitung lagi.
                    </p>

                    <form method="POST" action="{{ route('hasil.analisa', $attempt) }}" data-analysis-form class="mt-4">
                        @csrf
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" data-analysis-submit
                                class="inline-flex min-h-[44px] items-center gap-2 rounded-card bg-brand px-5 py-3 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
                                <x-icon name="pencil" class="h-4 w-4" />
                                Buat analisa AI
                            </button>
                            <span data-analysis-status hidden class="inline-flex items-center gap-2 text-small text-muted">
                                <x-icon name="clock" class="h-4 w-4" />
                                Sedang menganalisa. Satu permintaan bisa memakan waktu sampai satu menit, jangan tutup halaman ini.
                            </span>
                        </div>
                    </form>

                    <p class="mt-3 text-caption text-muted">
                        Hasilnya saran belajar otomatis, bukan penilaian resmi, dan tidak mengubah skormu. Setelah jadi,
                        kamu bisa tanya balik tentang bagian mana pun yang belum jelas.
                    </p>
                @else
                    <p class="mt-1 text-caption text-muted">
                        Percakapan dengan {{ config('ai.bot_name') }} tentang hasil percobaan ini. Analisanya ditulis
                        otomatis oleh {{ $analysis->model }}
                        @if ($analysis->created_at !== null)
                            · {{ $analysis->created_at->translatedFormat('d F Y, H:i') }}
                        @endif
                    </p>

                    <div data-chat-thread class="mt-4 space-y-3">
                        {{-- What the participant asked for, and what came back. The
                             analysis is the assistant's opening turn and stays one:
                             it is stored on the sitting rather than as a row of the
                             conversation, so regenerating it cannot leave the two
                             disagreeing with each other. --}}
                        <x-exam.chat-bubble :paragraphs="['Analisa hasil tryout saya.']" />

                        <x-exam.chat-bubble role="assistant" :paragraphs="$paragraphs" />

                        @foreach ($turns as $turn)
                            <x-exam.chat-bubble
                                :role="$turn->role->value"
                                :paragraphs="\App\Support\AnalysisText::paragraphs($turn->content)"
                            />
                        @endforeach
                    </div>

                    @if ($chatReady)
                        <form method="POST" action="{{ route('hasil.analisa.tanya', $attempt) }}" data-chat-form class="mt-5">
                            @csrf

                            @error('message')
                                <p class="mt-3 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">{{ $message }}</p>
                            @enderror

                            <label for="pertanyaan-analisa" class="text-small font-semibold text-ink">Tanya lanjutan</label>

                            <textarea
                                id="pertanyaan-analisa"
                                name="message"
                                rows="2"
                                maxlength="{{ \App\Services\Ai\AttemptTutor::MAX_MESSAGE_CHARS }}"
                                data-chat-input
                                placeholder="Contoh: kenapa TIU jadi subtes paling lemah?"
                                class="mt-1.5 w-full rounded-card border border-field bg-white px-3.5 py-2.5 text-small text-ink placeholder:text-muted focus:outline-2 focus:outline-offset-1 focus:outline-brand"
                            >{{ old('message') }}</textarea>

                            <div class="mt-3 flex flex-wrap items-center gap-3">
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
                    @endif

                    <p class="mt-4 rounded-card border border-line bg-surface px-4 py-3 text-caption text-muted">
                        Ini tulisan mesin, bukan penilaian resmi panitia: angkanya diambil dari halaman ini, tetapi
                        saran belajarnya dibuat oleh model bahasa dan bisa keliru. Skor resmimu tetap yang tertera di
                        atas.
                    </p>

                    <form method="POST" action="{{ route('hasil.analisa', $attempt) }}" data-analysis-form class="mt-4">
                        @csrf
                        <input type="hidden" name="ulangi" value="1">

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" data-analysis-submit
                                class="inline-flex min-h-[44px] items-center rounded-card border border-line bg-white px-4 py-3 font-heading text-small font-bold text-ink transition hover:border-brand hover:text-brand">
                                Buat ulang
                            </button>
                            <span data-analysis-status hidden class="inline-flex items-center gap-2 text-small text-muted">
                                <x-icon name="clock" class="h-4 w-4" />
                                Sedang menganalisa ulang. Hasil lama tetap tampil sampai yang baru selesai.
                            </span>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endif
