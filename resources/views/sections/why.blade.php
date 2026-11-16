{{--
    "Why" — the device screenshot beside the reasons to study here.

    Reference geometry, measured in a browser (.probe/computed/why-*.json):
      ≥992px   white panel, 70px/80px of padding; the 1140px grid is 44.35% + 54.73%
               with a 10px gutter and 60px of bottom padding, so the media column is
               484px and the copy 598px; the screenshot is the column width minus a
               20px right inset; the feature list is two columns of three filled
               down the first column, 44px rows with no row gap
      641-991  one column capped at 720px, 40px gaps, section padding 40px/48px; the
               screenshot centres at 360px; the kicker and copy centre; the list
               gains a 14px row gap and 20px columns
      ≤640px   the same single column, but the copy drops to 13px kicker / 25px
               heading / 15px text, the list tightens to 10px/14px with no side
               padding and the feature icons shrink to 34px

    Breakpoint note: each property carries at most one of `lg:` / `max-sm:` on top
    of its base value; those two never apply at the same width, so no property
    depends on Tailwind's variant ordering.
--}}
<section id="kenapa" data-section="why" class="bg-white pt-10 pb-12 lg:pt-[70px] lg:pb-20">
    <div class="mx-auto grid w-full max-w-[720px] gap-10 px-6 pb-[60px] lg:max-w-page lg:grid-cols-[44.35%_54.73%] lg:gap-[10px] max-sm:px-4">
        <div class="flex flex-col justify-evenly p-2.5 text-center lg:text-left">
            <p class="flex min-h-8 items-center justify-center font-heading text-[12px] leading-[1.4] font-bold tracking-[0.08em] text-muted uppercase max-sm:text-[13px] lg:justify-normal lg:text-sm">
                <span aria-hidden="true" class="mr-[11px] block h-3 w-3 flex-none rounded-full bg-accent"></span>{{ sprintf($why['kicker'], $site['brand']) }}
            </p>

            <figure class="mx-auto max-w-[360px] lg:mx-0 lg:max-w-none lg:pr-5">
                <x-ui.image
                    :image="$why['image']"
                    :alt="sprintf($why['image']['alt'], $site['brand'])"
                    class="mx-auto block h-auto max-h-[420px] w-full max-w-full object-contain"
                />
            </figure>
        </div>

        <div class="flex flex-col justify-center pl-[25px] text-center lg:text-left">
            <h2 id="why-title" class="font-heading text-[30px] leading-[1.3] font-extrabold tracking-[-0.02em] text-brand max-sm:text-[25px] lg:text-h2">{{ sprintf($why['title'], $site['brand']) }}</h2>

            <div
                aria-hidden="true"
                class="relative mx-auto mt-3 mb-4 block h-[3px] w-20 rounded-full bg-[#ff831f61] after:absolute after:top-1/2 after:left-0 after:block after:h-2.5 after:w-2.5 after:-translate-y-1/2 after:animate-dot-slide after:rounded-full after:bg-accent after:shadow-[0_0_10px_#ff831f,0_0_3px_#ff831f] after:content-['']"
            ></div>

            <p class="mx-auto mb-5 max-w-[600px] text-[16px] leading-[1.85714] font-medium text-body lg:mx-0 lg:mb-[18px] lg:max-w-none lg:text-[18px] max-sm:leading-[1.6]">{{ sprintf($why['body'], $site['brand']) }}</p>

            <ul class="m-0 grid list-none grid-cols-2 grid-flow-col grid-rows-[repeat(3,minmax(44px,auto))] gap-x-5 gap-y-3.5 px-2.5 text-left lg:gap-x-2.5 lg:gap-y-0 max-sm:gap-x-3.5 max-sm:gap-y-2.5 max-sm:px-0">
                @foreach ($why['features'] as $feature)
                    <li class="flex min-h-10 items-center gap-4 text-[18px] leading-[1.5] font-medium text-ink">
                        <img
                            src="{{ asset($feature['icon']) }}"
                            alt=""
                            aria-hidden="true"
                            width="40"
                            height="40"
                            loading="lazy"
                            decoding="async"
                            class="block h-10 w-10 shrink-0 basis-10 object-contain max-sm:h-[34px] max-sm:w-[34px] max-sm:basis-[34px]"
                        >
                        <span class="text-[15px] leading-[1.5] max-sm:leading-[1.4] lg:text-[18px]">{{ $feature['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
