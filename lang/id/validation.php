<?php

/*
|--------------------------------------------------------------------------
| Baris Bahasa Validasi
|--------------------------------------------------------------------------
|
| Terjemahan Indonesia dari pesan validasi bawaan Laravel. Berkas ini ada supaya
| setiap pesan @error yang tampil di aplikasi berbahasa Indonesia, bukan pesan
| bawaan berbahasa Inggris. Jumlah kuncinya sengaja dibuat sama persis dengan
| berkas bawaan framework; sebuah kunci yang hilang akan jatuh kembali ke bahasa
| Inggris lewat APP_FALLBACK_LOCALE, dan itu justru yang ingin dihindari.
|
| Atribut (:attribute) diterjemahkan di bagian "attributes" di bawah, memakai
| kata yang sama dengan label di tampilan. FormRequest tidak menamai kolomnya
| sendiri — tidak satu pun kelas di app/Http/Requests/ mendefinisikan
| attributes() — jadi tanpa daftar itu pesannya kembali menyebut nama kolom
| mentah ("name wajib diisi") di halaman yang seluruhnya berbahasa Indonesia.
|
*/

return [

    'accepted' => ':attribute harus disetujui.',
    'accepted_if' => ':attribute harus disetujui ketika :other bernilai :value.',
    'active_url' => ':attribute bukan URL yang valid.',
    'after' => ':attribute harus berisi tanggal setelah :date.',
    'after_or_equal' => ':attribute harus berisi tanggal setelah atau sama dengan :date.',
    'alpha' => ':attribute hanya boleh berisi huruf.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => ':attribute hanya boleh berisi huruf dan angka.',
    'any_of' => ':attribute tidak valid.',
    'array' => ':attribute harus berupa array.',
    'array_keys' => ':attribute hanya boleh berisi kunci berikut: :values.',
    'ascii' => ':attribute hanya boleh berisi karakter alfanumerik dan simbol satu byte.',
    'base64' => ':attribute harus berupa string Base64 yang valid.',
    'before' => ':attribute harus berisi tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus berisi tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => ':attribute harus memiliki antara :min sampai :max item.',
        'file' => ':attribute harus berukuran antara :min sampai :max kilobyte.',
        'numeric' => ':attribute harus bernilai antara :min sampai :max.',
        'string' => ':attribute harus terdiri antara :min sampai :max karakter.',
    ],
    'boolean' => ':attribute harus bernilai benar atau salah.',
    'can' => ':attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'contains' => ':attribute tidak memiliki nilai yang wajib ada.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_equals' => ':attribute harus berisi tanggal yang sama dengan :date.',
    'date_format' => ':attribute tidak cocok dengan format :format.',
    'decimal' => ':attribute harus memiliki :decimal angka di belakang koma.',
    'declined' => ':attribute harus ditolak.',
    'declined_if' => ':attribute harus ditolak ketika :other bernilai :value.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus terdiri dari :digits digit.',
    'digits_between' => ':attribute harus terdiri antara :min sampai :max digit.',
    'dimensions' => ':attribute memiliki dimensi gambar yang tidak valid.',
    'distinct' => ':attribute memiliki nilai yang duplikat.',
    'doesnt_contain' => ':attribute tidak boleh berisi salah satu dari: :values.',
    'doesnt_end_with' => ':attribute tidak boleh berakhir dengan salah satu dari: :values.',
    'doesnt_start_with' => ':attribute tidak boleh diawali dengan salah satu dari: :values.',
    'email' => ':attribute harus berupa alamat surel yang valid.',
    'encoding' => ':attribute harus dienkode dengan :encoding.',
    'ends_with' => ':attribute harus berakhir dengan salah satu dari: :values.',
    'enum' => ':attribute yang dipilih tidak valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'extensions' => ':attribute harus memiliki salah satu ekstensi berikut: :values.',
    'file' => ':attribute harus berupa berkas.',
    'filled' => ':attribute wajib memiliki nilai.',
    'gt' => [
        'array' => ':attribute harus memiliki lebih dari :value item.',
        'file' => ':attribute harus lebih besar dari :value kilobyte.',
        'numeric' => ':attribute harus lebih besar dari :value.',
        'string' => ':attribute harus lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => ':attribute harus memiliki :value item atau lebih.',
        'file' => ':attribute harus lebih besar dari atau sama dengan :value kilobyte.',
        'numeric' => ':attribute harus lebih besar dari atau sama dengan :value.',
        'string' => ':attribute harus lebih dari atau sama dengan :value karakter.',
    ],
    'hex_color' => ':attribute harus berupa warna heksadesimal yang valid.',
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'in_array' => ':attribute harus ada di dalam :other.',
    'in_array_keys' => ':attribute harus berisi setidaknya salah satu kunci berikut: :values.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'ip' => ':attribute harus berupa alamat IP yang valid.',
    'ipv4' => ':attribute harus berupa alamat IPv4 yang valid.',
    'ipv6' => ':attribute harus berupa alamat IPv6 yang valid.',
    'json' => ':attribute harus berupa string JSON yang valid.',
    'list' => ':attribute harus berupa daftar.',
    'lowercase' => ':attribute harus berupa huruf kecil.',
    'lt' => [
        'array' => ':attribute harus memiliki kurang dari :value item.',
        'file' => ':attribute harus lebih kecil dari :value kilobyte.',
        'numeric' => ':attribute harus lebih kecil dari :value.',
        'string' => ':attribute harus kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => ':attribute tidak boleh memiliki lebih dari :value item.',
        'file' => ':attribute harus lebih kecil dari atau sama dengan :value kilobyte.',
        'numeric' => ':attribute harus lebih kecil dari atau sama dengan :value.',
        'string' => ':attribute harus kurang dari atau sama dengan :value karakter.',
    ],
    'mac_address' => ':attribute harus berupa alamat MAC yang valid.',
    'max' => [
        'array' => ':attribute tidak boleh memiliki lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih besar dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih besar dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'max_digits' => ':attribute tidak boleh lebih dari :max digit.',
    'mimes' => ':attribute harus berupa berkas berjenis: :values.',
    'mimetypes' => ':attribute harus berupa berkas berjenis: :values.',
    'min' => [
        'array' => ':attribute harus memiliki setidaknya :min item.',
        'file' => ':attribute harus berukuran setidaknya :min kilobyte.',
        'numeric' => ':attribute harus bernilai setidaknya :min.',
        'string' => ':attribute harus terdiri setidaknya :min karakter.',
    ],
    'min_digits' => ':attribute harus memiliki setidaknya :min digit.',
    'missing' => ':attribute harus tidak ada.',
    'missing_if' => ':attribute harus tidak ada ketika :other bernilai :value.',
    'missing_unless' => ':attribute harus tidak ada kecuali :other bernilai :value.',
    'missing_with' => ':attribute harus tidak ada ketika :values ada.',
    'missing_with_all' => ':attribute harus tidak ada ketika :values ada.',
    'multiple_of' => ':attribute harus merupakan kelipatan dari :value.',
    'not_in' => ':attribute yang dipilih tidak valid.',
    'not_regex' => 'Format :attribute tidak valid.',
    'numeric' => ':attribute harus berupa angka.',
    'password' => [
        'letters' => ':attribute harus berisi setidaknya satu huruf.',
        'mixed' => ':attribute harus berisi setidaknya satu huruf besar dan satu huruf kecil.',
        'numbers' => ':attribute harus berisi setidaknya satu angka.',
        'symbols' => ':attribute harus berisi setidaknya satu simbol.',
        'uncompromised' => ':attribute yang diberikan pernah bocor dalam kebocoran data. Silakan pilih :attribute yang lain.',
    ],
    'present' => ':attribute harus ada.',
    'present_if' => ':attribute harus ada ketika :other bernilai :value.',
    'present_unless' => ':attribute harus ada kecuali :other bernilai :value.',
    'present_with' => ':attribute harus ada ketika :values ada.',
    'present_with_all' => ':attribute harus ada ketika :values ada.',
    'prohibited' => ':attribute dilarang.',
    'prohibited_if' => ':attribute dilarang ketika :other bernilai :value.',
    'prohibited_if_accepted' => ':attribute dilarang ketika :other disetujui.',
    'prohibited_if_declined' => ':attribute dilarang ketika :other ditolak.',
    'prohibited_unless' => ':attribute dilarang kecuali :other bernilai salah satu dari: :values.',
    'prohibits' => ':attribute melarang :other untuk ada.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_array_keys' => ':attribute harus berisi entri untuk: :values.',
    'required_if' => ':attribute wajib diisi ketika :other bernilai :value.',
    'required_if_accepted' => ':attribute wajib diisi ketika :other disetujui.',
    'required_if_declined' => ':attribute wajib diisi ketika :other ditolak.',
    'required_unless' => ':attribute wajib diisi kecuali :other bernilai salah satu dari: :values.',
    'required_with' => ':attribute wajib diisi ketika :values ada.',
    'required_with_all' => ':attribute wajib diisi ketika :values ada.',
    'required_without' => ':attribute wajib diisi ketika :values tidak ada.',
    'required_without_all' => ':attribute wajib diisi ketika tidak ada satu pun dari :values yang ada.',
    'same' => ':attribute harus sama dengan :other.',
    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'file' => ':attribute harus berukuran :size kilobyte.',
        'numeric' => ':attribute harus bernilai :size.',
        'string' => ':attribute harus terdiri dari :size karakter.',
    ],
    'starts_with' => ':attribute harus diawali dengan salah satu dari: :values.',
    'string' => ':attribute harus berupa string.',
    'timezone' => ':attribute harus berupa zona waktu yang valid.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => ':attribute gagal diunggah.',
    'uppercase' => ':attribute harus berupa huruf besar.',
    'url' => ':attribute harus berupa URL yang valid.',
    'ulid' => ':attribute harus berupa ULID yang valid.',
    'uuid' => ':attribute harus berupa UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Baris Validasi Kustom
    |--------------------------------------------------------------------------
    |
    | Tempat untuk pesan khusus per atribut memakai konvensi "attribute.rule".
    | Aplikasi ini tidak memakai pola tersebut: pesan khusus ditulis langsung di
    | masing-masing aturan, tempat pembacanya menemukannya.
    |
    */

    'custom' => [],

    /*
    |--------------------------------------------------------------------------
    | Nama Atribut Kustom
    |--------------------------------------------------------------------------
    |
    | Nama tampil untuk setiap kolom yang divalidasi, memakai kata yang sudah
    | dipakai tampilan supaya pesannya berbunyi seperti halaman yang memuatnya:
    | kolom `name` di form pendaftaran berlabel "Nama lengkap", jadi pesannya
    | "Nama lengkap wajib diisi." — bukan "name wajib diisi.".
    |
    | Daftar ini memang harus ada di sini dan tidak bisa diserahkan kepada
    | FormRequest: tidak satu pun kelas di app/Http/Requests/ mendefinisikan
    | attributes(), dan tiga di antaranya sengaja memakai nama lain (payload())
    | justru karena attributes() adalah hook milik framework, bukan wadah bebas.
    |
    */

    'attributes' => [
        // resources/views/auth/*.blade.php
        'name' => 'Nama lengkap',
        'email' => 'Email',
        'password' => 'Password',
        // Label di form pendaftaran; form ganti password menyebut kolom yang sama
        // "Ulangi password baru", tapi hanya satu nama yang bisa dipakai di sini.
        'password_confirmation' => 'Ulangi password',
        'current_password' => 'Password saat ini',

        // resources/views/admin/questions/form.blade.php
        'category' => 'Subtes',
        'subcategory' => 'Submateri',
        'stem' => 'Teks soal',
        'explanation' => 'Pembahasan',
        'is_active' => 'Status',
        'options' => 'Opsi',
        // Laravel mencocokkan pola bertanda bintang ini dengan tiap huruf opsi
        // (`options.A.body`, `options.B.body`, ...) dan memakai nilainya apa adanya.
        'options.*.body' => 'Isi opsi',
        'options.*.score' => 'Bobot opsi',
        'key' => 'Kunci jawaban',

        // resources/views/admin/packages/*.blade.php
        'title' => 'Judul',
        'slug' => 'Slug',
        'description' => 'Deskripsi',
        'duration_minutes' => 'Durasi (menit)',
        // Penyusun paket, dan endpoint penyimpanan jawaban ujian.
        'question_id' => 'Soal',

        // resources/views/admin/import/index.blade.php; kata sambung label
        // unggahannya ("Atau ...") dibuang supaya kalimat pesannya terbaca.
        'payload' => 'Tempel di sini',
        'file' => 'Berkas CSV',

        // resources/views/admin/users/index.blade.php
        'role' => 'Peran',
    ],

];
