<?php

declare(strict_types=1);

/*
 * The floating action stack: the back-to-top button, plus the WhatsApp bubble
 * once $site['contact']['whatsapp'] holds a number you answer on.
 *
 * The bubble's copy is here; its number and prefilled message come from
 * $site['contact'] so a contact change lands in one place. With no number
 * configured the view renders the back-to-top button alone.
 */

return [
    'whatsapp' => [
        'bubble' => 'Chat Sekarang',
        'aria_label' => 'Chat Sekarang via WhatsApp',
    ],
    'back_to_top' => [
        'aria_label' => 'Kembali ke atas',
    ],
];
