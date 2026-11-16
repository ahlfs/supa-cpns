<?php

declare(strict_types=1);

/*
 * Bank soal TKP — 45 item yang tersebar pada enam aspek kisi-kisi.
 *
 * Naskah disusun sendiri (spec §3). TKP tidak punya jawaban salah: setiap opsi
 * adalah tindakan yang bisa dibayangkan dilakukan orang, dan bobotnya menyatakan
 * seberapa dekat tindakan itu dengan nilai pelayanan publik dan integritas ASN.
 * Karena itu bentuk butir di sini berbeda dari TWK dan TIU:
 *
 *   'options'  lima opsi berlabel A–E, masing-masing berisi teks tindakan ('body')
 *              dan bobotnya ('score'), tepat satu opsi untuk setiap bobot 1–5.
 *   'key'      tidak ada. Bobot pada opsi terpilih itulah nilainya.
 *
 * Opsi bernilai 5 adalah tindakan yang paling dekat dengan nilai ASN — bukan
 * sekadar yang paling ramah, dan selalu tindakan yang masih berada di dalam
 * wewenang serta prosedur, bukan tindakan yang menabrak aturan demi hasil cepat.
 *
 * Letak opsi bernilai 5 sengaja tidak seragam: kalau ia selalu jatuh di huruf yang
 * sama, peserta bisa meraih nilai penuh tanpa membaca satu soal pun. Urutan opsi tiap
 * butir diputar sekali saat berkas ini disusun, dan ShippedQuestionBankTest menjaga
 * sebarannya tetap merata.
 */

return [
    // --- Pelayanan publik ---------------------------------------------------
    [
        'code' => 'TKP-001',
        'subcategory' => 'pelayanan_publik',
        'stem' => 'Antrean di loket layanan tempat Anda bertugas sudah sangat panjang, sementara jam layanan tersisa satu jam. Warga mulai terlihat gelisah.',
        'options' => [
            'A' => ['body' => 'Tetap bekerja dengan kecepatan biasa agar tidak terjadi kesalahan, tanpa memberi tahu apa pun kepada antrean', 'score' => 3],
            'B' => ['body' => 'Meminta warga yang mengeluh untuk datang lagi besok pagi agar pelayanan hari ini tidak bertambah berat', 'score' => 2],
            'C' => ['body' => 'Menutup loket lebih cepat dengan alasan jam layanan hampir habis', 'score' => 1],
            'D' => ['body' => 'Meminta satu warga mewakili antrean untuk menyampaikan keluhan kepada pimpinan sebelum Anda melanjutkan pekerjaan', 'score' => 4],
            'E' => ['body' => 'Mempercepat pelayanan sebisanya sambil meminta bantuan rekan yang sedang lengang, dan memberi tahu antrean perkiraan waktu tunggu', 'score' => 5],
        ],
        'explanation' => 'Bobot 5 diberikan pada tindakan yang menambah kapasitas layanan sekaligus menghormati waktu warga: mencari tambahan tenaga dan memberi kejelasan waktu tunggu. Memberi tahu keluhan ke pimpinan (4) memang bertanggung jawab, tetapi belum menyelesaikan antrean hari itu.',
    ],
    [
        'code' => 'TKP-002',
        'subcategory' => 'pelayanan_publik',
        'stem' => 'Seorang warga mengeluhkan lambatnya pengurusan dokumen melalui akun media sosial instansi Anda. Unggahannya mulai mendapat banyak tanggapan.',
        'options' => [
            'A' => ['body' => 'Membalas bahwa keluhan sebaiknya disampaikan melalui kanal pengaduan resmi saja', 'score' => 3],
            'B' => ['body' => 'Menghapus unggahan itu karena dianggap mencemarkan nama instansi', 'score' => 1],
            'C' => ['body' => 'Membalas keluhan itu dengan data waktu penyelesaian dan meminta nomor pengajuan lewat jalur pesan pribadi untuk ditindaklanjuti hari itu', 'score' => 5],
            'D' => ['body' => 'Menunggu pimpinan memberi arahan sebelum menanggapi apa pun', 'score' => 2],
            'E' => ['body' => 'Menjawab singkat bahwa semua pengajuan diproses sesuai antrean, lalu memantau pengajuan tersebut tanpa membalas', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 adalah tanggapan terbuka yang memindahkan keluhan ke jalur penanganan nyata tanpa mengabaikan publik. Jawaban yang hanya menunjuk kanal resmi (3) memindahkan beban ke warga, dan menunggu arahan (2) membuang waktu yang justru sedang jadi sorotan.',
    ],
    [
        'code' => 'TKP-003',
        'subcategory' => 'pelayanan_publik',
        'stem' => 'Seorang pemohon lanjut usia datang dengan berkas yang tidak lengkap dan tampak kebingungan membaca daftar persyaratan yang tertempel di dinding.',
        'options' => [
            'A' => ['body' => 'Menunjukkan daftar persyaratan di dinding dan memintanya membaca sendiri agar semua pemohon diperlakukan sama', 'score' => 2],
            'B' => ['body' => 'Menerima berkasnya dengan catatan akan dilengkapi menyusul, lalu memprosesnya lebih dahulu', 'score' => 3],
            'C' => ['body' => 'Menyarankan pemohon menitipkan berkas kepada siapa pun yang biasa mengurus dokumen di sekitar kantor', 'score' => 1],
            'D' => ['body' => 'Menjelaskan satu per satu kekurangannya, menuliskan daftar yang harus dilengkapi, dan mengarahkannya ke petugas yang bisa membantu mengurus salinan', 'score' => 5],
            'E' => ['body' => 'Membantu mengarahkan pengisian formulir dan memprioritaskan pemohon lansia di antrean tanpa mengabaikan urutan pemohon lain', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 memadukan dua hal: pemohon lansia benar-benar dibantu memahami persyaratan, dan berkasnya tetap lengkap sesuai aturan. Memproses berkas tak lengkap (3) menggeser risiko ke belakang, sedangkan menitipkan berkas kepada pihak tak resmi (1) justru membuka celah pungutan liar.',
    ],
    [
        'code' => 'TKP-004',
        'subcategory' => 'pelayanan_publik',
        'stem' => 'Saat jam layanan masih berjalan, aplikasi pengajuan daring instansi Anda tiba-tiba tidak dapat diakses dan warga mulai berdatangan ke kantor.',
        'options' => [
            'A' => ['body' => 'Memberitahukan gangguan melalui pengumuman resmi, melayani pengajuan secara manual dengan tanda terima, dan melaporkan gangguan ke tim teknis', 'score' => 5],
            'B' => ['body' => 'Meminta warga pulang dan kembali setelah aplikasi diperbaiki', 'score' => 1],
            'C' => ['body' => 'Melayani secara manual tanpa memberi tahu kapan aplikasi kembali normal', 'score' => 3],
            'D' => ['body' => 'Mengarahkan seluruh warga ke kanal pengaduan agar keluhannya tercatat lebih dahulu', 'score' => 2],
            'E' => ['body' => 'Melaporkan gangguan ke tim teknis dan menunggu perbaikan sambil menyiapkan formulir manual untuk dipakai bila gangguan berlanjut', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menjalankan tiga hal sekaligus: transparansi, layanan tetap berjalan, dan perbaikan segera diminta. Menyiapkan formulir manual tanpa memakainya lebih dahulu (4) tidak salah, tetapi warga yang sudah datang tetap menunggu tanpa kepastian.',
    ],
    [
        'code' => 'TKP-005',
        'subcategory' => 'pelayanan_publik',
        'stem' => 'Seorang tokoh setempat yang cukup berpengaruh meminta Anda memproses permohonannya lebih dahulu, padahal permohonan itu belum memenuhi dua syarat yang wajib.',
        'options' => [
            'A' => ['body' => 'Memprosesnya lebih dahulu karena hubungan baik dengan tokoh setempat penting bagi kelancaran program', 'score' => 2],
            'B' => ['body' => 'Menjelaskan syarat yang belum dipenuhi dan menawarkan membantu mempercepat pemeriksaan setelah syaratnya lengkap', 'score' => 5],
            'C' => ['body' => 'Menolak dengan singkat tanpa menjelaskan syarat yang kurang', 'score' => 3],
            'D' => ['body' => 'Menyarankan permohonan itu diserahkan kepada rekan lain agar Anda tidak menanggung risikonya', 'score' => 1],
            'E' => ['body' => 'Menyampaikan bahwa permohonan akan diproses sesuai urutan dan menyampaikan hal yang sama kepada pemohon lain', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menolak permintaan yang menabrak prosedur sekaligus tetap membantu: kekurangannya dijelaskan dan jalannya ditawarkan setelah syaratnya lengkap. Bobot 4 menyampaikan hal yang sama dengan menegaskan urutan, adil tetapi tidak menolong pemohon melengkapi berkasnya, sedangkan memproses lebih dahulu karena hubungan baik menempatkan kedekatan di atas aturan.',
    ],
    [
        'code' => 'TKP-006',
        'subcategory' => 'pelayanan_publik',
        'stem' => 'Anda menemukan bahwa layanan yang Anda kerjakan setiap hari belum punya prosedur baku, sehingga rekan-rekan sering mengerjakannya dengan cara berbeda.',
        'options' => [
            'A' => ['body' => 'Membiarkannya karena hasil pekerjaan tetap selesai meski caranya berbeda', 'score' => 1],
            'B' => ['body' => 'Mengerjakan bagian Anda dengan cara sendiri dan tidak mencampuri cara rekan', 'score' => 2],
            'C' => ['body' => 'Menyerahkan seluruh persoalan prosedur kepada pimpinan tanpa usulan apa pun', 'score' => 3],
            'D' => ['body' => 'Mencatat langkah kerja yang paling aman, mengusulkan penyusunan prosedur kepada pimpinan, dan membahasnya bersama rekan satu unit', 'score' => 5],
            'E' => ['body' => 'Menyusun prosedur sendiri dan meminta rekan mengikutinya tanpa membahasnya lebih dahulu', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 memperbaiki mutu layanan sekaligus melibatkan unit dan jalur wewenang yang benar. Menyusun prosedur lalu memaksakannya kepada rekan (4) sudah berniat baik, tetapi melewati pembahasan yang membuat prosedur itu benar-benar dipakai.',
    ],
    [
        'code' => 'TKP-007',
        'subcategory' => 'pelayanan_publik',
        'stem' => 'Seorang warga menunggu cukup lama di loket rekan Anda karena rekan itu sedang menyelesaikan pekerjaan lain. Warga mulai bertanya dengan nada tidak sabar.',
        'options' => [
            'A' => ['body' => 'Memberi tahu warga perkiraan waktu tunggu dan menawarkan memeriksa kelengkapan berkasnya sambil menunggu', 'score' => 5],
            'B' => ['body' => 'Menyarankan warga pindah ke loket Anda meski jenis layanannya berbeda', 'score' => 2],
            'C' => ['body' => 'Menjelaskan bahwa yang berhak melayani adalah rekan tersebut, lalu melanjutkan pekerjaan Anda', 'score' => 1],
            'D' => ['body' => 'Menyampaikan kepada warga bahwa rekan Anda sedang sibuk dengan pekerjaan lain', 'score' => 3],
            'E' => ['body' => 'Membantu memeriksa kelengkapan berkas dan memberi tahu rekan Anda bahwa ada warga menunggu', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 mengurangi waktu tunggu yang tidak perlu dan menjaga warga tetap terlayani tanpa mengambil alih wewenang loket lain. Memberi tahu rekan (4) juga benar, tetapi warga tetap menunggu tanpa penjelasan.',
    ],
    [
        'code' => 'TKP-008',
        'subcategory' => 'pelayanan_publik',
        'stem' => 'Anda mengetahui ada rekan di unit lain meminta imbalan tidak resmi kepada warga untuk mempercepat pengurusan dokumen.',
        'options' => [
            'A' => ['body' => 'Menegur rekan itu langsung di depan warga agar tidak terulang', 'score' => 2],
            'B' => ['body' => 'Membicarakannya dengan rekan lain untuk memastikan apakah hal itu memang terjadi', 'score' => 3],
            'C' => ['body' => 'Tidak mencampuri urusan unit lain karena bukan wilayah kerja Anda', 'score' => 1],
            'D' => ['body' => 'Mengingatkan rekan itu secara pribadi agar berhenti, dan melaporkannya bila tetap berlanjut', 'score' => 4],
            'E' => ['body' => 'Tidak terlibat, mengumpulkan bukti secukupnya, dan melaporkannya melalui kanal pengaduan resmi instansi', 'score' => 5],
        ],
        'explanation' => 'Bobot 5 menghentikan praktik itu lewat jalur yang memang disediakan, bukan lewat keributan di depan warga. Mengingatkan secara pribadi (4) adalah langkah yang beradab, tetapi tidak memberi instansi kesempatan menanganinya bila peringatan itu diabaikan.',
    ],

    // --- Jejaring kerja -----------------------------------------------------
    [
        'code' => 'TKP-009',
        'subcategory' => 'jejaring_kerja',
        'stem' => 'Anda memimpin pekerjaan yang menuntut bantuan unit lain, sementara unit tersebut sedang menangani pekerjaan yang juga mendesak.',
        'options' => [
            'A' => ['body' => 'Mengerjakan sendiri bagian unit lain agar pekerjaan Anda selesai tepat waktu', 'score' => 3],
            'B' => ['body' => 'Menunda pekerjaan Anda sampai unit tersebut senggang tanpa memberi tahu siapa pun', 'score' => 1],
            'C' => ['body' => 'Membahas bersama pimpinan kedua unit untuk menyepakati urutan prioritas dan batas waktu bantuan', 'score' => 5],
            'D' => ['body' => 'Melaporkan bahwa unit lain tidak kooperatif sehingga Anda tidak dapat menyelesaikan pekerjaan', 'score' => 2],
            'E' => ['body' => 'Menyepakati dengan unit itu bantuan pada jam tertentu dan menyesuaikan rencana kerja Anda', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menyelesaikan dua kepentingan sekaligus karena urutan prioritas ditetapkan bersama pihak yang berwenang menetapkannya. Kesepakatan antarpelaksana (4) sering cukup, tetapi rapuh ketika dua-duanya sama-sama mendesak dan tidak ada yang berhak memutuskan.',
    ],
    [
        'code' => 'TKP-010',
        'subcategory' => 'jejaring_kerja',
        'stem' => 'Dalam rapat, seorang rekan dari unit lain menolak usulan Anda dengan nada keras dan menyebut usulan itu tidak mungkin dijalankan.',
        'options' => [
            'A' => ['body' => 'Membalas dengan nada yang sama agar usulan Anda tidak dianggap lemah', 'score' => 1],
            'B' => ['body' => 'Mengalihkan pembicaraan ke data yang tersedia, merangkum titik kesepakatan, dan mengusulkan uji coba terbatas', 'score' => 5],
            'C' => ['body' => 'Mendiamkan keberatan itu dan melanjutkan bagian presentasi berikutnya', 'score' => 2],
            'D' => ['body' => 'Meminta pimpinan rapat memutuskan agar perdebatan tidak berlarut', 'score' => 3],
            'E' => ['body' => 'Menerima keberatan itu dan mengubah usulan sesuai permintaan rekan tanpa membahas dasarnya', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 memindahkan pertentangan dari nada ke bukti dan memberi jalan tengah yang bisa diuji, sehingga usulannya tetap hidup tanpa merendahkan rekan. Menerima keberatan begitu saja (4) menjaga suasana, tetapi mengorbankan dasar pertimbangan yang seharusnya dipakai.',
    ],
    [
        'code' => 'TKP-011',
        'subcategory' => 'jejaring_kerja',
        'stem' => 'Salah satu anggota tim yang Anda ikuti tidak menyelesaikan bagiannya sehingga pekerjaan bersama terhambat, meski ia tampak sibuk.',
        'options' => [
            'A' => ['body' => 'Melaporkan ke pimpinan bahwa anggota tersebut tidak menyelesaikan tugasnya', 'score' => 2],
            'B' => ['body' => 'Menanyakan hambatan yang dihadapinya secara pribadi dan menawarkan bantuan pada bagian yang bisa Anda kerjakan', 'score' => 5],
            'C' => ['body' => 'Mengerjakan bagiannya sekalian agar pekerjaan tim tidak tertunda', 'score' => 3],
            'D' => ['body' => 'Menunggu sampai ia selesai tanpa menanyakan apa pun agar tidak mencampuri pekerjaan orang lain', 'score' => 1],
            'E' => ['body' => 'Mengingatkan tenggatnya dan menyampaikan bahwa tim menunggu bagian tersebut', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 mencari penyebabnya lebih dahulu dan menawarkan bantuan, karena anggota yang tertinggal belum tentu tidak mau bekerja. Mengingatkan tenggat (4) benar dan sering diperlukan, tetapi tidak menolong kalau penyebabnya memang beban yang tak terselesaikan sendiri.',
    ],
    [
        'code' => 'TKP-012',
        'subcategory' => 'jejaring_kerja',
        'stem' => 'Anda dipercaya memimpin tim kecil, sementara salah satu anggotanya jelas tidak menyukai keputusan itu dan mulai bersikap pasif.',
        'options' => [
            'A' => ['body' => 'Mengabaikan sikapnya selama pekerjaannya selesai tepat waktu', 'score' => 3],
            'B' => ['body' => 'Memindahkannya ke bagian yang tidak berhubungan dengan tim', 'score' => 1],
            'C' => ['body' => 'Memberi tugas yang lebih banyak agar ia membuktikan kesungguhannya', 'score' => 2],
            'D' => ['body' => 'Menjelaskan rencana kerja tim dan membuka kesempatan menyampaikan keberatan pada rapat pertama', 'score' => 4],
            'E' => ['body' => 'Membicarakannya empat mata, menanyakan pandangannya, dan memberi peran yang sesuai keahliannya', 'score' => 5],
        ],
        'explanation' => 'Bobot 5 menangani sikap itu langsung pada sumbernya dan mengubahnya menjadi kontribusi. Membuka ruang keberatan di rapat (4) juga sehat, tetapi pembicaraan empat mata lebih dahulu biasanya yang membuat anggota itu merasa benar-benar didengar.',
    ],
    [
        'code' => 'TKP-013',
        'subcategory' => 'jejaring_kerja',
        'stem' => 'Di sela istirahat, seorang rekan menyampaikan kabar yang meragukan tentang kehidupan pribadi rekan lain di kantor Anda.',
        'options' => [
            'A' => ['body' => 'Mendengarkan tanpa ikut menyebarkannya, dan mengalihkan pembicaraan ke hal yang lebih pantas', 'score' => 5],
            'B' => ['body' => 'Menambahkan informasi yang Anda dengar dari pihak lain agar pembicaraan tidak terputus', 'score' => 1],
            'C' => ['body' => 'Menyampaikan kepada rekan yang dibicarakan bahwa namanya sedang menjadi bahan pembicaraan', 'score' => 3],
            'D' => ['body' => 'Menyampaikan kepada atasan agar kabar itu tidak menyebar lebih jauh', 'score' => 2],
            'E' => ['body' => 'Menegaskan bahwa kabar seperti itu sebaiknya tidak dibicarakan tanpa bukti, lalu melanjutkan istirahat', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menjaga marwah rekan tanpa memperbesar persoalan maupun ikut menikmatinya. Menegur secara terbuka (4) memang menjaga nilai, tetapi pada kabar yang belum jelas kebenarannya sering berbuah pertengkaran yang tidak perlu.',
    ],
    [
        'code' => 'TKP-014',
        'subcategory' => 'jejaring_kerja',
        'stem' => 'Instansi Anda baru menjalin kerja sama dengan instansi lain. Pembagian peran dan saluran komunikasi keduanya belum jelas, dan pekerjaan sudah harus mulai berjalan.',
        'options' => [
            'A' => ['body' => 'Menunggu kedua pimpinan menyepakati hal itu sebelum Anda mulai bekerja', 'score' => 2],
            'B' => ['body' => 'Mulai bekerja dengan pemahaman Anda sendiri dan menyesuaikan nanti bila ada koreksi', 'score' => 3],
            'C' => ['body' => 'Menghubungi pejabat kedua instansi melalui pesan singkat agar pekerjaan cepat berjalan', 'score' => 1],
            'D' => ['body' => 'Menyusun notula kesepahaman berisi peran, penanggung jawab, dan saluran komunikasi resmi untuk disetujui kedua pihak', 'score' => 5],
            'E' => ['body' => 'Menyepakati penanggung jawab tiap bagian dengan rekan sejawat di instansi pasangan secara lisan', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menghasilkan kesepakatan tertulis yang bisa dirujuk ketika muncul perbedaan tafsir, tepat ketika kerja sama masih baru. Kesepakatan lisan antarpelaksana (4) cukup untuk langkah pertama, tetapi mudah diperselisihkan kemudian.',
    ],
    [
        'code' => 'TKP-015',
        'subcategory' => 'jejaring_kerja',
        'stem' => 'Seorang rekan meminta bantuan mengerjakan tugasnya hari ini, sementara Anda sedang mengejar tenggat pekerjaan sendiri.',
        'options' => [
            'A' => ['body' => 'Meninggalkan pekerjaan sendiri lalu mengerjakan tugas rekan agar pekerjaannya selesai', 'score' => 2],
            'B' => ['body' => 'Menolak dengan alasan Anda juga sibuk tanpa menjelaskan lebih lanjut', 'score' => 1],
            'C' => ['body' => 'Menjelaskan kondisi Anda, menyepakati waktu yang memungkinkan, dan memberi arahan singkat agar ia bisa mulai sendiri', 'score' => 5],
            'D' => ['body' => 'Menyarankan rekan meminta bantuan orang lain yang mungkin lebih luang', 'score' => 3],
            'E' => ['body' => 'Membantu seperlunya pada bagian yang paling sulit di sela pekerjaan Anda', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menolong rekan tanpa mengorbankan tanggung jawab sendiri, dan arahannya membuat rekan itu tetap bisa berjalan. Menolong seperlunya di sela pekerjaan (4) baik, tetapi bisa membuat dua pekerjaan selesai setengah dan tenggat Anda yang tertabrak.',
    ],
    [
        'code' => 'TKP-016',
        'subcategory' => 'jejaring_kerja',
        'stem' => 'Terjadi kesalahan yang berimbas pada pengguna layanan. Atasan menanyakan siapa yang bertanggung jawab, dan sebagian kesalahan itu berasal dari bagian yang Anda kerjakan.',
        'options' => [
            'A' => ['body' => 'Menyampaikan fakta termasuk bagian Anda sendiri, dan mengusulkan langkah perbaikan yang bisa segera dijalankan', 'score' => 5],
            'B' => ['body' => 'Menyampaikan bahwa kesalahan berasal dari bagian lain agar tim Anda tidak disalahkan', 'score' => 1],
            'C' => ['body' => 'Menjelaskan bahwa kesalahan semacam itu wajar terjadi dalam pekerjaan yang terburu-buru', 'score' => 2],
            'D' => ['body' => 'Menyampaikan fakta bagian Anda saja tanpa menyinggung bagian lain', 'score' => 3],
            'E' => ['body' => 'Mengakui bagian Anda, lalu membahas bersama tim untuk menemukan penyebab dan menutup celahnya', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menggabungkan kejujuran dengan usulan perbaikan yang bisa ditindaklanjuti hari itu. Mengakui bagian sendiri dan membahas penyebab bersama tim (4) juga jujur, tetapi menunggu pembahasan menunda perbaikan layanan yang sedang terganggu.',
    ],

    // --- Sosial budaya ------------------------------------------------------
    [
        'code' => 'TKP-017',
        'subcategory' => 'sosial_budaya',
        'stem' => 'Rekan kerja Anda sedang menjalankan ibadah puasa menurut agamanya, sementara tim Anda terbiasa makan siang bersama di kantor.',
        'options' => [
            'A' => ['body' => 'Membatalkan kebiasaan makan bersama selama bulan puasa agar rekan itu tidak merasa tersisih', 'score' => 3],
            'B' => ['body' => 'Tetap makan bersama di ruangan yang sama sambil bercanda tentang makanannya agar suasana tetap akrab', 'score' => 2],
            'C' => ['body' => 'Menanyakan kepada rekan itu setiap hari apakah ia sudah boleh makan', 'score' => 1],
            'D' => ['body' => 'Mengajak tim menyesuaikan waktu makan bersama dan tetap melibatkan rekan itu dalam kebersamaan tanpa memaksanya makan', 'score' => 5],
            'E' => ['body' => 'Tetap makan bersama dan menyediakan makanan untuk berbuka bagi rekan itu menjelang waktunya', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menjaga kebersamaan tanpa membuat rekan itu merasa dijadikan pengecualian. Menyediakan makanan berbuka (4) adalah kebaikan yang tulus, tetapi perhatian pada waktu dan cara bergaul lebih dahulu yang membuat rekan itu nyaman sepanjang bulan.',
    ],
    [
        'code' => 'TKP-018',
        'subcategory' => 'sosial_budaya',
        'stem' => 'Sebuah program layanan di daerah penugasan Anda ditolak sebagian warga karena dianggap bertentangan dengan kebiasaan setempat yang sudah lama berjalan.',
        'options' => [
            'A' => ['body' => 'Melanjutkan program sesuai rencana karena tujuannya sudah benar dan telah disetujui atasan', 'score' => 3],
            'B' => ['body' => 'Mengajak tokoh masyarakat dan warga berdialog, mempelajari alasan penolakan, lalu menyesuaikan cara pelaksanaan tanpa mengubah sasaran program', 'score' => 5],
            'C' => ['body' => 'Menghentikan program itu dan menunggu kebijakan baru dari pusat', 'score' => 2],
            'D' => ['body' => 'Menyampaikan kepada warga bahwa program tetap akan dijalankan dan mereka hanya perlu menyesuaikan diri', 'score' => 1],
            'E' => ['body' => 'Menyesuaikan jadwal pelaksanaan dan menjelaskan tujuan program kepada warga pada pertemuan berikutnya', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menempatkan warga sebagai mitra: alasan penolakan dicari lebih dahulu, sasaran program tetap, dan caranya disesuaikan. Menyesuaikan jadwal sambil menjelaskan (4) bergerak ke arah yang benar, tetapi belum tentu menyentuh kebiasaan yang sebenarnya membuat warga menolak.',
    ],
    [
        'code' => 'TKP-019',
        'subcategory' => 'sosial_budaya',
        'stem' => 'Dalam rapat kantor, seorang rekan menyampaikan pendapat dengan bahasa daerah yang tidak dipahami sebagian peserta lain.',
        'options' => [
            'A' => ['body' => 'Melarang penggunaan bahasa daerah dalam rapat karena menghambat pemahaman bersama', 'score' => 2],
            'B' => ['body' => 'Menerjemahkan pokok pendapatnya ke bahasa Indonesia tanpa meminta izin kepadanya', 'score' => 3],
            'C' => ['body' => 'Menegur rekan itu setelah rapat agar ia tidak mengulanginya', 'score' => 1],
            'D' => ['body' => 'Meminta rekan itu mengulang pendapatnya dengan bahasa Indonesia tanpa menjelaskan alasannya', 'score' => 4],
            'E' => ['body' => 'Menghargai penyampaiannya dan mengusulkan agar pokok pendapatnya dituangkan kembali dalam bahasa Indonesia agar seluruh peserta memahami', 'score' => 5],
        ],
        'explanation' => 'Bobot 5 menghargai bahasa daerah sekaligus memastikan rapat berjalan efektif bagi semua peserta. Meminta mengulang dalam bahasa Indonesia (4) mencapai efek yang sama, tetapi terasa seperti teguran bila alasannya tidak dijelaskan.',
    ],
    [
        'code' => 'TKP-020',
        'subcategory' => 'sosial_budaya',
        'stem' => 'Anda baru ditugaskan di daerah yang adat istiadatnya belum Anda kenali. Tidak lama kemudian Anda harus memimpin kegiatan yang melibatkan warga setempat.',
        'options' => [
            'A' => ['body' => 'Mengikuti sepenuhnya apa yang diminta warga meski bertentangan dengan ketentuan kegiatan', 'score' => 2],
            'B' => ['body' => 'Menjalankan kegiatan sesuai pengalaman Anda di daerah sebelumnya karena caranya sudah terbukti', 'score' => 3],
            'C' => ['body' => 'Mempelajari adat setempat lebih dahulu dan meminta nasihat tokoh masyarakat mengenai tata cara yang pantas dalam kegiatan itu', 'score' => 5],
            'D' => ['body' => 'Meminta warga menyesuaikan diri dengan tata cara yang biasa Anda pakai', 'score' => 1],
            'E' => ['body' => 'Menanyakan tata cara yang berlaku kepada pegawai setempat yang telah lama bertugas di daerah itu', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 belajar langsung pada sumbernya dan melibatkan tokoh setempat, sehingga kegiatan dipimpin dengan pantas sejak awal. Bertanya kepada pegawai setempat (4) juga tepat dan lebih cepat, tetapi belum menyentuh kesepakatan dengan tokoh yang akan jadi panutan warga.',
    ],
    [
        'code' => 'TKP-021',
        'subcategory' => 'sosial_budaya',
        'stem' => 'Dalam sebuah acara kantor yang dihadiri pegawai dari berbagai latar kepercayaan, panitia meminta Anda memimpin doa menurut agama yang Anda anut.',
        'options' => [
            'A' => ['body' => 'Menjalankan permintaan itu dengan khidmat sambil mengusulkan agar acara berikutnya membuka ruang bagi kepercayaan lain', 'score' => 4],
            'B' => ['body' => 'Menolak karena acara kantor sebaiknya tidak memuat doa keagamaan', 'score' => 2],
            'C' => ['body' => 'Mengusulkan format yang menghormati semua peserta, misalnya saat hening atau doa menurut kepercayaan masing-masing', 'score' => 5],
            'D' => ['body' => 'Meminta panitia memilih peserta lain yang agamanya lebih banyak dianut di kantor', 'score' => 1],
            'E' => ['body' => 'Menerima permintaan itu tanpa menyampaikan usulan apa pun karena panitia yang berhak menentukan acara', 'score' => 3],
        ],
        'explanation' => 'Bobot 5 memastikan acara tetap khidmat sekaligus tidak mengabaikan keberagaman peserta. Menerima sambil mengusulkan perbaikan untuk acara berikutnya (4) juga menghargai keberagaman, tetapi peserta dari kepercayaan lain masih tertinggal pada acara hari itu.',
    ],
    [
        'code' => 'TKP-022',
        'subcategory' => 'sosial_budaya',
        'stem' => 'Di wilayah kerja Anda muncul perselisihan antara dua kelompok warga yang berbeda asal daerah, dan keduanya meminta Anda berpihak.',
        'options' => [
            'A' => ['body' => 'Mendukung kelompok yang paling banyak mendukung program instansi Anda', 'score' => 1],
            'B' => ['body' => 'Menyerahkan seluruh persoalan ke aparat keamanan tanpa melakukan apa pun lebih dahulu', 'score' => 3],
            'C' => ['body' => 'Menghindari wilayah tersebut sampai perselisihan mereda sendiri', 'score' => 2],
            'D' => ['body' => 'Mendengarkan kedua pihak dan meminta keduanya menahan diri sambil mengupayakan pertemuan lanjutan', 'score' => 4],
            'E' => ['body' => 'Menolak berpihak, mendengarkan kedua kelompok, dan memfasilitasi penyelesaian melalui musyawarah bersama tokoh setempat', 'score' => 5],
        ],
        'explanation' => 'Bobot 5 menjaga sikap netral sekaligus aktif mencari penyelesaian bersama pihak yang dipercaya warga. Meminta kedua pihak menahan diri (4) berguna untuk meredakan situasi, tetapi belum mempertemukan mereka untuk menyelesaikan akarnya.',
    ],
    [
        'code' => 'TKP-023',
        'subcategory' => 'sosial_budaya',
        'stem' => 'Rekan kerja baru Anda berasal dari daerah lain dan tampak kesulitan menyesuaikan diri dengan cara kerja serta kebiasaan tim.',
        'options' => [
            'A' => ['body' => 'Membiarkannya menyesuaikan diri sendiri karena setiap pegawai baru melewati hal yang sama', 'score' => 3],
            'B' => ['body' => 'Menjelaskan kebiasaan tim dan membantu rekan itu menyesuaikan diri tanpa menuntutnya meninggalkan kebiasaannya sendiri', 'score' => 5],
            'C' => ['body' => 'Menegurnya setiap kali caranya berbeda dari kebiasaan tim', 'score' => 2],
            'D' => ['body' => 'Mengingatkannya menjelang penilaian kinerja agar nilai rekan itu tidak terpengaruh', 'score' => 1],
            'E' => ['body' => 'Mengenalkannya kepada rekan lain dan memberi tahu kebiasaan yang berlaku sehari-hari di tim', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 memadukan penjelasan yang jelas tentang cara kerja tim dengan penghormatan pada kebiasaan rekan itu. Mengenalkannya kepada tim (4) sangat membantu pergaulan, tetapi belum menjelaskan hal praktis yang membuat pekerjaannya nyambung.',
    ],
    [
        'code' => 'TKP-024',
        'subcategory' => 'sosial_budaya',
        'stem' => 'Sejumlah warga meminta Anda hadir dalam kegiatan gotong royong lingkungan, sementara kegiatan itu berlangsung di luar jam kerja dan akhir pekan Anda.',
        'options' => [
            'A' => ['body' => 'Tidak hadir karena kegiatan di luar jam kerja bukan kewajiban pegawai', 'score' => 2],
            'B' => ['body' => 'Mengirimkan sumbangan tanpa hadir sehingga warga tetap merasa diperhatikan', 'score' => 3],
            'C' => ['body' => 'Menjanjikan akan hadir pada setiap kegiatan agar warga senang, meski tidak yakin bisa datang', 'score' => 1],
            'D' => ['body' => 'Hadir bila benar-benar memungkinkan dan memberi tahu tujuan penggunaan hasil gotong royong itu bagi layanan instansi', 'score' => 5],
            'E' => ['body' => 'Hadir pada sebagian acara yang paling penting bagi warga dan menyampaikan alasannya bila tidak bisa hadir penuh', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 memilih kehadiran yang jujur dan membawa manfaat: hadir bila memungkinkan, dengan keterangan yang mengaitkan kegiatan itu pada layanan. Hadir sebagian dengan alasan yang jelas (4) juga sah, tetapi kurang berguna bila kehadiran pada acara utama yang justru menentukan.',
    ],

    // --- Teknologi informasi dan komunikasi ---------------------------------
    [
        'code' => 'TKP-025',
        'subcategory' => 'teknologi_informasi',
        'stem' => 'Anda menerima surel yang mengatasnamakan pimpinan instansi dan meminta data seluruh peserta layanan dikirim hari itu juga melalui tautan yang disertakan.',
        'options' => [
            'A' => ['body' => 'Memastikan permintaan itu langsung kepada pimpinan lewat saluran resmi, tidak membuka tautannya, dan melaporkan surel itu ke pengelola sistem informasi', 'score' => 5],
            'B' => ['body' => 'Membuka tautannya untuk melihat formulir yang diminta, lalu menunggu kepastian lebih lanjut', 'score' => 2],
            'C' => ['body' => 'Mengirim data yang diminta karena pengirimnya mengaku pimpinan dan urusannya mendesak', 'score' => 1],
            'D' => ['body' => 'Membalas surel itu untuk menanyakan keasliannya sebelum melakukan apa pun', 'score' => 3],
            'E' => ['body' => 'Menghapus surel itu dan memberi tahu rekan-rekan agar berhati-hati terhadap surel mencurigakan', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menghentikan risiko pada dua jalur: data tidak dikirim dan surel itu dilaporkan agar sistemnya diperiksa. Menghapus dan mengingatkan rekan (4) melindungi Anda sendiri, tetapi membuang bukti yang justru dibutuhkan pengelola sistem, dan membalas surel itu (3) memberi tahu pengirim bahwa alamatnya aktif.',
    ],
    [
        'code' => 'TKP-026',
        'subcategory' => 'teknologi_informasi',
        'stem' => 'Anda perlu mengolah data pribadi warga untuk menyusun laporan, dan harus memilih tempat menyimpan berkas kerjanya.',
        'options' => [
            'A' => ['body' => 'Menyimpannya pada cakram lepas pribadi agar data tidak tercampur dengan data pegawai lain', 'score' => 2],
            'B' => ['body' => 'Mengirimkannya ke surel pribadi agar bisa dikerjakan dari rumah bila diperlukan', 'score' => 1],
            'C' => ['body' => 'Menyimpannya di komputer kerja tanpa pengamanan tambahan karena komputernya hanya Anda yang pakai', 'score' => 3],
            'D' => ['body' => 'Menyimpan pada penyimpanan resmi instansi dan mencatat siapa saja yang diberi akses ke berkas itu', 'score' => 4],
            'E' => ['body' => 'Menyimpan pada sistem penyimpanan resmi instansi yang aksesnya terbatas, dan menghapus berkas kerja sementara setelah laporan selesai', 'score' => 5],
        ],
        'explanation' => 'Bobot 5 menjaga data pribadi tetap di dalam kendali instansi, sekaligus menutup risiko yang paling sering terlupa: berkas kerja yang tertinggal setelah laporan selesai. Mencatat pemberian akses (4) sudah baik, tetapi berkasnya masih bisa tersimpan tanpa batas waktu.',
    ],
    [
        'code' => 'TKP-027',
        'subcategory' => 'teknologi_informasi',
        'stem' => 'Di grup percakapan kantor beredar pesan berantai berisi kabar yang mengejutkan tentang kebijakan pemerintah, tanpa menyebutkan sumbernya.',
        'options' => [
            'A' => ['body' => 'Tidak menyebarkannya, memeriksa kebenarannya pada sumber resmi, dan menyampaikan hasilnya di grup itu dengan sopan', 'score' => 5],
            'B' => ['body' => 'Meneruskan pesan itu ke grup lain agar orang lain dapat menilai sendiri kebenarannya', 'score' => 1],
            'C' => ['body' => 'Membiarkan pesan itu tanpa menanggapi karena bukan urusan Anda', 'score' => 2],
            'D' => ['body' => 'Mengeluarkan diri dari grup kantor agar tidak menerima pesan semacam itu', 'score' => 3],
            'E' => ['body' => 'Menanyakan sumber kabar itu di grup dan mengingatkan agar menunggu keterangan resmi', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 melakukan dua hal yang jarang dilakukan sekaligus: memeriksa lebih dahulu dan mengembalikan hasilnya ke kelompok yang sudah terpapar kabar itu. Mengingatkan menunggu keterangan resmi (4) benar, tetapi belum memastikan kabarnya salah atau benar.',
    ],
    [
        'code' => 'TKP-028',
        'subcategory' => 'teknologi_informasi',
        'stem' => 'Instansi Anda mulai memakai aplikasi baru untuk seluruh layanan, dan banyak rekan masih kebingungan menggunakannya.',
        'options' => [
            'A' => ['body' => 'Meminta pengelola aplikasi menyelenggarakan pelatihan resmi sebelum Anda ikut memakainya', 'score' => 2],
            'B' => ['body' => 'Menggunakan aplikasi itu untuk pekerjaan Anda sendiri tanpa mencampuri kesulitan rekan', 'score' => 3],
            'C' => ['body' => 'Mengerjakan bagian rekan yang belum bisa memakai aplikasi agar layanan tetap berjalan', 'score' => 1],
            'D' => ['body' => 'Mempelajari aplikasinya lebih dahulu, menyusun panduan singkat berisi langkah yang paling sering dipakai, dan mendampingi rekan yang masih ragu', 'score' => 5],
            'E' => ['body' => 'Membantu rekan yang bertanya dan menyampaikan kesulitan yang sering muncul kepada pengelola aplikasi', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menyebarkan penguasaan aplikasi ke seluruh unit, bukan hanya menambal kesulitan sesaat. Membantu sambil melaporkan kesulitan (4) juga tepat, tetapi tanpa panduan tertulis pertanyaan yang sama akan berulang setiap hari.',
    ],
    [
        'code' => 'TKP-029',
        'subcategory' => 'teknologi_informasi',
        'stem' => 'Anda harus mengirimkan dokumen berisi data yang bersifat terbatas kepada instansi mitra hari itu juga.',
        'options' => [
            'A' => ['body' => 'Mengirim melalui pesan percakapan pribadi karena cara itu paling cepat sampai', 'score' => 3],
            'B' => ['body' => 'Mengirim melalui surel pribadi Anda karena surel kedinasan sedang tidak dapat dibuka', 'score' => 2],
            'C' => ['body' => 'Mengirim melalui saluran resmi yang disepakati, memastikan penerimanya benar, dan memberi tahu bahwa dokumennya sudah terkirim', 'score' => 5],
            'D' => ['body' => 'Mengunggahnya ke layanan penyimpanan umum dan mengirimkan tautan tanpa pembatasan siapa yang boleh membuka', 'score' => 1],
            'E' => ['body' => 'Mengirim melalui saluran resmi dan mencatat pengiriman itu di berkas administrasi untuk dapat ditelusuri', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menutup tiga risiko sekaligus: saluran yang benar, penerima yang terverifikasi, dan penerima mengetahui kiriman itu datang. Catatan administrasi (4) memang penting untuk penelusuran, tetapi pada dokumen terbatas kejelasan penerima lebih dahulu harus dipastikan.',
    ],
    [
        'code' => 'TKP-030',
        'subcategory' => 'teknologi_informasi',
        'stem' => 'Anda menyadari akun kedinasan Anda pernah terbuka di komputer bersama dan mungkin belum Anda tutup dengan benar.',
        'options' => [
            'A' => ['body' => 'Menutup akun itu pada komputer tersebut dan berharap tidak terjadi apa-apa', 'score' => 2],
            'B' => ['body' => 'Segera mengganti kata sandinya, memeriksa riwayat aktivitas akun, dan melaporkan kejadian itu kepada pengelola sistem informasi', 'score' => 5],
            'C' => ['body' => 'Menunggu sampai ada kejanggalan pada akun sebelum melaporkan', 'score' => 1],
            'D' => ['body' => 'Mengganti kata sandi dan berhenti memakai komputer bersama untuk urusan kedinasan', 'score' => 4],
            'E' => ['body' => 'Memberi tahu rekan-rekan agar memeriksa komputernya masing-masing', 'score' => 3],
        ],
        'explanation' => 'Bobot 5 tidak hanya menutup celah tetapi juga memeriksa apakah celah itu sudah dipakai orang, dan melaporkannya agar sistem dapat diperiksa. Mengganti kata sandi saja (4) menutup pintunya tanpa mengetahui apakah ada yang sudah masuk.',
    ],
    [
        'code' => 'TKP-031',
        'subcategory' => 'teknologi_informasi',
        'stem' => 'Di akun media sosial pribadi Anda, seorang warga menanyakan kebijakan instansi dan meminta tanggapan Anda sebagai pegawai.',
        'options' => [
            'A' => ['body' => 'Menjawab dengan pendapat pribadi Anda tentang kebijakan itu secara terbuka', 'score' => 2],
            'B' => ['body' => 'Menghapus pertanyaannya dan menutup kolom tanggapan akun Anda', 'score' => 1],
            'C' => ['body' => 'Tidak menanggapi dan berharap pertanyaan itu tenggelam oleh percakapan lain', 'score' => 3],
            'D' => ['body' => 'Menjawabnya sebagai warga biasa, menjelaskan bahwa keterangan resmi hanya keluar dari kanal instansi, dan mengarahkannya ke kanal tersebut', 'score' => 5],
            'E' => ['body' => 'Mengarahkannya ke kanal resmi instansi dan meneruskan pertanyaannya ke unit yang berwenang menjawab', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menjaga batas antara pendapat pribadi dan keterangan resmi sekaligus tetap melayani penanya. Meneruskan pertanyaan ke unit berwenang (4) membantu warga memperoleh jawaban, tetapi jawaban Anda sendiri masih perlu menegaskan batas itu.',
    ],

    // --- Profesionalisme ----------------------------------------------------
    [
        'code' => 'TKP-032',
        'subcategory' => 'profesionalisme',
        'stem' => 'Menjelang tenggat, atasan memberi Anda tugas tambahan yang mendesak, sementara dua pekerjaan Anda sebelumnya belum selesai.',
        'options' => [
            'A' => ['body' => 'Menyampaikan keadaan pekerjaan Anda secara jujur, menyusun urutan prioritas bersama atasan, dan menjalankan urutan itu', 'score' => 5],
            'B' => ['body' => 'Menerima semua tugas dan berusaha mengerjakan semuanya sekaligus tanpa memberi tahu keadaan sebenarnya', 'score' => 2],
            'C' => ['body' => 'Menerima tugas tambahan itu dan menyelesaikan yang lain sebisanya setelah tenggatnya lewat', 'score' => 3],
            'D' => ['body' => 'Menolak tugas tambahan itu karena pekerjaan Anda sudah banyak', 'score' => 1],
            'E' => ['body' => 'Menerima tugas tambahan itu sambil meminta rekan mengambil alih sebagian pekerjaan Anda yang belum selesai', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menempatkan keputusan prioritas pada pihak yang berwenang mengambilnya, dengan keadaan yang sesungguhnya sebagai dasar. Meminta rekan mengambil alih (4) menyelesaikan beban hari itu, tetapi pemindahan tanggung jawab semacam itu belum tentu sejalan dengan rencana kerja yang lain.',
    ],
    [
        'code' => 'TKP-033',
        'subcategory' => 'profesionalisme',
        'stem' => 'Anda ditugaskan pada bidang yang belum pernah Anda tangani dan harus menyerahkan hasilnya dalam dua minggu.',
        'options' => [
            'A' => ['body' => 'Meminta atasan memindahkan tugas itu kepada pegawai yang sudah berpengalaman', 'score' => 1],
            'B' => ['body' => 'Mengerjakannya berdasarkan pemahaman sendiri agar tidak merepotkan pegawai lain', 'score' => 3],
            'C' => ['body' => 'Memetakan hal yang harus dikuasai, mempelajarinya secara terarah, dan meminta bimbingan pegawai yang berpengalaman pada bidang itu', 'score' => 5],
            'D' => ['body' => 'Menunggu sampai ada pegawai yang menawarkan bantuan sebelum mulai bekerja', 'score' => 2],
            'E' => ['body' => 'Mempelajari bidang itu dari panduan yang tersedia dan menyampaikan perkembangannya kepada atasan secara berkala', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menambahkan bimbingan orang berpengalaman pada pembelajaran mandiri, yang membuat hasilnya jauh lebih kecil kemungkinan salah tafsir. Belajar mandiri dengan laporan berkala (4) menunjukkan tanggung jawab, tetapi menutup peluang koreksi dini.',
    ],
    [
        'code' => 'TKP-034',
        'subcategory' => 'profesionalisme',
        'stem' => 'Anda menemukan kekeliruan angka pada laporan yang sudah Anda kirimkan kepada atasan kemarin.',
        'options' => [
            'A' => ['body' => 'Memperbaiki angkanya pada berkas Anda dan menunggu diminta mengirimkan laporan baru', 'score' => 2],
            'B' => ['body' => 'Segera melaporkan kekeliruan itu beserta perbaikannya, tanpa menunggu ada yang menemukannya', 'score' => 5],
            'C' => ['body' => 'Membiarkannya karena selisihnya kecil dan tidak mengubah kesimpulan laporan', 'score' => 1],
            'D' => ['body' => 'Menyampaikan perbaikannya pada kesempatan berikutnya agar tidak tampak ceroboh', 'score' => 3],
            'E' => ['body' => 'Melaporkan kekeliruan itu bersama langkah pencegahan agar tidak terulang pada laporan berikutnya', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 memulihkan kepercayaan pada data sedini mungkin, karena laporan yang salah angka dapat dipakai orang lain mengambil keputusan. Menambahkan langkah pencegahan (4) memang lebih baik, tetapi pada kekeliruan yang sudah tersebar kecepatan melaporkan lebih dahulu.',
    ],
    [
        'code' => 'TKP-035',
        'subcategory' => 'profesionalisme',
        'stem' => 'Seorang pengguna layanan menawarkan imbalan kepada Anda agar berkasnya diproses lebih cepat daripada antrean.',
        'options' => [
            'A' => ['body' => 'Menerima imbalan itu sebagai tanda terima kasih karena tidak merugikan siapa pun', 'score' => 1],
            'B' => ['body' => 'Menolak tanpa penjelasan dan langsung memanggil pengguna layanan berikutnya', 'score' => 3],
            'C' => ['body' => 'Menerima imbalan itu dan menyisihkan sebagian untuk kegiatan kantor', 'score' => 2],
            'D' => ['body' => 'Menolak imbalan itu, menjelaskan alasan aturannya, dan melaporkan tawaran tersebut kepada atasan', 'score' => 4],
            'E' => ['body' => 'Menolak imbalan itu dengan sopan, menjelaskan alasan aturannya, dan memastikan berkasnya masuk antrean pada waktunya', 'score' => 5],
        ],
        'explanation' => 'Bobot 5 menolak tanpa membuat pengguna layanan merasa dipermalukan, dan memastikan haknya tetap berjalan. Melaporkan tawaran itu (4) juga benar dan bahkan menjadi kewajiban pada beberapa keadaan, tetapi tanpa penjelasan aturan pengguna layanan bisa mengulangi tawaran yang sama kepada petugas lain.',
    ],
    [
        'code' => 'TKP-036',
        'subcategory' => 'profesionalisme',
        'stem' => 'Rekan kerja meminta Anda menandatangani berita acara pemeriksaan yang belum tempat Anda periksa, karena acaranya harus segera ditutup.',
        'options' => [
            'A' => ['body' => 'Menandatanganinya karena rekan itu sudah memeriksa dan Anda mempercayainya', 'score' => 2],
            'B' => ['body' => 'Menandatanganinya sambil meminta rekan itu bertanggung jawab bila ada kekeliruan', 'score' => 1],
            'C' => ['body' => 'Menolak menandatangani dokumen apa pun yang belum Anda susun sendiri', 'score' => 3],
            'D' => ['body' => 'Meminta waktu sebentar untuk memeriksa isinya, dan menandatangani hanya setelah isinya sesuai dengan yang Anda saksikan', 'score' => 5],
            'E' => ['body' => 'Memeriksa bagian yang penting saja, lalu menandatangani dan mencatat bagian yang belum tempat diperiksa', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menjunjung kebenaran isi dokumen sekaligus tidak menghambat acara. Memeriksa sebagian dengan catatan (4) adalah kompromi yang jujur, tetapi berita acara yang ditandatangani setengah periksa tetap menyimpan risiko di bagian yang tidak diperiksa.',
    ],
    [
        'code' => 'TKP-037',
        'subcategory' => 'profesionalisme',
        'stem' => 'Anda ditawari mengikuti pelatihan yang menambah keahlian, tetapi jadwalnya bertabrakan dengan pekerjaan yang sedang padat.',
        'options' => [
            'A' => ['body' => 'Mengambil pelatihan itu dengan mengatur ulang pekerjaan dan menyepakati penyesuaian tenggat bersama atasan', 'score' => 5],
            'B' => ['body' => 'Menolak pelatihan itu karena pekerjaan tidak boleh terganggu', 'score' => 2],
            'C' => ['body' => 'Mengambil pelatihan itu tanpa memberi tahu siapa pun agar tidak dihalangi', 'score' => 1],
            'D' => ['body' => 'Menunda pelatihan itu sampai pekerjaan benar-benar selesai, kapan pun itu terjadi', 'score' => 3],
            'E' => ['body' => 'Mengambil pelatihan itu dan menitipkan pekerjaan Anda kepada rekan selama pelatihan berlangsung', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menumbuhkan keahlian tanpa mengorbankan pekerjaan, karena penyesuaian tenggatnya disepakati lebih dahulu. Menitipkan pekerjaan kepada rekan (4) bisa berjalan, tetapi pemindahan beban tanpa kesepakatan resmi sering menimbulkan masalah baru di unit.',
    ],
    [
        'code' => 'TKP-038',
        'subcategory' => 'profesionalisme',
        'stem' => 'Hasil kerja unit Anda dalam beberapa bulan terakhir menurun, sementara setiap orang tampak mengerjakan bagiannya masing-masing dengan benar.',
        'options' => [
            'A' => ['body' => 'Menunggu pimpinan mengevaluasi kinerja unit karena itu bagian dari tugasnya', 'score' => 1],
            'B' => ['body' => 'Bekerja lebih keras pada bagian Anda agar hasil unit secara keseluruhan terangkat', 'score' => 3],
            'C' => ['body' => 'Mengusulkan penelusuran bersama atas proses kerja unit, menemukan simpul yang menghambat, dan mengusulkan perbaikannya', 'score' => 5],
            'D' => ['body' => 'Menyampaikan kepada pimpinan bahwa rekan-rekan perlu diminta meningkatkan kinerjanya', 'score' => 2],
            'E' => ['body' => 'Mencatat pekerjaan yang paling sering terlambat dan menyampaikan temuan itu kepada pimpinan beserta usulan perbaikan', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menempatkan masalahnya pada proses, bukan pada orang, dan mengajak unit menelusurinya bersama — cara itu biasanya menemukan penyebab yang tidak terlihat dari satu bagian saja. Menyampaikan temuan sendiri (4) berguna, tetapi tanpa penelusuran bersama simpul penghambatnya masih bisa salah tebak.',
    ],

    // --- Anti radikalisme ---------------------------------------------------
    [
        'code' => 'TKP-039',
        'subcategory' => 'anti_radikalisme',
        'stem' => 'Di grup percakapan kantor, seseorang mengirimkan ajakan yang merendahkan sekelompok orang karena perbedaan asal daerah, dan beberapa anggota menanggapinya dengan emosi.',
        'options' => [
            'A' => ['body' => 'Membalas dengan kalimat tajam agar pengirimnya menerima akibat dari ucapannya', 'score' => 1],
            'B' => ['body' => 'Mengingatkan dengan sopan bahwa pegawai layanan wajib menjaga keberagaman, dan meminta pembahasan itu dihentikan di grup kantor', 'score' => 5],
            'C' => ['body' => 'Membiarkan pembicaraan itu berjalan agar tidak dianggap ikut campur', 'score' => 2],
            'D' => ['body' => 'Mengeluarkan diri dari grup kantor itu dan menutup pembicaraan dengan siapa pun', 'score' => 3],
            'E' => ['body' => 'Menyampaikan keberatan kepada pengirimnya secara pribadi agar ia menghapus kirimannya', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menghentikan penyebaran di tempat terjadinya sambil menegaskan kewajiban pegawai layanan, tanpa memperbesar keributan. Menyampaikan keberatan secara pribadi (4) menjaga hubungan, tetapi kirimannya tetap terbaca anggota grup yang lain.',
    ],
    [
        'code' => 'TKP-040',
        'subcategory' => 'anti_radikalisme',
        'stem' => 'Seorang rekan mengajak Anda ikut dalam gerakan yang menuntut perubahan dasar negara, dengan alasan keadaan bangsa sudah sangat buruk.',
        'options' => [
            'A' => ['body' => 'Ikut sekali agar dapat menilai sendiri arah gerakannya', 'score' => 1],
            'B' => ['body' => 'Mendengarkan tanpa menanggapi dan tidak hadir pada kegiatan apa pun', 'score' => 3],
            'C' => ['body' => 'Menolak ikut dan memutuskan hubungan kerja dengan rekan tersebut', 'score' => 2],
            'D' => ['body' => 'Menolak dengan tegas dan menyarankan rekan itu menyalurkan aspirasinya melalui jalur resmi yang tersedia', 'score' => 4],
            'E' => ['body' => 'Menolak dengan tegas dan menjelaskan bahwa ASN wajib menjaga dasar negara serta menempuh saluran yang tersedia untuk menyampaikan pendapat', 'score' => 5],
        ],
        'explanation' => 'Bobot 5 menolak gerakan itu sekaligus menegaskan dasar negara dan jalur penyampaian pendapat yang sah. Hanya menyarankan jalur resmi tanpa menegaskan penolakan (4) kurang jelas, karena pada ajakan yang menyentuh dasar negara duduk perkaranya perlu disampaikan apa adanya.',
    ],
    [
        'code' => 'TKP-041',
        'subcategory' => 'anti_radikalisme',
        'stem' => 'Di media sosial Anda menemukan unggahan provokatif yang mengajak orang membenci pemerintah dan menyebarkan kecurigaan terhadap seluruh pejabat.',
        'options' => [
            'A' => ['body' => 'Membalas unggahan itu dengan unggahan balasan bernada tinggi agar pengaruhnya berkurang', 'score' => 2],
            'B' => ['body' => 'Menyebarkannya kepada rekan-rekan agar mereka berhati-hati terhadap isu tersebut', 'score' => 1],
            'C' => ['body' => 'Membiarkannya karena satu unggahan tidak akan berpengaruh pada keadaan', 'score' => 3],
            'D' => ['body' => 'Tidak menyebarkannya dan melaporkan unggahan itu melalui kanal pengaduan konten yang tersedia', 'score' => 4],
            'E' => ['body' => 'Memeriksa kebenaran isinya, tidak menyebarkannya, dan menyampaikan penjelasan berbasis data di lingkungan terdekat Anda', 'score' => 5],
        ],
        'explanation' => 'Bobot 5 melawan provokasi dengan keterangan yang berdasar, bukan dengan kemarahan yang justru menyebarkan jangkauannya. Melaporkan lewat kanal yang tersedia (4) benar, tetapi orang di sekitar Anda tetap belum memperoleh penjelasan apa pun.',
    ],
    [
        'code' => 'TKP-042',
        'subcategory' => 'anti_radikalisme',
        'stem' => 'Sebuah kelompok mengadakan kegiatan di lingkungan Anda dan menyampaikan pandangan bahwa hanya kelompoknya yang paling benar serta mengajak peserta menjauh dari kelompok lain.',
        'options' => [
            'A' => ['body' => 'Menghentikan kegiatan itu dengan cara mendatangi lokasinya dan meminta peserta membubarkan diri', 'score' => 1],
            'B' => ['body' => 'Melarang keluarga Anda ikut serta, dan tidak mencampuri kegiatan warga lain', 'score' => 2],
            'C' => ['body' => 'Menyerahkan seluruh persoalan kepada aparat keamanan tanpa membahasnya bersama pengurus lingkungan', 'score' => 3],
            'D' => ['body' => 'Mengajak pengurus lingkungan membahas kegiatan itu, menyampaikan keberatan atas pandangannya, dan mendorong kegiatan yang terbuka bagi semua warga', 'score' => 5],
            'E' => ['body' => 'Menyampaikan keberatan kepada pengurus lingkungan dan mendorong pembahasan tentang kegiatan yang inklusif bagi warga', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 bekerja melalui pengurus lingkungan — pihak yang memang berhak menata kegiatan warga — dan mengusulkan bentuk kegiatan yang menyatukan, bukan sekadar melarang. Menyampaikan keberatan tanpa mendorong kegiatan pengganti (4) meninggalkan kekosongan yang akan diisi kembali oleh kelompok yang sama.',
    ],
    [
        'code' => 'TKP-043',
        'subcategory' => 'anti_radikalisme',
        'stem' => 'Seorang remaja di lingkungan Anda mulai sering menyampaikan pandangan yang mengagungkan kekerasan untuk memperjuangkan keyakinan dan menjauh dari keluarganya.',
        'options' => [
            'A' => ['body' => 'Melaporkannya kepada aparat agar segera ditangani sebagai ancaman keamanan', 'score' => 3],
            'B' => ['body' => 'Menasihatinya sekali kemudian menjauh karena itu urusan keluarganya', 'score' => 2],
            'C' => ['body' => 'Mendekatinya dengan cara yang tidak menghakimi, mengajaknya berdialog bersama keluarganya, dan menghubungkan keluarga dengan lembaga yang berkompeten mendampingi', 'score' => 5],
            'D' => ['body' => 'Menyampaikan kepada warga lain agar mereka mewaspadai remaja tersebut', 'score' => 1],
            'E' => ['body' => 'Mengajaknya berdialog dan menyampaikan kepada keluarganya agar mendampingi serta mengarahkannya pada kegiatan yang lebih positif', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 memadukan pendekatan pribadi yang tidak menghakimi dengan pendampingan yang benar-benar berkompeten — pada pandangan yang mengagungkan kekerasan, keluarga saja biasanya tidak cukup. Mendekati keluarga (4) adalah langkah pertama yang tepat, tetapi belum menambahkan bantuan ahli.',
    ],
    [
        'code' => 'TKP-044',
        'subcategory' => 'anti_radikalisme',
        'stem' => 'Menjelang pemilihan umum, Anda ditawari menjadi bagian tim pemenangan salah satu calon dengan imbalan tambahan penghasilan.',
        'options' => [
            'A' => ['body' => 'Menolak tawaran itu dan menjelaskan bahwa ASN wajib menjaga netralitas serta tidak boleh berpihak', 'score' => 5],
            'B' => ['body' => 'Menerima tawaran itu karena dilakukan di luar jam kerja dan tidak mengganggu pekerjaan', 'score' => 1],
            'C' => ['body' => 'Menolaknya tanpa menjelaskan alasannya kepada penawar', 'score' => 3],
            'D' => ['body' => 'Menerima tawaran itu tetapi tidak memakai atribut atau fasilitas instansi', 'score' => 2],
            'E' => ['body' => 'Menolak tawaran itu, menjelaskan kewajiban netralitas, dan menyampaikan tawaran tersebut kepada atasan', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menolak sekaligus menegaskan aturannya sehingga penawar tidak mengulanginya kepada pegawai lain. Melaporkan kepada atasan (4) keliru dijadikan bobot tertinggi di sini karena tawaran itu datang dari luar dan belum tentu perlu dicatat sebagai dugaan pelanggaran — menegaskan aturannya lebih dahulu yang menutup jalan.',
    ],
    [
        'code' => 'TKP-045',
        'subcategory' => 'anti_radikalisme',
        'stem' => 'Di lingkungan tempat tinggal Anda muncul kesepakatan tidak tertulis untuk mempersulit warga pendatang yang berbeda latar belakang budaya.',
        'options' => [
            'A' => ['body' => 'Mengikuti kesepakatan itu karena mayoritas warga menghendakinya', 'score' => 2],
            'B' => ['body' => 'Menyampaikan keberatan dalam pertemuan warga, menjelaskan bahwa warga pendatang berhak mendapat perlakuan yang sama, dan mengusulkan kesepakatan yang adil bagi semua', 'score' => 5],
            'C' => ['body' => 'Tidak mengikuti kesepakatan itu, tetapi juga tidak menyampaikan keberatan dalam pertemuan warga', 'score' => 3],
            'D' => ['body' => 'Memberi tahu warga pendatang agar mencari tempat tinggal lain yang lebih menerima mereka', 'score' => 1],
            'E' => ['body' => 'Menyampaikan keberatan kepada pengurus lingkungan dan mendorong pertemuan warga untuk membahasnya kembali', 'score' => 4],
        ],
        'explanation' => 'Bobot 5 menyampaikan keberatan di ruang tempat kesepakatan itu dibuat dan menawarkan penggantinya, bukan hanya menolak di belakang. Menyampaikan keberatan melalui pengurus (4) tetap benar, tetapi suara pada pertemuan warga yang membuat kesepakatan tidak tertulis itu berubah arah.',
    ],
];
