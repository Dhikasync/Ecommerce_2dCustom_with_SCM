<?php

/*
|--------------------------------------------------------------------------
| Pesan Validasi
|--------------------------------------------------------------------------
|
| Pesan bawaan Laravel berbahasa Inggris. Aplikasi ini memakai
| APP_LOCALE=id (config/app.php), jadi seluruh pesan yang mungkin muncul
| diterjemahkan di sini supaya tidak ada pesan Inggris yang bocor ke User.
|
| Daftar key mengikuti vendor/laravel/framework/src/Illuminate/Translation/
| lang/en/validation.php. Kalau Laravel menambah key baru, pesan yang belum
| diterjemahkan akan jatuh ke bahasa Inggris sampai key-nya ditambahkan.
|
| `custom` dipakai untuk pesan yang ditetapkan dokumen (User Story v1.1.4):
| FR-AUTH-02 #2-#5, FR-PROF-01 #3, dan format gambar NFR-FIL-01. Pesan di
| sini berlaku untuk semua endpoint, sehingga pesan yang sama tidak perlu
| ditulis ulang di setiap controller.
|
*/

return [

    'accepted' => ':attribute harus disetujui',
    'accepted_if' => ':attribute harus disetujui ketika :other bernilai :value',
    'active_url' => ':attribute harus berupa URL yang valid',
    'after' => ':attribute harus berupa tanggal setelah :date',
    'after_or_equal' => ':attribute harus berupa tanggal setelah atau sama dengan :date',
    'alpha' => ':attribute hanya boleh berisi huruf',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah',
    'alpha_num' => ':attribute hanya boleh berisi huruf dan angka',
    'any_of' => ':attribute tidak valid',
    'array' => ':attribute harus berupa larik',
    'array_keys' => ':attribute hanya boleh berisi key berikut: :values',
    'ascii' => ':attribute hanya boleh berisi karakter alfanumerik dan simbol satu byte',
    'base64' => ':attribute harus berupa string Base64 yang valid',
    'before' => ':attribute harus berupa tanggal sebelum :date',
    'before_or_equal' => ':attribute harus berupa tanggal sebelum atau sama dengan :date',
    'between' => [
        'array' => ':attribute harus berisi antara :min sampai :max item',
        'file' => ':attribute harus berukuran antara :min sampai :max kilobyte',
        'numeric' => ':attribute harus bernilai antara :min sampai :max',
        'string' => ':attribute harus berisi antara :min sampai :max karakter',
    ],
    'boolean' => ':attribute harus bernilai benar atau salah',
    'can' => ':attribute berisi nilai yang tidak diizinkan',
    'confirmed' => 'Konfirmasi :attribute tidak sesuai',
    'contains' => ':attribute kekurangan nilai yang wajib diisi',
    'current_password' => 'Password salah',
    'date' => ':attribute harus berupa tanggal yang valid',
    'date_equals' => ':attribute harus berupa tanggal yang sama dengan :date',
    'date_format' => ':attribute harus sesuai format :format',
    'decimal' => ':attribute harus memiliki :decimal angka desimal',
    'declined' => ':attribute harus ditolak',
    'declined_if' => ':attribute harus ditolak ketika :other bernilai :value',
    'different' => ':attribute dan :other harus berbeda',
    'digits' => ':attribute harus terdiri dari :digits angka',
    'digits_between' => ':attribute harus terdiri dari antara :min sampai :max angka',
    'dimensions' => ':attribute memiliki dimensi gambar yang tidak valid',
    'distinct' => ':attribute memiliki nilai yang duplikat',
    'doesnt_contain' => ':attribute tidak boleh berisi salah satu dari: :values',
    'doesnt_end_with' => ':attribute tidak boleh diakhiri dengan salah satu dari: :values',
    'doesnt_start_with' => ':attribute tidak boleh diawali dengan salah satu dari: :values',
    'email' => 'Format :attribute tidak valid',
    'encoding' => ':attribute harus dienkode dengan :encoding',
    'ends_with' => ':attribute harus diakhiri dengan salah satu dari: :values',
    'enum' => ':attribute yang dipilih tidak valid',
    'exists' => ':attribute yang dipilih tidak valid',
    'extensions' => ':attribute harus memiliki salah satu ekstensi berikut: :values',
    'file' => ':attribute harus berupa berkas',
    'filled' => ':attribute wajib memiliki nilai',
    'gt' => [
        'array' => ':attribute harus berisi lebih dari :value item',
        'file' => ':attribute harus lebih besar dari :value kilobyte',
        'numeric' => ':attribute harus lebih besar dari :value',
        'string' => ':attribute harus lebih panjang dari :value karakter',
    ],
    'gte' => [
        'array' => ':attribute harus berisi :value item atau lebih',
        'file' => ':attribute harus lebih besar dari atau sama dengan :value kilobyte',
        'numeric' => ':attribute harus lebih besar dari atau sama dengan :value',
        'string' => ':attribute harus lebih panjang dari atau sama dengan :value karakter',
    ],
    'hex_color' => ':attribute harus berupa warna heksadesimal yang valid',
    'image' => ':attribute harus berupa gambar',
    'in' => ':attribute yang dipilih tidak valid',
    'in_array' => ':attribute harus ada di dalam :other',
    'in_array_keys' => ':attribute harus berisi setidaknya salah satu key berikut: :values',
    'integer' => ':attribute harus berupa bilangan bulat',
    'ip' => ':attribute harus berupa alamat IP yang valid',
    'ipv4' => ':attribute harus berupa alamat IPv4 yang valid',
    'ipv6' => ':attribute harus berupa alamat IPv6 yang valid',
    'json' => ':attribute harus berupa string JSON yang valid',
    'list' => ':attribute harus berupa daftar',
    'lowercase' => ':attribute harus berupa huruf kecil',
    'lt' => [
        'array' => ':attribute harus berisi kurang dari :value item',
        'file' => ':attribute harus lebih kecil dari :value kilobyte',
        'numeric' => ':attribute harus lebih kecil dari :value',
        'string' => ':attribute harus lebih pendek dari :value karakter',
    ],
    'lte' => [
        'array' => ':attribute tidak boleh berisi lebih dari :value item',
        'file' => ':attribute harus lebih kecil dari atau sama dengan :value kilobyte',
        'numeric' => ':attribute harus lebih kecil dari atau sama dengan :value',
        'string' => ':attribute harus lebih pendek dari atau sama dengan :value karakter',
    ],
    'mac_address' => ':attribute harus berupa alamat MAC yang valid',
    'max' => [
        'array' => ':attribute tidak boleh berisi lebih dari :max item',
        'file' => ':attribute tidak boleh lebih besar dari :max kilobyte',
        'numeric' => ':attribute tidak boleh lebih besar dari :max',
        'string' => ':attribute tidak boleh lebih panjang dari :max karakter',
    ],
    'max_digits' => ':attribute tidak boleh lebih dari :max angka',
    'mimes' => ':attribute harus berupa berkas bertipe: :values',
    'mimetypes' => ':attribute harus berupa berkas bertipe: :values',
    'min' => [
        'array' => ':attribute harus berisi setidaknya :min item',
        'file' => ':attribute harus berukuran setidaknya :min kilobyte',
        'numeric' => ':attribute harus bernilai setidaknya :min',
        'string' => ':attribute harus berisi setidaknya :min karakter',
    ],
    'min_digits' => ':attribute harus terdiri dari setidaknya :min angka',
    'missing' => ':attribute tidak boleh ada',
    'missing_if' => ':attribute tidak boleh ada ketika :other bernilai :value',
    'missing_unless' => ':attribute tidak boleh ada kecuali :other bernilai :value',
    'missing_with' => ':attribute tidak boleh ada ketika :values terisi',
    'missing_with_all' => ':attribute tidak boleh ada ketika :values terisi',
    'multiple_of' => ':attribute harus merupakan kelipatan dari :value',
    'not_in' => ':attribute yang dipilih tidak valid',
    'not_regex' => 'Format :attribute tidak valid',
    'numeric' => ':attribute harus berupa angka',
    'password' => [
        'letters' => ':attribute harus berisi setidaknya satu huruf',
        'mixed' => ':attribute harus berisi setidaknya satu huruf besar dan satu huruf kecil',
        'numbers' => ':attribute harus berisi setidaknya satu angka',
        'symbols' => ':attribute harus berisi setidaknya satu simbol',
        'uncompromised' => ':attribute yang diberikan pernah muncul dalam kebocoran data. Pilih :attribute lain',
    ],
    'present' => ':attribute harus ada',
    'present_if' => ':attribute harus ada ketika :other bernilai :value',
    'present_unless' => ':attribute harus ada kecuali :other bernilai :value',
    'present_with' => ':attribute harus ada ketika :values terisi',
    'present_with_all' => ':attribute harus ada ketika :values terisi',
    'prohibited' => ':attribute tidak diizinkan',
    'prohibited_if' => ':attribute tidak diizinkan ketika :other bernilai :value',
    'prohibited_if_accepted' => ':attribute tidak diizinkan ketika :other disetujui',
    'prohibited_if_declined' => ':attribute tidak diizinkan ketika :other ditolak',
    'prohibited_unless' => ':attribute tidak diizinkan kecuali :other bernilai :values',
    'prohibits' => ':attribute membuat :other tidak boleh ada',
    'regex' => 'Format :attribute tidak valid',
    'required' => ':attribute wajib diisi',
    'required_array_keys' => ':attribute harus berisi entri untuk: :values',
    'required_if' => ':attribute wajib diisi ketika :other bernilai :value',
    'required_if_accepted' => ':attribute wajib diisi ketika :other disetujui',
    'required_if_declined' => ':attribute wajib diisi ketika :other ditolak',
    'required_unless' => ':attribute wajib diisi kecuali :other bernilai :values',
    'required_with' => ':attribute wajib diisi ketika :values terisi',
    'required_with_all' => ':attribute wajib diisi ketika :values terisi',
    'required_without' => ':attribute wajib diisi ketika :values tidak terisi',
    'required_without_all' => ':attribute wajib diisi ketika tidak ada satu pun dari :values yang terisi',
    'same' => ':attribute harus sama dengan :other',
    'size' => [
        'array' => ':attribute harus berisi :size item',
        'file' => ':attribute harus berukuran :size kilobyte',
        'numeric' => ':attribute harus bernilai :size',
        'string' => ':attribute harus berisi :size karakter',
    ],
    'starts_with' => ':attribute harus diawali dengan salah satu dari: :values',
    'string' => ':attribute harus berupa teks',
    'timezone' => ':attribute harus berupa zona waktu yang valid',
    'unique' => ':attribute sudah digunakan',
    'uploaded' => ':attribute gagal diunggah',
    'uppercase' => ':attribute harus berupa huruf besar',
    'url' => ':attribute harus berupa URL yang valid',
    'ulid' => ':attribute harus berupa ULID yang valid',
    'uuid' => ':attribute harus berupa UUID yang valid',

    /*
    |--------------------------------------------------------------------------
    | Pesan Khusus Per Field
    |--------------------------------------------------------------------------
    |
    | Pesan di sini menimpa pesan umum di atas untuk field tertentu. Semua
    | pesan yang ditetapkan dokumen dikumpulkan di sini supaya cukup diubah
    | di satu tempat.
    |
    */

    'custom' => [
        'email' => [
            // User Story FR-AUTH-02 #4 dan FR-PROF-01 #8.
            'email' => 'Format email tidak valid',
            // User Story FR-AUTH-02 #2 dan FR-PROF-01 #8.
            'unique' => 'Email sudah digunakan',
        ],
        'password' => [
            // User Story FR-AUTH-02 #3.
            'confirmed' => 'Konfirmasi password tidak sesuai',
            // User Story FR-AUTH-02 #5, batas minimum ditetapkan 8 karakter.
            'min' => 'Password minimal 8 karakter',
        ],
        // FR-PROD-01 #9 / NFR-FIL-01: gambar produk JPG/PNG maksimal 2 MB.
        // Tanda * membuat pesan berlaku untuk setiap berkas dalam larik
        // `gambar`, bukan hanya berkas pertama.
        //
        // Sengaja TIDAK ada `gambar.*.file` di sini. Untuk aturan ukuran
        // (max/min/size), Laravel menambahkan tipe nilai di akhir key
        // (`gambar.0.max.file`), dan wildcard `gambar.*.file` ikut menangkap
        // pencarian itu sehingga pesan `max` berubah menjadi pesan `file`.
        // Pesan untuk aturan `file` sudah sama dari `validation.file` +
        // attributes.
        'gambar.*' => [
            'mimes' => 'Format gambar harus JPG atau PNG',
            'max' => 'Ukuran gambar maksimal 2 MB',
            'uploaded' => 'Gambar gagal diunggah',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama Field yang Ditampilkan
    |--------------------------------------------------------------------------
    |
    | Tanpa daftar ini, pesan akan menyebut nama kolom mentah seperti
    | "category_id" atau "no_hp". Nama di sini mengikuti istilah yang dipakai
    | dokumen dan formulir.
    |
    */

    'attributes' => [
        'name' => 'Nama',
        'email' => 'Email',
        'password' => 'Password',
        'password_lama' => 'Password lama',
        'password_confirmation' => 'Konfirmasi password',
        'no_hp' => 'Nomor telepon',
        'nama_penerima' => 'Nama penerima',
        'alamat_lengkap' => 'Alamat lengkap',
        'kode_lokasi' => 'Lokasi',
        'label_lokasi' => 'Lokasi',
        'category_id' => 'Kategori',
        'nama' => 'Nama',
        'deskripsi' => 'Deskripsi',
        'tipe' => 'Tipe produk',
        'harga_dasar' => 'Harga dasar',
        'is_aktif' => 'Status aktif',
        'gambar' => 'Gambar',
        'gambar.*' => 'Gambar',
        'sku' => 'Kode SKU',
        'ukuran' => 'Ukuran',
        'warna' => 'Warna',
        'ketebalan' => 'Ketebalan',
        'harga' => 'Harga',
        'berat' => 'Berat',
        'moq' => 'MOQ',
        'token' => 'Token',
    ],

];
