<?php

declare(strict_types=1);

/*
 * "Program Belajar" — the centred heading block above the program grid, plus the
 * tablist that switches between categories.
 *
 * The tabs' `slug` is the contract with the behaviour: program-tabs.js matches
 * `[data-tab="<slug>"]` against `[data-program-panel="<slug>"]`, so the slugs in
 * this list and the keys in programs.php must stay identical.
 *
 * The tab row only renders when there is more than one tab, so a single category
 * shows the heading and the grid without an empty-looking pill row. Adding PPPK
 * or Kedinasan later is one entry here plus one panel in programs.php.
 */

return [
    'title' => 'Program Belajar',
    'description' => 'Semua yang kamu butuhkan untuk mengukur kesiapan SKD: simulasi CAT lengkap, penilaian otomatis, dan analisa yang menunjukkan bagian mana yang harus diperbaiki.',

    // The tablist's accessible name; the heading above it is `id="program-title"`.
    'tabs_label' => 'Kategori Program Belajar',

    'tabs' => [
        ['slug' => 'cpns', 'label' => 'SKD CPNS'],
    ],
];
