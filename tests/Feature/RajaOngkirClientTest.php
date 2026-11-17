<?php

namespace Tests\Feature;

use App\Exceptions\Profil\RajaOngkirException;
use App\Services\Profil\RajaOngkir;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RajaOngkirClientTest extends TestCase
{
    private const URL_DASAR = 'https://rajaongkir.komerce.id/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rajaongkir.key' => 'kunci-uji',
            'services.rajaongkir.base_url' => self::URL_DASAR.'/',
        ]);
    }

    private function client(): RajaOngkir
    {
        return $this->app->make(RajaOngkir::class);
    }

    /** Bentuk respons disalin dari panggilan asli, agar pemetaannya teruji. */
    private function responsLokasi(array $data = [
        [
            'id' => 4816,
            'label' => '-, BANDUNG, BANDUNG, JAWA BARAT, 40614',
            'province_name' => 'JAWA BARAT',
            'city_name' => 'BANDUNG',
            'district_name' => 'BANDUNG',
            'subdistrict_name' => '-',
            'zip_code' => '40614',
        ],
    ]): array
    {
        return [
            'meta' => [
                'message' => 'Success Get Domestic Destinations',
                'code' => 200,
                'status' => 'success',
            ],
            'data' => $data,
        ];
    }

    public function test_pencarian_lokasi_dinormalkan_ke_kode_dan_label(): void
    {
        Http::fake([
            '*' => Http::response($this->responsLokasi()),
        ]);

        $hasil = $this->client()->cariLokasi('bandung');

        $this->assertSame([
            [
                'kode_lokasi' => '4816',
                'label_lokasi' => '-, BANDUNG, BANDUNG, JAWA BARAT, 40614',
            ],
        ], $hasil);

        Http::assertSent(function (Request $request) {
            return $request->url() === self::URL_DASAR.'/destination/domestic-destination?search=bandung'
                && $request->hasHeader('key', 'kunci-uji')
                && $request->method() === 'GET';
        });
    }

    public function test_base_url_tanpa_garis_miring_di_akhir_tetap_benar(): void
    {
        config(['services.rajaongkir.base_url' => self::URL_DASAR]);

        Http::fake([
            '*' => Http::response($this->responsLokasi()),
        ]);

        $this->client()->cariLokasi('bandung');

        Http::assertSent(
            fn (Request $request) => $request->url() === self::URL_DASAR.'/destination/domestic-destination?search=bandung',
        );
    }

    public function test_kata_kunci_kosong_diteruskan_apa_adanya(): void
    {
        // Dokumen tidak mengatur kata kunci kosong; keputusan ada di endpoint.
        Http::fake([
            '*' => Http::response($this->responsLokasi([])),
        ]);

        $hasil = $this->client()->cariLokasi('');

        $this->assertSame([], $hasil);

        Http::assertSent(
            fn (Request $request) => str_ends_with($request->url(), '/destination/domestic-destination?search='),
        );
    }

    public function test_hasil_kosong_dikembalikan_sebagai_array_kosong(): void
    {
        Http::fake([
            '*' => Http::response($this->responsLokasi([])),
        ]);

        $this->assertSame([], $this->client()->cariLokasi('tidak-ada-lokasi'));
    }

    public function test_status_http_gagal_dilempar_sebagai_exception(): void
    {
        Http::fake([
            '*' => Http::response(['meta' => ['status' => 'error']], 500),
        ]);

        $this->expectException(RajaOngkirException::class);
        $this->expectExceptionMessage('status 500');

        $this->client()->cariLokasi('bandung');
    }

    public function test_meta_status_bukan_success_dilempar_sebagai_exception(): void
    {
        Http::fake([
            '*' => Http::response([
                'meta' => ['message' => 'Invalid API key', 'code' => 200, 'status' => 'error'],
                'data' => [],
            ]),
        ]);

        $this->expectException(RajaOngkirException::class);

        $this->client()->cariLokasi('bandung');
    }

    public function test_bentuk_data_tidak_dikenali_dilempar_sebagai_exception(): void
    {
        Http::fake([
            '*' => Http::response([
                'meta' => ['status' => 'success'],
                'data' => [['nama' => 'tanpa id dan label']],
            ]),
        ]);

        $this->expectException(RajaOngkirException::class);
        $this->expectExceptionMessage('Bentuk data lokasi tidak dikenali');

        $this->client()->cariLokasi('bandung');
    }

    public function test_gangguan_koneksi_dilempar_sebagai_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->expectException(RajaOngkirException::class);
        $this->expectExceptionMessage('tidak dapat dihubungi');

        $this->client()->cariLokasi('bandung');
    }

    public function test_kunci_kosong_tidak_memanggil_api(): void
    {
        config(['services.rajaongkir.key' => '']);

        Http::fake();

        try {
            $this->client()->cariLokasi('bandung');
            $this->fail('Seharusnya melempar RajaOngkirException');
        } catch (RajaOngkirException $e) {
            $this->assertStringContainsString('RAJAONGKIR_API_KEY', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    /** Bentuk respons tarif disalin dari dokumentasi RajaOngkir Komerce. */
    private function responsTarif(array $data = [
        [
            'name' => 'JNE',
            'code' => 'jne',
            'service' => 'REG',
            'description' => 'Layanan Reguler',
            'cost' => 8000,
            'etd' => '2-3 day',
        ],
    ]): array
    {
        return [
            'meta' => [
                'message' => 'Success Calculate Domestic Shipping cost',
                'code' => 200,
                'status' => 'success',
            ],
            'data' => $data,
        ];
    }

    public function test_tarif_dinormalkan_ke_layanan_dan_biaya(): void
    {
        Http::fake(['*' => Http::response($this->responsTarif())]);

        $hasil = $this->client()->hitungOngkir('4816', '4816', 1000, 'jne');

        $this->assertSame([
            [
                'kode_kurir' => 'jne',
                'nama_kurir' => 'JNE',
                'layanan' => 'REG',
                'deskripsi' => 'Layanan Reguler',
                'biaya' => 8000,
                'etd' => '2-3 day',
            ],
        ], $hasil);

        Http::assertSent(function (Request $request) {
            return $request->url() === self::URL_DASAR.'/calculate/domestic-cost'
                && $request->method() === 'POST'
                && $request->hasHeader('key', 'kunci-uji')
                && $request['origin'] === '4816'
                && $request['destination'] === '4816'
                // Dikirim sebagai form, jadi angkanya sampai sebagai string.
                && (int) $request['weight'] === 1000
                && $request['courier'] === 'jne';
        });
    }

    public function test_tarif_kosong_dikembalikan_sebagai_array_kosong(): void
    {
        Http::fake(['*' => Http::response($this->responsTarif([]))]);

        $this->assertSame([], $this->client()->hitungOngkir('4816', '4816', 1000, 'jne'));
    }

    public function test_bentuk_data_tarif_tidak_dikenali_dilempar_sebagai_exception(): void
    {
        Http::fake([
            '*' => Http::response([
                'meta' => ['status' => 'success'],
                'data' => [['name' => 'JNE', 'cost' => 8000]],
            ]),
        ]);

        $this->expectException(RajaOngkirException::class);
        $this->expectExceptionMessage('Bentuk data tarif tidak dikenali');

        $this->client()->hitungOngkir('4816', '4816', 1000, 'jne');
    }

    public function test_status_http_tarif_gagal_dilempar_sebagai_exception(): void
    {
        Http::fake(['*' => Http::response(['meta' => ['status' => 'error']], 500)]);

        $this->expectException(RajaOngkirException::class);

        $this->client()->hitungOngkir('4816', '4816', 1000, 'jne');
    }
}
