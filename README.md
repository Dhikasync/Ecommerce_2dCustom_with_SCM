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
| Katalog produk | FR-KTLG | ⏳ berikutnya |
| Keranjang & checkout | FR-CART | ⏳ |
| Pesanan & pembayaran | FR-ORDR, FR-PAYM | ⏳ |
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
│   └── Middleware/
│       └── Auth/
│           ├── EnsureUserHasRole.php    # pembatasan akses per role (FR-AUTH-01 #7)
│           └── EnsureUserIsActive.php   # akun Nonaktif ditolak (FR-AUTH-01 #6)
├── Exceptions/
│   └── Profil/RajaOngkirException.php   # kegagalan layanan pencarian lokasi
├── Mail/
│   └── Auth/
│       ├── VerifyEmailMail.php          # email verifikasi bertoken
│       ├── VerifyNewEmailMail.php       # tautan konfirmasi email baru (FR-AUTH-06 #7)
│       ├── EmailChangeNoticeMail.php    # pemberitahuan ke email lama
│       └── ResetPasswordMail.php        # email reset password bertoken
├── Rules/NomorTelepon.php               # format nomor telepon +62 (dipakai auth + profil)
├── Services/
│   └── Profil/RajaOngkir.php            # client pencarian lokasi
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
routes/web.php                           # halaman utama
tests/Feature/                           # pengujian perilaku per kebutuhan
```

Aturan penataan: file dikelompokkan per modul SRS ke **subfolder di dalam folder Laravel yang sudah ada** (`Controllers/Auth`, `Models/Auth`, `Mail/Auth`, `Middleware/Auth`, `database/factories/Auth`) - tidak ada folder top-level baru. Migrasi sengaja **tetap flat** di `database/migrations/` dan nama filenya tidak pernah diubah, karena tabel `migrations` mencatat nama file yang sudah dijalankan; mengubah nama/timestamp membuat migrasi lama dianggap belum jalan.

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
```

Lalu:

```bash
php artisan migrate
php artisan serve          # http://127.0.0.1:8000
```

## Pengujian

```bash
php artisan test
```

Pengujian berjalan di basis data terpisah `nichibag_test` (diatur di `phpunit.xml`), memakai PostgreSQL — bukan SQLite — supaya constraint dan tipe kolom yang dipakai produksi ikut teruji.

Status terakhir: **188 test lulus, 671 assertion** (dijalankan lokal dan otomatis di CI).

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
| GET | `/lokasi?search=` | `lokasi.cari` | `auth`, `active`, throttle 30/menit | FR-PROF-01 #4/#11/#12, UC-08 — cari kecamatan/kelurahan lewat RajaOngkir |
| GET | `/profil` | `profil.tampil` | `auth`, `active` | FR-PROF-01 #1 — nama, email, nomor telepon, foto profil (jika ada) |
| PUT | `/profil` | `profil.ubah` | `auth`, `active` | FR-PROF-01 #2/#3/#10 — ubah nama dan nomor telepon |
| PUT | `/profil/password` | `profil.password.ubah` | `auth`, `active`, throttle 6/menit | FR-PROF-01 #9 — ubah password dengan verifikasi password lama |
| GET | `/profil/alamat` | `alamat.index` | `auth`, `active` | FR-PROF-01 #1 — daftar alamat milik sendiri (utama lebih dulu) |
| POST | `/profil/alamat` | `alamat.store` | `auth`, `active` | FR-PROF-01 #4 — tambah alamat dengan lokasi hasil pencarian |
| PUT | `/profil/alamat/{id}` | `alamat.update` | `auth`, `active` | FR-PROF-01 #5 — ubah alamat |
| DELETE | `/profil/alamat/{id}` | `alamat.destroy` | `auth`, `active` | FR-PROF-01 #6 — hapus alamat (hanya jika tersisa lebih dari satu) |
| PUT | `/profil/alamat/{id}/utama` | `alamat.utama` | `auth`, `active` | FR-PROF-01 #7 — tandai alamat utama |
| PUT | `/profil/email` | `profil.email.ajukan` | `auth`, `active`, throttle 6/menit | FR-PROF-01 #8 — ajukan email baru; email lama tetap berlaku |
| GET | `/profil/email/verify/{id}/{hash}` | `profil.email.verify` | `signed`, throttle 6/menit | FR-AUTH-06 #7 — konfirmasi email baru (tautan 24 jam, sekali pakai) |
| GET | `/products` | — | — | Scaffolding katalog, belum final |

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
- **Pesan validasi alamat**: dokumen hanya menetapkan pesan format nomor telepon. Pesan `"Nama penerima wajib diisi"`, `"Nomor telepon wajib diisi"`, `"Alamat lengkap wajib diisi"`, dan `"Lokasi wajib dipilih"` mengikuti pola `"Nama wajib diisi"` (#10).
- **Alamat milik User lain dijawab `404`** (`"Alamat tidak ditemukan"`), bukan `403`, supaya keberadaan alamat orang lain tidak terbuka (NFR-SEC-03).
- **Batas waktu dan throttle RajaOngkir**: 8 detik (4 detik koneksi), 30 permintaan/menit untuk `/lokasi`.

## Aturan proyek yang tidak boleh dilanggar

- Pembayaran **hanya lewat gateway**; tidak ada pembayaran manual maupun status "Menunggu Verifikasi".
- Tidak ada Super Admin dan tidak ada pengelolaan akun Admin di dalam aplikasi. Akun Admin dibuat saat penyiapan sistem.
- Dashboard hanya untuk Admin (Ecommerce/SCM); User tidak pernah melihat dashboard.
- Stok hanya per varian/SKU: `stok_fisik`, `stok_direservasi`, dan `Tersedia = Fisik − Direservasi` dihitung, bukan disimpan.
- Kategori memakai `restrict`; produk harus dipindahkan dulu sebelum kategori dihapus.
- Laporan keuangan adalah laporan, bukan tabel.
- Setiap tabel memiliki timestamp; nama kolom bisnis memakai Bahasa Indonesia.

## Alur kerja Git

- `main` selalu hijau; pekerjaan dilakukan di **branch fitur** bernama `<modul>/<fitur>` (misalnya `auth/login`, `profil/alamat`), lalu di-merge dengan `--no-ff`.
- Satu branch per fitur, test ikut di dalamnya — tidak ada branch test terpisah.
- Branch fitur **tidak dihapus** setelah di-merge, supaya riwayat per fitur tetap bisa dilihat.
- Commit memakai Conventional Commits dan menyebut kode FR, misalnya `feat(auth): login ... (FR-AUTH-01, FR-AUTH-03)`.
- Rilis ditandai tag annotated: `v0.1.0` (skema), `v0.2.0` (auth inti), `v0.3.0` (reset password + role).
- `.env` dan `vendor/` tidak pernah di-commit.
- Setiap push dijalankan Pint + PHPUnit lewat GitHub Actions; `main` tidak boleh merah.

## Dokumen acuan

SRS v1.0 dan User Story v1.1.4 adalah satu-satunya sumber kebenaran untuk tabel, kolom, status, dan aturan bisnis. Dokumen tersebut tidak disertakan di repositori ini.
