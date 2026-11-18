<!--
  Template pull request Nichibag.
  Isi setiap bagian; hapus bagian yang benar-benar tidak berlaku.
  Jangan menempelkan .env, kunci gateway, atau token di sini.
-->

## Ringkasan

<!-- Apa yang berubah dan mengapa. Dua sampai empat kalimat. -->

## Kode FR dan sumber dokumen

<!-- Sebut kode FR + nomor skenario. Kalau perilakunya tidak diatur dokumen,
     tulis "tidak diatur dokumen" dan jelaskan alasannya. -->

| Kode FR | Skenario | Sumber (SRS / User Story, baris) |
| --- | --- | --- |
|  |  |  |

## Bukti

<!-- Tempel keluaran nyata, jangan diketik ulang dari ingatan. -->

```
php artisan test
Tests:  ... passed (... assertions)

./vendor/bin/pint --test
{"tool":"pint","result":"passed"}
```

<!-- Untuk perubahan yang menyentuh gateway atau layanan luar, sebutkan bukti
     terpisah (misalnya balasan sandbox), bukan hanya hasil Http::fake. -->

## Yang berubah

<!-- Berkas penting dan alasannya. Bukan daftar semua berkas. -->

| Berkas | Alasan |
| --- | --- |
|  |  |

## Sengaja belum dikerjakan

<!-- Bagian dari FR ini yang ditunda, dan apa yang menghalanginya.
     Kalau tidak ada, tulis "tidak ada". -->

## Daftar periksa

- [ ] `php artisan test` lulus seluruhnya, bukan hanya test yang baru.
- [ ] `./vendor/bin/pint --test` bersih.
- [ ] Test baru sudah dibuktikan **gagal** sebelum implementasi.
- [ ] Tidak ada `.env`, `vendor/`, kunci, atau token di diff.
- [ ] Pesan/status yang berasal dari dokumen dikutip persis.
- [ ] Status di README sesuai kenyataan; tidak ada status yang dinaikkan tanpa bukti.
- [ ] Migration baru bisa di-rollback dan tidak merusak data lama.
- [ ] Branch dinamai `<tipe>/<ringkasan>` dan tidak ada force push.
