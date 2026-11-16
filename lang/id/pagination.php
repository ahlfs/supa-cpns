<?php

/*
|--------------------------------------------------------------------------
| Baris Bahasa Penomoran Halaman
|--------------------------------------------------------------------------
|
| Dua tautan yang dicetak oleh tampilan paginator bawaan Laravel
| (`pagination::tailwind`, yaitu tampilan bawaan di aplikasi ini). Berkas ini
| ada supaya daftar admin yang berhalaman — Bank soal dan Pengguna — tidak lagi
| menampilkan "Previous" dan "Next" di halaman berbahasa Indonesia.
|
| Kalimat jumlahnya ("Menampilkan 1 sampai 20 dari 25 hasil") TIDAK ada di sini:
| tampilan itu memanggil __('Showing'), __('to'), __('of') dan __('results')
| tanpa nama berkas, jadi kuncinya dibaca dari lang/id.json.
|
*/

return [
    'previous' => '&laquo; Sebelumnya',
    'next' => 'Berikutnya &raquo;',
];
