# Nichibag — Marketplace Toko Nichibag

Backend marketplace untuk **Toko Nichibag**: penjualan tas dengan **desain kustom 2D** dan **integrasi rantai pasok** (bahan baku, distributor, purchase order, penerimaan barang).

Proyek ini adalah bagian dari tugas akhir dan dibangun bertahap mengikuti **SRS v1.0** dan **User Story v1.1.4**. Saat ini fokusnya backend JSON; antarmuka belum dibangun.

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
| Keranjang & checkout | FR-CART, FR-CKOT | 🔄 berjalan: FR-CART-01 kelola keranjang, FR-CART-02 ringkasan (subtotal, label "Stok tidak mencukupi"/"Produk tidak tersedia", `bisa_checkout`), FR-CKOT-04 kurir + alamat asal toko, FR-CKOT-01 checkout (`GET /checkout`, `POST /checkout/ongkir`, `POST /checkout`: reservasi stok, pesanan Menunggu Pembayaran/Menunggu Validasi, salinan alamat). Sisi custom masih dummy (`design_id` diterima tanpa aturan "hanya dari preview") sampai FR-CDSN-04 |
| Pesanan & pembayaran | FR-ORDR, FR-PAYM | ⏳ pesanan sudah dibentuk lewat FR-CKOT-01 (status, salinan alamat, reservasi stok); daftar/detail pesanan, pembayaran Midtrans, dan pembatalan belum ada |
| Desain kustom 2D | FR-DSN | ⏳ |
| Rantai pasok (SCM) | FR-SCM | ⏳ |
| Profil & alamat | FR-PROF | ✅ semua skenario: lihat/ubah profil, ganti email, ubah password, cari lokasi, kelola alamat + alamat utama. Penjagaan hapus alamat yang dipakai pesanan berjalan menunggu modul pesanan (`srs.md:378`) |
| Dashboard admin | FR-DASH | ⏳ ditunda sampai UI dimulai |

Catatan jujur: login Admin sudah berjalan, tetapi **dashboard belum ada**; sementara Admin diarahkan ke halaman utama sesuai `user-story.md:19`. Modul profil masih berjalan — lihat baris FR-PROF.

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
│   └── Middleware/
│       └── Auth/
│           ├── EnsureUserHasRole.php    # pembatasan akses per role (FR-AUTH-01 #7)
│           └── EnsureUserIsActive.php   # akun Nonaktif ditolak (FR-AUTH-01 #6)
├── Exceptions/
│   ├── Checkout/
│   │   ├── KeranjangBerubah.php         # item bermasalah saat pesanan dibentuk (#7)
│   │   └── OngkirTidakTersedia.php      # ongkir gagal dihitung (#8)
│   └── Profil/RajaOngkirException.php   # kegagalan layanan pencarian lokasi dan tarif
├── Mail/
│   └── Auth/
│       ├── VerifyEmailMail.php          # email verifikasi bertoken
│       ├── VerifyNewEmailMail.php       # tautan konfirmasi email baru (FR-AUTH-06 #7)
│       ├── EmailChangeNoticeMail.php    # pemberitahuan ke email lama
│       └── ResetPasswordMail.php        # email reset password bertoken
├── Console/Commands/
│   └── SinkronLayananKurir.php          # isi layanan kurir dari RajaOngkir (FR-CKOT-04)
├── Rules/NomorTelepon.php               # format nomor telepon +62 (dipakai auth + profil)
├── Support/
│   ├── GambarPublik.php                 # URL gambar dari disk publik
│   └── MasalahItemKeranjang.php         # label masalah item, dipakai keranjang + checkout
├── Services/
│   └── Profil/
│       ├── LokasiDitawarkan.php         # ingatan hasil pencarian lokasi (24 jam)
│       └── RajaOngkir.php               # client RajaOngkir: pencarian lokasi + tarif
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
php artisan rajaongkir:sinkron-layanan              # asal dari pengaturan pengiriman
php artisan rajaongkir:sinkron-layanan --asal=4816  # atau pakai kode lokasi tertentu
php artisan rajaongkir:sinkron-layanan --kurir=jne,pos  # batasi ke sebagian kurir
```

Perintah ini memakai kuota RajaOngkir (satu permintaan per kurir) dan butuh jaringan.
Layanan yang didapat tersimpan sebagai **nonaktif**; Admin memilih mana yang ditawarkan.

## Pengujian

```bash
php artisan test
```

Pengujian berjalan di basis data terpisah `nichibag_test` (diatur di `phpunit.xml`), memakai PostgreSQL — bukan SQLite — supaya constraint dan tipe kolom yang dipakai produksi ikut teruji.

Status terakhir: **273 test lulus, 944 assertion** (dijalankan lokal dan otomatis di CI).

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
| POST | `/keranjang/item` | `keranjang.item.store` | `auth`, `active` | FR-CART-01 #1/#2 - tambah item: `variant_id`, `quantity`, `design_id` (custom). Varian sama digabung. `422` "Minimal pemesanan untuk varian ini adalah {moq} pcs" / "Stok tidak mencukupi" / "Produk tidak tersedia" / "Produk custom harus ditambahkan dari preview desain"; `404` "Desain tidak ditemukan" |
| PATCH | `/keranjang/item/{id}` | `keranjang.item.update` | `auth`, `active` | FR-CART-01 #3 - ubah `quantity` saja; MOQ dan Stok Tersedia diperiksa ulang, `harga_satuan` tidak berubah. `404` "Item keranjang tidak ditemukan" untuk item User lain |
| DELETE | `/keranjang/item/{id}` | `keranjang.item.destroy` | `auth`, `active` | FR-CART-01 #4 - hapus item; `404` "Item keranjang tidak ditemukan" untuk item User lain |
| GET | `/checkout?item_ids[]=` | `checkout.tampil` | `auth`, `active` | FR-CKOT-01 #2/#3/#6 + FR-CART-01 #4/#5 - ringkasan sebelum pesanan dibentuk: `items`, `subtotal_barang`, `berat_total`, `alamat.dipilih` (alamat utama **hanya** bila lokasinya terisi) + `alamat.daftar` (`bisa_dipakai`, `catatan` "Lengkapi lokasi"), `kurir` aktif saja, `sla_hari` + `estimasi_sla`. `422` "Pilih minimal satu item untuk di-checkout" / "Produk jadi dan produk custom di-checkout terpisah, selesaikan salah satunya terlebih dahulu"; `404` "Item keranjang tidak ditemukan" |
| POST | `/checkout/ongkir` | `checkout.ongkir` | `auth`, `active` | FR-CKOT-01 #3/#8 - `item_ids[]`, `address_id`, `courier_id` → `subtotal_barang`, `berat_total`, `ongkir`, `total`. `422` "Alamat dan kurir wajib dipilih" (termasuk alamat tanpa lokasi, kurir nonaktif, atau alamat User lain) / "Ongkir belum dapat dihitung, silakan coba lagi" |
| POST | `/checkout` | `checkout.buat` | `auth`, `active` | FR-CKOT-01 #1/#4/#5/#7 - buat pesanan: status `menunggu_pembayaran` (jadi, `kadaluwarsa_pada` +24 jam) atau `menunggu_validasi` (custom), `status_pembayaran` "belum_dibayar", `status_desain` "tidak_ada"/"menunggu_validasi", salinan alamat, Stok Direservasi naik, item terpilih keluar dari keranjang. `ongkir` dan `total` dari body diabaikan. `422` "Keranjang berubah, periksa kembali item Anda" + `data.items[].label` |
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
| POST | `/admin/produk` | `produk.store` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #1/#2 — tambah produk; multipart `gambar[]` JPG/PNG maks 2 MB per file |
| PUT | `/admin/produk/{id}` | `produk.update` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #4/#6 — ubah produk, ganti foto utama, ubah status Aktif/Nonaktif |
| DELETE | `/admin/produk/{id}` | `produk.destroy` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #5 - hapus; ditolak kalau masih ada pesanan aktif atau variannya masih berstok |
| DELETE | `/admin/produk/{id}/gambar/{gambarId}` | `produk.gambar.destroy` | `auth`, `active`, `role:admin_ecommerce` | Hapus satu foto galeri (foto utama diganti lewat ubah produk) |
| GET | `/admin/produk/{id}/varian` | `varian.index` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #3 — daftar varian + Stok Tersedia hasil hitung |
| POST | `/admin/produk/{id}/varian` | `varian.store` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #3/#8 — tambah varian berstok 0; SKU duplikat ditolak |
| PUT | `/admin/produk/{id}/varian/{varianId}` | `varian.update` | `auth`, `active`, `role:admin_ecommerce` | FR-PROD-01 #3 + FR-PROD-03 #1 — ubah varian/MOQ; stok tidak ikut berubah |
| DELETE | `/admin/produk/{id}/varian/{varianId}` | `varian.destroy` | `auth`, `active`, `role:admin_ecommerce` | Hapus varian; ditolak kalau masih berstok atau punya pesanan aktif |

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
- **Produk custom selalu lahir Nonaktif (FR-PROD-01 #7)**: `user-story:222` meminta produk custom dikaitkan ke template desain, tetapi `templates.product_id` membuat template baru bisa ada **setelah** produknya. Database juga menolak produk custom aktif tanpa `sla_hari` (`products_custom_aktif_check`). Karena itu form produk tidak menerima template/SLA; aktivasi produk custom menunggu FR-CDSN-01 (template) dan FR-CDSN-07 (SLA).
- **Aturan Nonaktif hanya untuk custom baru, bukan custom yang sudah aktif**: `ProductController::validasi()` menerima produk yang sedang diubah supaya bisa membedakan keduanya. Tanpa itu, memperbaiki nama atau harga produk custom yang sudah aktif akan mematikannya. Pengecualiannya sempit: hanya produk yang **sudah** custom **dan sudah** aktif. Mengubah produk jadi menjadi custom, atau mengaktifkan custom lewat form, tetap menghasilkan Nonaktif — memaksa Nonaktif pada custom aktif justru akan membuat Admin tidak bisa memperbaiki datanya tanpa menurunkan produk dari katalog.
- **Gambar produk tanpa batas jumlah**: dokumen hanya menetapkan JPG/PNG maks 2 MB per file (NFR-FIL-01) dan tidak menyebut jumlah. Berkas pertama menjadi `products.gambar` (foto kartu katalog), sisanya masuk `product_images` sebagai galeri dengan `urutan` 1..n. Batas teknis tetap ada di PHP (`upload_max_filesize`, `max_file_uploads`, `post_max_size`), bukan di aturan aplikasi.
- **Gambar tidak ditautkan ke varian**: tabel `product_images` hanya punya `product_id`, jadi "banyak foto per warna" belum bisa diwakili tanpa migrasi baru. FR-KTLG-02 tetap memakai galeri per produk karena dokumen tidak meminta foto per varian.
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
- **Sisi custom masih dummy**: FR-CART-01 #2 mensyaratkan item custom hanya masuk dari Preview Desain (FR-CDSN-04), tetapi modul itu belum dibangun. Untuk sekarang `POST /keranjang/item` menerima `design_id` dan memeriksa bahwa desain itu milik User sendiri dan variannya cocok (`404` "Desain tidak ditemukan"), tanpa memeriksa status atau asal preview. Aturan "hanya dari preview" ditambahkan saat FR-CDSN-04 dikerjakan.
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
- **SLA pesanan memakai yang terpanjang**: FR-CDSN-06 menetapkan estimasi per produk custom, sedangkan checkout bisa berisi beberapa produk. Produksi baru selesai setelah item paling lama ikut selesai, jadi respons memakai `max(sla_hari)` dan `order_items.sla_hari` menyimpan salinan per item (FR-CDSN-07 #3: pesanan berjalan tidak ikut berubah saat SLA produk diubah).
- **Pesanan custom tanpa `kadaluwarsa_pada`**: batas 24 jam hanya berlaku pada "Menunggu Pembayaran" (Lampiran A, `srs.md:822`), sedangkan pesanan custom lahir "Menunggu Validasi" dan baru masuk batas bayar setelah desain disetujui (FR-PAYM-02).
- **Daftar kurir dan layanan berasal dari RajaOngkir, bukan daftar tulisan tangan (FR-CKOT-04 #2)**: `srs.md:228` dan `:289` menyatakan layanan kurir yang didukung datang dari API Kurir, dan `srs.md:809` melarang membangun komponen tarif sendiri. Karena RajaOngkir tidak punya endpoint "daftar layanan" — `service` seperti `REG`/`YES`/`OKE` hanya keluar dari `POST /calculate/domestic-cost` untuk satu rute tertentu — daftar diisi perintah `php artisan rajaongkir:sinkron-layanan`, bukan seeder. Yang tetap statis hanya **kode** kurir (17 kode dari dokumentasi RajaOngkir) karena itu memang kontrak, bukan hasil panggilan. Dokumen tetap tidak punya skenario menambah layanan dari aplikasi, jadi tidak ada route tambah; baris baru lahir `is_aktif = false` dan `is_aktif` baris lama tidak disentuh supaya sinkronisasi ulang tidak menghidupkan layanan yang sudah dimatikan Admin.
- **Keterbatasan sinkronisasi layanan**: perintah memakai satu rute contoh (asal ke asal, berat 1000 g, tarif termurah), jadi layanan yang hanya tersedia di sebagian rute bisa tidak ikut tersimpan. Berat tidak mengubah daftar layanan, hanya tarifnya. Rute contoh dipilih sekali dan tidak disimpan sebagai aturan bisnis.
- **Aktif dan nonaktif sebagai dua endpoint, bukan satu `PUT {is_aktif}`**: penolakan FR-CKOT-04 #4 hanya berlaku pada aksi mematikan layanan terakhir. Memisahkan URL membuat niat User terbaca dari route, bukan dari isi body yang bisa salah bentuk, dan pesan penolakan tidak pernah muncul saat Admin justru sedang mengaktifkan.
- **Penjagaan "minimal satu layanan aktif" hidup di controller**: aturan ini menghitung baris **lain**, sedangkan `CHECK` constraint PostgreSQL tidak bisa melakukannya. Konsekuensi yang diterima: dua permintaan nonaktif yang benar-benar bersamaan masih bisa menurunkan jumlah layanan aktif menjadi nol — hutang yang sama dengan keunikan nama kategori (lihat butir di atas), dan tidak ditutup dengan `lockForUpdate` karena menambah kunci tabel untuk keadaan yang belum pernah terjadi.
- **Alamat asal toko memakai `LokasiDitawarkan` yang sama dengan alamat User**: FR-CKOT-04 #1 secara eksplisit merujuk FR-PROF-01 #11/#12, jadi kode lokasi wajib berasal dari hasil `GET /lokasi` dan `label_lokasi` kiriman klien diabaikan. Tabelnya `shop_settings` dengan `CHECK (id = 1)`; karena `id` tidak ada di `$fillable`, baris pertama dibuat dengan `id = 1` ditulis eksplisit — `updateOrCreate(['id' => 1], ...)` akan membuang `id` saat insert dan gagal begitu urutan id sudah lewat dari 1.
- **Urutan daftar kurir**: `kode` lalu `layanan`, supaya layanan satu kurir berdekatan. Dokumen tidak mengatur urutan, jadi test memeriksa keanggotaan (`assertJsonFragment`), bukan indeks larik.

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

SRS v1.0 dan User Story v1.1.4 adalah satu-satunya sumber kebenaran untuk tabel, kolom, status, dan aturan bisnis. Dokumen tersebut tidak disertakan di repositori ini.
