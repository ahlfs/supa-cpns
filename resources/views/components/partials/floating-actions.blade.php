{{--
    Floating actions — the animated WhatsApp bubble and the back-to-top button.

    Reference geometry, measured in a browser (.probe/computed/floating-*):
      stack    fixed 20px from the bottom right, 12px gap, right-aligned column;
               z-index 9999
      ≥769px   the back-to-top button is display:none, so the stack is 58px tall
               (the circle alone) and 191.4px wide
      ≤768px   the button (46x46, #3f4753) takes part — stack 116px tall — but
               stays invisible (opacity 0, visibility hidden) until 600px of
               scroll, when [data-visible="true"] fades it in
      ≤480px   circle 52x52 and the bubble drops to 13px text with 8px 14px
               padding (stack 175x110 — the 390px capture)

    Hooks read by resources/js/modules/back-to-top.js: [data-back-to-top] +
    data-visible. The bubble's bounce reuses the `animate-whatsapp-bounce` token
    in resources/css/app.css and pauses on hover, as the reference does.
--}}
@props(['site', 'floating'])

@php
    /*
     * The bubble is opt-in: with no number configured it is not rendered at all,
     * so the page never shows a chat button that leads nowhere.
     */
    $phone = $site['contact']['whatsapp'] ?? '';
    $whatsapp = 'https://api.whatsapp.com/send?phone='.$phone.'&text='.rawurlencode($site['contact']['whatsapp_message']);
@endphp

<div data-section="floating" class="fixed right-5 bottom-5 z-[9999] flex flex-col items-end gap-3">
    <button
        type="button"
        data-back-to-top
        data-visible="false"
        aria-label="{{ $floating['back_to_top']['aria_label'] }}"
        class="flex h-[46px] w-[46px] cursor-pointer items-center justify-center rounded-full bg-[#3f4753] p-0 text-white opacity-0 invisible translate-y-2 shadow-[0_4px_14px_#00000040] transition-[opacity,transform,visibility] duration-[250ms] ease-[cubic-bezier(0.25,0.1,0.25,1)] min-[769px]:hidden data-[visible=true]:visible data-[visible=true]:translate-y-0 data-[visible=true]:opacity-100"
    >
        <x-icon name="arrow-up" class="h-5 w-5" />
    </button>

    {{-- Only offered once a staffed number is configured. --}}
    @if ($phone !== '')
        <a
            href="{{ $whatsapp }}"
            target="_blank"
            rel="nofollow noopener noreferrer"
            aria-label="{{ $floating['whatsapp']['aria_label'] }}"
            class="flex animate-whatsapp-bounce items-center gap-3 hover:[animation-play-state:paused]"
        >
            <span class="relative rounded-xl bg-white px-4 py-2.5 text-sm leading-[1.3] font-semibold whitespace-nowrap text-[#0f172a] shadow-[0_4px_14px_#00000026] after:absolute after:top-1/2 after:-right-1.5 after:h-3 after:w-3 after:-translate-y-1/2 after:rotate-45 after:rounded-[2px] after:bg-white after:content-[''] max-[480px]:px-3.5 max-[480px]:py-2 max-[480px]:text-[13px]">{{ $floating['whatsapp']['bubble'] }}</span>

            <span class="flex h-[58px] w-[58px] shrink-0 items-center justify-center rounded-full border-[3px] border-white bg-[#25d366] shadow-[0_6px_20px_#25d36673] max-[480px]:h-[52px] max-[480px]:w-[52px]">
                <x-icon name="whatsapp" class="h-[30px] w-[30px]" />
            </span>
        </a>
    @endif
</div>
