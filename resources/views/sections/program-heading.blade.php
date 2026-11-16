{{--
    "Program Belajar" — the centred heading block and the six-tab tablist.

    Reference geometry, measured in a browser (tools/probe-breakpoints.mjs and
    .probe/computed/program-heading-<width>.json):
      ≥992px   section padding 30px 0 20px; container capped at 1140px with 24px
               gutters; heading block capped at 820px; h2 30px/1.3, 800 weight,
               -0.02em tracking; 80x3 divider walking a 10px dot left→right;
               tablist 28px above / 48px below, pill 6px padding, 4px gaps
      641-991  section padding 36px 20px 0; the heading block fills the 680px
               column; paragraph 18px/1.85714; tablist unchanged
      ≤640px   section padding 28px 20px 0; container gutters 16px; h2 25px;
               paragraph 15px/1.6; tablist 16px above / 30px below, 4px padding,
               left-aligned so an overflowing row scrolls from the first tab

    The tablist's bottom margin is intentionally the container's last margin: it
    collapses through the container into the section, which is what makes the
    reference's section 355px tall at 1440px (257px of content + 48px of margin
    + 20px of padding).

    Breakpoint note: every property is varied by `lg:` and optionally `max-sm:`
    only — those two never apply at the same width, so the result does not depend
    on the order Tailwind emits its variants in.
--}}
<section
    id="program"
    data-section="program-heading"
    class="bg-white px-5 pt-9 pb-0 text-center lg:px-0 lg:pt-[30px] lg:pb-5 max-sm:pt-7"
>
    <div class="mx-auto w-full max-w-page px-6 max-sm:px-4">
        <div class="mx-auto mb-[30px] flex w-full max-w-[820px] flex-col items-center justify-center">
            <h2
                id="program-title"
                class="mb-2.5 w-full font-heading text-[30px] leading-[1.3] font-extrabold tracking-[-0.02em] text-brand lg:text-h2 max-sm:text-[25px]"
            >{{ $programHeading['title'] }}</h2>

            {{-- Decorative rule: the dot is the reference's own 2.2s alternate sweep. --}}
            <div
                aria-hidden="true"
                class="relative mt-3 mb-4 block h-[3px] w-20 rounded-full bg-[#ff831f61] after:absolute after:top-1/2 after:left-0 after:block after:h-2.5 after:w-2.5 after:-translate-y-1/2 after:animate-dot-slide after:rounded-full after:bg-accent after:shadow-[0_0_10px_#ff831f,0_0_3px_#ff831f] after:content-['']"
            ></div>

            <p class="mx-auto my-3.5 w-full max-w-[720px] font-heading text-[18px] leading-[1.85714] font-normal text-[#2b2b2b] max-sm:text-[15px] max-sm:leading-[1.6]">{{ $programHeading['description'] }}</p>
        </div>

        @if (count($programHeading['tabs']) > 1)
            <div class="mt-7 mb-12 flex max-w-full justify-center px-4 max-sm:mt-4 max-sm:mb-[30px]">
                <div
                    data-program-tabs
                    role="tablist"
                    aria-label="{{ $programHeading['tabs_label'] }}"
                    class="flex max-w-full items-center gap-1 overflow-x-auto rounded-full border border-[#e2e8f0f2] bg-white p-1.5 shadow-[0_10px_25px_#0000000d,0_2px_6px_#00000005] max-sm:w-full max-sm:justify-start max-sm:p-1"
                >
                    @foreach ($programHeading['tabs'] as $tab)
                        <button
                            type="button"
                            role="tab"
                            data-tab="{{ $tab['slug'] }}"
                            data-active="{{ $loop->first ? 'true' : 'false' }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                            @unless ($loop->first) tabindex="-1" @endunless
                            class="cursor-pointer rounded-full px-[22px] py-2.5 font-heading text-sm leading-[normal] font-bold whitespace-nowrap text-[#475569] transition duration-[180ms] hover:bg-[#f1f5f9] hover:text-brand data-[active=true]:bg-[linear-gradient(135deg,#0088ff,#0657a8)] data-[active=true]:text-white data-[active=true]:shadow-[0_4px_14px_#0657a859] max-sm:px-4 max-sm:text-[13px]"
                        >{{ $tab['label'] }}</button>
                    @endforeach
                </div>
            </div>
        @else
            {{-- A single category has nothing to switch between, so the heading goes
                 straight into the grid instead of showing a one-button pill row. --}}
            <div class="mt-7 mb-12 max-sm:mt-4 max-sm:mb-[30px]"></div>
        @endif
    </div>
</section>
