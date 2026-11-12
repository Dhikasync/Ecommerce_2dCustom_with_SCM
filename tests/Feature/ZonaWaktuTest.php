<?php

namespace Tests\Feature;

use App\Models\Produk\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * NFR-LOC-01, srs.md:162 dan srs.md:315: seluruh batas waktu dihitung dalam
 * WIB (UTC+7), dan seluruh data waktu dikirim serta ditampilkan dalam WIB.
 *
 * Konfigurasi bawaan Laravel adalah `UTC`, sehingga tanpa perubahan ini semua
 * timestamp tersimpan dan tampil 7 jam lebih awal dari waktu Indonesia.
 */
class ZonaWaktuTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplikasi_memakai_zona_waktu_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));

        // Laravel menurunkan nilai config ini ke PHP saat boot; kalau tidak,
        // date()/strtotime() bawaan PHP tetap memakai UTC.
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }

    public function test_waktu_sekarang_bergeser_tujuh_jam_dari_utc(): void
    {
        $sekarang = now();

        $this->assertSame('+07:00', $sekarang->format('P'));
        $this->assertSame('WIB', $sekarang->format('T'));

        // Selisih zona waktu tidak boleh menggeser momennya: hanya cara
        // menuliskannya yang berubah, bukan waktu sebenarnya.
        $selisihDetik = abs($sekarang->getTimestamp() - Carbon::now('UTC')->getTimestamp());

        $this->assertLessThan(5, $selisihDetik);
    }

    public function test_timestamp_tersimpan_dan_terbaca_kembali_dalam_wib(): void
    {
        $kategori = Category::create(['nama' => 'Tas']);

        // Timestamp ditulis oleh database lalu dibaca ulang oleh model.
        // Kalau aplikasi menulis UTC padahal dirinya mengaku WIB, selisihnya
        // akan sekitar 7 jam, bukan beberapa detik.
        $tersimpan = $kategori->fresh()->created_at;

        $this->assertSame('+07:00', $tersimpan->format('P'));
        $this->assertLessThan(5, abs($tersimpan->diffInSeconds(now())));
    }
}
