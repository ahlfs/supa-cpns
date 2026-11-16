@props([
    'site',
    'footer' => [],
])

{{--
    Site footer — the brand mark and the legal bar. Rendered by the layout,
    outside <main>.

    The bar used to carry four link columns (Situs, Akun, Tryout, Penilaian).
    They are gone, because every destination in them was already one click away
    in the header, the hero buttons or the dashboard, and the ones a signed-out
    visitor does not own yet — "Dashboard Peserta", "Ganti Password", "Riwayat
    Pengerjaan", "Analisa Skor" — answered that visitor with a redirect to
    /masuk. A footer that advertises a login page is not navigation; it is the
    cloned reference site's link dump surviving a redesign.

    Both halves of that block left with them: the four column entries in
    `resources/content/home/footer.php`, and the flex wrapper that laid them out
    — it carried `data-footer-columns`, a hook nothing read (no JS module, no
    test, no tool queries it), so it would only have gone on describing columns
    that no longer exist. The brand mark stays: it is the footer's identity, and
    it is the only link back to the homepage at the bottom of a long page.

    Geometry, measured in a browser before the columns came out
    (tools/probe-breakpoints.mjs, snapshots in .probe/computed/footer-*.json):
      ≥992px   #f8fafc surface, columns 1140px wide with padding 40/20/30 and
               z-index 2; five columns of 34/16/17/16/17%, every column 10px
               apart; legal bar on #f8fafc with a 1px #e2e8f0 top border and
               24px padding
      601-991  col 1 full width with a 24px bottom margin; the nav columns 25%
      ≤600px   col 1 and the nav columns stacked 100% + 50%, 30px bottom margin

    With the columns gone the tiers collapse to one: the mark sits in the page
    container's own 20px gutter — where the copyright line below it also starts,
    so the two finally share a left edge instead of the mark being indented 10px
    further by the column padding it used to carry.

    Everything optional is driven by content, not by markup: the contact block,
    the policy links and the social row each render only when $site holds
    something to put in them. An empty block here means the block is genuinely
    absent, never a heading with nothing under it.
--}}
@php
    // Brand colour of each social circle, measured on the reference's legal row.
    $socialColor = [
        'facebook' => 'bg-[#0657a8]',
        'instagram' => 'bg-[#dd2a7b]',
        'tiktok' => 'bg-black',
        'youtube' => 'bg-[#d74b4b]',
        'x' => 'bg-[#52abe8]',
        'threads' => 'bg-black',
        'linkedin' => 'bg-[#386fc6]',
        'tumblr' => 'bg-[#0d336f]',
    ];

    $contact = array_filter([
        'office' => $site['contact']['office'] ?? '',
        'whatsapp' => $site['contact']['whatsapp'] ?? '',
        'email' => $site['contact']['email'] ?? '',
    ]);
@endphp

<footer data-section="footer" class="relative bg-[#f8fafc]">
    {{-- Brand mark, then the contact details once they are configured. --}}
    <div class="relative z-[2] mx-auto max-w-page px-5 pt-10 pb-[30px]">
        {{--
            The anchor is `flex w-fit`, not `inline-block`: an inline-level box
            sits on a text baseline, so the anchor's own height would be the mark's
            36px plus the font's descender space (44.67px measured) and the legal
            bar below would start 8.67px lower than its margin says. `w-fit` keeps
            the click target on the mark instead of the full 1140px row.
        --}}
        <a href="{{ $footer['logo_url'] }}" class="flex w-fit">
            <x-ui.brand-mark :mark="36" :text="20" />
        </a>

        @if ($contact !== [])
            <div class="mt-[18px] max-w-[420px] text-[13.5px] leading-[1.6] font-normal text-[#54595f]">
                @if ($contact['office'] ?? null)
                    <p class="mb-2.5">{{ $site['contact']['office'] }}</p>
                @endif
                @if ($contact['whatsapp'] ?? null)
                    <p class="mb-2.5">{{ $footer['contact_label'] }} {{ $contact['whatsapp'] }}</p>
                @endif
                @if ($contact['email'] ?? null)
                    <p class="mb-4">
                        <a href="mailto:{{ $contact['email'] }}" class="text-[#54595f] hover:text-brand">{{ $contact['email'] }}</a>
                    </p>
                @endif
            </div>
        @endif
    </div>

    {{-- Legal bar: copyright, the policy links and the social row, each optional. --}}
    <div class="border-t border-[#e2e8f0] bg-[#f8fafc] px-5 py-6">
        <div class="mx-auto flex max-w-page flex-wrap items-center justify-between gap-5">
            <div>
                <p class="mb-2.5 text-[13px] font-medium text-[#64748b]">
                    {{ $footer['copyright']['prefix'] }} {{ $site['legal']['copyright_year'] }}. {{ $footer['copyright']['suffix'] }} {{ $site['brand'] }}@if ($site['legal_name'] !== $site['brand']) ({{ $site['legal_name'] }})@endif
                </p>

                @if ($site['legal']['links'] !== [])
                    <div class="flex flex-wrap gap-[18px] text-[13px]">
                        @foreach ($site['legal']['links'] as $link)
                            <a href="{{ $link['url'] }}" class="text-[#475569] transition-colors duration-200 hover:text-brand">{{ $link['label'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($site['socials'] !== [])
                <div class="flex flex-wrap items-center gap-2.5">
                    @foreach ($site['socials'] as $social)
                        <a
                            href="{{ $social['url'] }}"
                            target="_blank"
                            rel="nofollow noreferrer"
                            aria-label="{{ $site['brand'] }} di {{ $social['name'] }}"
                            class="flex h-8 w-8 items-center justify-center rounded-full text-white transition-[transform,opacity] duration-200 hover:scale-105 hover:opacity-90 {{ $socialColor[$social['icon']] ?? 'bg-brand' }}"
                        >
                            <x-icon :name="$social['icon']" class="h-4 w-4" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</footer>
