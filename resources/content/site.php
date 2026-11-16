<?php

declare(strict_types=1);

/*
 * Site-wide values: brand identity, the routes the header and footer link to,
 * contact details and the calls to action that appear in more than one section.
 *
 * This is the first file to edit after a rebrand. Anything here is available in
 * every view as $site.
 *
 * Two rules keep this file honest:
 *   - Every URL here must resolve. Internal pages use route names, so renaming a
 *     route cannot leave a dead link in the header or the footer; anything an
 *     authenticated participant owns is prefixed with `auth.` and the route name,
 *     and the view asks Laravel for the URL.
 *   - Optional blocks (contact, socials, legal links) are empty by default and the
 *     views render nothing for them. Fill them in and they appear — no view edit
 *     needed. Do not invent an address or a handle you do not own: an empty footer
 *     block is better than a false claim.
 */

return [
    'brand' => 'SUPA CPNS',
    'tagline' => 'Platform Belajar & Tryout Online CPNS/ PPPK/ KEDINASAN/ TNI/ POLRI',
    'description' => 'Simulasi CAT SKD berstandar BKN: 110 soal TWK, TIU dan TKP dalam 100 menit, dinilai otomatis dengan analisa skor per subtes dan per submateri kisi-kisi.',

    /*
     * Legal entity shown in the footer's copyright line. Deliberately the brand
     * rather than a company name: put your registered entity here once the site
     * is operated by one.
     */
    'legal_name' => 'SUPA CPNS',

    // Primary and secondary calls to action, reused by the header, hero, prefooter.
    'cta' => [
        'register' => [
            'label' => 'Daftar',
            'route' => 'register',
        ],
        'login' => [
            'label' => 'Masuk',
            'route' => 'login',
        ],
        'dashboard' => [
            'label' => 'Dashboard',
            'route' => 'dashboard',
        ],
    ],

    /*
     * The header's navigation tree: the desktop mega-menu columns and the
     * flattened sub-lists the mobile drawer shows.
     *
     * `route` entries are resolved with Laravel's router; `url` entries are used
     * as written (an in-page anchor, for example). Every destination in here
     * exists today — an item that points nowhere is worse than no item.
     *
     * EMPTY ON PURPOSE. The bar used to carry "Tryout" and "Program" mega menus;
     * both duplicated destinations the visitor reaches from the hero buttons, the
     * account cluster and the program cards, so the bar was four items wide to say
     * two things. The mega-menu markup in components/partials/header.blade.php is
     * untouched and drives off this list, so bringing an item back is an entry
     * here — no view edit.
     *
     * `home` is not a menu entry. It is the brand mark's destination, and its
     * `label` is the accessible name that mark carries, which is why it is still
     * here after the bar's "Beranda" link was removed: the mark in the pill is the
     * way home at every width, so a link saying so beside it was a duplicate.
     */
    'navigation' => [
        'home' => ['label' => 'Beranda', 'route' => 'home'],

        'items' => [],

        'drawer' => [
            'open_menu' => 'Buka menu',
            'expanded' => 'Tampilkan submenu',
        ],
    ],

    /*
     * Contact details. Empty by default and the footer hides the block — fill in
     * the address, the WhatsApp number and the support mailbox you actually
     * monitor, then the footer and the floating WhatsApp bubble appear on their own.
     */
    'contact' => [
        'office' => '',
        'email' => '',
        'whatsapp' => '',
        'whatsapp_message' => 'Halo, saya ingin bertanya tentang tryout SUPA CPNS.',
    ],

    // The footer's "Ikuti kami" row. One entry per profile you really publish on.
    'socials' => [],

    /*
     * Policy pages. Empty until those pages exist; the footer renders the row only
     * when there is something in it.
     */
    'legal' => [
        'copyright_year' => '2026',
        'links' => [],
    ],

    /*
     * Third-party IDs, read from the environment so no tracking id is hardcoded
     * in the markup. Empty values render nothing at all.
     */
    'analytics' => [
        'google_tag_manager' => env('ANALYTICS_GTM_ID'),
        'google_analytics' => env('ANALYTICS_GA_ID'),
        'meta_pixel' => env('ANALYTICS_META_PIXEL_ID'),
        'clarity' => env('ANALYTICS_CLARITY_ID'),
    ],
];
