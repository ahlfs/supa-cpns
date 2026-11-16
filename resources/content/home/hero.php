<?php

declare(strict_types=1);

/*
 * Hero — the blue opener: the kicker line (which carries the page's only <h1>),
 * the headline, the lead, the two calls to action and the four decorative props
 * that float beside them.
 *
 * `%s` is the brand: each string that names it stays one string and the view
 * fills the brand in (see `$site['brand']`).
 */

return [
    'title' => 'Buka Jalan dan Wujudkan Mimpimu Menjadi ASN',

    'lead' => 'Kerjakan simulasi SKD 110 soal dalam 100 menit bersama %s, lalu lihat skor tiap subtes, rincian per submateri dan pembahasan setiap soalnya.',

    'actions' => [
        'register' => [
            'label' => 'Daftar',
            'route' => 'register',
        ],
        'login' => [
            'label' => 'Masuk',
            'route' => 'login',
        ],
    ],

    'visual' => [
        /**
         * Decorative props — the hero's only artwork. There is no product shot any
         * more, so the view lays these four out as one 2x2 cluster; adding a fifth
         * entry is a content edit, and the order here is the grid order.
         */
        'floats' => [
            [
                'image' => [
                    'src' => 'assets/img/floating/float-idea.svg',
                    'alt' => '',
                    'aria_hidden' => 'true',
                    'width' => 96,
                    'height' => 96,
                ],
            ],
            [
                'image' => [
                    'src' => 'assets/img/floating/float-trophy.svg',
                    'alt' => '',
                    'aria_hidden' => 'true',
                    'width' => 96,
                    'height' => 96,
                ],
            ],
            [
                'image' => [
                    'src' => 'assets/img/floating/float-video-ui.svg',
                    'alt' => '',
                    'aria_hidden' => 'true',
                    'width' => 96,
                    'height' => 96,
                ],
            ],
            [
                'image' => [
                    'src' => 'assets/img/floating/float-podium.svg',
                    'alt' => '',
                    'aria_hidden' => 'true',
                    'width' => 96,
                    'height' => 96,
                ],
            ],
        ],
    ],
];
