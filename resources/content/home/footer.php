<?php

declare(strict_types=1);

/*
 * The site footer's own content: the labels and the copyright.
 *
 * The footer itself is a Blade component rendered by the layout, so it receives
 * this array as $footer and the site-wide values as $site.
 *
 * The brand block (address, support number) is driven entirely by
 * $site['contact'] — fill those values in and it appears; leave them empty and
 * the footer shows the mark alone, with no empty headings.
 *
 * NO LINK COLUMNS, ON PURPOSE. This file used to carry four of them (Situs,
 * Akun, Tryout, Penilaian) plus the em dash that prefixed every entry. They were
 * removed: each destination was already one click away in the header, the hero
 * buttons or the dashboard, and the ones a guest does not own yet — "Dashboard
 * Peserta", "Ganti Password", "Riwayat Pengerjaan", "Analisa Skor" — sent that
 * guest to /masuk. A footer whose links advertise a login page is worse than a
 * short footer. The column markup is gone too, so bringing one back is a new
 * entry here *and* the loop in components/partials/footer.blade.php, which is
 * the correct amount of friction for re-adding a link dump.
 */

return [
    // The mark links back to the homepage, like the reference's logo.
    'logo_url' => '/',

    'contact_label' => 'Kontak:',

    'copyright' => [
        'prefix' => 'Copyright ©',
        'suffix' => 'All rights reserved.',
    ],
];
