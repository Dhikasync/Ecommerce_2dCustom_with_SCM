<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\ShopSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Perintah sinkronisasi layanan kurir (FR-CKOT-04 #2, srs.md:228): daftar
 * layanan berasal dari RajaOngkir, bukan dari daftar tulisan tangan.
 */
class SinkronLayananTest extends TestCase
{
    use RefreshDatabase;

    private const URL_DASAR = 'https://rajaongkir.komerce.id/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rajaongkir.key' => 'kunci-uji',
            'services.rajaongkir.base_url' => self::URL_DASAR,
        ]);
    }

    private function alamatAsal(string $kodeLokasi = '4816'): ShopSetting
    {
        return ShopSetting::forceCreate([
            'id' => 1,
            'alamat_jalan' => 'Jl. Cibeunying No. 12',
            'kode_lokasi' => $kodeLokasi,
            'label_lokasi' => 'CIBEUNYING, BANDUNG, JAWA BARAT, 40191',
        ]);
    }

    /**
     * Respons tarif per kurir, bentuknya disalin dari dokumentasi RajaOngkir.
     *
     * @param  array<string, list<string>>  $layanan  kode kurir => daftar service
     */
    private function palsukanTarif(array $layanan): void
    {
        Http::fake(function (Request $request) use ($layanan) {
            $kurir = $request['courier'];
            $services = $layanan[$kurir] ?? null;

            if ($services === null) {
                return Http::response(['meta' => ['status' => 'error']], 500);
            }

            return Http::response([
                'meta' => ['message' => 'Success Calculate Domestic Shipping cost', 'code' => 200, 'status' => 'success'],
                'data' => array_map(fn (string $service) => [
                    'name' => strtoupper($kurir),
                    'code' => $kurir,
                    'service' => $service,
                    'description' => "Layanan {$service}",
                    'cost' => 8000,
                    'etd' => '2-3 day',
                ], $services),
            ]);
        });
    }

    public function test_layanan_dari_api_tersimpan_sebagai_nonaktif(): void
    {
        $this->alamatAsal();
        $this->palsukanTarif(['jne' => ['REG', 'YES']]);

        $this->artisan('rajaongkir:sinkron-layanan', ['--kurir' => 'jne'])
            ->assertExitCode(0);

        $this->assertSame(
            ['REG', 'YES'],
            Courier::where('kode', 'jne')->orderBy('layanan')->pluck('layanan')->all(),
        );
        $this->assertSame(0, Courier::where('is_aktif', true)->count());
        $this->assertSame('JNE', Courier::where('layanan', 'REG')->value('nama'));
    }

    public function test_sinkronisasi_tidak_menghidupkan_layanan_yang_sudah_dimatikan(): void
    {
        $this->alamatAsal();
        $this->palsukanTarif(['jne' => ['REG']]);

        $ada = Courier::create([
            'kode' => 'jne',
            'nama' => 'JNE',
            'layanan' => 'REG',
            'is_aktif' => true,
        ]);

        $this->artisan('rajaongkir:sinkron-layanan', ['--kurir' => 'jne'])
            ->assertExitCode(0);

        $this->assertTrue($ada->refresh()->is_aktif);
        $this->assertSame(1, Courier::count());
    }

    public function test_kurir_yang_gagal_tidak_menghentikan_kurir_lain(): void
    {
        $this->alamatAsal();
        $this->palsukanTarif(['jne' => ['REG']]);

        $this->artisan('rajaongkir:sinkron-layanan', ['--kurir' => 'jne,pos'])
            ->assertExitCode(1);

        $this->assertSame(1, Courier::where('kode', 'jne')->count());
        $this->assertSame(0, Courier::where('kode', 'pos')->count());
    }

    public function test_perintah_berhenti_bila_alamat_asal_belum_diisi(): void
    {
        Http::fake();

        $this->artisan('rajaongkir:sinkron-layanan', ['--kurir' => 'jne'])
            ->expectsOutputToContain('Alamat asal belum diisi')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_opsi_asal_menimpa_alamat_asal_tersimpan(): void
    {
        $this->alamatAsal('9999');
        $this->palsukanTarif(['jne' => ['REG']]);

        $this->artisan('rajaongkir:sinkron-layanan', [
            '--kurir' => 'jne',
            '--asal' => '4816',
        ])->assertExitCode(0);

        Http::assertSent(fn (Request $request) => $request['origin'] === '4816'
            && $request['destination'] === '4816');
    }

    public function test_gangguan_koneksi_dilaporkan_sebagai_kegagalan(): void
    {
        $this->alamatAsal();
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->artisan('rajaongkir:sinkron-layanan', ['--kurir' => 'jne'])
            ->assertExitCode(1);

        $this->assertSame(0, Courier::count());
    }
}
