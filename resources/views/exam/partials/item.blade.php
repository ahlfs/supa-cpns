@props(['question', 'answer', 'number', 'visible' => false])

<div
    data-exam-item="{{ $question->id }}"
    @unless ($visible) hidden @endunless
    class="rounded-card border border-line bg-white p-5 shadow-card lg:p-7"
>
    <p class="text-caption font-semibold tracking-wide text-muted uppercase">
        {{ $question->category->label() }} · {{ $question->subcategoryLabel() }}
    </p>

    <h2 class="mt-3 text-[16px] leading-[1.6] font-semibold text-ink lg:text-[17px]">
        <span class="text-muted">{{ $number }}.</span>
        {!! nl2br(e($question->stem)) !!}
    </h2>

    <div class="mt-5 space-y-2.5">
        @foreach ($question->options as $option)
            <button
                type="button"
                data-exam-option
                data-option-id="{{ $option->id }}"
                data-label="{{ $option->label }}"
                data-selected="{{ $answer?->selected_option_id === $option->id ? 'true' : 'false' }}"
                class="flex min-h-[44px] w-full items-start gap-3 rounded-card border border-line bg-white px-4 py-3 text-left text-body text-ink transition hover:border-brand data-[selected=true]:border-brand data-[selected=true]:bg-brand/5"
            >
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-line font-heading text-caption font-bold text-muted">
                    {{ $option->label }}
                </span>
                <span class="min-w-0">{{ $option->body }}</span>
            </button>
        @endforeach
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-3">
        <button
            type="button"
            data-exam-flag
            data-question-id="{{ $question->id }}"
            data-flagged="{{ $answer?->is_flagged ? 'true' : 'false' }}"
            class="inline-flex min-h-[44px] items-center gap-2 rounded-card border border-line px-4 py-2.5 text-small font-semibold text-muted transition hover:border-warning hover:text-warning data-[flagged=true]:border-warning data-[flagged=true]:bg-warning/5 data-[flagged=true]:text-warning"
        >
            <x-icon name="flag" class="h-4 w-4" />
            Tandai ragu-ragu
        </button>
    </div>
</div>
