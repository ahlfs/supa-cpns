<?php

declare(strict_types=1);

/*
 * The CPNS SKD blueprint. Every number the exam engine, the grader, the results
 * page and the admin panel needs lives here, so no view or controller carries a
 * copy of "110" or "166" that can drift from the regulation.
 *
 * Source: KepmenPANRB 321/2024 as published, cross-checked against the BKN
 * selection handbook and the cat.bkn.go.id simulation (see docs/SPEC-CPNS-TRYOUT.md §4).
 */
return [
    /* Per-subtest rules. `correct`/`wrong` are null for TKP because its score
       comes from the chosen option's weight, not from being right. */
    'subtests' => [
        'twk' => [
            'label' => 'TWK',
            'name' => 'Tes Wawasan Kebangsaan',
            'items' => 30,
            'correct' => 5,
            'wrong' => 0,
            'max' => 150,
            'threshold' => 65,
        ],
        'tiu' => [
            'label' => 'TIU',
            'name' => 'Tes Intelegensia Umum',
            'items' => 35,
            'correct' => 5,
            'wrong' => 0,
            'max' => 175,
            'threshold' => 80,
        ],
        'tkp' => [
            'label' => 'TKP',
            'name' => 'Tes Karakteristik Pribadi',
            'items' => 45,
            'correct' => null,
            'wrong' => 0,
            'max' => 225,
            'threshold' => 166,
        ],
    ],

    'total_items' => 110,
    'total_max' => 550,
    'duration_minutes' => 100,
    /* The netra arrangement. Stored for reference; not selectable in the UI yet. */
    'duration_minutes_netra' => 130,
    /* The package form's own bounds — shorter than this is not a sitting and
       longer than this is not an exam. They are read by the request and the form
       so the two can never disagree about what the field accepts. */
    'duration_minutes_min' => 10,
    'duration_minutes_max' => 300,

    /* Special-formation cumulative thresholds. Stored, deliberately NOT enforced:
       participants do not pick a formation (spec §4). */
    'special_thresholds' => [
        'cumlaude' => ['total' => 311, 'tiu' => 85],
        'diaspora' => ['total' => 311, 'tiu' => 85],
        'disabilitas' => ['total' => 286, 'tiu' => 60],
        'papua' => ['total' => 286, 'tiu' => 60],
    ],

    /* The kisi-kisi subtopics offered in the question form and the importer. */
    'subcategories' => [
        'twk' => [
            'nasionalisme' => 'Nasionalisme',
            'integritas' => 'Integritas',
            'bela_negara' => 'Bela Negara',
            'pilar_negara' => 'Pilar Negara',
            'bahasa_indonesia' => 'Bahasa Indonesia',
        ],
        'tiu' => [
            'analogi_verbal' => 'Analogi Verbal',
            'silogisme' => 'Silogisme',
            'analitis' => 'Analitis',
            'deret_angka' => 'Deret Angka',
            'aritmetika' => 'Aritmetika',
            'perbandingan_kuantitatif' => 'Perbandingan Kuantitatif',
            'soal_cerita' => 'Soal Cerita',
            'analogi_figural' => 'Analogi Figural',
            'ketidaksamaan_figural' => 'Ketidaksamaan Figural',
            'serial_figural' => 'Serial Figural',
        ],
        'tkp' => [
            'pelayanan_publik' => 'Pelayanan Publik',
            'jejaring_kerja' => 'Jejaring Kerja',
            'sosial_budaya' => 'Sosial Budaya',
            'teknologi_informasi' => 'Teknologi Informasi dan Komunikasi',
            'profesionalisme' => 'Profesionalisme',
            'anti_radikalisme' => 'Anti Radikalisme',
        ],
    ],

    /* Per-item timing is an estimate. One interval can never contribute more than
       this, so an abandoned tab cannot absorb half an hour (spec §6.4). */
    'timing_cap_seconds' => 300,
    /* Answered this fast and wrong is near-certainly a guess. */
    'guess_threshold_seconds' => 10,

    'password' => [
        'min_length' => 8,
        'login_attempts_per_minute' => 5,
        'register_attempts_per_minute' => 5,
    ],
];
