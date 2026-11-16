{{--
    Site header — the fixed white pill, its five mega menus and the mobile drawer.

    Reference geometry, measured in a browser (tools/probe-element.mjs and the
    captured computed styles in .probe/computed/header-*):
      ≥992px   pill 1140px wide, 65px tall, 20px from the top, radius 999px,
               hairline rgba(226,232,240,.85), padding 8/12/8/24, shadow
               0 10px 30px #00000014 + 0 2px 6px #0000000a; nav links 15px/700
               Quicksand with 8px 14px padding and 4px between items; mega panel
               560px wide, two 1fr columns, 20px gap, 22px 24px padding, radius
               20px; logo rendered 131.7x36 (36px tall, width auto)
      769-991  pill 54px tall (padding 6/8/6/16), 14px from the top, 16px side
               padding; nav and the desktop CTAs give way to "Masuk" + the
               hamburger; drawer sits 10px under the pill, radius 20px
      condensed (scrollY > 20): wrapper top 14px (10px below 992) and the pill
               shadow becomes 0 14px 38px #0000001f + 0 3px 10px #0000000f

    Variants: every property is set once, plus at most one `max-lg:` override —
    never two overlapping min-width variants, so the emitted order cannot change
    the result. State is carried by data attributes (`data-scrolled` on the
    wrapper, `data-open` on the nav items, the accordions and the hamburger) and
    styled with `data-[...]:` / `group-data-[...]:` variants so no class is
    toggled at runtime.

    The account cluster is the one part that depends on `auth()`, and it is not
    a JS hook: `data-header-account="dashboard"` and `="admin"` only give the
    tests and `tools/live-smoke.mjs` something stable to address. A guest sees the
    marketing CTAs unchanged; the panel link is rendered for administrators only,
    and disappears entirely if the route is not registered.

    NO HOME ITEM, ON PURPOSE. The bar used to carry a "Beranda" link beside two
    mega menus; with the menus gone it was the bar's only entry, and it said
    nothing the mark to its left does not already say. The mark is the way home at
    every width — it sits in the pill above the mobile drawer — so the drawer's
    copy went with it. `data-header-brand` is the hook, and it is the same idiom as
    the account cluster's: tests and tools address the affordance by marker, not
    by the Indonesian label. `navigation.items` still drives the `<nav>` and the
    drawer accordions, so re-adding a real destination is one content entry.

    The mark's anchor is a FLEX container, not a block, and that is the fix for a
    measured defect rather than a preference. An `inline-flex` mark inside a block
    anchor sits on a text baseline, so the anchor's height was a LINE BOX: the
    mark's 34px plus the font's descender space, 42.67px in total. The pill centres
    that box, which put 10.11px above the mark and 18.78px below it — the "logo is
    not centred" report, 8.67px of it. Making the anchor a flex container blockifies
    the mark, the height becomes the mark's own 34px, and the two sides match.
    tools/header-geometry.mjs measures all four widths and fails on any drift.

    Hooks read by resources/js/modules/header.js: [data-site-header] (+
    data-scrolled), [data-nav-item], [data-menu-trigger], [data-menu-panel],
    [data-drawer-toggle], [data-mobile-drawer], [data-drawer-accordion] and the
    [data-drawer-sublist] that must follow its accordion button.
--}}
@props(['site'])

@php($nav = $site['navigation'])

{{--
    The CTAs address the app's own routes rather than the legacy storefront URLs
    the content file still carries for the marketing rebuild: a participant who
    lands on /dashboard must find links that lead somewhere useful. Guests reach
    the login and registration pages; a signed-in visitor who taps "Masuk" is
    sent on to their dashboard by the `guest` middleware. Changing an href cannot
    move the marketing pixel-parity numbers, which are visual.
--}}
@php($loginUrl = route('login', absolute: false))
@php($registerUrl = route('register', absolute: false))

{{--
    A signed-in visitor is not a prospect: the guest CTAs would invite someone to
    register for an account they already hold. So the cluster turns on `auth()` —
    a participant gets the dashboard, and an administrator also gets the panel,
    which is the only door into /admin anywhere in the participant chrome.

    Both links carry `data-header-account` so a test and a smoke script can
    address the affordance without asserting on the Indonesian copy.
--}}
@php($isAdmin = auth()->check() && auth()->user()->isAdmin())
@php($dashboardUrl = auth()->check() ? route('dashboard', absolute: false) : null)
@php($adminUrl = $isAdmin && Route::has('admin.dashboard') ? route('admin.dashboard', absolute: false) : null)

<header
    data-section="header"
    data-site-header
    data-scrolled="false"
    class="group pointer-events-none fixed inset-x-0 top-5 z-[1000] box-border w-full bg-transparent px-5 transition-[top] duration-200 max-lg:top-3.5 max-lg:px-4 data-[scrolled=true]:top-3.5 data-[scrolled=true]:max-lg:top-2.5"
>
    <div class="pointer-events-auto relative mx-auto flex max-w-page items-center justify-between rounded-[999px] border border-[#e2e8f0d9] bg-white py-2 pr-3 pl-6 shadow-[0_10px_30px_#00000014,0_2px_6px_#0000000a] transition-[box-shadow,padding] duration-200 max-lg:py-1.5 max-lg:pr-2 max-lg:pl-4 group-data-[scrolled=true]:shadow-[0_14px_38px_#0000001f,0_3px_10px_#0000000f]">
        <div class="flex shrink-0 items-center">
            <a
                href="{{ $nav['home']['url'] }}"
                data-header-brand
                aria-label="{{ $site['brand'] }} — {{ $nav['home']['label'] }}"
                class="flex items-center"
            >
                <x-ui.brand-mark :mark="34" :text="18" />
            </a>
        </div>

        {{-- No home entry: the mark above is the way home at every width, and with
             `navigation.items` empty this bar has nothing else to say. The list
             stays driven by the content file, so a real destination is one entry. --}}
        <nav class="flex items-center max-lg:hidden">
            <ul class="flex items-center gap-1">
                @foreach ($nav['items'] as $item)
                    <li class="group relative -mb-2 pb-2" data-nav-item>
                        <button
                            type="button"
                            data-menu-trigger
                            aria-expanded="false"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-[999px] bg-transparent px-3.5 py-2 font-heading text-[15px] leading-[normal] font-bold text-[#1e2428] transition-colors duration-150 hover:bg-[#f1f5f9] hover:text-[#0657a8]"
                        >
                            {{ $item['label'] }}
                            <x-icon name="chevron-down" class="h-2.5 w-2.5 fill-current transition-transform duration-200 group-data-[open=true]:rotate-180" />
                        </button>

                        {{-- The :before bridge keeps the panel reachable while the
                             pointer travels from the trigger down to it. --}}
                        <div
                            data-menu-panel="{{ $item['label'] }}"
                            hidden
                            class="absolute top-full left-1/2 z-[1001] grid w-[560px] -translate-x-1/2 animate-header-slide-down grid-cols-2 gap-5 rounded-[20px] border border-[#e2e8f0e6] bg-white px-6 py-[22px] shadow-[0_20px_45px_#0623472e] before:absolute before:-top-3 before:right-0 before:left-0 before:h-3 before:content-['']"
                        >
                            @foreach ($item['columns'] as $column)
                                <div class="flex flex-col">
                                    <div class="mb-2.5 border-b-2 border-[#0657a81f] pb-2 font-heading text-[12px] font-extrabold tracking-[0.05em] text-[#0657a8] uppercase">
                                        {{ $column['title'] }}
                                    </div>
                                    <ul class="flex flex-col gap-1">
                                        @foreach ($column['links'] as $link)
                                            <li>
                                                <a href="{{ $link['url'] }}" class="flex flex-col rounded-lg px-2.5 py-1.5 transition-colors duration-150 hover:bg-[#f4f8fc]">
                                                    <span class="font-heading text-sm leading-[1.855] font-bold text-[#1e2428]">{{ $link['label'] }}</span>
                                                    <span class="mt-px font-body text-xs leading-[1.855] text-[#6b7280]">{{ $link['description'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="flex shrink-0 items-center gap-2 max-lg:hidden">
            @auth
                @if ($adminUrl)
                    <a
                        href="{{ $adminUrl }}"
                        data-header-account="admin"
                        class="inline-flex items-center justify-center gap-2 rounded-[999px] border-[1.5px] border-brand bg-white px-5 py-[8.5px] font-heading text-[14.5px] font-bold whitespace-nowrap text-brand transition-[background-color,border-color,transform] duration-150 hover:-translate-y-px hover:bg-[#f0f7ff]"
                    >
                        <x-icon name="grid" class="h-4 w-4" />
                        Panel admin
                    </a>
                @endif

                <a
                    href="{{ $dashboardUrl }}"
                    data-header-account="dashboard"
                    class="inline-flex items-center justify-center rounded-[999px] border-[1.5px] border-brand bg-brand px-5 py-[8.5px] font-heading text-[14.5px] font-bold whitespace-nowrap text-white shadow-[0_3px_10px_#0657a840] transition-[background-color,border-color,transform,box-shadow] duration-150 hover:-translate-y-px hover:border-brand-dark hover:bg-brand-dark hover:shadow-[0_5px_14px_#0657a859]"
                >Dashboard</a>
            @else
                <a
                    href="{{ $registerUrl }}"
                    class="inline-flex items-center justify-center rounded-[999px] bg-accent px-[22px] py-2.5 font-heading text-[14.5px] font-extrabold whitespace-nowrap text-white shadow-[0_4px_14px_#ff831f59] transition-[background-color,transform] duration-150 hover:-translate-y-px hover:bg-[#ff9038]"
                >{{ $site['cta']['register']['label'] }}</a>

                <a
                    href="{{ $loginUrl }}"
                    class="inline-flex items-center justify-center rounded-[999px] border-[1.5px] border-brand bg-brand px-5 py-[8.5px] font-heading text-[14.5px] font-bold whitespace-nowrap text-white shadow-[0_3px_10px_#0657a840] transition-[background-color,border-color,transform,box-shadow] duration-150 hover:-translate-y-px hover:border-brand-dark hover:bg-brand-dark hover:shadow-[0_5px_14px_#0657a859]"
                >{{ $site['cta']['login']['label'] }}</a>
            @endauth
        </div>

        <div class="flex items-center gap-2 lg:hidden">
            @auth
                {{-- One pill only on a narrow bar: the brand mark and the
                     hamburger already take most of it. An administrator gets the
                     panel here and both destinations in the drawer. --}}
                @if ($adminUrl)
                    <a
                        href="{{ $adminUrl }}"
                        data-header-account="admin"
                        class="inline-flex items-center justify-center gap-1.5 rounded-[999px] border border-brand bg-brand px-4 py-[6.5px] font-heading text-[13.5px] font-bold whitespace-nowrap text-white shadow-[0_2px_6px_#0657a840] transition-[background-color,border-color,transform] duration-150 hover:-translate-y-px hover:border-brand-dark hover:bg-brand-dark"
                    >
                        <x-icon name="grid" class="h-3.5 w-3.5" />
                        Admin
                    </a>
                @else
                    <a
                        href="{{ $dashboardUrl }}"
                        data-header-account="dashboard"
                        class="inline-flex items-center justify-center rounded-[999px] border border-brand bg-brand px-4 py-[6.5px] font-heading text-[13.5px] font-bold whitespace-nowrap text-white shadow-[0_2px_6px_#0657a840] transition-[background-color,border-color,transform] duration-150 hover:-translate-y-px hover:border-brand-dark hover:bg-brand-dark"
                    >Dashboard</a>
                @endif
            @else
                <a
                    href="{{ $loginUrl }}"
                    class="inline-flex items-center justify-center rounded-[999px] border border-brand bg-brand px-4 py-[6.5px] font-heading text-[13.5px] font-bold whitespace-nowrap text-white shadow-[0_2px_6px_#0657a840] transition-[background-color,border-color,transform] duration-150 hover:-translate-y-px hover:border-brand-dark hover:bg-brand-dark"
                >{{ $site['cta']['login']['label'] }}</a>
            @endauth

            <button
                type="button"
                data-drawer-toggle
                aria-controls="site-header-drawer"
                aria-expanded="false"
                aria-label="{{ $nav['drawer']['open_menu'] }}"
                class="flex cursor-pointer items-center justify-center rounded-[999px] border-[1.5px] border-[#e2e8f0] bg-white px-2.5 py-1.5 text-[#1e2428] shadow-[0_2px_5px_#0000000a] transition-all duration-150 hover:border-[#cbd5e1] hover:bg-[#f8fafc] data-[open=true]:border-brand data-[open=true]:bg-brand data-[open=true]:text-white lg:hidden"
            >
                <x-icon name="menu" class="h-5 w-5" />
            </button>
        </div>
    </div>

    <div
        id="site-header-drawer"
        data-mobile-drawer
        hidden
        class="pointer-events-auto mt-2.5 box-border max-h-[calc(85vh-70px)] w-full max-w-page animate-drawer-slide-down overflow-y-auto overscroll-contain rounded-[20px] border border-[#e2e8f0f2] bg-white p-3.5 shadow-[0_20px_45px_#00000038] lg:hidden"
    >
        @if ($nav['items'] !== [])
            <ul class="flex flex-col gap-1.5">
                @foreach ($nav['items'] as $item)
                    <li class="border-b border-[#f1f5f9] pb-1.5 last:border-b-0 last:pb-0">
                        <button
                            type="button"
                            data-drawer-accordion
                            aria-expanded="false"
                            class="group flex w-full cursor-pointer items-center justify-between rounded-[10px] bg-transparent px-3.5 py-2.5 text-left font-heading text-[15px] leading-[normal] font-bold text-[#1e2428] transition-colors duration-150 hover:bg-[#f1f5f9] hover:text-[#0657a8] data-[open=true]:bg-[#f1f5f9] data-[open=true]:text-[#0657a8]"
                        >
                            <span>{{ $item['mobile_label'] }}</span>
                            <x-icon name="chevron-down" class="h-3 w-3 shrink-0 fill-current transition-transform duration-200 group-data-[open=true]:rotate-180" />
                        </button>

                        <div data-drawer-sublist hidden class="mt-1.5 mb-0.5 rounded-xl bg-[#f8fafc] p-2">
                            <div class="grid grid-cols-2 gap-1.5">
                                @foreach ($item['mobile_links'] as $link)
                                    <a
                                        href="{{ $link['url'] }}"
                                        class="flex items-center justify-center rounded-lg border border-[#e2e8f0] bg-white px-2.5 py-[9px] text-center font-heading text-[13px] font-bold text-[#1e293b] transition-[border-color,background-color,color] duration-150 hover:border-[#0657a8] hover:bg-[#f0f7ff] hover:text-[#0657a8]"
                                    >{{ $link['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        {{--
            The separator belongs to the link list above, not to the account block.
            With `navigation.items` empty there is no list, and a border-top over
            nothing reads as a stray hairline at the top of the drawer — so the rule
            is attached to the list's presence instead of sitting unconditionally.
        --}}
        @auth
            {{-- Stacked rather than a two-column grid: a participant has one
                 destination and an administrator has two, and a half-width
                 button floating in a two-column row reads as a mistake. --}}
            <div @class(['flex flex-col gap-2', 'mt-3 border-t border-[#e2e8f0] pt-3' => $nav['items'] !== []])>
                <a
                    href="{{ $dashboardUrl }}"
                    data-header-account="dashboard"
                    class="inline-flex w-full items-center justify-center rounded-[999px] border-[1.5px] border-brand bg-white px-5 py-[8.5px] text-center font-heading text-[14.5px] font-bold whitespace-nowrap text-brand"
                >Dashboard</a>

                @if ($adminUrl)
                    <a
                        href="{{ $adminUrl }}"
                        data-header-account="admin"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-[999px] border-[1.5px] border-brand bg-brand px-5 py-[8.5px] text-center font-heading text-[14.5px] font-bold whitespace-nowrap text-white shadow-[0_3px_10px_#0657a840]"
                    >
                        <x-icon name="grid" class="h-4 w-4" />
                        Panel admin
                    </a>
                @endif
            </div>
        @else
            <div @class(['grid grid-cols-2 gap-2', 'mt-3 border-t border-[#e2e8f0] pt-3' => $nav['items'] !== []])>
                <a
                    href="{{ $loginUrl }}"
                    class="inline-flex w-full items-center justify-center rounded-[999px] border-[1.5px] border-brand bg-brand px-5 py-[8.5px] text-center font-heading text-[14.5px] font-bold whitespace-nowrap text-white shadow-[0_3px_10px_#0657a840]"
                >{{ $site['cta']['login']['label'] }}</a>

                <a
                    href="{{ $registerUrl }}"
                    class="inline-flex w-full items-center justify-center rounded-[999px] bg-accent px-[22px] py-2.5 text-center font-heading text-[14.5px] font-extrabold whitespace-nowrap text-white shadow-[0_4px_14px_#ff831f59]"
                >{{ $site['cta']['register']['label'] }}</a>
            </div>
        @endauth
    </div>
</header>
