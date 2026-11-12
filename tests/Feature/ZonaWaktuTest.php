<?php

namespace Tests\Feature;

use App\Models\Produk\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * NFR-LOC-01, srs.md:162/315. Bawaan Laravel `UTC`, jadi tanpa ini semua
 * timestamp tersimpan dan tampil 7 jam lebih awal dari WIB.
 */
class ZonaWaktuTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplikasi_memakai_zona_waktu_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));

        // Laravel menurunkan config ini ke PHP saat boot; kalau tidak, date() tetap UTC.
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }

    public function test_waktu_sekarang_bergeser_tujuh_jam_dari_utc(): void
    {
        $sekarang = now();

        $this->assertSame('+07:00', $sekarang->format('P'));
        $this->assertSame('WIB', $sekarang->format('T'));

        // Selisih zona waktu hanya mengubah penulisan, bukan momennya.
        $selisihDetik = abs($sekarang->getTimestamp() - Carbon::now('UTC')->getTimestamp());

        $this->assertLessThan(5, $selisihDetik);
    }

    public function test_timestamp_tersimpan_dan_terbaca_kembali_dalam_wib(): void
    {
        $kategori = Category::create(['nama' => 'Tas']);

        // Ditulis database lalu dibaca model; kalau aplikasi menulis UTC, selisihnya 7 jam.
        $tersimpan = $kategori->fresh()->created_at;

        $this->assertSame('+07:00', $tersimpan->format('P'));
        $this->assertLessThan(5, abs($tersimpan->diffInSeconds(now())));
    }
}
