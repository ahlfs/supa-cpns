{{--
    One option row of a keyed or weighted item.

    It lives in a component because two pages now print the same list: the review
    list inside the result page, and the page where a participant asks about one
    item. Both have to say the same three things about an option — whether it is
    the key, what it is worth, and whether it was the participant's own pick — and
    a second copy of that reasoning is how the two would start disagreeing.

    The verdict is the FROZEN one from the answer row, never the option's live
    flag: an admin who re-keys an item after the sitting must not repaint a
    finished answer.

    `data-option-tone` is the row's verdict as a word. Assertions about "nothing
    on this page is marked wrong" read that instead of a colour utility, so a
    restyle cannot silently turn a real check into a passing one.
--}}
@props(['option', 'selected' => false, 'requiresKey' => true, 'frozenCorrect' => null])

@php
    $verdict = $selected && $requiresKey ? $frozenCorrect : null;
    $frozenWasCorrect = $requiresKey && $frozenCorrect === true;
    // A live key is shown only when it is not already the participant's own
    // frozen-correct pick, so the key badge can never contradict a "benar".
    $isKey = $requiresKey && $option->is_correct === true && ! $frozenWasCorrect;

    $tone = match (true) {
        $verdict === true => 'success',
        $verdict === false => 'danger',
        $isKey => 'success',
        $selected => 'neutral',
        default => 'plain',
    };
@endphp

<li
    data-option-tone="{{ $tone }}"
    class="flex items-start gap-3 rounded-card border px-4 py-2.5 text-small
        {{ match ($tone) {
            'success' => 'border-success bg-success/5',
            'danger' => 'border-danger bg-danger/5',
            'neutral' => 'border-brand bg-brand/5',
            default => 'border-line',
        } }}"
>
    <span class="font-heading font-bold text-muted">{{ $option->label }}</span>
    <span class="min-w-0 flex-1 text-ink">{{ $option->body }}</span>
    @if ($option->score !== null)
        <span class="shrink-0 text-caption text-muted">bobot {{ $option->score }}</span>
    @endif
    @if ($selected)
        <span class="shrink-0 text-caption font-semibold {{ match ($tone) {
            'success' => 'text-success',
            'danger' => 'text-danger',
            default => 'text-muted',
        } }}">jawabanmu</span>
    @elseif ($isKey)
        <span class="shrink-0 text-caption font-semibold text-success">kunci</span>
    @endif
</li>
