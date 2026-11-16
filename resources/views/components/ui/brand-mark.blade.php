{{--
    The SUPA CPNS lockup: the blue cloud mark plus the wordmark.

    Inline SVG rather than an <img> so the mark paints from the same theme tokens
    as the rest of the page and never waits on a second request above the fold.
    The cloud glyph is Tabler Icons' filled cloud (MIT); the wordmark is live text
    set in the app's heading face, so it stays selectable, translatable and crisp
    at any zoom — no rasterised logo to re-export after a copy change.

    Variants:
      tone="default"  brand-blue cloud on a light surface (header, footer)
      tone="inverse"  white cloud for a brand-coloured surface (hero, blue cards)
      :show-wordmark="false"  the mark alone, for tight spots like a favicon slot

    Size comes from the `mark` prop (the cloud's height in px); the wordmark scales
    with `text` so the two keep their optical relationship.
--}}
@props([
    'mark' => 34,
    'text' => 18,
    'tone' => 'default',
    'showWordmark' => true,
])

@php
    $cloudColor = $tone === 'inverse' ? '#ffffff' : '#0657a8';
    $firstWord = $tone === 'inverse' ? 'text-white' : 'text-ink';
    $secondWord = $tone === 'inverse' ? 'text-white' : 'text-brand';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 select-none']) }}>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        width="{{ $mark }}"
        height="{{ $mark }}"
        fill="{{ $cloudColor }}"
        aria-hidden="true"
        focusable="false"
        class="block shrink-0"
    >
        {{-- Tabler Icons "cloud" (filled), MIT licensed. --}}
        <path d="M10.04 4.305c2.195-.667 4.615-.224 6.36 1.176c1.386 1.108 2.188 2.686 2.252 4.34l.003 .212l.091 .003c2.3 .107 4.143 1.961 4.25 4.27l.004 .211c0 2.407 -1.885 4.372 -4.255 4.482l-.21 .005h-11.878l-.222 -.008c-2.94 -.11 -5.317 -2.399 -5.43 -5.263l-.005 -.216c0 -2.747 2.08 -5.01 4.784 -5.417l.114 -.016l.07 -.181c.663 -1.62 2.056 -2.906 3.829 -3.518l.244 -.08z" />
    </svg>

    @if ($showWordmark)
        <span class="font-heading leading-none font-extrabold tracking-[-0.01em] whitespace-nowrap" style="font-size: {{ $text }}px">
            <span class="{{ $firstWord }}">SUPA</span> <span class="{{ $secondWord }}">CPNS</span>
        </span>
    @endif
</span>
