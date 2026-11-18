# Panduan Kontribusi

> **Ringkasan Inggris** — Contribution guide for the Nichibag marketplace.
> Business rules come only from SRS v1.1 and User Story v1.1.4; do not invent
> tables, fields, statuses, or messages. Work on one `<type>/<summary>` branch,
> open a pull request to `main`, and merge only after CI (Pint + PHPUnit on
> PostgreSQL) is green. Never commit `.env` or `vendor/`.

Berkas ini menjelaskan cara menyiapkan lingkungan, mengerjakan perubahan, dan
mengajukan pull request. Aturan main proyek yang lebih lengkap ada di
[`AGENTS.md`](AGENTS.md) dan bagian *Aturan proyek yang tidak boleh dilanggar*
serta *Alur kerja Git* di [`README.md`](README.md).

## Sebelum menulis kode

1. Baca [`README.md`](README.md) — status modul, endpoint, dan keputusan yang
   sudah diambil.
2. Pahami sumber kebenaran: **SRS v1.1** dan **User Story v1.1.4**. Kedua
   dokumen itu tidak disertakan di repositori ini karena berisi naskah tugas
   akhir.
3. Jangan mengarang tabel, kolom, status, aturan bisnis, atau kalimat pesan
   yang tidak ada di dokumen. Kalau aturannya tidak ada di sana, tanyakan dulu
   kepada pemelihara sebelum menulis kode.

## Lingkungan pengembangan

| Kebutuhan | Versi |
| --- | --- |
| PHP | 8.5 di CI; `composer.json` menerima `^8.3` |
| Ekstensi PHP | `pdo_pgsql`, `pgsql`, `mbstring` |
| Composer | 2.x |
| PostgreSQL | 18 di CI; README meminta 15+ |

Pengujian **tidak boleh** memakai SQLite. Seluruh test berjalan di PostgreSQL
(`nichibag_test`) supaya constraint, tipe kolom, dan indeks parsial yang dipakai
produksi ikut teruji.

## Menyiapkan proyek

```bash
git clone https://github.com/Dhikasync/Ecommerce_2dCustom_with_SCM.git
cd Ecommerce_2dCustom_with_SCM
composer install
copy .env.example .env          # Windows; pakai cp di Linux/macOS
php artisan key:generate
```

Buat dua basis data, lalu sesuaikan `.env`:

```sql
CREATE DATABASE nichibag_db;
CREATE DATABASE nichibag_test;
```

```bash
php artisan migrate
php artisan storage:link
```

Rincian pengisian `.env` (termasuk `RAJAONGKIR_API_KEY` dan kunci Midtrans) ada
di bagian *Menjalankan di lokal* pada [`README.md`](README.md).

## Menjalankan pemeriksaan

```bash
php artisan test                 # seluruh suite
php artisan test --filter=NamaTest
./vendor/bin/pint                # rapikan format
./vendor/bin/pint --test         # periksa format saja, sama seperti CI
```

Tiga hal yang sering membingungkan saat pertama kali:

- Kalau test gagal dengan `SQLSTATE[08006] ... the database system is starting
  up`, itu PostgreSQL yang belum siap, bukan bug kode. Nyalakan layanan lalu
  ulangi.
- Kuota RajaOngkir terbatas (100 panggilan). Test memakai `Http::fake`, jadi
  jangan menambahkan test yang benar-benar memanggil layanan itu.
- Jangan pernah memakai kunci gateway asli di test. Kunci sandbox pun tidak
  boleh di-commit.

## Alur kerja Git

1. **Satu pekerjaan, satu branch.** Nama branch: `<tipe>/<ringkasan>`, dengan
   `<tipe>` mengikuti Conventional Commits — `feat`, `fix`, `refactor`, `perf`,
   `docs`, `chore`, `test`. Contoh: `feat/kelola-produk`,
   `fix/relasi-order-alamat`, `docs/berkas-komunitas`. Tanpa awalan modul.
2. **Commit memakai Conventional Commits** dan menyebut kode FR, misalnya
   `feat(auth): login admin dan user (FR-AUTH-01)`. Satu commit = satu maksud
   yang bisa dijelaskan.
3. **Push branch**, lalu buka pull request ke `main` memakai template yang
   tersedia.
4. **Merge hanya kalau CI hijau** (Pint + PHPUnit di PostgreSQL), memakai
   *Create a merge commit*. Jangan pernah push langsung ke `main` dan jangan
   pernah force push.
5. **Hapus branch setelah merge**: *Delete branch* di GitHub, lalu di lokal
   `git branch -d <branch>` dan `git fetch --prune`.

## Yang tidak boleh masuk repositori

- `.env`, `.env.backup`, kunci gateway, atau token apa pun.
- `vendor/` dan `node_modules/`.
- Berkas hasil build (`public/build`, `public/hot`).
- Berkas dokumen acuan (SRS/User Story) beserta folder `.opencode/` — sudah
  diabaikan Git.

## Mengubah perilaku yang diatur dokumen

Kalau perubahanmu menyentuh aturan bisnis, urutannya: **dokumen dulu, kode
kemudian**. Perubahan aturan dibahas dengan pemelihara, lalu kode dan test
disesuaikan. Ini mencegah kode dan dokumen bercerita berbeda — README wajib
jujur soal status, termasuk bagian yang belum dibangun.

## Melaporkan bug dan mengusulkan fitur

- Bug dan usulan: pakai template issue yang tersedia di
  [`.github/ISSUE_TEMPLATE`](.github/ISSUE_TEMPLATE).
- Kerentanan keamanan: **jangan** lewat issue publik. Ikuti
  [`SECURITY.md`](SECURITY.md).
- Perilaku yang melanggar aturan komunitas: ikuti
  [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

## Daftar periksa sebelum membuka pull request

- [ ] `php artisan test` lulus seluruhnya, bukan hanya test yang baru ditulis.
- [ ] `./vendor/bin/pint --test` bersih.
- [ ] Test baru ditulis **gagal dulu**, baru dibuat lulus (bukti bahwa test-nya menguji sesuatu).
- [ ] Tidak ada `.env`, `vendor/`, kunci, atau token di diff.
- [ ] Pesan penolakan/validasi yang berasal dari dokumen dikutip persis.
- [ ] Status di README sesuai kenyataan; tidak ada yang dinaikkan tanpa bukti.
- [ ] Migration baru bisa di-rollback dan tidak merusak data lama.
- [ ] Pull request menyebut kode FR dan nomor skenario yang dikerjakan.

## Lisensi kontribusi

Proyek ini memakai lisensi [MIT](LICENSE). Dengan mengirim pull request, kamu
setuju kontribusimu dilisensikan dengan ketentuan yang sama.
