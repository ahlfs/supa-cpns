<?php

declare(strict_types=1);

/*
 * "Kenapa" — the product preview beside the reasons to practise here.
 *
 * `%s` is the brand (see `$site['brand']`): the reference names it in the kicker,
 * the heading, the paragraph and the preview's alt text.
 *
 * The preview is our own exam room, drawn as one vector file
 * (assets/img/app-preview.svg) — it shows the interface a participant actually
 * gets, not a mocked-up screenshot of something else.
 */

return [
    'kicker' => '%s: SIMULASI CAT SKD',

    'title' => 'Kenapa Harus Latihan di %s ?',

    'body' => '%s menirukan alur ujian SKD yang sebenarnya: 110 soal dalam 100 menit, panel nomor soal untuk melompat dan menandai, penilaian otomatis, lalu analisa skor yang menunjukkan bagian mana yang masih lemah.',

    'image' => [
        'src' => 'assets/img/app-preview.svg',
        'alt' => 'Tampilan ruang ujian %s',
        'width' => 1000,
        'height' => 740,
    ],

    // Two columns of three, filled down the first column first (grid-auto-flow).
    'features' => [
        ['icon' => 'assets/icons/home-feature-access.svg', 'label' => 'Akses Kapan Saja'],
        ['icon' => 'assets/icons/home-feature-interactive.svg', 'label' => 'Panel Nomor Soal & Tanda Ragu'],
        ['icon' => 'assets/icons/home-feature-analysis.svg', 'label' => 'Analisa Skor per Submateri'],
        ['icon' => 'assets/icons/home-feature-tryout.svg', 'label' => 'Penilaian Otomatis SKD'],
        ['icon' => 'assets/icons/home-feature-free.svg', 'label' => 'Gratis Tanpa Biaya'],
        ['icon' => 'assets/icons/home-feature-materials.svg', 'label' => 'Pembahasan Tiap Soal'],
    ],
];
