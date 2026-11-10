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
| Autentikasi | FR-AUTH | ✅ Login, registrasi, verifikasi email, reset password, logout, middleware role |
| Katalog produk | FR-KTLG | ⏳ berikutnya |
| Keranjang & checkout | FR-CART | ⏳ |
| Pesanan & pembayaran | FR-ORDR, FR-PAYM | ⏳ |
| Desain kustom 2D | FR-DSN | ⏳ |
| Rantai pasok (SCM) | FR-SCM | ⏳ |
| Profil & alamat | FR-PROF | 🚧 pencarian lokasi, lihat & ubah profil selesai; ganti password, alamat, dan ganti email menyusul |
| Dashboard admin | FR-DASH | ⏳ ditunda sampai UI dimulai |

Catatan jujur: login Admin sudah berjalan, tetapi **dashboard belum ada**; sementara Admin diarahkan ke halaman utama. Verifikasi email baru dari halaman profil (FR-AUTH-06 #7) menunggu modul profil.

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
│   ├── Controllers/AuthController.php   # registrasi, verifikasi, login, reset password, logout
│   └── Middleware/                       # EnsureUserHasRole (lintas role),
│                                         # EnsureUserIsActive (akun Nonaktif)
├── Mail/
│   ├── VerifyEmailMail.php              # email verifikasi bertoken
│   └── ResetPasswordMail.php            # email reset password bertoken
└── Models/                              # 27 model sesuai skema SRS

bootstrap/app.php                        # alias middleware + handler JSON untuk tautan kedaluwarsa
database/migrations/                     # 30 migrasi
routes/web.php                           # seluruh route (session guard)
tests/Feature/                           # pengujian perilaku per kebutuhan
```

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

Status terakhir: **132 test lulus, 448 assertion** (dijalankan lokal dan otomatis di CI).

Setiap push dan pull request ke `main` menjalankan `.github/workflows/tests.yml` di GitHub Actions: PHP 8.5, PostgreSQL 18, pemeriksaan format Laravel Pint, lalu seluruh test. Pekerjaan dianggap selesai hanya kalau badge CI hijau.

Keamanan sesi: password yang berubah (reset) langsung memutus sesi lain lewat `AuthenticateSession`, dan akun Nonaktif ditolak middleware `active` walaupun masih memegang cookie lama. Waktu respons login disamakan dengan hash palsu saat email tidak ditemukan, dan email reset/verifikasi dikirim setelah respons terkirim, supaya waktu respons tidak membocorkan email mana yang terdaftar.

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
| GET | `/products` | — | — | Scaffolding katalog, belum final |

### Keputusan di luar dokumen

SRS dan User Story tidak mengatur hal-hal berikut; nilainya dipilih saat implementasi dan boleh berubah tanpa mengubah aturan bisnis:

- **Format nomor telepon**: wajib diawali `+62` lalu 9–12 angka. Nomor gaya `08...` **ditolak**, bukan dinormalkan. Pesan errornya tetap kutipan dokumen, `"Format nomor telepon tidak valid"` (FR-PROF-01 #3). Aturan yang sama dipakai saat registrasi supaya nomor yang tersimpan tidak ditolak ketika User menyimpan profilnya.
- **Foto profil**: hanya **ditampilkan** kalau kolomnya terisi (FR-PROF-01 #1). Tidak ada endpoint unggah karena dokumen tidak memuat skenario unggah; tidak ada upload untuk Admin juga.
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

- `main` selalu hijau; pekerjaan dilakukan di branch `feat/*`, lalu di-merge dengan `--no-ff`.
- Commit memakai Conventional Commits dan menyebut kode FR, misalnya `feat(auth): login ... (FR-AUTH-01, FR-AUTH-03)`.
- Rilis ditandai tag annotated: `v0.1.0` (skema), `v0.2.0` (auth inti), `v0.3.0` (reset password + role).
- `.env` dan `vendor/` tidak pernah di-commit.
- Setiap push dijalankan Pint + PHPUnit lewat GitHub Actions; `main` tidak boleh merah.

## Dokumen acuan

SRS v1.0 dan User Story v1.1.4 adalah satu-satunya sumber kebenaran untuk tabel, kolom, status, dan aturan bisnis. Dokumen tersebut tidak disertakan di repositori ini.
