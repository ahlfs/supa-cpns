{{--
    One turn of a conversation with the assistant.

    Two conversations print through this component: the thread about one item of a
    sitting, and the conversation about that sitting's own analysis. It is the only
    markup a model's text is ever printed by on either of them, so there is one
    description of what a turn looks like, and a change to it lands in both.

    Who is speaking is carried by three things at once, because on a phone one of
    them is always the one that is hard to see: the side the turn sits on, the name
    above it, and the tint. The name comes from the one place that defines it, so
    the assistant is called the same thing wherever it appears.

    Paragraph splitting happens before the component is called (`App\Support\
    AnalysisText`), so what arrives is already trimmed and stripped of the markdown
    emphasis the model adds anyway; `{{ }}` escapes it, and no page here renders
    markup.
--}}
@props(['role' => 'user', 'paragraphs' => []])

@php
    $fromTutor = $role === 'assistant';
@endphp

<div {{ $attributes->merge([
    'data-chat-turn' => $fromTutor ? 'assistant' : 'user',
    'class' => 'w-fit max-w-[88%] rounded-card border px-3.5 py-3 '.($fromTutor
        ? 'mr-auto rounded-bl-sm border-brand/20 bg-brand/5'
        : 'ml-auto rounded-br-sm border-line bg-white'),
]) }}>
    <p class="flex items-center gap-1.5 text-caption font-semibold tracking-wide uppercase {{ $fromTutor ? 'text-brand' : 'text-muted' }}">
        @if ($fromTutor)
            <x-icon name="message-circle" class="h-3.5 w-3.5 shrink-0" />
        @endif
        {{ $fromTutor ? config('ai.bot_name') : 'Kamu' }}
    </p>

    <div class="mt-1.5 space-y-1.5">
        @foreach ($paragraphs as $paragraph)
            <p class="text-small leading-relaxed text-ink">{{ $paragraph }}</p>
        @endforeach
    </div>
</div>
