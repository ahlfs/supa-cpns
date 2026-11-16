<?php

declare(strict_types=1);

/*
 * The CTA band that closes the page: the heading, the paragraph, the primary
 * button, the reassurance line.
 *
 * `{brand}` in a string is replaced by $site['brand'] when the partial renders it,
 * so a rebrand never leaves a stale name in copy.
 *
 * `secondary` is optional and the view skips it when `contact.whatsapp` is empty:
 * the band keeps its single primary button rather than offering a chat that has
 * nowhere to go.
 *
 * Two things left this file on purpose. The "GRATIS DICOBA" badge above the
 * heading restated `note` below the button, and the phone illustration was a
 * second product shot on a page that already shows the product twice.
 */

return [
    'aria_label' => 'Ajakan Mulai Tryout',

    'title' => 'Belum Tahu Seberapa Siap Kamu Menghadapi SKD?',
    'description' => 'Buat akun, kerjakan satu paket penuh 110 soal, lalu lihat skor tiap subtes dan daftar submateri yang masih lemah. Tidak ada biaya dan tidak ada paket yang perlu dibeli.',

    'primary' => [
        'label' => 'Daftar Gratis',
        'route' => 'register',
    ],

    'secondary' => [
        'label' => 'Tanya via WhatsApp',
        'message' => 'Halo {brand}, saya ingin bertanya tentang tryout SKD',
    ],

    'note' => 'Gratis • Tanpa kartu kredit • Hasil langsung setelah submit',
];
