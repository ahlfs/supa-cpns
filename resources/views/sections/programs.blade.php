{{--
    "Program Belajar" — the six program panels behind the tablist.

    Every panel is rendered by the server; the five inactive ones carry `hidden`,
    so switching tabs is a visibility toggle and no markup is built client-side
    (see resources/js/modules/program-tabs.js).

    Reference geometry, measured in a browser (tools/probe-breakpoints.mjs and
    .probe/computed/programs-<width>.json):
      ≥1025px  section padding 0 40px 80px; grid gap 70px/24px; card 260px wide,
               capped at 270px, padding 24px 18px, no minimum height, 180px image
               pulled up 80px
      992-1024 section padding 0 40px 80px; grid gap 70px/20px; card 280px wide,
               capped at 320px, still padding 24px 18px with no minimum height
      641-991  section padding 0 24px 60px; card 24px 20px 28px with a 380px
               minimum height, so `space-between` pins the CTA to the bottom and
               the 180px image is pulled up 90px
      ≤640px   section padding 0 20px 56px; container gutters 16px; the grid
               becomes a centred column with 52px/16px gaps; card fills the
               column up to 340px, padding 24px 20px, no minimum height, 160px
               image pulled up 64px, description 14px/1.5 with a 12px top margin

    The negative image margin is the reference's own trick: the artwork hangs
    above the blue card, which is why the grid needs a 70px row gap.

    Breakpoint note: the card's width and the grid's column gap step at
    `min-[1025px]:` — the reference collapses its wide layout at `max-width:
    1024px`, which sits between Tailwind's `lg` (992px in this app) and the
    default 1024px, so neither stock variant is right. Padding, minimum height,
    alignment and the image offset step at `lg:`, and the phone layout at
    `max-sm:`; no property carries two overlapping min-width variants.
--}}
<section
    data-section="programs"
    class="bg-white px-6 pb-[60px] lg:px-10 lg:pb-20 max-sm:px-5 max-sm:pb-14"
>
    <div class="mx-auto w-full max-w-page px-6 max-sm:px-4">
        @foreach ($programs['tabs'] as $slug => $panel)
            <div data-program-panel="{{ $slug }}" @unless ($loop->first) hidden @endunless>
                <div class="flex w-full flex-wrap justify-center gap-y-[70px] gap-x-5 pt-5 min-[1025px]:gap-x-6 max-sm:flex-col max-sm:items-center max-sm:gap-y-[52px] max-sm:gap-x-4">
                    @foreach ($panel['items'] as $item)
                        <a
                            href="{{ $item['url'] }}"
                            class="relative flex w-[280px] max-w-[320px] flex-[0_0_280px] flex-col justify-between rounded-card bg-brand px-5 pt-6 pb-7 shadow-card transition duration-[180ms] hover:-translate-y-[3px] hover:shadow-card-hover min-[1025px]:w-[260px] min-[1025px]:max-w-[270px] min-[1025px]:flex-[0_0_260px] min-h-[380px] lg:px-[18px] lg:pb-6 lg:min-h-[auto] lg:justify-start max-sm:w-full max-sm:max-w-[340px] max-sm:flex-none max-sm:min-h-0 max-sm:justify-start max-sm:px-5 max-sm:pt-6 max-sm:pb-6"
                        >
                            <div class="relative z-[1] -mt-[90px] flex h-[180px] min-h-[180px] w-full items-center justify-center lg:-mt-20 max-sm:-mt-16 max-sm:h-40 max-sm:min-h-40">
                                <x-ui.image
                                    :image="$item['image']"
                                    class="h-[180px] w-auto max-w-full object-contain max-sm:h-40"
                                />
                            </div>

                            <p class="relative z-[1] my-4 flex-1 text-center font-heading text-[15.5px] leading-[1.6] font-normal text-white lg:text-sm lg:leading-[1.5] max-sm:mt-3 max-sm:mb-4 max-sm:text-sm max-sm:leading-[1.5]">{{ $item['description'] }}</p>

                            <span class="relative z-[1] inline-block self-center rounded-full bg-accent px-8 py-2.5 font-heading text-[15px] leading-none font-bold whitespace-nowrap text-white select-none cursor-default shadow-[0_8px_20px_-8px_#d65c088c] lg:py-[11px] max-sm:px-7 max-sm:py-2.5 max-sm:text-sm">{{ $item['cta'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
