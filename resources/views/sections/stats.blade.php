{{--
    Headline numbers under the hero.

    Reference geometry, measured in a browser (tools/probe-breakpoints.mjs):
      ≥992px   card is a row, padding 14/28/14/22, hairline #dce5ef, 122px section
               that lifts 40px over the hero; 4 columns of 258px with a 20px gap
      641-991  card is a row, padding 18px 20px, border #e2e8f0, softer shadow;
               grid capped at 720px, 2 columns, 16px gap, section padding 20px
      ≤640px   card becomes a centred column, gap 8px, padding 16px 12px, 40px icon

    Breakpoint note: one property is only ever varied by `lg:` plus optionally a
    `max-sm:` override — those two never apply at the same width, so the result
    does not depend on Tailwind's variant ordering.
--}}
<section
    data-section="stats"
    class="relative z-20 px-5 lg:-mt-10 lg:h-[122px] lg:px-10 max-sm:px-0"
>
    <div class="mx-auto grid max-w-[720px] grid-cols-2 gap-4 px-0 pt-4 pb-4 lg:max-w-page lg:grid-cols-4 lg:gap-5 lg:px-6 lg:pt-0 lg:pb-2.5 max-sm:gap-3 max-sm:px-4 max-sm:pt-2.5 max-sm:pb-2.5">
        @foreach ($stats['items'] as $item)
            <div class="flex h-[100px] flex-row items-center justify-center gap-3 rounded-card border border-[#e2e8f0] bg-white px-5 pt-[18px] pb-[18px] text-left shadow-[0_4px_16px_#07396c0a] transition duration-[180ms] hover:-translate-y-[3px] hover:shadow-card-hover lg:border-line lg:px-[22px] lg:pt-[14px] lg:pr-7 lg:pb-[14px] lg:shadow-card max-sm:h-auto max-sm:flex-col max-sm:gap-2 max-sm:px-3 max-sm:pt-4 max-sm:pb-4 max-sm:text-center">
                <img
                    src="{{ asset($item['icon']) }}"
                    alt=""
                    aria-hidden="true"
                    width="52"
                    height="52"
                    loading="lazy"
                    decoding="async"
                    class="h-[52px] w-[52px] shrink-0 object-contain max-sm:h-10 max-sm:w-10"
                >
                <div class="min-w-0">
                    <strong class="block font-heading text-[28px] leading-[1.1] font-bold text-[#69727d] whitespace-nowrap max-sm:text-[23px] max-sm:leading-[1.2] max-sm:font-extrabold max-sm:whitespace-normal">{{ $item['value'] }}</strong>
                    <span class="mt-1 block font-heading text-sm leading-[1.4] font-medium text-[#69727d] whitespace-nowrap max-sm:mt-0.5 max-sm:leading-[1.3] max-sm:font-semibold max-sm:whitespace-normal">{{ $item['label'] }}</span>
                </div>
            </div>
        @endforeach
    </div>
</section>
