# Kebijakan Keamanan

> **Ringkasan Inggris** — Security policy for the Nichibag marketplace. Please
> report vulnerabilities privately to **GANTI_EMAIL_KEAMANAN**, never in a
> public issue. Do not test against real user data, and do not run denial of
> service or mail-spam tests. This document describes what counts as a
> vulnerability, what does not, and how a report is handled.

> **Catatan untuk pemelihara:** ganti `GANTI_EMAIL_KEAMANAN` di berkas ini dan di
> `CODE_OF_CONDUCT.md` dengan alamat email asli sebelum PR di-merge. Placeholder
> ini sengaja ditulis dengan huruf kapital semua supaya tidak lolos tanpa sengaja.

## Versi yang didukung

Perbaikan keamanan hanya diberikan untuk `main`.

| Versi | Didukung |
| --- | --- |
| `main` (rilis terbaru) | Ya |
| Tag rilis lama (`v0.1.0`, `v0.2.0`, `v0.3.0`) | Tidak — naikkan ke `main` |
| Branch pekerjaan yang belum di-merge | Tidak |

## Cara melapor

Kirim email ke **GANTI_EMAIL_KEAMANAN** dengan subjek
`[KEAMANAN] <ringkasan singkat>`. Jangan membuka issue publik, jangan menulis
detail teknis di diskusi, dan jangan mengirim proof-of-concept ke kanal umum —
laporan yang terbuka sebelum diperbaiki membahayakan pengguna.

Isi laporan yang membantu:

- Bagian yang terdampak (rute, controller, atau berkas) dan kode FR terkait bila ada.
- Langkah reproduksi yang bisa diulang dari instalasi bersih.
- Dampak nyata: apa yang bisa dibaca, diubah, atau dilewati oleh penyerang.
- Versi PHP dan PostgreSQL yang kamu pakai.
- Saran perbaikan bila ada (tidak wajib).

## Batas pengujian yang diizinkan

- Uji hanya di **instalasi milikmu sendiri** dengan data uji, bukan di server
  bersama dan bukan pada data pengguna lain.
- Jangan melakukan uji beban, pemindaian agresif, atau apa pun yang bisa
  menjatuhkan layanan (denial of service).
- Jangan mengirim email massal lewat fitur verifikasi/reset password.
- Jangan memakai kunci gateway asli untuk pengujian. Pakai kunci sandbox
  Midtrans; kuota RajaOngkir terbatas dan setiap panggilan memotong kuota.

## Yang dianggap kerentanan pada proyek ini

- Bypass autentikasi atau otorisasi: melewati middleware `auth`, `active`,
  `role`, atau membaca pesanan/alamat milik User lain (`NFR-SEC-03`).
- Eskalasi peran: menjadikan akun `user` sebagai Admin, atau mengubah `role`
  lewat registrasi/profil.
- Pemalsuan notifikasi pembayaran: mengubah status pesanan menjadi Lunas tanpa
  tanda tangan Midtrans yang sah.
- Kebocoran rahasia: `.env`, `MIDTRANS_SERVER_KEY`, `RAJAONGKIR_API_KEY`, token
  verifikasi, atau token reset password yang ikut ter-commit atau tercetak di
  log/respons.
- Injeksi: SQL, XSS pada keluaran JSON/Blade, atau pemalsuan input yang lolos
  validasi dan mengubah data di luar aturan dokumen.
- Kebocoran data pribadi: alamat, nomor telepon, atau email User yang bisa
  dibaca tanpa hak.
- Ketidaksesuaian stok yang bisa dieksploitasi: misalnya membuat Stok Tersedia
  menjadi negatif dengan permintaan paralel.

## Yang bukan kerentanan

- Hasil pemindai otomatis tanpa bukti dampak yang bisa diulang.
- Salah konfigurasi di mesin lokal, misalnya `APP_DEBUG=true` di `.env` milikmu
  sendiri — README sudah meminta nilai produksi diisi sebelum dipasang.
- Ketidaksesuaian antara kode dan dokumen acuan (SRS/User Story). Itu laporan
  bug biasa, bukan kerentanan.
- Ketiadaan fitur yang memang belum dibangun, termasuk modul yang masih ⏳ di
  README.

## Proses penanganan

1. Laporan masuk dan dibaca. Proyek ini dikerjakan satu orang, jadi balasan
   diusahakan secepatnya; tidak ada janji waktu yang mengikat.
2. Laporan dikonfirmasi atau ditolak dengan alasan. Bila diterima, dampak dan
   tingkat bahayanya disepakati lebih dulu.
3. Perbaikan dikerjakan di branch tersendiri, diuji, lalu masuk lewat pull
   request setelah CI hijau — sama seperti perubahan lain.
4. Setelah perbaikan ter-merge, detail teknis boleh dibuka. Kalau kamu ingin
   disebut, sebutkan nama yang mau dipakai di laporan.

## Kalau rahasia sudah bocor

Anggap kunci yang pernah ter-commit sebagai sudah bocor walaupun commit-nya
sudah dihapus: riwayat Git menyimpannya. Tindakan yang perlu dilakukan:
rotasi kunci di dasbor Midtrans dan RajaOngkir, perbarui `.env` di semua
lingkungan, lalu periksa log untuk pemakaian yang tidak dikenal. Melaporkan
kebocoran seperti ini juga lewat email di atas, bukan lewat issue.
