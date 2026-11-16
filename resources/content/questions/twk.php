<?php

declare(strict_types=1);

/*
 * Bank soal TWK — 30 item, enam per submateri kisi-kisi.
 *
 * Naskah disusun sendiri mengikuti kisi-kisi resmi SKD (spec §3). Sumber isinya
 * adalah naskah publik seperti Pembukaan dan batang tubuh UUD 1945, UU yang
 * disebutkan pada butir pembahasan, serta kaidah bahasa Indonesia baku. Setiap
 * item punya satu kunci dan satu pembahasan yang menjelaskan mengapa kunci itu
 * benar — bukan sekadar menyebutnya.
 *
 * Bentuk satu butir, sama untuk semua berkas di direktori ini:
 *
 *   'code'        identitas stabil (TWK-001). Dipakai seeder untuk menulis ulang
 *                 item yang sama tanpa menggandakan barisnya.
 *   'subcategory' harus salah satu kunci di config('cpns.subcategories.twk').
 *   'options'     tepat lima opsi berlabel A–E, berisi teks jawaban saja.
 *   'key'         label opsi yang benar.
 *   'explanation' pembahasan yang dibaca peserta setelah submit.
 */

return [
    [
        'code' => 'TWK-001',
        'subcategory' => 'nasionalisme',
        'stem' => 'Lambang sila ketiga Pancasila adalah pohon beringin. Makna lambang tersebut yang paling tepat sebagai nilai nasionalisme adalah bahwa bangsa Indonesia ...',
        'options' => [
            'A' => 'memiliki kekayaan alam yang berlimpah sehingga mampu menghidupi rakyatnya',
            'B' => 'memiliki jumlah penduduk yang besar sebagai modal membangun negara',
            'C' => 'memiliki wilayah yang luas dengan batas alam yang sulit diterobos',
            'D' => 'memiliki pemerintahan yang kuat dan berwibawa di kawasan',
            'E' => 'memiliki satu tempat berlindung dan bernaung bagi seluruh rakyat, tanpa memandang suku dan daerah asalnya',
        ],
        'key' => 'E',
        'explanation' => 'Pohon beringin berakar kuat, berbatang besar, dan berdaun rindang sebagai tempat berlindung — menggambarkan tempat bagi seluruh rakyat Indonesia untuk bernaung sebagai satu kesatuan bangsa. Pilihan lain menyebut kekayaan alam, jumlah penduduk, luas wilayah, atau kekuatan pemerintahan, yang semuanya lebih dekat ke sila kelima, bukan sila Persatuan Indonesia.',
    ],
    [
        'code' => 'TWK-002',
        'subcategory' => 'nasionalisme',
        'stem' => 'Ikrar hasil Kongres Pemuda II pada 28 Oktober 1928 yang kita kenal sebagai Sumpah Pemuda berisi tiga pernyataan, yaitu ...',
        'options' => [
            'A' => 'berdaulat satu, negara Indonesia; berlambang satu, Garuda Pancasila; berbahasa satu, bahasa Melayu',
            'B' => 'beragama satu, Ketuhanan Yang Maha Esa; berbangsa satu, bangsa Indonesia; bersemboyan satu, Bhinneka Tunggal Ika',
            'C' => 'bersatu satu, seluruh pemuda Indonesia; bersatu satu, seluruh organisasi pemuda; bersatu satu, di bawah satu partai',
            'D' => 'bertumpah darah satu, tanah air Indonesia; berbangsa satu, bangsa Indonesia; menjunjung bahasa persatuan, bahasa Indonesia',
            'E' => 'bertanah air satu, Hindia Belanda; berbangsa satu, bangsa jajahan; berbangsa satu, bahasa Belanda',
        ],
        'key' => 'D',
        'explanation' => 'Ikrar 28 Oktober 1928 menyebut tiga hal: bertumpah darah satu, tanah air Indonesia; berbangsa satu, bangsa Indonesia; dan menjunjung bahasa persatuan, bahasa Indonesia. Pilihan lain menambahkan unsur yang tidak ada dalam ikrar (lambang, partai, agama) atau memakai nama dan bahasa yang justru ingin dilampaui oleh ikrar itu.',
    ],
    [
        'code' => 'TWK-003',
        'subcategory' => 'nasionalisme',
        'stem' => 'Pada 13 Desember 1957 pemerintah Indonesia mengeluarkan pernyataan yang kemudian dikenal sebagai Deklarasi Djuanda. Isi pokok deklarasi itu adalah ...',
        'options' => [
            'A' => 'wilayah laut Indonesia hanya sejauh tiga mil dari garis pantai sebagaimana berlaku pada masa kolonial',
            'B' => 'laut antarpulau di dalam wilayah Indonesia termasuk bagian dari wilayah kedaulatan negara, sehingga laut tidak lagi menjadi pemisah antarpulau',
            'C' => 'Indonesia membuka seluruh pelabuhannya sebagai pelabuhan bebas untuk kapal asing',
            'D' => 'Indonesia menyerahkan pengelolaan wilayah lautnya kepada badan internasional',
            'E' => 'batas wilayah Indonesia hanya ditentukan oleh garis pantai pulau-pulau yang berpenghuni',
        ],
        'key' => 'B',
        'explanation' => 'Deklarasi Djuanda menegaskan bahwa laut antarpulau adalah bagian wilayah kedaulatan Indonesia, sehingga negara ini menjadi satu kesatuan wilayah darat dan laut, bukan kumpulan pulau yang dipisahkan laut. Opsi yang menyebut batas tiga mil justru aturan lama yang ditinggalkan oleh deklarasi ini.',
    ],
    [
        'code' => 'TWK-004',
        'subcategory' => 'nasionalisme',
        'stem' => 'Semboyan Bhinneka Tunggal Ika yang tertulis pada pita yang dicengkeram burung Garuda diambil dari sebuah kitab yang dikarang oleh ...',
        'options' => [
            'A' => 'Mpu Tantular pada masa Kerajaan Majapahit',
            'B' => 'Mpu Prapanca pada masa Kerajaan Majapahit',
            'C' => 'Mpu Sedah pada masa Kerajaan Kediri',
            'D' => 'Mpu Panuluh pada masa Kerajaan Kediri',
            'E' => 'Mpu Kanwa pada masa Kerajaan Kahuripan',
        ],
        'key' => 'A',
        'explanation' => 'Semboyan itu berasal dari Kitab Sutasoma karya Mpu Tantular. Mpu Prapanca adalah pengarang Negarakertagama, Mpu Sedah dan Mpu Panuluh menyusun Bharatayuddha, dan Mpu Kanwa menyusun Arjunawiwaha.',
    ],
    [
        'code' => 'TWK-005',
        'subcategory' => 'nasionalisme',
        'stem' => 'Rasa cinta tanah air yang berlebihan sehingga menganggap bangsa sendiri paling unggul dan memandang rendah bangsa lain disebut ...',
        'options' => [
            'A' => 'patriotisme',
            'B' => 'pluralisme',
            'C' => 'chauvinisme',
            'D' => 'universalisme',
            'E' => 'kosmopolitanisme',
        ],
        'key' => 'C',
        'explanation' => 'Chauvinisme adalah bentuk cinta tanah air yang berlebihan dan disertai merendahkan bangsa lain. Patriotisme adalah cinta tanah air yang wajar dan bertanggung jawab, sementara tiga pilihan terakhir justru menekankan keterbukaan terhadap keberagaman.',
    ],
    [
        'code' => 'TWK-006',
        'subcategory' => 'nasionalisme',
        'stem' => 'Perjuangan Indonesia agar wilayah laut antarpulau diakui sebagai bagian dari wilayah negara memperoleh pengakuan internasional melalui Konvensi Hukum Laut PBB 1982. Dalam konvensi tersebut Indonesia diakui sebagai ...',
        'options' => [
            'A' => 'negara kepulauan (archipelagic state)',
            'B' => 'negara pelabuhan bebas',
            'C' => 'negara netral yang tidak boleh dipersenjatai',
            'D' => 'negara maritim yang tidak memiliki laut teritorial',
            'E' => 'negara dengan wilayah laut yang ditentukan oleh negara tetangga',
        ],
        'key' => 'A',
        'explanation' => 'Konvensi Hukum Laut PBB 1982 mengakui Indonesia sebagai negara kepulauan, yaitu negara yang seluruh wilayahnya terdiri atas satu atau lebih gugusan kepulauan dan perairan di antaranya. Pengakuan ini meratifikasi perjuangan Deklarasi Djuanda dan diratifikasi Indonesia melalui UU 17/1985.',
    ],

    [
        'code' => 'TWK-007',
        'subcategory' => 'integritas',
        'stem' => 'Seorang ASN menerima hadiah berupa cendera mata dari rekanan instansinya setelah kontrak pengadaan selesai. Menurut ketentuan pemberantasan tindak pidana korupsi, langkah yang benar bagi ASN tersebut adalah ...',
        'options' => [
            'A' => 'menyerahkan hadiah itu kepada atasan langsungnya tanpa laporan tertulis',
            'B' => 'menyimpannya dahulu dan melaporkannya ketika ia memasuki masa pensiun',
            'C' => 'mengumumkan penerimaan hadiah itu kepada rekan kerja satu kantor',
            'D' => 'melaporkan penerimaan hadiah itu kepada KPK dalam tenggat yang ditentukan sejak diterima',
            'E' => 'mengembalikan hadiah itu kepada pemberi dan menganggap persoalan selesai',
        ],
        'key' => 'D',
        'explanation' => 'Hadiah dari pihak yang berhubungan dengan jabatan tergolong gratifikasi, dan kewajiban hukumnya adalah melaporkannya kepada KPK dalam 30 hari kerja sejak tanggal diterima. Mengembalikan saja tanpa laporan tidak memenuhi kewajiban itu, dan mengumumkan ke rekan kerja bukan bentuk pelaporan yang sah.',
    ],
    [
        'code' => 'TWK-008',
        'subcategory' => 'integritas',
        'stem' => 'Kewajiban penyelenggara negara untuk menyampaikan laporan harta kekayaannya (LHKPN) pertama kali diatur dalam ...',
        'options' => [
            'A' => 'UU 20/2001 tentang Perubahan atas UU Pemberantasan Tindak Pidana Korupsi',
            'B' => 'UU 30/2002 tentang Komisi Pemberantasan Tindak Pidana Korupsi',
            'C' => 'UU 5/2014 tentang Aparatur Sipil Negara',
            'D' => 'UU 23/2019 tentang Pengelolaan Sumber Daya Nasional untuk Pertahanan Negara',
            'E' => 'UU 28/1999 tentang Penyelenggara Negara yang Bersih dan Bebas dari Korupsi, Kolusi, dan Nepotisme',
        ],
        'key' => 'E',
        'explanation' => 'UU 28/1999 adalah dasar pertama kewajiban LHKPN. UU 20/2001 mengubah pasal-pasal pemberantasan korupsi, UU 30/2002 membentuk KPK, UU 5/2014 mengatur manajemen ASN, dan UU 23/2019 mengatur pertahanan negara.',
    ],
    [
        'code' => 'TWK-009',
        'subcategory' => 'integritas',
        'stem' => 'Seorang ASN melaporkan dugaan korupsi yang terjadi di instansinya. Sesuai UU 13/2006 tentang Perlindungan Saksi dan Korban, perlindungan yang paling tepat ia peroleh adalah ...',
        'options' => [
            'A' => 'kenaikan pangkat otomatis setelah laporan terbukti',
            'B' => 'perlindungan hukum dan kerahasiaan identitas sebagai pelapor',
            'C' => 'mutasi ke unit lain di luar keinginannya',
            'D' => 'penggantian seluruh biaya hidup sehari-hari',
            'E' => 'jaminan kenaikan tunjangan kinerja setiap tahun',
        ],
        'key' => 'B',
        'explanation' => 'Pelapor berhak memperoleh perlindungan hukum dan kerahasiaan identitas. Ketentuan itu tidak menjanjikan kenaikan pangkat, mutasi, penggantian biaya hidup, maupun tambahan tunjangan, karena perlindungannya bersifat hukum dan keamanan.',
    ],
    [
        'code' => 'TWK-010',
        'subcategory' => 'integritas',
        'stem' => 'Dalam kode etik ASN dikenal istilah konflik kepentingan. Yang dimaksud dengan konflik kepentingan adalah ...',
        'options' => [
            'A' => 'perbedaan pendapat antarbidang mengenai cara pelaksanaan sebuah kebijakan',
            'B' => 'pertentangan antara kebijakan pusat dan kebijakan daerah dalam satu urusan',
            'C' => 'situasi ketika kepentingan pribadi atau kepentingan kelompok berpotensi mempengaruhi pelaksanaan tugas secara tidak patut',
            'D' => 'persaingan antarpelamar dalam seleksi pengisian jabatan di instansi',
            'E' => 'kesalahpahaman komunikasi antara atasan dan bawahan dalam rapat kerja',
        ],
        'key' => 'C',
        'explanation' => 'Konflik kepentingan terjadi ketika kepentingan pribadi atau kelompok dapat mempengaruhi pelaksanaan tugas atau wewenang secara tidak patut. Perbedaan pendapat, persaingan jabatan, dan kesalahpahaman bukan konflik kepentingan karena tidak menyangkut kepentingan pribadi yang bertabrakan dengan tugas.',
    ],
    [
        'code' => 'TWK-011',
        'subcategory' => 'integritas',
        'stem' => 'Integritas seorang ASN dalam pelayanan publik paling tepat dimaknai sebagai ...',
        'options' => [
            'A' => 'kemampuan menyelesaikan seluruh pekerjaan lebih cepat daripada pegawai lain',
            'B' => 'penguasaan keterampilan teknis paling tinggi di bidang yang ditanganinya',
            'C' => 'kedekatan hubungan pribadi dengan pimpinan instansi',
            'D' => 'keberanian menyampaikan tuduhan kepada pihak lain tanpa bukti',
            'E' => 'kesesuaian antara nilai dan norma yang diyakini dengan perilaku nyata, termasuk menolak menyalahgunakan wewenang',
        ],
        'key' => 'E',
        'explanation' => 'Integritas menunjuk pada konsistensi antara apa yang diyakini dan apa yang dilakukan, terlihat antara lain dari kesediaan menolak penyalahgunaan wewenang. Kecepatan kerja, keterampilan teknis, dan kedekatan dengan pimpinan adalah hal lain, dan menuduh tanpa bukti justru pelanggaran.',
    ],
    [
        'code' => 'TWK-012',
        'subcategory' => 'integritas',
        'stem' => 'Lembaga negara yang diberi kewenangan melakukan penyelidikan, penyidikan, dan penuntutan tindak pidana korupsi berdasarkan UU 30/2002 adalah ...',
        'options' => [
            'A' => 'Inspektorat Jenderal kementerian',
            'B' => 'Badan Pemeriksa Keuangan',
            'C' => 'Komisi Pemberantasan Korupsi',
            'D' => 'Ombudsman Republik Indonesia',
            'E' => 'Kementerian Hukum',
        ],
        'key' => 'C',
        'explanation' => 'UU 30/2002 membentuk KPK dan memberinya kewenangan penyelidikan, penyidikan, serta penuntutan tindak pidana korupsi. Inspektorat, BPK, dan Ombudsman bekerja di ranah pengawasan, pemeriksaan keuangan, dan pengaduan layanan, bukan penuntutan pidana.',
    ],

    [
        'code' => 'TWK-013',
        'subcategory' => 'bela_negara',
        'stem' => 'Hak dan kewajiban setiap warga negara untuk ikut serta dalam upaya pembelaan negara ditegaskan dalam UUD NRI Tahun 1945, yaitu pada ...',
        'options' => [
            'A' => 'Pasal 30 ayat (1)',
            'B' => 'Pasal 31 ayat (1)',
            'C' => 'Pasal 33 ayat (3)',
            'D' => 'Pasal 27 ayat (3)',
            'E' => 'Pasal 23A',
        ],
        'key' => 'D',
        'explanation' => 'Pasal 27 ayat (3) menyatakan bahwa setiap warga negara berhak dan wajib ikut serta dalam upaya pembelaan negara. Pasal 30 ayat (1) memakai rumusan keikutsertaan dalam usaha pertahanan dan keamanan negara, Pasal 31 ayat (1) tentang pendidikan, Pasal 33 ayat (3) tentang kekayaan alam, dan Pasal 23A tentang pajak.',
    ],
    [
        'code' => 'TWK-014',
        'subcategory' => 'bela_negara',
        'stem' => 'Warga negara yang dilatih dan disiapkan sebagai komponen cadangan untuk memperkuat kekuatan pertahanan negara diatur dalam ...',
        'options' => [
            'A' => 'UU 23/2019 tentang Pengelolaan Sumber Daya Nasional untuk Pertahanan Negara',
            'B' => 'UU 3/2002 tentang Pertahanan Negara',
            'C' => 'UU 5/2014 tentang Aparatur Sipil Negara',
            'D' => 'UU 28/1999 tentang Penyelenggara Negara yang Bersih dan Bebas dari KKN',
            'E' => 'UU 17/1985 tentang Pengesahan Konvensi Hukum Laut PBB',
        ],
        'key' => 'A',
        'explanation' => 'Komponen cadangan diatur dalam UU 23/2019, yang mengatur pengelolaan sumber daya nasional dan pembentukan komponen cadangan sebagai bagian dari kekuatan pertahanan. UU 3/2002 mengatur sistem pertahanan negara secara umum, tanpa mengatur komponen cadangan.',
    ],
    [
        'code' => 'TWK-015',
        'subcategory' => 'bela_negara',
        'stem' => 'Dalam wawasan nusantara, aspek alamiah (trigatra) meliputi ...',
        'options' => [
            'A' => 'ideologi, politik, dan ekonomi',
            'B' => 'letak geografis, kekayaan alam, dan keadaan serta kemampuan penduduk',
            'C' => 'sosial budaya, pertahanan keamanan, dan politik',
            'D' => 'hukum, pemerintahan, dan administrasi negara',
            'E' => 'pendidikan, kesehatan, dan kesejahteraan sosial',
        ],
        'key' => 'B',
        'explanation' => 'Trigatra adalah tiga aspek alamiah: letak geografis, kekayaan alam, dan keadaan serta kemampuan penduduk. Ideologi, politik, ekonomi, sosial budaya, dan pertahanan keamanan termasuk aspek sosial (pancagatra).',
    ],
    [
        'code' => 'TWK-016',
        'subcategory' => 'bela_negara',
        'stem' => 'Ancaman non-militer di bidang ideologi ditunjukkan oleh ...',
        'options' => [
            'A' => 'penerbangan pesawat asing yang melintasi wilayah udara Indonesia tanpa izin',
            'B' => 'perompakan kapal di perairan Indonesia yang dilakukan kelompok bersenjata',
            'C' => 'penyelundupan senjata ilegal oleh jaringan teroris bersenjata',
            'D' => 'gerakan terorganisasi yang berupaya mengganti Pancasila dengan paham lain sebagai dasar negara',
            'E' => 'pendudukan sebagian wilayah Indonesia oleh satuan militer asing',
        ],
        'key' => 'D',
        'explanation' => 'Ancaman berdimensi ideologi menyasar keyakinan dan dasar negara, misalnya gerakan yang hendak menggantikan Pancasila. Pilihan lainnya berbentuk ancaman bersenjata atau gangguan keamanan fisik, yang digolongkan sebagai ancaman militer.',
    ],
    [
        'code' => 'TWK-017',
        'subcategory' => 'bela_negara',
        'stem' => 'Nilai dasar bela negara yang menjadi acuan sikap dan perilaku warga negara meliputi ...',
        'options' => [
            'A' => 'kedisiplinan, kecepatan bekerja, kemampuan negosiasi, keberanian bersaing, serta jiwa wirausaha',
            'B' => 'kejujuran, ketertiban administrasi, kepatuhan pada atasan, kepandaian berbahasa, serta kemampuan menabung',
            'C' => 'cinta tanah air, kesadaran berbangsa dan bernegara, keyakinan pada Pancasila sebagai ideologi negara, rela berkorban, serta kemampuan awal bela negara',
            'D' => 'kepercayaan pada sesama, kesabaran, kesederhanaan, ketepatan waktu, serta kesenangan bergaul',
            'E' => 'ketaatan beribadah, keuletan bekerja, kemandirian ekonomi, ketelitian, serta keberanian mengambil risiko',
        ],
        'key' => 'C',
        'explanation' => 'Lima nilai dasar bela negara adalah cinta tanah air, kesadaran berbangsa dan bernegara, yakin pada Pancasila sebagai ideologi negara, rela berkorban untuk bangsa dan negara, serta memiliki kemampuan awal bela negara. Pilihan lain berisi nilai kerja yang berguna, tetapi bukan rumusan nilai dasar bela negara.',
    ],
    [
        'code' => 'TWK-018',
        'subcategory' => 'bela_negara',
        'stem' => 'Hakikat wawasan nusantara bagi bangsa Indonesia adalah cara pandang yang ...',
        'options' => [
            'A' => 'didasari Pancasila dan UUD 1945 serta menyatukan Indonesia dalam kesatuan politik, ekonomi, sosial budaya, dan pertahanan keamanan',
            'B' => 'menempatkan kepentingan daerah masing-masing di atas kepentingan nasional',
            'C' => 'membedakan hak warga negara berdasarkan daerah asalnya',
            'D' => 'mengutamakan kepentingan golongan terbesar dalam pengambilan keputusan',
            'E' => 'menyerahkan urusan wilayah perbatasan kepada pemerintah daerah setempat',
        ],
        'key' => 'A',
        'explanation' => 'Wawasan nusantara adalah cara pandang bangsa Indonesia tentang diri dan lingkungannya berdasarkan Pancasila dan UUD 1945, dengan menempatkan kesatuan wilayah, ideologi, politik, ekonomi, sosial budaya, dan pertahanan keamanan sebagai satu kebulatan. Pilihan lain menggambarkan sikap yang justru memecah kesatuan itu.',
    ],

    [
        'code' => 'TWK-019',
        'subcategory' => 'pilar_negara',
        'stem' => 'Setelah perubahan UUD NRI Tahun 1945, keanggotaan Majelis Permusyawaratan Rakyat terdiri atas ...',
        'options' => [
            'A' => 'anggota Dewan Perwakilan Rakyat dan utusan partai politik',
            'B' => 'anggota Dewan Perwakilan Rakyat dan anggota Dewan Perwakilan Daerah',
            'C' => 'anggota Dewan Perwakilan Rakyat dan utusan golongan',
            'D' => 'anggota Dewan Perwakilan Daerah dan utusan daerah provinsi',
            'E' => 'anggota Dewan Perwakilan Rakyat, anggota Dewan Perwakilan Daerah, dan utusan golongan',
        ],
        'key' => 'B',
        'explanation' => 'Pasal 2 ayat (1) UUD 1945 hasil perubahan menyatakan MPR terdiri atas anggota DPR dan anggota DPD. Susunan lama yang menyertakan utusan golongan dan utusan daerah sudah tidak dipakai lagi.',
    ],
    [
        'code' => 'TWK-020',
        'subcategory' => 'pilar_negara',
        'stem' => 'UUD NRI Tahun 1945 telah mengalami perubahan (amandemen) sebanyak ...',
        'options' => [
            'A' => 'dua kali, pada 1999 dan 2000',
            'B' => 'tiga kali, pada 1999 sampai 2001',
            'C' => 'lima kali, pada 1999 sampai 2003',
            'D' => 'satu kali, pada 1999',
            'E' => 'empat kali, pada 1999 sampai 2002',
        ],
        'key' => 'E',
        'explanation' => 'Perubahan UUD 1945 dilakukan empat kali dalam Sidang Umum dan Sidang Tahunan MPR: tahun 1999, 2000, 2001, dan 2002.',
    ],
    [
        'code' => 'TWK-021',
        'subcategory' => 'pilar_negara',
        'stem' => 'Ketentuan bahwa bumi, air, dan kekayaan alam yang terkandung di dalamnya dikuasai oleh negara dan dipergunakan untuk sebesar-besarnya kemakmuran rakyat terdapat pada ...',
        'options' => [
            'A' => 'Pasal 27 ayat (2)',
            'B' => 'Pasal 28 ayat (1)',
            'C' => 'Pasal 33 ayat (3)',
            'D' => 'Pasal 31 ayat (2)',
            'E' => 'Pasal 34 ayat (1)',
        ],
        'key' => 'C',
        'explanation' => 'Rumusan itu ada pada Pasal 33 ayat (3). Pasal 27 ayat (2) tentang pekerjaan dan penghidupan yang layak, Pasal 28 tentang hak asasi manusia, Pasal 31 tentang pendidikan, dan Pasal 34 tentang fakir miskin dan anak terlantar.',
    ],
    [
        'code' => 'TWK-022',
        'subcategory' => 'pilar_negara',
        'stem' => 'Lembaga negara yang berwenang menguji apakah sebuah undang-undang bertentangan dengan UUD NRI Tahun 1945 adalah ...',
        'options' => [
            'A' => 'Mahkamah Agung',
            'B' => 'Mahkamah Konstitusi',
            'C' => 'Badan Pemeriksa Keuangan',
            'D' => 'Komisi Yudisial',
            'E' => 'Majelis Permusyawaratan Rakyat',
        ],
        'key' => 'B',
        'explanation' => 'Pengujian undang-undang terhadap UUD 1945 menjadi kewenangan Mahkamah Konstitusi. Mahkamah Agung menguji peraturan di bawah undang-undang, BPK memeriksa pengelolaan keuangan negara, Komisi Yudisial mengawasi hakim, dan MPR tidak lagi menjadi lembaga tertinggi negara.',
    ],
    [
        'code' => 'TWK-023',
        'subcategory' => 'pilar_negara',
        'stem' => 'Jaminan kemerdekaan setiap penduduk untuk memeluk agamanya masing-masing dan untuk beribadat menurut agama dan kepercayaannya itu diatur dalam ...',
        'options' => [
            'A' => 'Pasal 29 ayat (2)',
            'B' => 'Pasal 29 ayat (1)',
            'C' => 'Pasal 28E ayat (3)',
            'D' => 'Pasal 30 ayat (2)',
            'E' => 'Pasal 32 ayat (1)',
        ],
        'key' => 'A',
        'explanation' => 'Pasal 29 ayat (2) memberi jaminan itu secara tegas. Pasal 29 ayat (1) menyatakan negara berdasar atas Ketuhanan Yang Maha Esa, Pasal 28E ayat (3) menjamin kebebasan berserikat dan berpendapat, Pasal 30 tentang pertahanan, dan Pasal 32 tentang kebudayaan.',
    ],
    [
        'code' => 'TWK-024',
        'subcategory' => 'pilar_negara',
        'stem' => 'Tujuan negara Indonesia untuk melindungi segenap bangsa, memajukan kesejahteraan umum, mencerdaskan kehidupan bangsa, dan ikut melaksanakan ketertiban dunia termuat dalam ...',
        'options' => [
            'A' => 'alinea pertama Pembukaan UUD NRI Tahun 1945',
            'B' => 'alinea kedua Pembukaan UUD NRI Tahun 1945',
            'C' => 'alinea ketiga Pembukaan UUD NRI Tahun 1945',
            'D' => 'alinea keempat Pembukaan UUD NRI Tahun 1945',
            'E' => 'Pasal 1 ayat (1) batang tubuh UUD NRI Tahun 1945',
        ],
        'key' => 'D',
        'explanation' => 'Alinea keempat Pembukaan memuat tujuan negara sekaligus dasar negara. Alinea pertama memuat pernyataan kemerdekaan, alinea kedua cita-cita negara merdeka, dan alinea ketiga pernyataan syukur atas rahmat Tuhan.',
    ],

    [
        'code' => 'TWK-025',
        'subcategory' => 'bahasa_indonesia',
        'stem' => 'Kelompok kata berikut yang seluruhnya merupakan bentuk baku adalah ...',
        'options' => [
            'A' => 'apotik, izin, praktik',
            'B' => 'apotek, ijin, praktek',
            'C' => 'apotik, izin, praktek',
            'D' => 'apotek, ijin, praktik',
            'E' => 'apotek, izin, praktik',
        ],
        'key' => 'E',
        'explanation' => 'Bentuk baku menurut kaidah bahasa Indonesia adalah apotek, izin, dan praktik. Bentuk apotik, ijin, dan praktek tidak baku sehingga keliru dipakai dalam tulisan resmi.',
    ],
    [
        'code' => 'TWK-026',
        'subcategory' => 'bahasa_indonesia',
        'stem' => 'Penulisan kalimat yang menggunakan tanda baca secara tepat adalah ...',
        'options' => [
            'A' => 'Panitia menyediakan buku, pensil, dan penghapus.',
            'B' => 'Panitia menyediakan buku, pensil dan penghapus.',
            'C' => 'Panitia menyediakan, buku, pensil, dan, penghapus.',
            'D' => 'Panitia menyediakan buku; pensil; dan penghapus.',
            'E' => 'Panitia menyediakan buku:pensil:dan penghapus.',
        ],
        'key' => 'A',
        'explanation' => 'Dalam perincian lebih dari dua unsur, tanda koma dipakai di antara unsur-unsur itu, termasuk sebelum kata dan yang mendahului unsur terakhir. Pilihan lain menghilangkan koma terakhir atau memakai tanda baca yang tidak berfungsi untuk perincian.',
    ],
    [
        'code' => 'TWK-027',
        'subcategory' => 'bahasa_indonesia',
        'stem' => 'Kalimat yang paling efektif adalah ...',
        'options' => [
            'A' => 'Semua pegawai-pegawai wajib mengikuti apel pagi.',
            'B' => 'Para hadirin sekalian dipersilakan duduk kembali.',
            'C' => 'Banyak siswa-siswa yang tidak hadir hari ini.',
            'D' => 'Kami semua-kami setuju dengan usulan tersebut.',
            'E' => 'Peserta diharapkan hadir tepat waktu.',
        ],
        'key' => 'E',
        'explanation' => 'Kalimat efektif tidak mengulang makna yang sama. Pilihan lain mubazir karena kata para atau semua sudah menunjukkan jamak sehingga pengulangan jamak pada hadirin dan pegawai-pegawai tidak diperlukan, sedangkan semua-kami salah bentuk.',
    ],
    [
        'code' => 'TWK-028',
        'subcategory' => 'bahasa_indonesia',
        'stem' => "Ungkapan kambing hitam dalam kalimat 'Dia selalu dijadikan kambing hitam setiap kali terjadi masalah di kantor' bermakna ...",
        'options' => [
            'A' => 'orang yang menjadi pemimpin dalam menyelesaikan masalah',
            'B' => 'orang yang bekerja tanpa mendapat upah',
            'C' => 'orang yang berpenampilan berbeda dari kebanyakan orang',
            'D' => 'orang yang disalahkan atas kesalahan pihak lain',
            'E' => 'orang yang paling lama bertugas di kantor tersebut',
        ],
        'key' => 'D',
        'explanation' => 'Kambing hitam adalah orang yang disalahkan atas perbuatan atau kesalahan orang lain. Makna itu terbaca dari konteks kalimatnya: ia disalahkan setiap kali masalah muncul, bukan karena kesalahannya sendiri.',
    ],
    [
        'code' => 'TWK-029',
        'subcategory' => 'bahasa_indonesia',
        'stem' => 'Kelompok kata berimbuhan berikut yang seluruhnya ditulis secara tepat adalah ...',
        'options' => [
            'A' => 'merubah, mencolok, menyukseskan',
            'B' => 'mengubah, mencolok, menyukseskan',
            'C' => 'mengubah, menyolok, mensukseskan',
            'D' => 'merubah, menyolok, mensukseskan',
            'E' => 'mengubah, mencolok, mensukseskan',
        ],
        'key' => 'B',
        'explanation' => 'Kata dasar ubah, colok, dan sukses dilekati awalan me- dengan peluluhan sesuai kaidah: mengubah, mencolok, menyukseskan. Bentuk merubah dan mensukseskan tidak baku, demikian pula menyolok.',
    ],
    [
        'code' => 'TWK-030',
        'subcategory' => 'bahasa_indonesia',
        'stem' => 'Sinonim kata kontaminasi adalah ...',
        'options' => [
            'A' => 'pembersihan',
            'B' => 'penyaringan',
            'C' => 'pencemaran',
            'D' => 'pengendapan',
            'E' => 'pembusukan',
        ],
        'key' => 'C',
        'explanation' => 'Kontaminasi berarti pencemaran, yaitu masuknya unsur lain yang membuat sesuatu tidak lagi murni. Penyaringan dan pengendapan adalah proses pemisahan, sedangkan pembusukan adalah akibat yang mungkin timbul dari pencemaran.',
    ],
];
