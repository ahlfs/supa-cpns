@props([
    'section',
    'centered' => false,
])

{{--
    The shell an application page sits in — the account, tryout, dashboard and
    result screens share it.

    The header is `fixed`, so it reserves no space of its own and every page has
    to clear it. The marketing sections do that themselves (the hero's
    `pt-[110px]`); this component is the same reservation for app pages, so the
    offset lives in one place instead of in each view.

    The numbers are measured, not guessed: the pill's bottom edge sits 85px from
    the top at ≥992px (top 20px + 65px) and 68px below that (14px + 54px), so
    `pt-[96px]` / `lg:pt-[112px]` leaves the same ~28px gap under it at both
    sizes. Change the pill's geometry and this is the one edit.

    `centered` is the focused shape the account pages use: the section fills the
    viewport and the card sits in the middle of what the header leaves. Centring
    is done with `m-auto` on the card rather than `items-center` on the flex
    parent, because a flex item taller than its container gets its top clipped
    that way — the register form already exceeds a phone viewport, and `m-auto`
    lets it start at the padding and scroll instead.
--}}

@php
    $chrome = 'bg-surface px-5 pt-[96px] pb-14 lg:px-10 lg:pt-[112px] lg:pb-20';
@endphp

<section
    data-section="{{ $section }}"
    @class([$chrome, 'flex min-h-[100svh] flex-col' => $centered])
>
    @if ($centered)
        <div class="m-auto w-full max-w-[420px]">{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</section>
