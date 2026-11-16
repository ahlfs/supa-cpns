@props(['review', 'filter', 'attempt', 'aiReady' => false, 'chats' => null, 'open' => null])

@php
    /*
     * One collapsed row per item instead of 110 expanded articles.
     *
     * Rendered open, the full review made the page 55,000px tall on a desktop and
     * 92,000px on a phone — a single column of scroll with no way to find one
     * item again. `<details>` keeps the list scannable and needs no JavaScript:
     * the server still renders every answer, so filtering by URL and reading a
     * particular item both work with scripting off. The toggle only removes the
     * clicking.
     *
     * This file is also, on its own, the whole body of the review card — which is
     * what a filter tab fetches when scripting is on. The card itself is declared
     * in `exam.result` and is never replaced: the swapped body goes inside it, so
     * the page keeps its scroll position instead of starting again at the top.
     */
    $statusTone = static fn (string $status): string => match ($status) {
        'correct' => 'text-success',
        'wrong' => 'text-danger',
        'answered' => 'text-ink',
        default => 'text-muted',
    };
    $statusLabel = static fn (string $status): string => match ($status) {
        'correct' => 'benar',
        'wrong' => 'salah',
        'answered' => 'terjawab',
        default => 'kosong',
    };
@endphp

<div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="font-heading text-h3 font-bold text-ink">Pembahasan</h2>

    <div class="flex flex-wrap items-center gap-2">
        {{-- Hidden until the module reveals it: without scripting the button
             cannot do anything, and a dead control is worse than none. --}}
        <button
            type="button"
            data-review-toggle-all
            data-open="false"
            hidden
            class="inline-flex min-h-[44px] items-center rounded-card border border-line px-3.5 py-2 text-small font-semibold text-muted transition hover:border-brand hover:text-brand"
        >
            Buka semua
        </button>

        {{-- Real links, because the filter is a URL: it survives a refresh, it can
             be shared, and the server is the only thing that decides what a filter
             contains. `data-review-tab` is what the module looks for to fetch the
             same address as a fragment instead of following it. --}}
        <nav class="flex flex-wrap gap-2" aria-label="Filter pembahasan">
            @foreach (['all' => 'Semua', 'wrong' => 'Salah', 'blank' => 'Kosong', 'correct' => 'Benar', 'answered' => 'Terjawab'] as $key => $label)
                <a href="{{ route('hasil.show', ['attempt' => $attempt, 'filter' => $key]) }}"
                    data-review-tab
                    @if ($filter === $key) aria-current="page" @endif
                    class="inline-flex min-h-[44px] items-center rounded-card border px-3.5 py-2 text-small font-semibold transition {{ $filter === $key ? 'border-brand bg-brand/5 text-brand' : 'border-line text-muted hover:border-brand hover:text-brand' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </div>
</div>

<div class="mt-5 divide-y divide-line" data-review-list>
    @forelse ($review as $row)
        @php($question = $row['question'])
        @php($answer = $row['answer'])
        {{-- TWK/TIU are marked against a key, so they get "benar"/"salah". TKP
             has no key at all: it reads "terjawab", a neutral claim that does
             not pretend a defensible option was right or wrong. --}}
        @php($requiresKey = $question->category->requiresKey())
        @php($chat = $chats?->get($question->id))

        <details
            data-review-item
            id="soal-{{ $row['number'] }}"
            {{-- Opened on arrival only when the participant was just sent back
                 here with an answer to a question they asked about this item. --}}
            @if ($open === $row['number']) open @endif
            class="group py-4 first:pt-0 last:pb-0"
        >
            <summary class="flex cursor-pointer list-none items-start gap-3 rounded-card px-1 py-1 transition hover:bg-surface [&::-webkit-details-marker]:hidden">
                <x-icon name="chevron-down" class="mt-0.5 h-4 w-4 shrink-0 fill-current text-muted transition-transform duration-200 group-open:rotate-180" />

                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-baseline gap-x-2 text-caption font-semibold tracking-wide text-muted uppercase">
                        Soal {{ $row['number'] }} · {{ $question->category->label() }} · {{ $question->subcategoryLabel() }}
                        <span class="{{ $statusTone($row['status']) }}">{{ $statusLabel($row['status']) }}</span>
                    </span>
                    {{-- The closed row still says what the item is about, so the
                         list can be scanned without opening all 110. --}}
                    <span class="mt-1 block truncate text-small text-body">
                        {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', strip_tags($question->stem)), 96) }}
                    </span>
                </span>
            </summary>

            <div class="mt-3 pl-7">
                <h3 class="font-semibold text-ink">{!! nl2br(e($question->stem)) !!}</h3>

                <ul class="mt-3 space-y-2">
                    @foreach ($question->options as $option)
                        <x-exam.question-option
                            :option="$option"
                            :selected="$answer?->selected_option_id === $option->id"
                            :requires-key="$requiresKey"
                            :frozen-correct="$answer?->is_correct"
                        />
                    @endforeach
                </ul>

                @if ($question->explanation)
                    <p class="mt-3 rounded-card bg-surface px-4 py-3 text-small text-body">
                        <strong class="font-semibold text-ink">Pembahasan:</strong> {{ $question->explanation }}
                    </p>
                @endif

                {{-- A link and not an inline box: asking is a conversation, and
                     a conversation needs room. Rendering 110 collapsed forms
                     inside a list of 110 rows cost 5.4 kB per item — more than
                     half a megabyte of markup for boxes almost nobody opens,
                     paid on every result page load, mostly on phones. --}}
                @if ($aiReady)
                    <a
                        href="{{ route('hasil.tanya', ['attempt' => $attempt, 'question' => $question, 'filter' => $filter]) }}"
                        class="mt-3 inline-flex min-h-[44px] items-center gap-2 rounded-card border border-line bg-white px-3.5 py-2 text-small font-semibold text-brand transition hover:border-brand hover:bg-brand/5"
                    >
                        <x-icon name="message-circle" class="h-4 w-4 shrink-0" />
                        Tanya {{ config('ai.bot_name') }} tentang soal ini
                        @if ($chat !== null && $chat->messages->isNotEmpty())
                            <span class="rounded-card bg-brand/10 px-1.5 py-0.5 text-caption font-bold text-brand">
                                {{ $chat->messages->count() }}
                            </span>
                        @endif
                    </a>
                @endif
            </div>
        </details>
    @empty
        <p class="py-2 text-small text-muted">Tidak ada soal yang cocok dengan filter ini.</p>
    @endforelse
</div>
