{{--
    Prefooter — the CTA band that closes the page.

    Geometry: the card is 1140px wide with a 20px radius, an 8px gap around it and
    the footer's #f8fafc behind it; the copy sits in a 740px column centred inside
    the card. Measured in a browser (tools/probe-breakpoints.mjs and the snapshots
    in .probe/computed/prefooter-*.json) the copy column used to be sized against
    the illustration beside it — the illustration is gone, so the column is now
    centred at the width that keeps the paragraph readable rather than stretched to
    the full 1140px.

    There is no badge above the heading any more: "GRATIS DICOBA" restated the note
    under the button ("Gratis • Tanpa kartu kredit"), and the page already says it
    three times. Removing it also removed the only capsule on the band.

    Breakpoint note: `lg:` (992px) and `max-[640px]:` never apply at the same width,
    so every property still has exactly one variant boundary.

    The surface: in the reference this band is the first child of <footer>, so the
    gutters around the card are the footer's #f8fafc; the app renders the band as a
    section of <main>, so it carries the same colour to stay identical under it.
--}}
@php
    // Content strings may carry {brand}; the site value wins over a hardcoded name.
    $copy = static fn (string $text): string => str_replace('{brand}', $site['brand'], $text);

    /*
     * No number configured means no chat button: the band keeps its single
     * primary action instead of offering a conversation nobody answers.
     */
    $phone = $site['contact']['whatsapp'] ?? '';
    $whatsapp = $phone === '' ? null : 'https://wa.me/'.$phone.'?text='.rawurlencode($copy($prefooter['secondary']['message']));
@endphp

<aside
    data-section="prefooter"
    aria-label="{{ $prefooter['aria_label'] }}"
    class="max-[640px]:pt-2 max-[640px]:pb-[22px] bg-[#f8fafc]"
>
    <div class="mx-auto flex w-[calc(100%_-_30px)] max-w-page justify-center overflow-hidden rounded-[20px] border border-white/22 bg-brand shadow-[0_14px_34px_rgba(6,87,168,0.18)]">
        <div class="flex w-full max-w-[740px] flex-col items-start justify-center px-[34px] py-[34px] lg:px-12 lg:py-10 max-[640px]:px-6 max-[640px]:py-[30px]">
            <p class="mb-3 max-w-[640px] font-heading text-[clamp(22px,3vw,30px)] leading-[1.3] font-extrabold tracking-[-0.01em] text-white">{{ $prefooter['title'] }}</p>

            <p class="mb-6 text-[15.5px] leading-[1.65] font-medium text-white/90">{{ $prefooter['description'] }}</p>

            <div class="flex flex-wrap items-center gap-3 max-[640px]:flex-col max-[640px]:items-stretch">
                <a
                    href="{{ $prefooter['primary']['url'] }}"
                    class="inline-flex items-center justify-center gap-[9px] rounded-full border border-transparent bg-accent px-7 py-3.5 font-heading text-[15px] font-bold whitespace-nowrap text-white shadow-[inset_0_0_0_1.5px_rgba(255,255,255,0.9),0_6px_18px_rgba(255,131,31,0.4)] transition-[transform,background-color,border-color] duration-150 [text-shadow:0_1px_2px_rgba(0,0,0,0.35)] hover:-translate-y-0.5 hover:bg-accent-dark max-[640px]:w-full max-[640px]:transition-none max-[640px]:hover:translate-y-0"
                >
                    <span class="font-body">{{ $prefooter['primary']['label'] }}</span>
                    <x-icon name="cursor-click" class="h-[17px] w-[17px] shrink-0" />
                </a>

                {{-- Offered only once a staffed WhatsApp number is configured. --}}
                @if ($whatsapp !== null)
                    <a
                        href="{{ $whatsapp }}"
                        target="_blank"
                        rel="nofollow noopener"
                        class="inline-flex items-center justify-center gap-[9px] rounded-full border border-white/45 bg-white/10 px-7 py-3.5 font-heading text-[15px] font-bold whitespace-nowrap text-white transition-[transform,background-color,border-color] duration-150 hover:-translate-y-0.5 hover:border-white hover:bg-white/20 max-[640px]:w-full max-[640px]:transition-none max-[640px]:hover:translate-y-0"
                    >
                        <x-icon name="whatsapp-solid" class="h-[19px] w-[19px] shrink-0" />
                        <span class="font-body">{{ $prefooter['secondary']['label'] }}</span>
                    </a>
                @endif
            </div>

            <p class="mt-3.5 text-[13px] font-medium text-white/82">{{ $prefooter['note'] }}</p>
        </div>
    </div>
</aside>
