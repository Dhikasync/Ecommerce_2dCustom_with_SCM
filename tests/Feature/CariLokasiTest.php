<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FR-PROF-01 #4, #11, #12, UC-08: GET /lokasi.
 *
 * Panggilan ke RajaOngkir selalu dipalsukan. CI tidak punya jaringan, dan
 * hasil test tidak boleh bergantung pada kuota atau latensi layanan luar.
 * Bentuk respons palsu disalin dari respons asli (id 4816, Bandung).
 */
class CariLokasiTest extends TestCase
{
    use RefreshDatabase;

    private const URL_DASAR = 'https://rajaongkir.komerce.id/api/v1';

    private const PESAN_TIDAK_DITEMUKAN = 'Lokasi tidak ditemukan, coba kata kunci lain';

    private const PESAN_GANGGUAN = 'Layanan lokasi sedang bermasalah, silakan coba lagi';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rajaongkir.key' => 'kunci-uji',
            'services.rajaongkir.base_url' => self::URL_DASAR.'/',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>|null  $data
     * @return array<string, mixed>
     */
    private function responsLokasi(?array $data = null): array
    {
        return [
            'meta' => [
                'message' => 'Success Get Domestic Destinations',
                'code' => 200,
                'status' => 'success',
            ],
            'data' => $data ?? [
                [
                    'id' => 4816,
                    'label' => '-, BANDUNG, BANDUNG, JAWA BARAT, 40614',
                    'province_name' => 'JAWA BARAT',
                    'city_name' => 'BANDUNG',
                    'district_name' => 'BANDUNG',
                    'subdistrict_name' => '-',
                    'zip_code' => '40614',
                ],
            ],
        ];
    }

    public function test_guest_tidak_boleh_mencari_lokasi(): void
    {
        Http::fake();

        $this->getJson('/lokasi?search=bandung')->assertStatus(401);

        // Endpoint tidak boleh dipakai tanpa login, jadi API pun tidak dipanggil.
        Http::assertNothingSent();
    }

    public function test_hasil_pencarian_dikembalikan_sebagai_kode_dan_label(): void
    {
        Http::fake(['*' => Http::response($this->responsLokasi())]);

        $this->actingAs(User::factory()->create())
            ->getJson('/lokasi?search=bandung')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'kode_lokasi' => '4816',
                        'label_lokasi' => '-, BANDUNG, BANDUNG, JAWA BARAT, 40614',
                    ],
                ],
            ]);

        Http::assertSent(function (Request $request) {
            return $request->url() === self::URL_DASAR.'/destination/domestic-destination?search=bandung'
                && $request->hasHeader('key', 'kunci-uji');
        });
    }

    public function test_kata_kunci_dirapikan_sebelum_dikirim(): void
    {
        Http::fake(['*' => Http::response($this->responsLokasi())]);

        $this->actingAs(User::factory()->create())
            ->getJson('/lokasi?search=%20%20bandung%20%20')
            ->assertOk();

        Http::assertSent(
            fn (Request $request) => str_ends_with($request->url(), 'search=bandung'),
        );
    }

    public function test_kata_kunci_kosong_dianggap_tidak_ada_hasil_tanpa_memanggil_api(): void
    {
        Http::fake();

        // Keputusan yang disetujui: dokumen tidak mengatur kata kunci kosong,
        // jadi diperlakukan sama dengan "tidak ditemukan" memakai pesan #11.
        $this->actingAs(User::factory()->create())
            ->getJson('/lokasi?search=')
            ->assertOk()
            ->assertExactJson([
                'message' => self::PESAN_TIDAK_DITEMUKAN,
                'data' => [],
            ]);

        Http::assertNothingSent();
    }

    public function test_parameter_search_yang_tidak_dikirim_juga_dianggap_kosong(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson('/lokasi')
            ->assertOk()
            ->assertJson([
                'message' => self::PESAN_TIDAK_DITEMUKAN,
                'data' => [],
            ]);

        Http::assertNothingSent();
    }

    public function test_kata_kunci_hanya_spasi_tidak_memanggil_api(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson('/lokasi?search=%20%20%20')
            ->assertOk()
            ->assertJson(['data' => []]);

        Http::assertNothingSent();
    }

    public function test_api_tanpa_hasil_memakai_pesan_tidak_ditemukan(): void
    {
        Http::fake(['*' => Http::response($this->responsLokasi([]))]);

        $this->actingAs(User::factory()->create())
            ->getJson('/lokasi?search=zzzz')
            ->assertOk()
            ->assertExactJson([
                'message' => self::PESAN_TIDAK_DITEMUKAN,
                'data' => [],
            ]);
    }

    public function test_layanan_bermasalah_memakai_pesan_gangguan(): void
    {
        Http::fake(['*' => Http::response(['meta' => ['status' => 'error']], 500)]);

        $this->actingAs(User::factory()->create())
            ->getJson('/lokasi?search=bandung')
            ->assertStatus(503)
            ->assertExactJson(['message' => self::PESAN_GANGGUAN]);
    }

    public function test_gangguan_jaringan_juga_memakai_pesan_gangguan(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->actingAs(User::factory()->create())
            ->getJson('/lokasi?search=bandung')
            ->assertStatus(503)
            ->assertExactJson(['message' => self::PESAN_GANGGUAN]);
    }

    public function test_kunci_api_kosong_memakai_pesan_gangguan_tanpa_memanggil_api(): void
    {
        config(['services.rajaongkir.key' => '']);

        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson('/lokasi?search=bandung')
            ->assertStatus(503)
            ->assertExactJson(['message' => self::PESAN_GANGGUAN]);

        Http::assertNothingSent();
    }

    public function test_akun_nonaktif_ditolak_sebelum_memanggil_api(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create(['is_aktif' => false]))
            ->getJson('/lokasi?search=bandung')
            ->assertStatus(403)
            ->assertJson(['message' => 'Akun tidak aktif']);

        Http::assertNothingSent();
    }
}
