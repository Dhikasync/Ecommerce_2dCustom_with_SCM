# Nichibag — Marketplace Toko Nichibag

Backend marketplace untuk **Toko Nichibag**: penjualan tas dengan **desain kustom 2D** dan **integrasi rantai pasok** (bahan baku, distributor, purchase order, penerimaan barang).

Proyek ini adalah bagian dari tugas akhir dan dibangun bertahap mengikuti **SRS v1.1** dan **User Story v1.1.4**. Saat ini fokusnya backend JSON; antarmuka belum dibangun.

<p>
  <img alt="PHP" src="https://img.shields.io/badge/PHP-%5E8.3-777BB4?logo=php&logoColor=white">
  <img alt="Laravel" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white">
  <img alt="PostgreSQL" src="https://img.shields.io/badge/PostgreSQL-18-4169E1?logo=postgresql&logoColor=white">
  <img alt="CI" src="https://github.com/Dhikasync/Ecommerce_2dCustom_with_SCM/actions/workflows/tests.yml/badge.svg">
  <img alt="Versi" src="https://img.shields.io/badge/versi-v0.3.0-blue">
</p>

## Status

| Modul | Kode | Status |
| --- | --- | --- |
| Autentikasi | FR-AUTH | ✅ lengkap: login Admin/User, registrasi, verifikasi email, ganti email dari profil, lupa/reset password, logout, middleware role |
| Produk (admin) | FR-PROD | ✅ sisi admin: produk + gambar, varian (SKU/harga/berat/MOQ), kategori. Penegakan MOQ saat tambah ke keranjang sudah berjalan di FR-CART-01 |
| Katalog produk | FR-KTLG | ✅ semua skenario: daftar produk (FR-KTLG-01), detail produk (FR-KTLG-02), filter kategori/jenis + pencarian + urut harga + reset filter (FR-KTLG-03) |
| Keranjang & checkout | FR-CART, FR-CKOT | 🔄 berjalan: FR-CART-01 kelola keranjang, FR-CART-02 ringkasan (subtotal, label "Stok tidak mencukupi"/"Produk tidak tersedia", `bisa_checkout`), FR-CKOT-04 kurir + alamat asal toko, FR-CKOT-01 checkout (`GET /checkout`, `POST /checkout/ongkir`, `POST /checkout`: reservasi stok, pesanan Menunggu Pembayaran/Menunggu Validasi, salinan alamat). Sisi custom sudah mengikuti FR-CDSN-04: item custom hanya diterima bila desainnya punya `preview_gambar` (**hanya dari Preview Desain**) |
| Pesanan & pembayaran | FR-ORDR, FR-PAYM | 🔄 berjalan: pesanan sudah dibentuk lewat FR-CKOT-01 (status, salinan alamat, reservasi stok). FR-ORDR-01 #1 berjalan — `GET /pesanan` menampilkan daftar pesanan milik User dengan filter sembilan status Lampiran B, masing-masing membawa status pesanan, status pembayaran, dan status desain. FR-ORDR-01 #2 berjalan — `GET /pesanan/{nomor}` menampilkan detail terpisah: tiga domain status, item dengan salinan nama/harga saat pesanan dibentuk, alamat salinan checkout, kurir, dan riwayat status beserta pelakunya. FR-ORDR-01 #3 berjalan — setiap perubahan status pembayaran (`lunas`, `gagal`, `kedaluwarsa`) dikabarkan lewat notifikasi in-app + email; perubahan status desain sudah dikabarkan lewat FR-CDSN-05 #7 (notifikasi revisi beserta catatan dan tautan editor); perubahan oleh Admin menyusul bersama FR-ORDR-03. FR-PAYM-01 #1 berjalan — `POST /pesanan/{nomor}/pembayaran` membuat transaksi Snap Midtrans untuk pesanan produk jadi. FR-PAYM-01 #5 berjalan — `POST /pesanan/{nomor}/pembayaran/cek` menyamakan status pembayaran dan pesanan dengan jawaban gateway, termasuk konversi Stok Direservasi saat Lunas. FR-PAYM-01 #2/#3/#4 berjalan — `POST /pembayaran/notifikasi` memverifikasi tanda tangan Midtrans, memproses tiap notifikasi sekali, lalu mengabarkan Lunas/kedaluwarsa lewat notifikasi in-app + email. FR-PAYM-02 #1 berjalan — approval desain mengirim notifikasi + email berisi tautan halaman pembayaran. FR-PAYM-02 #2-#4 juga terverifikasi khusus pesanan custom (bayar lewat gateway, kedaluwarsa link, desain terkunci setelah Lunas). FR-ORDR-03 #5 berjalan — `POST /admin/pesanan/{nomor}/mulai-cetak` memindahkan pesanan custom `diproses` ke `sedang_dicetak`, menulis riwayat dengan pelaku Admin, dan mengabari User. Belum ada: sisa FR-ORDR-03 (#1-#4/#6-#8) dan FR-ORDR-04 |
| Rantai pasok (SCM) | FR-SCM | ⏳ |
| Profil & alamat | FR-PROF | ✅ semua skenario FR-PROF-01: lihat/ubah profil, ganti email, ubah password, cari lokasi, kelola alamat + alamat utama. Di luar skenario itu, `srs.md:378` meminta sistem menolak hapus alamat yang dipakai pesanan berjalan; aturan itu **belum dibangun** karena `AlamatController::hapus` baru memeriksa "minimal satu alamat tersisa", padahal pesanan sudah bisa dibentuk sejak FR-CKOT-01 |
| Dashboard admin | FR-DASH | ⏳ ditunda sampai UI dimulai |
| Proses otomatis berbasis waktu | FR-SYS-01 | 🔄 berjalan: #1 (pembayaran kedaluwarsa) lewat `php artisan orders:kedaluwarsa`, dijadwalkan tiap menit - pesanan yang lewat 24 jam dibatalkan dengan `alasan_pembatalan` tercatat, Stok Direservasi dilepas, pembayaran `belum_dibayar`/`gagal` menjadi `kedaluwarsa`, riwayat status ditulis, dan User diberi kabar email + in-app. #2 (masa revisi desain 3 hari kalender) lewat `php artisan orders:revisi-kedaluwarsa`, juga tiap menit — order `menunggu_revisi_desain` yang lewat batas dibatalkan dengan alasan "Masa revisi desain berakhir", desain jadi `kedaluwarsa`, reservasi dilepas tanpa menyentuh Stok Fisik, riwayat ditulis, dan User dikabari email + in-app. #7 (proses tidak diterapkan ganda) dijaga dengan membaca ulang status di bawah kunci. Belum ada: #3-#6 dan #8 (OPSIONAL) |

Catatan jujur: login Admin sudah berjalan, tetapi **dashboard belum ada**; sementara Admin diarahkan ke halaman utama sesuai `user-story.md:19`. Pada FR-PROF, seluruh skenario FR-PROF-01 sudah berjalan, tetapi satu reaksi sistem di `srs.md:378` — menolak hapus alamat yang dipakai pesanan berjalan — belum dibangun; lihat baris FR-PROF.

## Tumpukan teknologi

| Bagian | Teknologi |
| --- | --- |
| Bahasa | PHP ^8.3 (dikembangkan di PHP 8.5) |
| Framework | Laravel 13 |
| Basis data | PostgreSQL 18 |
| Pengujian | PHPUnit 12 |
| Frontend | Vite 7 + Tailwind 4 (belum dipakai; direncanakan untuk tahap UI) |

Tanpa Breeze/Fortify/Jetstream — autentikasi ditulis manual.

## Struktur penting

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php       # registrasi, verifikasi, login, reset password, logout
│   │   └── Profil/
│   │       ├── ProfilController.php     # lihat/ubah profil, ganti email, ubah password
│   │       ├── AlamatController.php     # kelola alamat + alamat utama
│   │       └── LokasiController.php     # pencarian lokasi (RajaOngkir)
│   │   └── Checkout/
│   │       ├── CheckoutController.php   # FR-CKOT-01: ringkasan, ongkir, buat pesanan
│   │       ├── KurirController.php      # aktif/nonaktif layanan kurir (FR-CKOT-04)
│   │       └── PengaturanPengirimanController.php  # alamat asal toko (FR-CKOT-04)
│   │   └── Pembayaran/
│   │       ├── PembayaranController.php # FR-PAYM-01 #1/#5: transaksi Snap + cek status
│   │       └── NotifikasiController.php # FR-PAYM-01 #2/#3/#4: webhook Midtrans
│   └── Middleware/
│       └── Auth/
│           ├── EnsureUserHasRole.php    # pembatasan akses per role (FR-AUTH-01 #7)
│           └── EnsureUserIsActive.php   # akun Nonaktif ditolak (FR-AUTH-01 #6)
├── Exceptions/
│   ├── Checkout/
│   │   ├── KeranjangBerubah.php         # item bermasalah saat pesanan dibentuk (#7)
│   │   └── OngkirTidakTersedia.php      # ongkir gagal dihitung (#8)
│   └── Profil/RajaOngkirException.php   # kegagalan layanan pencarian lokasi dan tarif
│   └── Pembayaran/MidtransException.php # payment gateway gagal (SI-01)
├── Mail/
│   ├── Auth/
│       ├── VerifyEmailMail.php          # email verifikasi bertoken
│       ├── VerifyNewEmailMail.php       # tautan konfirmasi email baru (FR-AUTH-06 #7)
│       ├── EmailChangeNoticeMail.php    # pemberitahuan ke email lama
│       └── ResetPasswordMail.php        # email reset password bertoken
│   └── Pesanan/
│       └── PembayaranMail.php           # kabar Lunas/kedaluwarsa (FR-PAYM-01 #2/#4)
├── Console/Commands/
│   ├── SinkronLayananKurir.php          # isi layanan kurir dari RajaOngkir (FR-CKOT-04)
│   ├── BatalkanPesananKedaluwarsa.php   # sapu pesanan lewat batas bayar (FR-SYS-01 #1)
│   └── BatalkanMasaRevisi.php            # sapu pesanan lewat batas revisi (FR-SYS-01 #2)
├── Rules/NomorTelepon.php               # format nomor telepon +62 (dipakai auth + profil)
├── Support/
│   ├── BatasPermintaan.php              # balasan 429 Bahasa Indonesia (NFR-LOC-01)
│   ├── GambarPublik.php                 # URL gambar dari disk publik
│   ├── MasalahItemKeranjang.php         # label masalah item, dipakai keranjang + checkout
│   └── RingkasanPesanan.php             # status pesanan/pembayaran/desain, dipakai daftar + checkout (FR-ORDR-01)
├── Services/
│   └── Profil/
│       ├── LokasiDitawarkan.php         # ingatan hasil pencarian lokasi (24 jam)
│       └── RajaOngkir.php               # client RajaOngkir: pencarian lokasi + tarif
│   └── Pembayaran/
│       ├── Midtrans.php                 # client Midtrans: Snap + cek status (SI-01)
│       ├── JumlahMidtrans.php           # baca `gross_amount` sebelum jadi rupiah
│       ├── KabarPembayaran.php          # kabar in-app + email saat Lunas/kedaluwarsa
│       └── SinkronPembayaran.php        # pemetaan status gateway → keadaan lokal (#2–#5)
└── Models/
    ├── Auth/
    │   └── User.php                     # model modul auth (27 model total sesuai skema SRS)
    ├── Produk/
    │   ├── Category.php                 # kategori produk (hapus = restrict)
    │   ├── Product.php                  # produk
    │   ├── ProductImage.php             # gambar produk (urut tampil)
    │   └── Variant.php                  # varian/SKU: stok_fisik + stok_direservasi
    └── Profil/
        └── Address.php                  # alamat pengiriman user

bootstrap/app.php                        # alias middleware + handler JSON untuk tautan kedaluwarsa
database/factories/Auth/UserFactory.php  # factory mengikuti subfolder modelnya
database/migrations/                     # 31 migrasi (tetap flat)
routes/auth.php                          # login, registrasi, verifikasi, reset password
routes/profil.php                        # profil, ganti email, pencarian lokasi
routes/produk.php                        # kelola kategori, produk, dan varian (FR-PROD-01/02/03)
routes/katalog.php                       # katalog, detail, filter (FR-KTLG-01/02/03)
routes/keranjang.php                     # keranjang belanja (FR-CART-01/02)
routes/checkout.php                      # ringkasan, ongkir, buat pesanan (FR-CKOT-01/04)
routes/pembayaran.php                    # transaksi Snap + cek status (FR-PAYM-01 #1/#5)
routes/notifikasi-pembayaran.php         # webhook gateway, di luar grup `web` (FR-PAYM-01 #2/#3/#4)
routes/pesanan.php                       # daftar pesanan User + filter status (FR-ORDR-01 #1)
routes/console.php                       # jadwal `orders:kedaluwarsa` + `orders:revisi-kedaluwarsa` tiap menit (FR-SYS-01 #1/#2)
routes/web.php                           # halaman utama
lang/id/validation.php                   # seluruh pesan validasi Bahasa Indonesia + pesan yang ditetapkan dokumen
tests/Feature/                           # pengujian perilaku per kebutuhan
```

Aturan penataan: file dikelompokkan per modul SRS ke **subfolder di dalam folder Laravel yang sudah ada** (`Controllers/Auth`, `Models/Auth`, `Mail/Auth`, `Middleware/Auth`, `database/factories/Auth`, `lang/id`) - tidak ada folder top-level baru di luar folder standar Laravel. Migrasi sengaja **tetap flat** di `database/migrations/` dan nama filenya tidak pernah diubah, karena tabel `migrations` mencatat nama file yang sudah dijalankan; mengubah nama/timestamp membuat migrasi lama dianggap belum jalan.

## Menjalankan di lokal

Prasyarat: PHP 8.3+, Composer, PostgreSQL 15+, ekstensi `pdo_pgsql`.

```bash
git clone https://github.com/Dhikasync/Ecommerce_2dCustom_with_SCM.git
cd Ecommerce_2dCustom_with_SCM
composer install
copy .env.example .env          # Windows; pakai cp di Linux/macOS
php artisan key:generate
```

Buat dua basis data:

```sql
CREATE DATABASE nichibag_db;
CREATE DATABASE nichibag_test;
```

Sesuaikan `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nichibag_db
DB_USERNAME=postgres
DB_PASSWORD=...
RAJAONGKIR_API_KEY=...          # kosong membuat pencarian lokasi dan ongkir gagal
RAJAONGKIR_BASE_URL=https://rajaongkir.komerce.id/api/v1
```

Lalu:

```bash
php artisan migrate
php artisan storage:link   # agar gambar produk di storage/app/public bisa diakses lewat /storage
php artisan serve          # http://127.0.0.1:8000
```

Urutan penyiapan data awal: akun Admin dibuat di luar aplikasi, alamat asal toko diisi
lewat `PUT /admin/pengaturan-pengiriman`, lalu daftar layanan kurir diisi dari RajaOngkir:

```bash
# Rute contoh antar kota: layanan yang tersimpan adalah yang benar-benar dijual.
php artisan rajaongkir:sinkron-layanan --kurir=jne,pos,tiki --tujuan=17601

# Asal dari pengaturan pengiriman; --asal dipakai kalau ingin kode lain.
php artisan rajaongkir:sinkron-layanan --asal=4816 --tujuan=17601
```

Perintah ini memakai kuota RajaOngkir (satu permintaan per kurir) dan butuh jaringan.
Layanan yang didapat tersimpan sebagai **nonaktif**; Admin memilih mana yang ditawarkan.

Tanpa `--tujuan`, rute contohnya asal ke asal. Rute satu kecamatan hanya menawarkan
layanan dalam kota (`CTC`) dan truk (`JTR`), bukan `REG`/`YES` antar kota, sehingga
perintah memberi peringatan dan hasilnya tidak mewakili layanan yang dijual toko.

Kuota RajaOngkir dihitung **per panggilan HTTP**, bukan per endpoint: pencarian lokasi
(`GET /lokasi`) sama mahalnya dengan hitung tarif (`POST /checkout/ongkir`). Karena itu
batasi `--kurir` ke kurir yang dipakai, dan jangan jalankan sinkronisasi berulang tanpa
alasan — hasilnya idempoten di database, tetapi tetap memotong kuota.

### Scheduler pembatalan otomatis (FR-SYS-01 #1)

Pesanan berstatus **Menunggu Pembayaran** yang melewati `kadaluwarsa_pada` dibatalkan
oleh perintah terjadwal. Di server, jalankan cron Laravel sekali — `* * * * * cd /path/aplikasi && php artisan schedule:run` — karena jadwalnya didefinisikan di
`routes/console.php`, bukan di crontab.

```bash
# Menjalankan pemeriksaan sekali secara manual (juga dipakai test).
php artisan orders:kedaluwarsa
# 2 pesanan dibatalkan karena batas pembayaran lewat.

# Melihat jadwal yang terdaftar.
php artisan schedule:list
```

Efek satu pesanan: status pesanan `dibatalkan` disertai `alasan_pembatalan`
("Batas pembayaran 24 jam terlewat" dari scheduler, "Pembayaran kedaluwarsa di
payment gateway" dari jalur `expire`), baris `payments` yang masih `belum_dibayar`
atau `gagal` menjadi `kedaluwarsa`, Stok Direservasi dilepas (Stok Fisik tidak
disentuh), satu baris riwayat status dengan `diubah_oleh` kosong, dan notifikasi
email + in-app. Pesanan yang sudah Lunas dan pesanan custom yang masih
Menunggu Validasi tidak ikut tersapu.

Di Windows, `php.ini` sering tidak punya CA bundle sehingga panggilan nyata gagal dengan
`cURL error 60: SSL certificate ... unable to get local issuer certificate`. Perbaikannya
di luar repo:

```ini
; php.ini
curl.cainfo = "D:\php\extras\ssl\cacert.pem"
openssl.cafile = "D:\php\extras\ssl\cacert.pem"
```

Berkas CA-nya diunduh dari `https://curl.se/ca/cacert.pem`. Tanpa itu `GET /lokasi` dan
`POST /checkout/ongkir` dijawab `422` walau `RAJAONGKIR_API_KEY` benar.

## Pengujian

```bash
php artisan test
```

Pengujian berjalan di basis data terpisah `nichibag_test` (diatur di `phpunit.xml`), memakai PostgreSQL — bukan SQLite — supaya constraint dan tipe kolom yang dipakai produksi ikut teruji.

Status terakhir: **592 test lulus, 2296 assertion** (dijalankan lokal dan otomatis di CI).

Setiap push dan pull request ke `main` menjalankan `.github/workflows/tests.yml` di GitHub Actions: PHP 8.5, PostgreSQL 18, pemeriksaan format Laravel Pint, lalu seluruh test. Pekerjaan dianggap selesai hanya kalau badge CI hijau.

Keamanan sesi: password yang berubah (lewat reset maupun dari halaman profil) langsung memutus sesi lain lewat `AuthenticateSession`, dan akun Nonaktif ditolak middleware `active` walaupun masih memegang cookie lama. Waktu respons login disamakan dengan hash palsu saat email tidak ditemukan, dan email reset/verifikasi dikirim setelah respons terkirim, supaya waktu respons tidak membocorkan email mana yang terdaftar.

Token verifikasi email disimpan sebagai hash SHA-256, bukan token asli, sehingga isi tabel `users` yang bocor tidak bisa dipakai memverifikasi email orang lain. Email selalu disimpan dalam huruf kecil, dan redirect setelah login hanya diterima kalau host tujuannya sama dengan `APP_URL`.

## Sebelum dipasang di produksi

Nilai di `.env.example` disetel untuk pengembangan lokal. Saat memasang di server, ubah minimal:

- `APP_ENV=production` dan `APP_DEBUG=false` — pesan error tidak boleh tampil ke pengunjung.
- `APP_URL=https://...` — dipakai sebagai pembanding host tujuan redirect setelah login.
- `SESSION_SECURE_COOKIE=true` — cookie sesi hanya dikirim lewat HTTPS.
- `SESSION_ENCRYPT=true` — isi cookie sesi dienkripsi.
- `SESSION_DRIVER` selain `array`, dan `QUEUE_CONNECTION` yang benar-benar punya worker kalau nanti memakai queue.
- `RAJAONGKIR_API_KEY` dan `RAJAONGKIR_BASE_URL` — kunci hanya dipakai di server (tidak pernah dikirim ke browser), dan base URL ditulis tanpa garis miring di akhir.

Mail memakai `MAIL_MAILER=log` di `.env.example`, jadi tautan verifikasi dan reset hanya masuk ke `storage/logs/laravel.log` selama pengembangan.

## Endpoint yang tersedia

Semua route dipisah per modul di `routes/*.php` dan memakai session guard. Respons berupa JSON (backend tanpa Blade).

| Metode | Path | Nama route | Middleware | Keterangan |
| --- | --- | --- | --- | --- |
| GET | `/` | `home` | — | Halaman utama (sementara JSON) |
| POST | `/register` | `register.store` | `guest`, throttle 6/menit | FR-AUTH-02 |
| GET | `/email/verify/{id}/{hash}` | `verification.verify` | `signed`, throttle 6/menit | FR-AUTH-06 |
| GET | `/email/verify` | `verification.notice` | — | Halaman "Cek email Anda" |
| POST | `/email/verification-notification` | `verification.send` | batas 5/15 menit per email+IP | Kirim ulang verifikasi |
| POST | `/login` | `login.store` | `guest` | FR-AUTH-01/03, batas 5 gagal/15 menit per akun, 20 per IP |
| GET | `/login` | `login` | `guest` | Halaman login |
| POST | `/logout` | `logout` | `auth` | Mengakhiri sesi |
| POST | `/forgot-password` | `password.email` | `guest`, throttle 6/menit | FR-AUTH-04 |
| GET | `/reset-password/{token}` | `password.reset` | `guest` | Halaman password baru |
| POST | `/reset-password` | `password.store` | `guest` | Simpan password baru (sekali pakai, 24 jam) |
| GET | `/lokasi?search=` | `lokasi.cari` | `auth`, `active`, throttle 30/menit | FR-PROF-01 #4/#11/#12, UC-08 — cari kecamatan/kelurahan lewat RajaOngkir; hasilnya diingat 24 jam sebagai lokasi yang boleh dipilih |
| GET | `/profil` | `profil.tampil` | `auth`, `active` | FR-PROF-01 #1 — nama, email, nomor telepon, foto profil (jika ada) |
| PUT | `/profil` | `profil.ubah` | `auth`, `active` | FR-PROF-01 #2/#3/#10 — ubah nama dan nomor telepon |
| PUT | `/profil/password` | `profil.password.ubah` | `auth`, `active`, throttle 6/menit | FR-PROF-01 #9 — ubah password dengan verifikasi password lama |
| GET | `/profil/alamat` | `alamat.index` | `auth`, `active` | FR-PROF-01 #1 — daftar alamat milik sendiri (utama lebih dulu) |
| POST | `/profil/alamat` | `alamat.store` | `auth`, `active` | FR-PROF-01 #4 — tambah alamat; `kode_lokasi` wajib berasal dari hasil `/lokasi`, label diambil dari server |
| PUT | `/profil/alamat/{id}` | `alamat.update` | `auth`, `active` | FR-PROF-01 #5 — ubah alamat dengan aturan lokasi yang sama |
| DELETE | `/profil/alamat/{id}` | `alamat.destroy` | `auth`, `active` | FR-PROF-01 #6 — hapus alamat (hanya jika tersisa lebih dari satu) |
| PUT | `/profil/alamat/{id}/utama` | `alamat.utama` | `auth`, `active` | FR-PROF-01 #7 — tandai alamat utama |
| PUT | `/profil/email` | `profil.email.ajukan` | `auth`, `active`, throttle 6/menit | FR-PROF-01 #8 — ajukan email baru; email lama tetap berlaku |
| GET | `/profil/email/verify/{id}/{hash}` | `profil.email.verify` | `signed`, throttle 6/menit | FR-AUTH-06 #7 — konfirmasi email baru (tautan 24 jam, sekali pakai) |
| GET | `/katalog` | `katalog.index` | — | FR-KTLG-01, UC-12 — daftar produk aktif: foto, nama, badge Custom, `harga_mulai` dari varian termurah, MOQ terkecil, `status_stok`, rating, paginasi `per_page` (12, maks 50). FR-KTLG-03 — filter `kategori_id`, `jenis` (`jadi`/`custom`), cari `q`, urut `harga_asc` |
| GET | `/katalog/{id}` | `katalog.show` | — | FR-KTLG-02, UC-13 — detail produk: galeri, deskripsi, kategori, `harga_mulai`, dan `varian` (ukuran/warna/ketebalan, harga, berat, MOQ, `stok_tersedia`). `404` "Produk tidak ditemukan" untuk produk terhapus, nonaktif, atau id non-angka |
| GET | `/kategori` | `katalog.kategori` | - | FR-KTLG-03 #1 - daftar kategori publik untuk pilihan filter: `id` dan `nama`, urut nama |
| GET | `/keranjang` | `keranjang.index` | `auth`, `active` | FR-CART-01 #1/#5 + FR-CART-02, UC-24/UC-25 - ringkasan dua bagian `jadi` dan `custom`; tiap bagian `items`, `total`, `bisa_checkout`. Tiap item membawa `label` (`null`, "Stok tidak mencukupi", "Produk tidak tersedia", atau pesan MOQ) dan `is_custom` untuk penanda "Custom". Pesan "Keranjang Anda kosong" hanya saat keduanya kosong |
| POST | `/keranjang/item` | `keranjang.item.store` | `auth`, `active` | FR-CART-01 #1/#2 - tambah item: `variant_id`, `quantity`, `design_id` (custom). Varian sama digabung. `422` "Minimal pemesanan untuk varian ini adalah {moq} pcs" / "Stok tidak mencukupi" / "Produk tidak tersedia" / "Produk custom harus ditambahkan dari preview desain" / "Buka preview desain terlebih dahulu sebelum menambahkan ke keranjang"; `404` "Desain tidak ditemukan" |
| PATCH | `/keranjang/item/{id}` | `keranjang.item.update` | `auth`, `active` | FR-CART-01 #3 - ubah `quantity` saja; MOQ dan Stok Tersedia diperiksa ulang, `harga_satuan` tidak berubah. `404` "Item keranjang tidak ditemukan" untuk item User lain |
| DELETE | `/keranjang/item/{id}` | `keranjang.item.destroy` | `auth`, `active` | FR-CART-01 #4 - hapus item; `404` "Item keranjang tidak ditemukan" untuk item User lain |
| GET | `/checkout?item_ids[]=` | `checkout.tampil` | `auth`, `active` | FR-CKOT-01 #2/#3/#6 + FR-CART-01 #4/#5 - ringkasan sebelum pesanan dibentuk: `items`, `subtotal_barang`, `berat_total`, `alamat.dipilih` (alamat utama **hanya** bila lokasinya terisi) + `alamat.daftar` (`bisa_dipakai`, `catatan` "Lengkapi lokasi"), `kurir` aktif saja, `sla_hari` + `estimasi_sla`. `422` "Pilih minimal satu item untuk di-checkout" / "Produk jadi dan produk custom di-checkout terpisah, selesaikan salah satunya terlebih dahulu"; `404` "Item keranjang tidak ditemukan" |
| POST | `/checkout/ongkir` | `checkout.ongkir` | `auth`, `active`, throttle 10/menit | FR-CKOT-01 #3/#8 - `item_ids[]`, `address_id`, `courier_id` → `subtotal_barang`, `berat_total`, `ongkir`, `total`. Gangguan RajaOngkir diulang sekali, lalu `422` "Alamat dan kurir wajib dipilih" (termasuk alamat tanpa lokasi, kurir nonaktif, atau alamat User lain) / "Ongkir belum dapat dihitung, silakan coba lagi". `Cache-Control: no-store` |
| POST | `/checkout` | `checkout.buat` | `auth`, `active`, throttle 6/menit | FR-CKOT-01 #1/#4/#5/#7 - buat pesanan: status `menunggu_pembayaran` (jadi, `kadaluwarsa_pada` +24 jam) atau `menunggu_validasi` (custom), `status_pembayaran` "belum_dibayar", `status_desain` "tidak_ada"/"menunggu_validasi", salinan alamat, Stok Direservasi naik, item terpilih keluar dari keranjang. `ongkir` dan `total` dari body diabaikan. `422` "Keranjang berubah, periksa kembali item Anda" + `data.items[].label`. `Cache-Control: no-store` |
| GET | `/pesanan` | `pesanan.index` | `auth`, `active` | FR-ORDR-01 #1, NFR-SEC-03 - daftar "Pesanan Saya": `data[]` berisi `id`, `nomor_pesanan`, `status`, `status_pembayaran`, `status_desain`, `total`, `jumlah_item`, `nomor_resi`, `alasan_pembatalan`, `dibuat_pada`, `kadaluwarsa_pada`. Filter opsional `?status=` dari sembilan status Lampiran B; nilai lain dijawab `422` "status yang dipilih tidak valid". Hanya pesanan milik User yang login, urut terbaru. `401` tanpa login |
| GET | `/pesanan/{nomor}` | `pesanan.show` | `auth`, `active` | FR-ORDR-01 #2, NFR-SEC-03 - detail satu pesanan: seluruh isi baris daftar ditambah `subtotal_barang`, `ongkir`, `diskon_voucher`, `item[]` (`nama_produk`, `nama_varian`, `sku`, `quantity`, `harga_satuan`, `subtotal`, `is_custom`, `sla_hari`, `desain` berisi `id`/`status`/`versi`/`preview` atau `null`), `alamat` (salinan checkout), `kurir` (`kode`, `nama`, `layanan`), `dikirim_pada`, `selesai_pada`, dan `riwayat[]` (`status_lama`, `status_baru`, `oleh` = nama User/Admin atau "Sistem", `catatan`, `waktu`) urut kronologis. Nomor milik User lain dijawab `404` "Pesanan tidak ditemukan", sama seperti nomor yang tidak ada |
| POST | `/pesanan/{nomor}/pembayaran` | `pembayaran.buat` | `auth`, `active`, throttle 10/menit | FR-PAYM-01 #1, UC-31 - buat transaksi Snap untuk pesanan produk jadi berstatus `menunggu_pembayaran`: `data.nomor_pesanan`, `snap_token`, `redirect_url` (hanya saat transaksi baru), `jumlah`, `kadaluwarsa_pada`. Nominal diambil dari `orders.total`. Membuka ulang memakai token yang masih aktif (`200`), percobaan baru setelah `gagal` membuat baris baru (`201`). `422` "Pesanan ini sudah dibayar" / "Pesanan ini tidak dapat dibayar" / "Pembayaran hanya dapat dilakukan setelah desain disetujui" / "Batas pembayaran pesanan sudah lewat" / "Pembayaran belum dapat diproses, silakan coba lagi"; `404` "Pesanan tidak ditemukan". `Cache-Control: no-store` |
| POST | `/pembayaran/notifikasi` | `pembayaran.notifikasi` | **tanpa** `web` (tanpa sesi/CSRF), throttle 60/menit | FR-PAYM-01 #2/#3/#4, UC-31, KM-02 - webhook Midtrans: `order_id`, `status_code`, `gross_amount`, `signature_key`, `transaction_status`, `transaction_id`, `payment_type`, `fraud_status`. Tanda tangan `sha512(order_id+status_code+gross_amount+server_key)` diperiksa dengan `hash_equals` sebelum satu baris pun berubah → `403` "Tanda tangan notifikasi tidak sah". Efek status sama dengan `pembayaran.cek`; `transaction_status` yang tidak mengubah apa pun (`pending`) dijawab `200` tanpa efek. Notifikasi ganda diproses sekali dan tidak mengirim kabar dua kali. `422` "Nominal pembayaran tidak cocok dengan pesanan" / "Nominal pembayaran tidak dikenali" / "Pembayaran untuk pesanan ini belum dibuat"; `404` "Pesanan tidak ditemukan" |
| POST | `/pesanan/{nomor}/pembayaran/cek` | `pembayaran.cek` | `auth`, `active`, throttle 6/menit | FR-PAYM-01 #5, UC-33 - tanya status langsung ke Midtrans lalu samakan keadaan lokal: `data.status_pembayaran`, `status_pesanan`, `dibayar_pada`. `settlement`/`capture`+`accept` → pembayaran `lunas`, pesanan `diproses`, Stok Fisik dan Stok Direservasi turun; `pending`/`capture`+`challenge` → tidak berubah; `deny`/`cancel`/`failure` → pembayaran `gagal`, pesanan tetap `menunggu_pembayaran`; `expire` → pembayaran `kedaluwarsa`, pesanan `dibatalkan`, reservasi dilepas. Pesanan yang sudah `lunas` dijawab dari keadaan lokal tanpa memanggil gateway. Pesanan yang sudah `dibatalkan` tetapi sudah punya baris `payments` tetap diperiksa, karena notifikasi gateway yang tertunda bisa datang setelah scheduler membatalkan pesanan - inilah jalur pemulihan untuk uang yang sudah masuk. `422` "Pembayaran untuk pesanan ini belum dibuat" / "Nominal pembayaran tidak cocok dengan pesanan" / "Pembayaran belum dapat diproses, silakan coba lagi"; `404` "Pesanan tidak ditemukan". `Cache-Control: no-store` |
| POST | `/admin/pesanan/{nomor}/mulai-cetak` | `admin.pesanan.mulai-cetak` | `auth`, `active`, `role:admin_ecommerce` | FR-ORDR-03 #5/#7 - pesanan custom `diproses` menjadi `sedang_dicetak`; riwayat status ditulis dengan `diubah_oleh` Admin dan User dikabari in-app + email. `422` "Perubahan status tidak valid" untuk status lain atau pesanan tanpa desain; `404` "Pesanan tidak ditemukan" |
| GET | `/admin/kurir` | `kurir.index` | `auth`, `active`, `role:admin_ecommerce` | FR-CKOT-04 #2/#3 - daftar **semua** layanan kurir (`kode`, `nama`, `layanan`, `is_aktif`), termasuk yang nonaktif. Tidak ada route tambah: barisnya hasil `php artisan rajaongkir:sinkron-layanan` |
| PUT | `/admin/kurir/{id}/aktif` | `kurir.aktif` | `auth`, `active`, `role:admin_ecommerce` | FR-CKOT-04 #2 - aktifkan layanan; `404` "Kurir tidak ditemukan" |
| PUT | `/admin/kurir/{id}/nonaktif` | `kurir.nonaktif` | `auth`, `active`, `role:admin_ecommerce` | FR-CKOT-04 #3/#4 - nonaktifkan layanan; `422` "Minimal satu layanan kurir harus aktif" bila itu yang terakhir |
| GET | `/admin/pengaturan-pengiriman` | `pengaturan-pengiriman.tampil` | `auth`, `active`, `role:admin_ecommerce` | FR-CKOT-04 #1 - alamat asal toko (`alamat_jalan`, `kode_lokasi`, `label_lokasi`), `data` null bila belum diisi |
| PUT | `/admin/pengaturan-pengiriman` | `pengaturan-pengiriman.simpan` | `auth`, `active`, `role:admin_ecommerce` | FR-CKOT-04 #1 - simpan alamat asal; `kode_lokasi` wajib berasal dari `/lokasi`, label dari klien diabaikan, `422` "Lokasi tidak ditemukan, coba kata kunci lain" |
| GET | `/admin/kategori` | `kategori.index` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-02 - daftar kategori urut nama |
| POST | `/admin/kategori` | `kategori.store` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-02 - tambah kategori (nama unik tanpa beda huruf) |
| PUT | `/admin/kategori/{id}` | `kategori.update` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-02 - ubah nama/deskripsi |
| DELETE | `/admin/kategori/{id}` | `kategori.destroy` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-02 - hapus; ditolak kalau masih dipakai produk |
| GET | `/admin/produk` | `produk.index` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 - daftar produk + harga mulai dari varian termurah |
| POST | `/admin/produk` | `produk.store` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #1/#2 + FR-CDSN-07 #1/#2/#4 - tambah produk; produk custom wajib `sla_hari` ≥ 1 dan tidak bisa langsung aktif sebelum punya template aktif; multipart `gambar[]` JPG/PNG maks 2 MB per file |
| PUT | `/admin/produk/{id}` | `produk.update` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #4/#6 + FR-CDSN-07 - ubah produk, ganti foto utama, ubah status Aktif/Nonaktif, ubah SLA produk custom; aktivasi custom yang belum lengkap dijawab `422` |
| DELETE | `/admin/produk/{id}` | `produk.destroy` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #5 - hapus; ditolak kalau masih ada pesanan aktif atau variannya masih berstok |
| DELETE | `/admin/produk/{id}/gambar/{gambarId}` | `produk.gambar.destroy` | `auth`, `active`, `role:admin_ecommerce` | Hapus satu foto galeri (foto utama diganti lewat ubah produk) |
| GET | `/admin/produk/{id}/varian` | `varian.index` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #3 — daftar varian + Stok Tersedia hasil hitung |
| POST | `/admin/produk/{id}/varian` | `varian.store` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #3/#8 — tambah varian berstok 0; SKU duplikat ditolak |
| PUT | `/admin/produk/{id}/varian/{varianId}` | `varian.update` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #3 + FR-PROD-03 #1 — ubah varian/MOQ; stok tidak ikut berubah |
| DELETE | `/admin/produk/{id}/varian/{varianId}` | `varian.destroy` | `auth`, `active`, `role:admin_ecommerce` | Hapus varian; ditolak kalau masih berstok atau punya pesanan aktif |
| GET | `/admin/template` | `template.index` | `auth`, `active`, `role:admin_ecommerce` | FR-CDSN-01 - daftar template + `file_url`; saring `product_id` dan `is_aktif` |
| POST | `/admin/template` | `template.store` | `auth`, `active`, `role:admin_ecommerce` | FR-CDSN-01 #1/#2/#5/#6 - tambah template; multipart `file` PNG/JPG/SVG maks 10 MB, `lebar_kanvas`/`tinggi_kanvas` > 0, `area_x`/`area_y`/`area_lebar`/`area_tinggi` lengkap atau kosong semua, dan produk harus bertipe custom |
| PUT | `/admin/template/{id}` | `template.update` | `auth`, `active`, `role:admin_ecommerce` | FR-CDSN-01 #2/#3/#4 - ubah nama/kanvas/area/file (file opsional); `is_aktif=false` menonaktifkan template; desain User tidak tersentuh |
| DELETE | `/admin/template/{id}` | `template.destroy` | `auth`, `active`, `role:admin_ecommerce` | FR-CDSN-01 #4 - hapus template + berkasnya; ditolak 422 selama masih ada desain yang memakainya |
| GET | `/admin/validasi-desain` | `validasi.index` | `auth`, `active`, `role:admin_ecommerce` | FR-CDSN-05 #1 - antrean desain `menunggu_validasi`, yang paling lama menunggu di atas, dengan `user`, `template`, `preview_gambar`, `diajukan_pada`; Admin SCM dan User dijawab `403` |
| GET | `/admin/validasi-desain/{id}` | `validasi.show` | `auth`, `active`, `role:admin_ecommerce` | FR-CDSN-05 #5 - detail desain + `data_desain` (gambar diberi `url`) + `riwayat` validasi (tanggal, versi, status, catatan, Admin yang memutuskan) |
| POST | `/admin/validasi-desain/{id}/setujui` | `validasi.setujui` | `auth`, `active`, `role:admin_ecommerce` | FR-CDSN-05 #2 - status desain jadi `disetujui`, `catatan_revisi`/`batas_revisi_pada` dikosongkan, satu baris riwayat ditulis; pesanan yang masih `menunggu_validasi` dan terhubung lewat `order_items.design_id` menjadi `menunggu_pembayaran` dengan `kadaluwarsa_pada` +24 jam (`srs.md:822`); `422` "Desain ini tidak sedang menunggu validasi" untuk status lain |
| POST | `/admin/validasi-desain/{id}/revisi` | `validasi.revisi` | `auth`, `active`, `role:admin_ecommerce` | FR-CDSN-05 #3 - `catatan_revisi` wajib (maks 2000), status jadi `perlu_revisi`, `batas_revisi_pada` = 3 hari kalender; order yang masih `menunggu_validasi` menjadi `menunggu_revisi_desain` tanpa batas bayar; `422` "Catatan revisi wajib diisi" / "Desain ini tidak sedang menunggu validasi" |
| GET | `/desain/unggahan` | `unggahan.index` | **tanpa** `auth` (Guest boleh mengustomisasi) | FR-CDSN-03 #5 - daftar unggahan sementara milik sesi ini: `berkas`, `url`, `lebar`, `tinggi`, `peringatan` |
| POST | `/desain/unggahan` | `unggahan.store` | **tanpa** `auth` (Guest boleh mengustomisasi) | FR-CDSN-03 #1-#4, NFR-FIL-03 - multipart `file` JPG/PNG maks 5 MB; `422` "Format file tidak didukung, gunakan JPG/PNG" / "Ukuran file melebihi batas maksimum" / "Pilih gambar yang akan diunggah"; resolusi < 200 x 200 px tetap diterima dengan `peringatan` "Resolusi gambar rendah, hasil cetak mungkin kurang tajam" |
| DELETE | `/desain/unggahan/{nama}` | `unggahan.destroy` | **tanpa** `auth` (Guest boleh mengustomisasi) | FR-CDSN-03 #5 - hapus berkas dari folder sesi; nama milik sesi lain dijawab `404` "Gambar tidak ditemukan" |
| GET | `/desain` | `desain.index` | `auth`, `active` | FR-CDSN-02 #10 - daftar desain milik User (`status`, `versi`, `catatan_revisi`, `template.nama`), terbaru dulu; `data_desain` tidak ikut agar ringan |
| POST | `/desain` | `desain.store` | `auth`, `active` | FR-CDSN-02 #1/#9 - simpan draft: `template_id` (wajib aktif), `variant_id` (harus milik produk template), `data_desain.elemen[]`; `422` "Template desain tidak tersedia" / "Varian tidak sesuai produk template" / "Elemen harus berada di dalam area kustomisasi" |
| GET | `/desain/{id}` | `desain.show` | `auth`, `active` | FR-CDSN-02 #10 - muat ulang elemen + `catatan_revisi` + `batas_revisi_pada` + template (kanvas dan area); desain milik User lain dijawab `404` "Desain tidak ditemukan" |
| PUT | `/desain/{id}` | `desain.update` | `auth`, `active` | FR-CDSN-02 #9/#10 + FR-CDSN-05 #4/#5 - perbarui elemen draft atau desain `perlu_revisi`; untuk desain `perlu_revisi`, kirim ulang mengembalikan desain dan order ke `menunggu_validasi` serta menaikkan `versi`; draft biasa tetap `draft`; `422` "Desain ini tidak dapat diubah lagi" untuk status lain; template tidak bisa diganti |
| GET | `/desain/editor/{product}` | `desain.editor` | **tanpa** `auth` (Guest boleh mengustomisasi) | FR-CDSN-02 #1/#10 - pembuka editor: `produk`, `template` (kanvas + `area_*` + `file_url`), dan `draft` terakhir milik User yang login (status `draft`/`perlu_revisi`) beserta `catatan_revisi`; `?template_id=` memilih template lain milik produk itu; `422` "Editor hanya tersedia untuk produk custom" / "Produk custom belum punya template desain aktif" / "Template desain tidak tersedia" |
| GET | `/desain/{id}/preview` | `desain.preview` | `auth`, `active` | FR-CDSN-04 #1/#4 - data render preview: `kanvas`, `template` (`file_url`), `elemen` terurut menurut `z` dengan `url` untuk gambar, dan `preview_gambar` bila sudah pernah disimpan; `422` "Preview gagal dimuat, periksa kembali elemen desain" bila berkas gambar elemen hilang/rusak; desain milik User lain `404` |
| POST | `/desain/{id}/preview` | `desain.preview.store` | `auth`, `active` | FR-CDSN-04 #3 - simpan PNG hasil render klien (multipart `file`, maks 10 MB) ke `desain/preview/{id}/` dan isi `designs.preview_gambar`; `422` "Pilih gambar preview yang akan disimpan" / "Format file tidak didukung, gunakan PNG" / "Ukuran file melebihi batas maksimum" / "Desain ini tidak dapat diubah lagi"; preview lama dilepas saat diganti |

### Keputusan di luar dokumen

SRS dan User Story tidak mengatur hal-hal berikut; nilainya dipilih saat implementasi dan boleh berubah tanpa mengubah aturan bisnis:

- **Format nomor telepon**: wajib diawali `+62` lalu 9–12 angka. Nomor gaya `08...` **ditolak**, bukan dinormalkan. Pesan errornya tetap kutipan dokumen, `"Format nomor telepon tidak valid"` (FR-PROF-01 #3). Aturan yang sama dipakai saat registrasi supaya nomor yang tersimpan tidak ditolak ketika User menyimpan profilnya.
- **Foto profil**: hanya **ditampilkan** kalau kolomnya terisi (FR-PROF-01 #1). Tidak ada endpoint unggah karena dokumen tidak memuat skenario unggah; tidak ada upload untuk Admin juga.
- **Ganti email bertahap**: email baru disimpan di kolom `pending_email` + `pending_email_token` (hash SHA-256), bukan langsung menimpa `email`. Alasannya aturan dokumen itu sendiri: "email lama tetap berlaku sampai verifikasi selesai" (FR-AUTH-06 #7), dan kolom tambahan adalah satu-satunya cara menyimpan alamat baru tanpa membuatnya aktif lebih dulu.
- **Masa berlaku tautan konfirmasi email**: 24 jam, memakai `config('auth.verification.expire')` yang sama dengan tautan verifikasi registrasi — tidak ada angka baru.
- **Konfirmasi email tanpa login**: endpoint konfirmasi tidak memakai middleware `auth`, karena tautan dibuka dari aplikasi email yang belum tentu berada di browser dengan sesi aktif. Pengamanannya tanda tangan URL (24 jam) + token sekali pakai, sama seperti verifikasi registrasi.
- **Tidak ada konfirmasi password saat ganti email**: skenario FR-PROF-01 #8 hanya menyebut submit email baru. Pengaman yang dipakai adalah pemberitahuan ke email lama (FR-AUTH-06 #7).
- **Pesan ubah password**: dokumen hanya menetapkan alurnya (FR-PROF-01 #9), jadi pesan `"Password lama tidak sesuai"`, `"Password lama wajib diisi"`, dan `"Password berhasil diubah"` disetujui pemilik proyek. Pesan `"Password minimal 8 karakter"` dan `"Konfirmasi password tidak sesuai"` memakai kalimat yang sudah ada di dokumen (FR-AUTH-04 #5, FR-AUTH-02 #3).
- **Dampak ubah password ke sesi**: sesi di perangkat lain langsung ditolak, sesi yang sedang dipakai tetap berjalan, dan `remember_token` diganti. Dokumen tidak mengaturnya; ini menyamakan perilaku dengan reset password yang sudah memakai `AuthenticateSession`.
- **Alamat utama**: alamat pertama yang ditambahkan otomatis menjadi utama, dan menghapus alamat utama mempromosikan alamat lain yang paling lama. Tujuannya supaya selalu ada tepat satu alamat utama dan checkout selalu punya pilihan default. Penanda utama hanya bisa diubah lewat `PUT /profil/alamat/{id}/utama`, tidak lewat ubah alamat, agar unique index `addresses_utama_unique` tidak pernah dilanggar.
- **Hapus alamat terakhir ditolak** (`"Minimal satu alamat harus tetap tersimpan"`). Skenario #6 memang hanya berlaku saat User punya lebih dari satu alamat.
- **Pesan validasi Bahasa Indonesia**: `.env` sudah memakai `APP_LOCALE=id`, tetapi Laravel tidak menyediakan terjemahan Indonesia, jadi tanpa folder `lang/` pesan yang keluar tetap bahasa Inggris dari vendor. `lang/id/validation.php` memuat seluruh key bawaan Laravel 13, dan `validation.custom` menyimpan pesan yang **ditetapkan dokumen** (`"Format email tidak valid"`, `"Email sudah digunakan"`, `"Konfirmasi password tidak sesuai"`, `"Password minimal 8 karakter"`) supaya sama persis di semua endpoint. Pesan format/ukuran gambar produk (`"Format gambar harus JPG atau PNG"`, `"Ukuran gambar maksimal 2 MB"`) mengikuti NFR-FIL-01; User Story hanya meminta "pesan error format/ukuran file yang didukung". `tests/Feature/PesanValidasiTest.php` menjaga kelengkapan: setiap key bawaan Laravel harus punya terjemahan dan tidak boleh ada yang masih sama dengan teks Inggris.
- **Jebakan wildcard pesan ukuran**: Laravel menambahkan tipe nilai di belakang key pesan untuk aturan `max`/`min`/`size` (misalnya `gambar.0.max.file`), sehingga custom message `gambar.*.file` ikut menangkap pencarian itu dan pesan ukuran berubah menjadi pesan tipe berkas. Karena itu `gambar.*.file` sengaja tidak didefinisikan; pesan untuk aturan `file` sudah sama dari `validation.file` + `attributes`.
- **Pesan validasi alamat**: dokumen hanya menetapkan pesan format nomor telepon. Pesan `"Nama penerima wajib diisi"`, `"Nomor telepon wajib diisi"`, `"Alamat lengkap wajib diisi"`, dan `"Lokasi wajib dipilih"` mengikuti pola `"Nama wajib diisi"` (#10). `"Lokasi wajib dipilih"` kini hanya untuk `kode_lokasi` yang kosong; `label_lokasi` tidak lagi wajib dikirim karena nilainya selalu diambil dari hasil pencarian.
- **Lokasi alamat harus berasal dari hasil pencarian (#4, #5, srs.md:174)**: pencarian terjadi di `GET /lokasi` dan penyimpanan di `POST/PUT /profil/alamat`, dua permintaan berbeda, sehingga hasil pencarian dicatat oleh `App\Services\Profil\LokasiDitawarkan` ke cache (`lokasi-ditawarkan:{kode}`, 24 jam — dokumen tidak mengatur masanya). Endpoint alamat hanya menerima kode yang ada di catatan itu; cache dipakai, bukan tabel baru, karena SRS tidak memuat tabel lokasi. `label_lokasi` kiriman klien **tidak dipakai**: label selalu versi server, supaya alamat tidak bisa diisi tulisan bebas walau kodenya sah. Kode yang tidak dikenal dijawab `422` dengan pesan dokumen #11, `"Lokasi tidak ditemukan, coba kata kunci lain"`, karena User memang harus mencari ulang. Pengecualian: pasangan kode+label yang sudah tersimpan di alamat itu tetap diterima walau tawarannya kedaluwarsa, supaya mengubah alamat lama tidak memaksa memilih lokasi lagi (#5 hanya menyebut "bila perlu"). Konsekuensi yang diterima: menghapus cache memaksa User memilih ulang lokasi sebelum menyimpan alamat baru.
- **Alamat milik User lain dijawab `404`** (`"Alamat tidak ditemukan"`), bukan `403`, supaya keberadaan alamat orang lain tidak terbuka (NFR-SEC-03).
- **Produk custom lahir Nonaktif dan hanya aktif kalau lengkap (FR-PROD-01 #7, FR-CDSN-07 #4)**: `user-story:222` meminta produk custom dikaitkan ke template desain, tetapi `templates.product_id` membuat template baru bisa ada **setelah** produknya — jadi template dikaitkan dari form template (`POST /admin/template`), bukan dari form produk. Form produk menerima `sla_hari` (FR-CDSN-07) dan `is_aktif`; permintaan aktivasi untuk produk custom diperiksa: `sla_hari` harus terisi dan minimal ada satu template **aktif** milik produk itu, kalau tidak dijawab `422` "Produk custom belum lengkap: SLA/template desain" dan produk tetap Nonaktif. Database ikut menjaga lewat `products_custom_aktif_check` (custom aktif wajib `sla_hari`).
- **Gerbang kelengkapan hanya pada permintaan aktivasi**: pemeriksaan di atas berjalan saat request membawa `is_aktif: true`, bukan pada setiap perubahan data. Alasannya, mengubah nama atau harga produk custom yang sudah aktif tidak boleh gagal hanya karena datanya dibuat sebelum FR-CDSN-07/01 ada; skenario #4 berbicara tentang Admin *mengaktifkan* produk. Custom baru yang tidak menyertakan `is_aktif` tetap disimpan Nonaktif.
- **Gambar produk tanpa batas jumlah**: dokumen hanya menetapkan JPG/PNG maks 2 MB per file (NFR-FIL-01) dan tidak menyebut jumlah. Berkas pertama menjadi `products.gambar` (foto kartu katalog), sisanya masuk `product_images` sebagai galeri dengan `urutan` 1..n. Batas teknis tetap ada di PHP (`upload_max_filesize`, `max_file_uploads`, `post_max_size`), bukan di aturan aplikasi.
- **Gambar tidak ditautkan ke varian**: tabel `product_images` hanya punya `product_id`, jadi "banyak foto per warna" belum bisa diwakili tanpa migrasi baru. FR-KTLG-02 tetap memakai galeri per produk karena dokumen tidak meminta foto per varian.
- **Template disimpan sebagai berkas + empat angka area**: dokumen menyebut "batas area kustomisasi" tanpa bentuk penyimpanan, sedangkan skema `templates` hanya punya `area_x`, `area_y`, `area_lebar`, `area_tinggi`. Keempatnya diisi lengkap atau kosong semua, dan tidak boleh melewati `lebar_kanvas`/`tinggi_kanvas`, karena editor memakai koordinat yang sama untuk menahan elemen. Daftar template admin menampilkan semua; penyaring `is_aktif` disediakan untuk pemilih template User (FR-CDSN-01 #4).
- **Ubah template tidak menyentuh desain, hapus ditolak selama desain memakainya**: FR-CDSN-01 #3 menetapkan desain lama tidak terpengaruh perubahan template, jadi berkas lama **tidak dihapus** selama `designs` masih menunjuk template itu, dan baris desain tidak pernah diperbarui. Karena `designs` belum punya kolom berkas template, kesinambungan gambar dasar untuk draft lama baru bisa dijamin saat FR-CDSN-02 menyimpan rujukan template ke `data_desain`; sampai itu, `preview_gambar` desain tetap utuh. Menghapus template yang punya desain ditolak (`422`) dan Admin diarahkan menonaktifkannya — kalau dihapus, `designs.template_id` menjadi `null` (`nullOnDelete`) dan riwayat desain kehilangan acuannya.
- **Unggahan gambar User disimpan per token sesi, tanpa tabel baru**: dokumen menyebut "daftar upload sementara" (FR-CDSN-03 #5) tetapi tidak punya tabel unggahan, dan `designs` baru lahir saat draft disimpan (FR-CDSN-02). Jadi berkas hidup di `storage/app/public/desain/unggahan/{token}` sementara rujukan permanennya baru masuk `data_desain` saat desain disimpan. Token dibuat sekali di sesi, bukan memakai `user_id`, karena editor boleh dibuka Guest (user-story.md:834 hanya membatasi keranjang, checkout, dan pesanan); token ikut bertahan saat sesi di-regenerate pada login. Nama dari URL dipotong `basename()` supaya tidak bisa keluar folder pemilik, dan pemilik lain mendapat `404`.
- **Peringatan resolusi rendah bukan penolakan**: FR-CDSN-03 #4 meminta gambar di bawah 200 x 200 px "tetap diizinkan", jadi unggahan dijawab `201` dengan `peringatan`, bukan `422`; editor yang menampilkannya. Dimensi dibaca `getimagesize()` dari header berkas, bukan dari nama berkas, karena GD tidak terpasang di server ini. Validasi format memakai aturan `mimes`, yang membaca isi berkas; ekstensi yang diubah-ubah tidak lolos.
- **Bentuk `data_desain` yang disimpan**: dokumen menyebut teks dengan "font, ukuran, warna" dan gambar unggahan tanpa menetapkan struktur JSON, jadi kontraknya ditulis di sini: `{"elemen": [{tipe, x, y, lebar, tinggi, rotasi, z, ...}]}`. Koordinat memakai piksel kanvas dengan titik nol di kiri atas, `rotasi` dalam derajat, dan `z` urutan lapisan (makin besar makin depan). `tipe=teks` wajib punya `isi` yang tidak kosong (spasi saja ditolak), `tipe=gambar` wajib punya `berkas`. Kunci lain di luar daftar itu dibuang oleh `validated()`, sehingga isi database tidak bisa diisi field liar dari klien.
- **Batas area dicek di server, bukan hanya di editor**: FR-CDSN-02 #7 mewajibkan elemen kembali ke posisi valid saat dilepas di luar area. Editor menahannya saat drag, tetapi penyimpanan juga menolak elemen yang kotaknya keluar dari `area_*` template (`422` "Elemen harus berada di dalam area kustomisasi"), karena draft yang tersimpan di luar area akan lolos ke validasi Admin. Kotak yang dipakai adalah kotak tanpa rotasi — rotasi tetap urusan tampilan editor, dan keputusan ini dicatat supaya tidak diklaim lebih ketat dari kenyataannya.
- **Gambar unggahan pindah ke folder desain saat draft disimpan (FR-CDSN-02 #10)**: kalau `data_desain` hanya menunjuk `desain/unggahan/{token}`, menghapus gambar dari daftar sementara (FR-CDSN-03 #5) akan memutus draft yang sudah tersimpan. Jadi setiap elemen gambar disalin ke `desain/gambar/{design_id}` dan berkas sementaranya dilepas; berkas desain yang tidak lagi dipakai elemen mana pun ikut dihapus. Gambar dari folder sesi lain ditolak (`422` "Gambar tidak ditemukan"), dan hanya template aktif yang bisa dipakai membuat draft.
- **Hanya `draft` dan `perlu_revisi` yang bisa disunting**: Lampiran B menetapkan desain `menunggu_validasi`, `disetujui`, dan `kedaluwarsa` sebagai keadaan yang tidak lagi boleh diubah User; penolakan disampaikan sebagai `422` "Desain ini tidak dapat diubah lagi". `template_id` tidak ikut divalidasi pada `PUT` karena mengganti template membuat koordinat elemen lama tidak bermakna; draft tetap terikat pada template saat ia lahir. Menyimpan desain `perlu_revisi` berarti mengirim ulang (FR-CDSN-05 #4), sedangkan menyimpan draft biasa tidak mengubah status.
- **Pembuka editor dipisah dari penyimpanan draft (FR-CDSN-02 #1)**: `GET /desain/editor/{produk}` sengaja publik meski `POST/PUT /desain` wajib login, karena #1 berlaku bagi Guest yang baru mengustomisasi dan belum punya akun (user-story.md:834 hanya membatasi keranjang, checkout, dan pesanan). Respons memuat template, kanvas, dan area cetak — tiga hal yang disebut #1 — sedangkan daftar varian tidak diduplikasi di sini karena halaman detail produk (FR-KTLG-02) sudah mengirimkannya; editor menerima varian yang User pilih di halaman itu. `?template_id=` hanya boleh menunjuk template **aktif** milik produk tersebut, kalau tidak dijawab `422` "Template desain tidak tersedia".
- **Draft yang dimuat editor hanya yang masih bisa disunting**: `draft` pada respons editor disaring ke status `draft` dan `perlu_revisi` — sama dengan daftar status pada `PUT /desain/{id}`. Desain `menunggu_validasi`, `disetujui`, dan `kedaluwarsa` tidak dikirim karena editor tidak akan bisa menyimpannya; menampilkannya hanya membuat User menyunting sesuatu yang ditolak saat disimpan.
- **Preview tidak merender PNG di server — GD dan Imagick tidak terpasang**: FR-CDSN-04 #1 meminta "hasil render desain final"; render terjadi di browser (Konva.js, srs.md:267), sedangkan server menyiapkan bahannya lewat `GET /desain/{id}/preview` dan menyimpan hasil render klien pada langkah #3. `php -m` di mesin ini hanya memuat `fileinfo` — tidak ada `gd` maupun `imagick` — jadi rasterisasi di server memang tidak mungkin, dan gambar preview yang disimpan nanti berasal dari kanvas klien (PNG). Elemen dikirim terurut menurut `z` supaya klien menggambar dari belakang ke depan tanpa menebak urutan tumpukan.
- **Preview hanya membaca, jadi semua status boleh dibuka pemiliknya**: halaman preview tidak mengubah apa pun (beda dengan `PUT /desain/{id}` yang hanya menerima `draft`/`perlu_revisi`), sehingga desain yang sudah `disetujui` atau `kedaluwarsa` tetap bisa dilihat sebagai riwayat. Yang diperiksa `GET /desain/{id}/preview` adalah kepemilikan (`404` untuk User lain) dan kesiapan berkas: setiap elemen gambar harus ada dan `getimagesize()` harus bisa membaca headernya, kalau tidak dijawab `422` "Preview gagal dimuat, periksa kembali elemen desain" (#4). Berkas template tidak ikut diperiksa pada langkah ini karena template bisa saja memakai gambar contoh saat data uji dibuat.
- **Gambar preview datang dari klien, jadi PNG yang diterima, bukan gambar apa saja**: `POST /desain/{id}/preview` hanya menerima PNG karena hanya kanvas (Konva `toDataURL`/`toBlob`) yang membuatnya; menerima format lain berarti membuka jalur untuk berkas yang tidak pernah dihasilkan aplikasi ini. Batas 10 MB dipinjam dari NFR-FIL-02 (template), bukan angka baru: preview adalah gambar seukuran kanvas, dan template adalah pembanding terdekatnya di dokumen. Hanya desain `draft`/`perlu_revisi` yang boleh menyimpan preview, karena preview ikut desain yang masih bisa berubah (Lampiran B); status lain dijawab `422` "Desain ini tidak dapat diubah lagi". Berkas lama dilepas setelah berkas baru tersimpan, sehingga mengganti preview berkali-kali tidak menumpuk berkas di `desain/preview/{id}`.
- **FR-CDSN-05 #2/#3/#4 sudah menyentuh pesanan**: persetujuan memindahkan order ke `menunggu_pembayaran` dengan `kadaluwarsa_pada` +24 jam (`srs.md:822`) dan mengirim notifikasi + email berisi tautan pembayaran (FR-PAYM-02 #1); permintaan revisi memindahkannya ke `menunggu_revisi_desain` tanpa batas bayar; kirim ulang desain oleh User mengembalikan keduanya ke `menunggu_validasi` dan menaikkan `versi` supaya riwayat #5 bisa membedakan putaran. Ketiganya mengabaikan order bila desain belum pernah checkout. #7 (notifikasi revisi) mengirim catatan Admin + tautan editor lewat in-app dan email; #8 (kedaluwarsa) dijalankan `orders:revisi-kedaluwarsa` (FR-SYS-01 #2). #10 (penandaan terlambat) masih menyusul karena bergantung FR-SYS-01 #4 yang OPSIONAL. Batas revisi memakai 3 hari **kalender** sesuai AC #3, bukan hari kerja.
- **Tautan kabar revisi mengarah ke editor (FR-CDSN-05 #7)**: dokumen meminta User mengklik "link revisi" lalu editor memuat elemen terakhir beserta catatan Admin, jadi pesan memakai `route('desain.editor', $product_id)` yang sudah ada. Email memakai `PembayaranMail` yang sama dengan kabar pembayaran; kalau desain belum pernah checkout, notifikasi in-app tetap dibuat tetapi email dilewati karena mailable itu butuh nomor pesanan.
- **Setiap keputusan Admin menyalin isi desain ke riwayat**: `design_revisions` sudah punya kolom `data_desain` dan `preview_gambar`, jadi baris riwayat menyimpan salinan apa yang dinilai Admin pada versi itu. Tanpa salinan, riwayat hanya menyebut "versi 1 perlu revisi" tanpa bukti apa yang dimaksud, dan desain yang sudah diperbaiki akan menimpa konteks keputusan lama. Saat disetujui, `designs.catatan_revisi` dan `batas_revisi_pada` dikosongkan karena fase revisinya berakhir — catatannya tetap terbaca di riwayat.
- **Hanya desain `menunggu_validasi` yang bisa diputuskan**: `setujui` dan `revisi` menolak status lain dengan `422` "Desain ini tidak sedang menunggu validasi". Tanpa penjagaan ini, Admin bisa menyetujui desain yang belum diajukan atau meminta revisi atas desain yang sudah disetujui, dan antrean kehilangan makna sebagai daftar tunggu.
- **Arti "pesanan aktif" pada hapus produk (FR-PROD-01 #5)**: semua status selain `selesai` dan `dibatalkan` (sesuai enum `orders.status`). Pesan `"Produk masih memiliki pesanan aktif dan tidak dapat dihapus"` tidak ditetapkan dokumen. Riwayat pesanan tetap utuh karena `order_items` menyimpan salinan nama produk/SKU/harga dan `variant_id` menjadi `null` saat varian ikut terhapus.
- **Simpan gambar**: disk `public` di `storage/app/public/produk`, sehingga `php artisan storage:link` wajib dijalankan sekali per mesin.
- **SKU unik tanpa beda huruf (FR-PROD-01 #8)**: `TAS-1` dan `tas-1` dianggap kode yang sama dan ditolak dengan `"Kode SKU sudah digunakan"` (user-story:223). Seperti nama kategori, aturan ini hidup di `VariantController` (`lower(sku)`), bukan lewat indeks tambahan; unique bawaan kolom `sku` tetap menolak kode yang persis sama. Konsekuensi yang diterima: jalur tulis di luar controller bisa menyisipkan SKU yang hanya beda huruf.
- **MOQ tidak diisi berarti 1**: dokumen hanya mewajibkan MOQ disimpan per varian (FR-PROD-03 #1). Nilai 1 adalah batas terkecil yang diizinkan `variants_moq_check`.
- **Angka stok diabaikan di endpoint varian**: `stok_fisik`, `stok_direservasi`, dan `stok_minimum` tidak dibaca dari request, bahkan kalau dikirim, karena hanya Admin SCM yang boleh mengubah stok (srs.md:398). `stok_tersedia` selalu dihitung dan tidak pernah disimpan.
- **Notifikasi varian baru**: satu baris `notifications` per Admin SCM aktif, `tipe = "stok_awal"`, dibuat saat varian baru disimpan (user-story:218, FR-SCM-01 #6). Judul dan isi pesan tidak ditetapkan dokumen. Kalau belum ada Admin SCM, varian tetap tersimpan tanpa notifikasi.
- **Penjagaan hapus produk dan varian**: varian ditolak `422` jika `stok_fisik` atau `stok_direservasi` masih lebih dari 0 (menghapusnya akan membuang riwayat `stock_movements` lewat cascade), atau jika varian dipakai pesanan berjalan. Produk ditolak dengan alasan yang sama bila salah satu variannya masih berstok, ditambah penjagaan pesanan aktif; tanpa itu, menghapus produk menjadi jalan pintas untuk melewati penjagaan varian. Pesan-pesannya tidak ditetapkan dokumen.
- **Batas waktu dan throttle RajaOngkir**: 8 detik (4 detik koneksi), 30 permintaan/menit untuk `/lokasi`.
- **Arti "nama kategori unik" (FR-PROD-02)**: dokumen tidak menyebut huruf besar/kecil, jadi `Tas` dan `tas` dianggap sama. Aturan ini ditegakkan **di `KategoriController`** (`lower(nama)` + `whereKeyNot`), bukan dengan indeks tambahan di database — database hanya menolak nama yang persis sama lewat unique bawaan kolom `nama`. Konsekuensi yang diterima: jalur tulis di luar controller (seeder, SQL manual, atau dua permintaan yang benar-benar bersamaan) masih bisa menyisipkan `Tas` dan `tas` berdampingan.
- **Prefiks rute admin**: endpoint kelola kategori memakai `/admin/...` supaya terpisah dari katalog publik yang nanti memakai `/katalog` (FR-KTLG). Dokumen tidak mengatur bentuk URL.
- **Zona waktu WIB ditulis tetap di `config/app.php` (NFR-LOC-01)**: nilai `Asia/Jakarta` tidak lewat `env()`, karena WIB adalah satu-satunya zona waktu yang dipakai (srs.md:807). Nilai env akan membuka celah zona lain masuk tanpa ada kebutuhan. `tests/Feature/ZonaWaktuTest.php` menjaga tiga hal: config dan zona waktu PHP sama-sama `Asia/Jakarta`, waktu sekarang berselisih `+07:00` tanpa menggeser momennya, dan timestamp yang ditulis database lalu dibaca model kembali muncul dalam WIB.
- **Kartu katalog (FR-KTLG-01)**: dokumen menentukan isi kartu tetapi tidak bentuk respons, jadi `GET /katalog` mengirim `harga_mulai` (varian termurah, atau `harga_dasar` bila belum ada varian), `moq` (MOQ terkecil antar varian, `null` bila belum ada varian), dan `status_stok` `"tersedia"`/`"habis"` (ada varian dengan `stok_fisik - stok_direservasi > 0`; produk tanpa varian dianggap habis). Angka stok per varian tidak dikirim di kartu karena itu isi detail produk (FR-KTLG-02). Format "Mulai Rp 15.000" diserahkan ke lapisan tampilan; API mengirim angka.
- **Paginasi katalog**: 12 produk per halaman (grid 3x4 di ponsel) dan `per_page` dibatasi maksimum 50; dokumen hanya meminta paginasi (FR-KTLG-01 #3) tanpa angka. Urutan default `nama` menaik supaya halaman tidak berubah isinya saat berpindah halaman; urutan harga adalah FR-KTLG-03 #6.
- **Rating katalog**: SRS 4.4.2 meminta rating "jika ada, dari FR-ORDR-05". Endpoint ulasan belum dibangun, tetapi tabel `reviews` sudah ada, jadi katalog menghitung `rating` (rata-rata, `null` bila kosong) dan `jumlah_ulasan` langsung dari sana — begitu ulasan masuk, kartu terisi tanpa mengubah kontrak.
- **Katalog tanpa login**: `GET /katalog` tidak memakai middleware `auth`/`active` karena SRS 4.4.1 menyatakan Guest boleh menjelajah katalog; keranjang dan checkout tetap butuh login.
- **Galeri detail produk (FR-KTLG-02 #1)**: `products.gambar` menjadi item pertama, lalu `product_images` urut kolom `urutan`; produk tanpa foto mengirim `galeri: []`. Galeri dikirim sebagai larik URL, bukan larik objek, karena tampilan hanya butuh urutan — foto utama adalah item pertama, tanpa penanda tambahan. Aturan ini tinggal di `Product::berkasGambar()` dan dipakai juga saat menghapus berkas produk, supaya urutan dan penyaringan berkas ganda tidak berbeda antar pemakai.
- **Produk nonaktif di halaman detail**: katalog menyembunyikan produk nonaktif (FR-PROD-01 #6), jadi detailnya dijawab `404` "Produk tidak ditemukan" — link lama mati, bukan halaman tanpa tombol beli. Id non-angka dijawab `404` yang sama sebelum menyentuh query PostgreSQL, supaya `/katalog/abc` tidak menjadi error 500.
- **Stok dan tombol di detail (FR-KTLG-02 #2/#3/#4)**: `varian` mengirim `stok_tersedia` berupa angka (`stok_fisik - stok_direservasi`), bukan `status_stok` seperti kartu, karena label "Stok Habis" butuh angka nol dan validasi MOQ butuh jumlah. Tombol "Kustomisasi Desain" ditentukan tampilan dari `is_custom`; alamat editor belum ada karena FR-CDSN-02 belum dibangun.
- **Utang KTLG — login saat Guest menekan Kustomisasi (SRS 4.4.2 baris 4)**: reaksi "sistem meminta login lalu mengembalikan User ke editor dengan produk, varian dasar, dan jumlah (return URL)" belum dibangun. Reaksi ini **tidak dimiliki FR mana pun** di 4.4.3, jadi dicatat di sini dan dikerjakan bersama FR-CDSN-02; sampai saat itu, Guest hanya menerima penanda `is_custom` dari `GET /katalog/{id}`.
- **Rating tidak dikirim di detail produk**: FR-KTLG-02 tidak memintanya; hanya kartu katalog yang meminta rating dan jumlah ulasan (SRS 4.4.2).
- **Filter katalog (FR-KTLG-03)**: parameter `kategori_id`, `jenis` (`jadi`/`custom`), `q` (nama produk), dan `urut=harga_asc`. Nilai filter yang tidak sah (`?jenis=apa-saja`, `?kategori_id=abc`, `?kategori_id=999`) diperlakukan sebagai filter yang tidak cocok: hasil kosong dengan pesan filter, bukan `422` — dokumen hanya mengenal dua keadaan, cocok dan tidak cocok, dan skenario #4 sudah menyediakan pesannya.
- **Pesan kosong berlapis**: dokumen punya tiga pesan kosong. Urutannya `q` ada → "Produk tidak ditemukan" (skenario #8 menang walau filter juga aktif), filter ada tanpa `q` → "Tidak ada produk yang sesuai dengan filter ini", tanpa parameter apa pun → "Belum ada produk tersedia". `q` yang hanya berisi spasi dianggap tidak ada.
- **Pencarian dan urutan harga**: kata kunci dicocokkan ke `nama` dengan `ilike` (tanpa beda huruf besar-kecil) karena skenario #7 menyebut "nama produk", dan `%`/`_` di-escape agar dicari sebagai teks biasa. `urut=harga_asc` memakai `coalesce(varian termurah, harga_dasar)` — angka yang sama dengan `harga_mulai` di kartu — lalu `nama` sebagai pengurut kedua agar halaman stabil; nilai `urut` lain kembali ke urutan nama.
- **Daftar kategori publik (`GET /kategori`)**: skenario #1 meminta User memilih kategori, tetapi `GET /admin/kategori` butuh login. Endpoint publik berisi `id` dan `nama` semua kategori, termasuk yang belum punya produk aktif — keadaan itu sudah diatur skenario #4 (filter tanpa hasil). Daftar ini tidak dititipkan ke `meta` katalog supaya tidak ikut terkirim di setiap halaman dan setiap scroll.
- **Serialisasi tanggal ke JSON masih UTC** (hutang, bukan keputusan): `toJson()` model mengirim `2026-11-12T05:57:47.000000Z`, bukan `+07:00`. Saat ini belum ada endpoint yang mengirim timestamp mentah — semua respons menulis kolom satu per satu — jadi belum melanggar NFR-LOC-01. Begitu modul Pesanan/Notifikasi mulai mengirim waktu, tanggal harus diformat WIB di lapisan respons (misalnya `->format('Y-m-d\TH:i:sP')`) atau lewat satu `serializeDate()` bersama, bukan dibiarkan `Z`.
- **Pesan `"Kategori tidak ditemukan"`**: dokumen hanya menetapkan pesan duplikat dan pesan kategori terpakai (`srs.md:406-407`); pesan untuk id yang tidak ada mengikuti pola `"Alamat tidak ditemukan"` dan dijawab `404`.
- **Keranjang memerlukan login (FR-CART-01)**: `GET/POST/PATCH/DELETE /keranjang` memakai `auth` + `active`; Guest yang mencoba mendapat `401`, karena `srs.md:430` menyebut keranjang hanya untuk User yang sudah masuk.
- **Satu baris per varian**: partial unique index `cart_items_produk_jadi_unique` dan `cart_items_produk_custom_unique` memaksa varian jadi yang sama menyatu dan item custom unik per `(cart_id, variant_id, design_id)`. Karena itu menambah varian yang sudah ada **menjumlahkan** `quantity` pada baris lama, bukan membuat baris baru. MOQ dan Stok Tersedia diperiksa terhadap jumlah **setelah digabung** — mengirim 1 pcs dengan MOQ 3 ditolak, tetapi menambah 1 pcs ke baris berisi 2 pcs diterima karena pesanannya menjadi 3.
- **Harga dikunci saat item masuk (FR-CART-01 #6)**: `cart_items.harga_satuan` diisi `variants.harga` ketika item ditambahkan dan tidak pernah dihitung ulang saat katalog naik atau saat jumlah diubah. Checkout (`FR-CKOT-01`) memakai angka ini, sehingga User membayar harga yang dilihatnya saat memasukkan barang.
- **Stok tidak dikunci di keranjang**: sesuai SRS 4.6.2, keranjang hanya **memeriksa** Stok Tersedia; `stok_direservasi` baru bertambah saat checkout. Konsekuensinya barang di keranjang bisa habis sebelum checkout, dan keadaan itu ditangani ringkasan (FR-CART-02).
- **Batas stok berlaku untuk jadi dan custom**: `srs.md:116` menyatakan keduanya memakai satuan stok yang sama, yaitu varian dasar (blanko) untuk produk custom, sehingga `stok_fisik` gudang juga membatasi jumlah item custom; MOQ tetap berlaku untuk keduanya. `design_id` yang dikirim untuk produk jadi diabaikan supaya baris jadi tidak pernah terikat desain.
- **Item custom hanya dari preview (FR-CART-01 #2 + `srs.md:471`)**: `POST /keranjang/item` dengan `design_id` tetap memeriksa kepemilikan dan kecocokan varian (`404` "Desain tidak ditemukan"), lalu menolak desain yang `preview_gambar`-nya masih kosong dengan `422` "Buka preview desain terlebih dahulu sebelum menambahkan ke keranjang". `preview_gambar` dipakai sebagai bukti karena baru terisi setelah User menyimpan hasil render di `POST /desain/{id}/preview` (FR-CDSN-04 #4), sehingga jalan satu-satunya menuju item custom adalah melewati halaman Preview Desain.
- **Pesan `"Desain tidak ditemukan"`** dipakai juga saat desain ada tetapi milik User lain atau variannya berbeda, dengan `404` mengikuti pola NFR-SEC-03: keberadaan data User lain tidak dibuka.
- **Bentuk ringkasan keranjang (FR-CART-02 #1)**: `GET /keranjang` mengirim `data.jadi` dan `data.custom` sebagai objek berisi `items`, `total` (jumlah subtotal seluruh item bagian itu, termasuk yang berlabel), dan `bisa_checkout` (ada item dan semua `label` null). Item membawa `label` persis teks dokumen dan `is_custom` untuk penanda "Custom"; bentuk ini dipilih supaya satu permintaan cukup untuk menggambar halaman sekaligus mengaktifkan/menonaktifkan tombol.
- **Urutan label validasi**: `"Produk tidak tersedia"` (produk dinonaktifkan) lebih dulu, lalu pesan MOQ, lalu `"Stok tidak mencukupi"`. Produk nonaktif membuat item tidak bisa dibeli sama sekali, sedangkan MOQ dan stok sama-sama bisa dibereskan dengan mengubah jumlah; satu label saja per item supaya tampilan tidak perlu memilih.
- **Label MOQ memakai pesan FR-PROD-03 #2** (`"Minimal pemesanan untuk varian ini adalah {moq} pcs"`) karena dokumen tidak menyediakan label khusus MOQ di keranjang, sementara srs.md:516 tetap meminta MOQ divalidasi saat ringkasan dibuka. Keadaan ini muncul ketika Admin menaikkan MOQ setelah item masuk keranjang.
- **Validasi harga (FR-CART-02)**: yang diperiksa adalah perhitungan ulang `subtotal = quantity x harga_satuan`, memakai harga yang sudah dikunci saat item masuk (FR-CART-01 #6), bukan harga katalog terbaru. Tidak ada peringatan "harga berubah" karena perubahan harga katalog memang tidak boleh mengubah isi keranjang.
- **Bentuk pilihan checkout `item_ids[]`** (keputusan, kini dipakai): skenario FR-CART-01 #4/#5 menuntut keranjang mengirim "item yang dipilih", dan dokumen tidak menetapkan bentuknya. Dipilih daftar id per baris keranjang, bukan per bagian, karena satu bagian bisa berisi beberapa item dan User boleh memilih sebagian. Pilihan kosong dijawab `422` "Pilih minimal satu item untuk di-checkout"; id milik User lain dijawab `404` "Item keranjang tidak ditemukan" mengikuti NFR-SEC-03.
- **Ongkir dan total selalu dihitung ulang di server**: `POST /checkout` mengabaikan `ongkir`/`total` yang dikirim klien. Angka uang tidak boleh berasal dari browser, dan skenario #3 memang menetapkan sistem yang menghitung dari berat total + rute.
- **Alamat/kurir yang tidak bisa dipakai memakai kalimat #4 yang sama**: alamat tanpa lokasi (skenario #2), alamat milik User lain, dan kurir yang sudah dinonaktifkan semuanya dijawab `422` "Alamat dan kurir wajib dipilih". Satu kalimat dipakai supaya keberadaan data User lain tidak terbuka, dan supaya tidak ada pesan baru yang tidak ada di dokumen.
- **Alamat asal toko kosong atau layanan tidak dikembalikan API = ongkir tidak tersedia**: keduanya dijawab `422` "Ongkir belum dapat dihitung, silakan coba lagi" (skenario #8). Ongkir **tidak pernah** jatuh ke Rp 0, sesuai `srs.md:520`. Konsekuensinya checkout berhenti sampai Admin melengkapi alamat asal dan `rajaongkir:sinkron-layanan` dijalankan.
- **Reservasi stok memakai `lockForUpdate`, tanpa baris `stock_movements`**: NFR-PRF-05 menuntut reservasi atomik, jadi stok diperiksa ulang di dalam transaksi setelah baris varian dikunci (bukan dari bacaan sebelum transaksi). Kartu stok tidak dicatat di sini karena `stock_movements.tipe` hanya punya `stok_awal`, `penjualan`, `restock`, `pembatalan`, `penyesuaian` (`srs.md:642`); reservasi bukan pergerakan stok fisik, dan `penjualan` baru dicatat saat Lunas.
- **`status_pembayaran` diturunkan, bukan baris `payments`**: Lampiran A menyebut "Belum Dibayar" = pesanan dibuat dan belum ada pembayaran, jadi checkout tidak membuat baris `payments`. Baris dan `snap_token` milik FR-PAYM-01 saat transaksi gateway dimulai; sebelum itu statusnya dihitung (`latestPayment?->status ?? 'belum_dibayar'`).
- **`status_desain` "Tidak Ada" bukan nilai kolom**: `designs.status` hanya punya `draft`, `menunggu_validasi`, `perlu_revisi`, `disetujui`, `kedaluwarsa`. "Tidak Ada" dari Lampiran A adalah keadaan "pesanan produk jadi", jadi dikirim sebagai `tidak_ada` di respons, bukan disimpan.
- **Nomor pesanan `INV-<ULID>`**: dokumen hanya mewajibkan `nomor_pesanan` unik. ULID dipilih karena tidak perlu membaca ulang baris setelah insert (kolomnya NOT NULL) dan urut waktu, sehingga mudah ditelusuri; formatnya boleh berubah tanpa mengubah aturan bisnis.
- **Pesan "Keranjang berubah, periksa kembali item Anda" adalah pilihan implementasi**: skenario #7 hanya menetapkan sistem membatalkan pembuatan pesanan dan menampilkan penjelasan item bermasalah, tanpa kalimat baku. Yang dipakai sebagai penjelasan adalah `label` item dari dokumen ("Produk tidak tersedia", "Stok tidak mencukupi", pesan MOQ). Pemeriksaan yang sama diulang di dalam transaksi; kalau stok habis di antara dua langkah, jawabannya tetap `422` dengan label yang sama.
- **"Mulai Cetak" hanya untuk custom yang Diproses (FR-ORDR-03 #5/#7)**: endpoint admin menolak pesanan tanpa `order_items.design_id` dan status selain `diproses`, karena #5 mensyaratkan desain Disetujui dan pembayaran Lunas. Status dibaca ulang di bawah kunci sebelum diubah, dan riwayat menyimpan `diubah_oleh` Admin supaya FR-ORDR-01 #2 tidak menampilkannya sebagai aksi Sistem.
- **SLA pesanan memakai yang terpanjang**: FR-CDSN-06 menetapkan estimasi per produk custom, sedangkan checkout bisa berisi beberapa produk. Produksi baru selesai setelah item paling lama ikut selesai, jadi respons memakai `max(sla_hari)` dan `order_items.sla_hari` menyimpan salinan per item (FR-CDSN-07 #3: pesanan berjalan tidak ikut berubah saat SLA produk diubah).
- **Pesanan custom tanpa `kadaluwarsa_pada`**: batas 24 jam hanya berlaku pada "Menunggu Pembayaran" (Lampiran A, `srs.md:822`), sedangkan pesanan custom lahir "Menunggu Validasi" dan baru masuk batas bayar setelah desain disetujui (FR-PAYM-02).
- **Batas 50 item per checkout**: bukan aturan dokumen, melainkan batas teknis. PostgreSQL menolak lebih dari 65.535 parameter; tanpa batas, 66.000 id menghasilkan kira-kira 2,6 detik kerja query lalu `500`. Id yang bentuknya bukan bilangan bulat (`1e3`, `1.5`, `true`, larik bersarang) dijawab `404` "Item keranjang tidak ditemukan", **bukan** `422` "Pilih minimal satu item untuk di-checkout": pilihan kosong dan id yang tidak dikenali adalah dua keadaan berbeda, dan `is_numeric` meloloskan bentuk yang kemudian ditolak PostgreSQL sebagai bigint.
- **Throttle 10/menit untuk `POST /checkout/ongkir` dan 6/menit untuk `POST /checkout`**: bukan aturan dokumen. Satu panggilan ongkir memotong satu kuota RajaOngkir (terukur: 4 panggilan = 4/100), sehingga satu akun bisa menghentikan seluruh penjualan; dan tiap pesanan mengunci stok, sehingga pembuatan pesanan tanpa batas mengunci stok untuk semua User. Keduanya memakai awalan `checkout-ongkir` dan `checkout-buat` supaya hitungannya terpisah — tanpa awalan, Laravel memakai satu kunci per User untuk semua route, sehingga batas 6 akan menimpa batas 10.
- **Gangguan RajaOngkir diulang satu kali**: hanya gangguan koneksi dan balasan `5xx`, dengan jeda 200 ms. `4xx` tidak diulang karena kunci salah atau permintaan cacat tidak akan sembuh dengan pengulangan. Pengulangan ini tidak mengubah kontrak: kalau tetap gagal, jawabannya `422` "Ongkir belum dapat dihitung, silakan coba lagi" seperti skenario #8, dan pesanan tetap tidak dibuat (`srs.md:521`).
- **`cost` dari RajaOngkir diperiksa sebelum menjadi uang**: harus bilangan bulat positif. Nilai `0` atau negatif ditolak dan alurnya jatuh ke pesan #8, karena `srs.md:520` melarang ongkir "diam-diam menjadi Rp 0" — dan nilai negatif akan menurunkan total pesanan.
- **Balasan 429 berbahasa Indonesia (NFR-LOC-01)**: middleware `throttle` bawaan Laravel melempar pesan Inggris `Too Many Attempts.` tanpa sisa waktu tunggu. `bootstrap/app.php` menerjemahkannya menjadi `429` `{"message":"Terlalu banyak permintaan, coba lagi beberapa saat lagi","retry_after":N}` dan tetap mengirim header `Retry-After` serta `X-RateLimit-*`. Kalimatnya satu tempat (`App\Support\BatasPermintaan`), dipakai juga oleh pembatas manual di `AuthController` (login 5 kegagalan, kirim ulang verifikasi) supaya tidak ada dua kalimat berbeda untuk keadaan yang sama.
- **`Cache-Control: no-store` pada tiga respons checkout**: bukan aturan dokumen. Respons memuat alamat lengkap dan nomor telepon, jadi tidak boleh tinggal di cache browser atau proksi bersama.
- **Reservasi yang kedaluwarsa kini dirilis otomatis (FR-SYS-01 #1)**: `orders.kadaluwarsa_pada` sebelumnya hanya ditulis tanpa ada yang menegakkan, sehingga `stok_direservasi` menggantung dan hanya bisa dikembalikan lewat Admin. Sekarang `php artisan orders:kedaluwarsa` (dijadwalkan tiap menit) membatalkan pesanan dengan `alasan_pembatalan` tercatat, melepas reservasi, menandai `payments` yang masih `belum_dibayar` atau `gagal` menjadi `kedaluwarsa`, menulis riwayat status, dan mengirim kabar. Pesanan `menunggu_validasi` tidak ikut tersapu karena batasnya adalah masa revisi desain; itu ditangani command terpisah (FR-SYS-01 #2).
- **Masa revisi punya penegak sendiri (FR-SYS-01 #2 + FR-CDSN-05 #8)**: `php artisan orders:revisi-kedaluwarsa` (dijadwalkan tiap menit) mencari order `menunggu_revisi_desain` yang `designs.batas_revisi_pada`-nya sudah lewat, membatalkannya dengan `alasan_pembatalan = "Masa revisi desain berakhir"`, menandai desain `kedaluwarsa`, melepas `stok_direservasi` tanpa menyentuh Stok Fisik, menulis riwayat dengan catatan `FR-SYS-01 #2`, lalu mengirim notifikasi + email. Order yang sudah dikirim ulang ke `menunggu_validasi` dilewati, dan menjalankan command dua kali tidak mengirim kabar dua kali.
- **Rute contoh sinkronisasi ditentukan `--tujuan`, bukan asal = tujuan**: `service` hanya keluar per rute, dan rute dalam satu kecamatan hanya memberi `CTC`/`JTR*`, bukan `REG`/`YES`. Tanpa `--tujuan` perintah tetap berjalan (perilaku lama) tetapi memberi peringatan, supaya alasan hasilnya tidak mewakili layanan antar kota terlihat langsung, bukan baru ketahuan setelah tabel terisi.
- **`filter=lowest` tidak dikirim ke RajaOngkir**: parameter itu sudah dicoba pada rute nyata dan diabaikan — tujuh layanan tetap dikembalikan. Membiarkannya di kode membuat pembaca mengira daftar tarif sudah disaring, padahal penyaringan yang benar dilakukan dengan `firstWhere('layanan', ...)` di `biayaOngkir()`. Yang disimpan tetap apa adanya dari API, sesuai `srs.md:809`.
- **Daftar kurir dan layanan berasal dari RajaOngkir, bukan daftar tulisan tangan (FR-CKOT-04 #2)**: `srs.md:228` dan `:289` menyatakan layanan kurir yang didukung datang dari API Kurir, dan `srs.md:809` melarang membangun komponen tarif sendiri. Karena RajaOngkir tidak punya endpoint "daftar layanan" — `service` seperti `REG`/`YES`/`OKE` hanya keluar dari `POST /calculate/domestic-cost` untuk satu rute tertentu — daftar diisi perintah `php artisan rajaongkir:sinkron-layanan`, bukan seeder. Yang tetap statis hanya **kode** kurir (17 kode dari dokumentasi RajaOngkir) karena itu memang kontrak, bukan hasil panggilan. Dokumen tetap tidak punya skenario menambah layanan dari aplikasi, jadi tidak ada route tambah; baris baru lahir `is_aktif = false` dan `is_aktif` baris lama tidak disentuh supaya sinkronisasi ulang tidak menghidupkan layanan yang sudah dimatikan Admin.
- **Keterbatasan sinkronisasi layanan**: perintah memakai satu rute contoh (berat 1000 g), jadi layanan yang hanya tersedia di rute lain bisa tidak ikut tersimpan. Berat tidak mengubah daftar layanan, hanya tarifnya. Rute contoh dipilih sekali dan tidak disimpan sebagai aturan bisnis.
- **Aktif dan nonaktif sebagai dua endpoint, bukan satu `PUT {is_aktif}`**: penolakan FR-CKOT-04 #4 hanya berlaku pada aksi mematikan layanan terakhir. Memisahkan URL membuat niat User terbaca dari route, bukan dari isi body yang bisa salah bentuk, dan pesan penolakan tidak pernah muncul saat Admin justru sedang mengaktifkan.
- **Penjagaan "minimal satu layanan aktif" hidup di controller**: aturan ini menghitung baris **lain**, sedangkan `CHECK` constraint PostgreSQL tidak bisa melakukannya. Konsekuensi yang diterima: dua permintaan nonaktif yang benar-benar bersamaan masih bisa menurunkan jumlah layanan aktif menjadi nol — hutang yang sama dengan keunikan nama kategori (lihat butir di atas), dan tidak ditutup dengan `lockForUpdate` karena menambah kunci tabel untuk keadaan yang belum pernah terjadi.
- **Alamat asal toko memakai `LokasiDitawarkan` yang sama dengan alamat User**: FR-CKOT-04 #1 secara eksplisit merujuk FR-PROF-01 #11/#12, jadi kode lokasi wajib berasal dari hasil `GET /lokasi` dan `label_lokasi` kiriman klien diabaikan. Tabelnya `shop_settings` dengan `CHECK (id = 1)`; karena `id` tidak ada di `$fillable`, baris pertama dibuat dengan `id = 1` ditulis eksplisit — `updateOrCreate(['id' => 1], ...)` akan membuang `id` saat insert dan gagal begitu urutan id sudah lewat dari 1.
- **Urutan daftar kurir**: `kode` lalu `layanan`, supaya layanan satu kurir berdekatan. Dokumen tidak mengatur urutan, jadi test memeriksa keanggotaan (`assertJsonFragment`), bukan indeks larik.

- **Transaksi Midtrans dipanggil langsung, tanpa mode simulasi**: `App\Services\Pembayaran\Midtrans` mengirim HTTP ke `app.sandbox.midtrans.com` (atau `app.midtrans.com` bila `MIDTRANS_IS_PRODUCTION=true`) memakai Basic auth `server_key:`. Tidak ada jalur "pura-pura lunas" dan tidak ada tarif/status yang dikarang saat gateway tidak tersedia — kalau gagal, pemanggil menerima `MidtransException` dan pesan ke User. Terbukti dari mesin ini: kunci palsu dijawab `401` oleh Midtrans sendiri, bukan oleh stub lokal.
- **Membuat transaksi Snap tidak diulang otomatis**: berbeda dari RajaOngkir, `POST /snap/v1/transactions` tidak idempoten — pengulangan setelah gangguan koneksi bisa membuat dua transaksi untuk satu pesanan. Jadi hanya `GET /v2/{order_id}/status` (FR-PAYM-01 #5) yang diulang sekali pada gangguan koneksi dan `5xx`.
- **`expiry` Midtrans mengikuti `orders.kadaluwarsa_pada`**: `start_time` dikirim dalam WIB (`+0700`, NFR-LOC-01) dan `duration` adalah sisa menit sampai batas bayar, sehingga gateway dan scheduler (FR-SYS-01 #1) kedaluwarsa pada saat yang sama. Bila batas itu sudah lewat, transaksi tidak dibuat sama sekali — tidak ada gunanya membuat transaksi yang lahir dalam keadaan kedaluwarsa.
- **`item_details` tidak dikirim ke Snap**: Midtrans menolak transaksi bila jumlah `item_details` tidak sama dengan `gross_amount`, sedangkan pesanan sudah punya `diskon_voucher` dan `ongkir` yang tidak dipecah per item. Yang dikirim hanya `transaction_details` (nomor pesanan + total) dan `customer_details`; totalnya tetap diambil dari `orders.total`, bukan dari browser.
- **`gross_amount` dari Midtrans diperiksa sebelum menjadi uang**: bentuk `"48000.00"` diterima, selain bilangan bulat rupiah ditolak (`MidtransException`). Angka dari pihak ketiga tidak boleh langsung masuk ke perbandingan nominal pesanan.
- **Baris `payments` lahir setelah Midtrans menjawab**: checkout tidak membuat baris (lihat butir `status_pembayaran`), dan controller pembayaran juga tidak membuatnya sebelum token diterima. Kalau Snap gagal, jawabannya `422` tanpa baris setengah jadi. Notifikasi nanti mencocokkan pesanan lewat `nomor_pesanan`, satu-satunya kunci yang dikirim ke Midtrans.
- **Membuka halaman pembayaran dua kali memakai token yang sama**: baris `belum_dibayar` yang masih punya `snap_token` dipakai ulang (`200`), sehingga Midtrans tidak menerima dua transaksi untuk satu pesanan. `redirect_url` hanya dikirim saat baris baru dibuat karena kolomnya tidak disimpan; pemanggilan ulang cukup memakai `snap_token` lewat Snap.js. Percobaan setelah `gagal` membuat baris baru, sesuai skenario #3 ("mencoba ulang metode lain").
- **Pesan penolakan pembayaran adalah pilihan implementasi**: skenario #1 hanya menetapkan keadaan ("pesanan produk jadi berstatus Menunggu Pembayaran") tanpa kalimat baku, jadi lima pesan di tabel endpoint dipilih sendiri dan tidak menambah keadaan yang tidak ada di dokumen.
- **Tautan pembayaran custom mengarah ke detail pesanan (FR-PAYM-02 #1)**: dokumen hanya meminta "link pembayaran" tanpa menetapkan URL; halaman `GET /pesanan/{nomor}` sudah memuat tombol bayar dan diamankan `auth` + `active`, jadi tidak dibuat route baru. Notifikasi dikirim setelah transaksi approval commit dan hanya bila order benar berstatus `menunggu_pembayaran`.
- **Alur bayar custom memakai jalur gateway yang sama (FR-PAYM-02 #2/#3)**: setelah approval order berstatus `menunggu_pembayaran`, endpoint Snap, webhook, dan `orders:kedaluwarsa` berlaku seperti produk jadi. Yang membedakan hanyalah asal status itu — desain harus `disetujui` dulu — dan SLA per item sudah disalin di checkout (`order_items.sla_hari`). Tes `PembayaranCustomAlurTest` mengunci ketiga keadaan ini tanpa menambah endpoint baru.
- **Batas 24 jam diperiksa di controller, bukan hanya di Midtrans**: pesanan yang `kadaluwarsa_pada`-nya sudah lewat dijawab `422` sebelum HTTP dikirim, bukan hanya menunggu scheduler membatalkannya. Tanpa ini, User bisa membayar pesanan yang seharusnya sudah dibatalkan.
- **Pesanan custom ditolak sampai desain disetujui (FR-PAYM-02)**: status `menunggu_validasi` dan `menunggu_revisi_desain` dijawab `422` dengan pesan yang menunjuk persetujuan desain. Sejak FR-CDSN-05 #2 menyentuh pesanan, approval memindahkan order ke `menunggu_pembayaran` sehingga endpoint ini dapat dipakai; notifikasi link pembayaran otomatis (FR-PAYM-02 #1) sudah dikirim saat approval dan mengarah ke halaman detail pesanan tempat tombol bayar berada.
- **`POST /pesanan/{nomor}/pembayaran` dibatasi 10/menit**: tiap permintaan baru bisa membuat transaksi di Midtrans (membuka ulang tidak, karena token dipakai ulang), sehingga percobaan yang terus gagal tidak boleh membanjiri gateway.
- **Pemetaan status Midtrans hanya ada di satu tempat**: `App\Services\Pembayaran\SinkronPembayaran` menerjemahkan `transaction_status` menjadi keadaan lokal, dan tombol "Cek Status Pembayaran" (FR-PAYM-01 #5) serta webhook (#2/#3/#4) memakai kelas yang sama, supaya status pesanan tidak mungkin berbeda tergantung jalur mana yang lebih dulu sampai.
- **`capture` dengan `fraud_status = challenge` belum dianggap lunas**: Midtrans belum menyatakan uangnya sah, jadi pembayaran tetap `belum_dibayar` dan stok belum turun. Hanya `settlement` atau `capture`+`accept` yang mengubah pesanan menjadi Diproses.
- **`expire` dari gateway langsung membatalkan pesanan**: skenario #4 menetapkan efek itu untuk scheduler, tetapi tombol cek status adalah tempat pertama di mana kedaluwarsa benar-benar terdeteksi. Sejak FR-SYS-01 #1 ada, `orders:kedaluwarsa` memakai efek pembatalan yang sama persis: pembayaran `kedaluwarsa`, pesanan `dibatalkan`, Stok Direservasi dilepas, Stok Fisik tidak disentuh.
- **Pesanan yang sudah dibatalkan tidak dihidupkan lagi oleh notifikasi terlambat**: uang yang tetap masuk setelah pembatalan adalah urusan refund (FR-PAYM-04, OPSIONAL) dan belum dibangun; menandai pesanan batal sebagai Diproses akan membuat stok dan status tidak lagi bisa dipercaya.
- **Cek status memeriksa `order_id` dan `gross_amount` sebelum mengubah apa pun**: jawaban gateway yang menyebut pesanan atau nominal lain dijawab `422` tanpa perubahan. Sama seperti butir `gross_amount` di atas, angka dari pihak ketiga tidak boleh langsung menjadi status pesanan.
- **Pesanan tanpa baris `payments` dijawab `422`**: pesan "Pembayaran untuk pesanan ini belum dibuat" adalah pilihan implementasi, karena FR-PAYM-01 #5 tidak mengatur keadaan ini. Yang tidak dilakukan adalah membuat tagihan baru diam-diam saat User menekan "Cek Status Pembayaran".
- **Setelah `lunas`, cek status tidak memanggil gateway lagi**: keadaan lokal sudah yang terbaru, sehingga menekan tombol berulang tidak memotong kuota dan tidak membuka peluang efek ganda. Sama halnya, `SinkronPembayaran` membaca ulang pesanan dan pembayaran di bawah `lockForUpdate` supaya tombol cek dan webhook yang datang bersamaan tidak memproses dua kali.
- **Webhook berada di luar grup `web`**: `routes/notifikasi-pembayaran.php` didaftarkan tanpa middleware `web`, jadi tidak ada sesi dan tidak ada token CSRF — Midtrans tidak punya keduanya. Keaslian diganti `signature_key = sha512(order_id + status_code + gross_amount + server_key)` yang dibandingkan dengan `hash_equals` (bukan `==`, agar waktu banding tidak membocorkan isi tanda tangan). Tanpa `server_key` terpasang, semua notifikasi ditolak.
- **Notifikasi ganda dijawab `200` tanpa efek**: `SinkronPembayaran::terapkan()` mengembalikan `diterapkan` selain status, sehingga pengiriman ulang dari Midtrans tidak menurunkan stok dua kali dan tidak mengirim email dua kali. Midtrans hanya berhenti mengulang bila menerima `2xx`, jadi permintaan ganda harus dijawab sukses, bukan `409`.
- **Kabar setiap perubahan status pembayaran lewat dua kanal**: `App\Services\Pembayaran\KabarPembayaran` menulis baris `notifications` (in-app) dan mengirim `PembayaranMail` (email) untuk tiga keadaan: `lunas` (FR-PAYM-01 #2), `gagal` (#3), dan `kedaluwarsa` (#4) — FR-ORDR-01 #3 mewajibkan kabar untuk setiap perubahan status pembayaran. `pending` tidak dikabarkan karena tidak mengubah apa pun. Kabar `gagal` menyebut batas pembayaran dalam WIB (NFR-LOC-01) dan menegaskan pesanan masih bisa dibayar dengan metode lain, bukan dibatalkan. Kabar untuk **perubahan status desain** sudah berjalan lewat `App\Services\Desain\KabarValidasiDesain` (FR-CDSN-05 #7: revisi berisi catatan + tautan editor), sedangkan kabar untuk **perubahan status pesanan oleh Admin** masih menunggu FR-ORDR-03/04.
- **Kabar Lunas mengikuti status pesanan**: FR-PAYM-01 #2 menetapkan efek Lunas adalah status Diproses. Bila notifikasi gateway baru sampai setelah scheduler membatalkan pesanan, pesanan tetap Dibatalkan (Lampiran B menyebutnya status akhir), jadi pesannya menyatakan pembayaran sudah diterima tetapi pesanan sudah dibatalkan — bukan klaim "sedang diproses" untuk pesanan yang tidak akan diproduksi. Refund belum otomatis karena FR-PAYM-04 (OPSIONAL) belum dibangun.
- **Kegagalan SMTP tidak membatalkan status pembayaran**: pengiriman email dibungkus `try/catch` + `report()`. Status `lunas` dan pengurangan stok sudah sah dari gateway; menggagalkannya karena server email mati akan membuat Midtrans mengulang notifikasi yang sebenarnya sudah diproses.
- **`transaction_id` dipakai mencocokkan baris `payments`, dengan cadangan baris terbaru**: satu pesanan boleh punya beberapa percobaan bayar (#3), dan notifikasi Midtrans menyebut `transaction_id`. Bila belum ada yang cocok (notifikasi pertama untuk percobaan terbaru), baris terbaru yang dipakai; kolomnya unik sehingga percobaan lain tidak bisa saling menimpa.
- **Webhook dibatasi 60/menit**: cukup untuk percobaan bayar berulang dalam satu pesanan, tetapi tetap menahan kiriman bertubi-tubi ke URL yang terbuka tanpa login.
- **Scheduler memakai efek kedaluwarsa yang sama dengan `expire` gateway**: `SinkronPembayaran::kedaluwarsakan()` dan cabang `expire` sama-sama memanggil satu metode pembatalan, jadi tidak mungkin scheduler melepas reservasi sementara webhook tidak (atau sebaliknya). Bedanya hanya pemicu dan catatan riwayatnya.
- **Jadwal tiap menit, bukan tiap jam**: `kadaluwarsa_pada` berpresisi detik dan kolomnya berindeks, jadi pemeriksaan murah; menunda sampai satu jam berarti pesanan bisa dibayar beberapa saat setelah seharusnya batal. `withoutOverlapping()` mencegah dua eksekusi berjalan bersamaan.
- **Pengembalian voucher dilewati**: FR-CKOT-02/03 (OPSIONAL) belum dibangun dan `orders.voucher_id` selalu kosong, sehingga tidak ada pemakaian yang bisa dikembalikan. `user-story.md:60` sudah menetapkan langkah ini dilewati sementara pembatalan tetap berjalan, jadi bukan aturan baru.
- **Kabar pembatalan dikirim setelah transaksi commit**: bila email dikirim di dalam transaksi lalu transaksi gagal, User sudah menerima kabar untuk pembatalan yang tidak pernah terjadi. `KabarPembayaran` juga menangkap kegagalan SMTP, sehingga pesanan tetap batal meski server email mati.
- **Checkout belum membuat baris `payments`**: scheduler menandai `kedaluwarsa` pada baris yang ada, dan tetap membatalkan pesanan bila barisnya belum lahir. Karena itu pesanan yang belum pernah dibuka halaman pembayarannya tidak mungkin lolos dari pembatalan hanya karena tidak punya tagihan.

## Aturan proyek yang tidak boleh dilanggar

- Pembayaran **hanya lewat gateway**; tidak ada pembayaran manual maupun status "Menunggu Verifikasi".
- Tidak ada Super Admin dan tidak ada pengelolaan akun Admin di dalam aplikasi. Akun Admin dibuat saat penyiapan sistem.
- Dashboard hanya untuk Admin (Ecommerce/SCM); User tidak pernah melihat dashboard.
- Stok hanya per varian/SKU: `stok_fisik`, `stok_direservasi`, dan `Tersedia = Fisik − Direservasi` dihitung, bukan disimpan.
- Kategori memakai `restrict`; produk harus dipindahkan dulu sebelum kategori dihapus.
- Laporan keuangan adalah laporan, bukan tabel.
- Setiap tabel memiliki timestamp; nama kolom bisnis memakai Bahasa Indonesia.

## Alur kerja Git

- `main` selalu hijau dan **hanya menerima perubahan lewat pull request**. Tidak ada push langsung ke `main`.
- Setiap pekerjaan dikerjakan di **satu branch aktif** bernama `<tipe>/<ringkasan>`, lalu di-push dan diajukan sebagai PR ke `main`.
- `<tipe>` mengikuti tipe Conventional Commits: `feat`, `fix`, `refactor`, `perf`, `docs`, `chore`, `test`. Contoh: `feat/kelola-produk`, `fix/relasi-order-alamat`, `refactor/struktur-produk`, `docs/alur-git`, `test/pesan-validasi`. Ringkasan ditulis huruf kecil dengan tanda hubung.
- Nama branch tidak memakai awalan modul (`produk/...`, `profil/...`). Tipe pekerjaan lebih informatif dan konsisten dengan pesan commit; modulnya sudah terlihat dari scope commit, misalnya `feat(produk): ...`.
- PR hanya di-merge setelah CI (`Pint + PHPUnit`) hijau, memakai **Create a merge commit** supaya batas pekerjaan tetap terlihat.
- Branch **langsung dihapus setelah PR di-merge**: klik *Delete branch* di GitHub, lalu di lokal `git branch -d <branch>` dan `git fetch --prune`. Riwayat tetap utuh di `main` lewat merge commit.
- Jangan membuka branch baru untuk setiap langkah kecil di dalam satu pekerjaan yang sama; satu pekerjaan = satu branch sampai PR-nya di-merge.
- Commit memakai Conventional Commits dan menyebut kode FR, misalnya `feat(auth): login ... (FR-AUTH-01, FR-AUTH-03)`.
- Rilis ditandai tag annotated: `v0.1.0` (skema), `v0.2.0` (auth inti), `v0.3.0` (reset password + role).
- `.env` dan `vendor/` tidak pernah di-commit.
- Setiap push dijalankan Pint + PHPUnit lewat GitHub Actions; `main` tidak boleh merah.

## Dokumen acuan

SRS v1.1 dan User Story v1.1.4 adalah satu-satunya sumber kebenaran untuk tabel, kolom, status, dan aturan bisnis. Dokumen tersebut tidak disertakan di repositori ini (folder `.opencode` diabaikan Git). Salinan Markdown untuk agen tersimpan di `.opencode/skills/nichibag-docs/references/`; salinan `srs.md` di mesin pengembang belum memuat Bab 5 Skenario Use Case yang menandai v1.1, jadi Bab 5 selalu dibaca dari dokumen resmi.
