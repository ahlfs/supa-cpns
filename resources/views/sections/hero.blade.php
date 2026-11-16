{{--
    Hero — the blue panel above the fold.

    Layout: the 1140px grid is 630px of copy plus a flexible second column, and the
    right column holds the four decorative props. The section keeps its measured
    640px height and 110px of top padding, and the copy keeps the 90px of bottom
    padding that put it on the reference's baseline, so removing the product shot
    moved nothing on the left.

    The tagline is the page's only <h1> and is set as a plain kicker line. It used
    to sit inside a translucent capsule; the capsule is gone because a badge drawn
    around the page's own tagline is decoration the hero does not need, and the
    heading is unchanged underneath it.

    The props are the hero's only artwork now. They are one 2x2 grid rather than
    four absolutely positioned corners of an invisible square: the cluster stays
    tidy at every width, the icons cannot drift apart, and adding or reordering one
    is an edit to resources/content/home/hero.php alone. They are white because the
    panel behind them is brand blue — at brand blue they disappeared into it, which
    only became visible once the light product shot was removed.

    Breakpoint note: every property is varied by at most one of `lg:` / `max-sm:`
    on top of its base value; those two never apply at the same width, so the
    result does not depend on Tailwind's variant ordering.
--}}
@php
    /*
     * One animation offset per grid cell so the cluster breathes instead of
     * pulsing in unison — the reference's own -1.2s stagger. Keyed by position, so
     * reordering the content re-staggers the cluster without touching this view.
     */
    $propDelays = ['', '[animation-delay:-1.2s]', '[animation-delay:-2.5s]', '[animation-delay:-3.7s]'];
@endphp
<section
    data-section="hero"
    class="relative w-full bg-brand px-6 pt-[110px] lg:h-[640px] lg:px-0 max-sm:px-5 max-sm:pt-[95px]"
>
    <div class="mx-auto grid w-full max-w-page items-end gap-[30px] px-6 lg:h-[530px] lg:grid-cols-[630px_minmax(0,1fr)] lg:justify-between lg:gap-0 max-sm:px-4">
        <div class="text-center lg:pb-[90px] lg:pr-[30px] lg:text-left">
            <h1 class="mb-[22px] font-heading text-sm leading-[1.2] font-bold tracking-[0.08em] text-[#ffffffd9] uppercase max-sm:mb-4 max-sm:text-[11px] max-sm:leading-[1.35]">{{ $site['tagline'] }}</h1>

            <h2 class="mb-[22px] font-heading text-[35px] leading-[1.35] font-bold text-white max-sm:mx-auto max-sm:mb-[18px] max-sm:max-w-[340px] max-sm:text-[22px] max-sm:leading-[1.4]">{{ $hero['title'] }}</h2>

            <p class="mb-8 max-w-[580px] text-[18px] leading-[1.75] font-normal text-[#ffffffe0] max-sm:mx-auto max-sm:mb-7 max-sm:max-w-[340px] max-sm:text-[15px] max-sm:leading-[1.65]">{{ sprintf($hero['lead'], $site['brand']) }}</p>

            <div class="mt-[30px] flex flex-wrap items-center justify-center gap-4 lg:justify-start lg:gap-6 max-sm:w-full max-sm:flex-col">
                <a
                    href="{{ $hero['actions']['register']['url'] }}"
                    class="inline-flex h-[55px] min-h-[55px] items-center gap-3 rounded-full bg-accent px-[55px] py-5 font-heading text-[15px] font-black whitespace-nowrap text-white [text-shadow:0_1px_2px_#00000059] shadow-[inset_0_0_0_1.5px_#ffffffe6,0_12px_28px_-10px_#ff831f8c,0_2px_8px_#04224233] transition-[background-color,box-shadow,transform] duration-[160ms] hover:-translate-y-0.5 hover:bg-accent-dark focus-visible:-translate-y-0.5 focus-visible:bg-accent-dark active:translate-y-0 active:shadow-[0_6px_16px_-8px_#04224280,0_1px_4px_#0422422e] max-sm:px-[48px] max-sm:py-[14px] max-sm:font-extrabold"
                >{{ $hero['actions']['register']['label'] }}<x-icon name="cursor-click" class="h-3.5 w-3.5" /></a>

                <a
                    href="{{ $hero['actions']['login']['url'] }}"
                    class="inline-flex h-[55px] min-h-[55px] items-center gap-2.5 rounded-full border border-white/45 bg-white/10 px-[42px] py-5 font-heading text-[15px] font-bold whitespace-nowrap text-white transition-[background-color,border-color,transform] duration-[160ms] hover:-translate-y-0.5 hover:border-white hover:bg-white/20 max-sm:px-[44px] max-sm:py-[14px]"
                >{{ $hero['actions']['login']['label'] }}</a>
            </div>
        </div>

        {{-- Decorative only: the cluster carries no text, and every prop already
             arrives with `aria-hidden` from resources/content/home/hero.php. --}}
        <div
            class="mx-auto grid w-[240px] grid-cols-2 grid-rows-2 place-items-center gap-4 lg:mx-0 lg:w-[440px] lg:justify-self-end lg:gap-6 lg:self-center"
        >
            @foreach ($hero['visual']['floats'] as $float)
                <x-ui.image
                    :image="$float['image']"
                    class="block h-[60px] w-[60px] animate-hero-float object-contain drop-shadow-[0_14px_22px_#073d7847] lg:h-28 lg:w-28 {{ $propDelays[$loop->index] ?? '' }}"
                />
            @endforeach
        </div>
    </div>
</section>
