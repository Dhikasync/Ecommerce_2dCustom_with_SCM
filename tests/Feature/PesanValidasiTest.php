<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Penjaga kelengkapan terjemahan pesan validasi (APP_LOCALE=id).
 *
 * Tanpa test ini, key yang lupa diterjemahkan akan diam-diam muncul dalam
 * bahasa Inggris, karena Laravel jatuh ke file bawaan vendor ketika key di
 * `lang/id/validation.php` tidak ada.
 */
class PesanValidasiTest extends TestCase
{
    /**
     * Meratakan file terjemahan menjadi daftar key bertitik, misalnya
     * "between.string" atau "custom.email.unique".
     *
     * @param  array<string, mixed>  $baris
     * @return array<string, string>
     */
    private function ratakan(array $baris, string $awalan = ''): array
    {
        $hasil = [];

        foreach ($baris as $kunci => $nilai) {
            $kunciPenuh = $awalan === '' ? (string) $kunci : $awalan.'.'.$kunci;

            if (is_array($nilai)) {
                $hasil += $this->ratakan($nilai, $kunciPenuh);

                continue;
            }

            $hasil[$kunciPenuh] = (string) $nilai;
        }

        return $hasil;
    }

    /**
     * @return array<string, string>
     */
    private function pesanBawaanLaravel(): array
    {
        $berkas = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

        $hasil = $this->ratakan(require $berkas);

        // `custom` dan `attributes` di file bawaan hanya contoh, bukan aturan,
        // jadi tidak perlu diterjemahkan satu per satu.
        unset($hasil['custom.attribute-name.rule-name']);

        return $hasil;
    }

    /**
     * @return array<string, string>
     */
    private function pesanIndonesia(): array
    {
        $berkas = lang_path('id/validation.php');

        $hasil = [];

        foreach ($this->ratakan(require $berkas) as $kunci => $nilai) {
            if (str_starts_with($kunci, 'custom.') || str_starts_with($kunci, 'attributes.')) {
                continue;
            }

            $hasil[$kunci] = $nilai;
        }

        return $hasil;
    }

    public function test_semua_pesan_bawaan_laravel_punya_terjemahan(): void
    {
        $bawaan = $this->pesanBawaanLaravel();
        $indonesia = $this->pesanIndonesia();

        $belum = array_keys(array_diff_key($bawaan, $indonesia));

        $this->assertSame(
            [],
            $belum,
            'Key berikut belum diterjemahkan di lang/id/validation.php: '.implode(', ', $belum),
        );
    }

    public function test_tidak_ada_pesan_yang_masih_berbahasa_inggris(): void
    {
        $bawaan = $this->pesanBawaanLaravel();
        $indonesia = $this->pesanIndonesia();

        $sama = [];

        foreach ($indonesia as $kunci => $nilai) {
            // Pesan kosong (misalnya key yang memang tidak dipakai) dilewati.
            if ($nilai !== '' && isset($bawaan[$kunci]) && $bawaan[$kunci] === $nilai) {
                $sama[] = $kunci;
            }
        }

        $this->assertSame(
            [],
            $sama,
            'Pesan berikut masih sama persis dengan bahasa Inggris: '.implode(', ', $sama),
        );
    }

    public function test_pesan_yang_ditetapkan_dokumen_terpasang_persis(): void
    {
        // User Story FR-AUTH-02 #2-#5 dan FR-PROF-01 #3/#8.
        $this->assertSame('Format email tidak valid', __('validation.custom.email.email'));
        $this->assertSame('Email sudah digunakan', __('validation.custom.email.unique'));
        $this->assertSame('Konfirmasi password tidak sesuai', __('validation.custom.password.confirmed'));
        $this->assertSame('Password minimal 8 karakter', __('validation.custom.password.min'));

        // FR-PROD-01 #9 / NFR-FIL-01: format dan ukuran gambar produk.
        // Dibaca lewat Arr::dot karena kunci `gambar.*` tidak bisa diambil
        // dengan titik biasa; validator sendiri membaca dengan cara yang sama.
        $custom = Arr::dot((array) __('validation.custom'));

        $this->assertSame('Format gambar harus JPG atau PNG', $custom['gambar.*.mimes']);
        $this->assertSame('Ukuran gambar maksimal 2 MB', $custom['gambar.*.max']);
    }
}
