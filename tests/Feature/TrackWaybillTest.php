<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FR-ORDR-04 #3: detail pesanan Dikirim mengambil status + manifest dari
 * POST track/waybill. TEST FIXTURE di bawah BUKAN contoh asli RajaOngkir.
 */
class TrackWaybillTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function pesanan(string $kodeKurir, string $noHp): Order
    {
        $kurir = Courier::create([
            'kode' => $kodeKurir,
            'nama' => strtoupper($kodeKurir),
            'layanan' => 'REG',
            'is_aktif' => true,
        ]);

        return Order::create([
            'user_id' => $this->user->id,
            'courier_id' => $kurir->id,
            'nomor_pesanan' => 'INV-TRACK-1',
            'status' => 'dikirim',
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'total' => 150000,
            'nama_penerima' => 'Andhi',
            'no_hp' => $noHp,
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
            'nomor_resi' => 'JP123456789',
            'dikirim_pada' => now()->subDay(),
        ]);
    }

    // TEST FIXTURE — BUKAN contoh asli RajaOngkir, hanya field terverifikasi bagian 8.
    private function fixtureWaybill(): array
    {
        return [
            'meta' => ['status' => 'success'],
            'data' => [
                'delivered' => false,
                'summary' => [
                    'courier_code' => 'jne',
                    'waybill_number' => 'JP123456789',
                    'status' => 'IN TRANSIT',
                ],
                'delivery_status' => ['status' => 'IN TRANSIT'],
                'manifest' => [[
                    'manifest_code' => '1',
                    'manifest_description' => 'Paket tiba di gudang',
                    'manifest_date' => '2026-11-26',
                    'manifest_time' => '10:00',
                    'city_name' => 'Surabaya',
                ]],
            ],
        ];
    }

    public function test_detail_mengambil_status_dan_manifest_dari_waybill(): void
    {
        Http::fake([
            'https://rajaongkir.komerce.id/api/v1/track/waybill' => Http::response($this->fixtureWaybill()),
        ]);

        $pesanan = $this->pesanan('jne', '+62 812-3456-7890');

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonPath('data.pesan_tracking', 'Paket dalam perjalanan')
            ->assertJsonPath('data.riwayat_tracking.0.manifest_description', 'Paket tiba di gudang')
            ->assertJsonPath('data.tautan_lacak', null);

        Http::assertSent(function (HttpRequest $permintaan) {
            return $permintaan->url() === 'https://rajaongkir.komerce.id/api/v1/track/waybill'
                && $permintaan['awb'] === 'JP123456789'
                && $permintaan['courier'] === 'jne'
                && $permintaan['last_phone_number'] === '67890';
        });
    }

    public function test_kurir_tanpa_dukungan_awb_tidak_memanggil_api(): void
    {
        Http::fake();

        $pesanan = $this->pesanan('sicepat', '+6281234567890');

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonPath('data.pesan_tracking', 'tracking tidak tersedia untuk kurir ini')
            ->assertJsonPath('data.tautan_lacak', 'https://www.sicepat.com/')
            ->assertJsonCount(0, 'data.riwayat_tracking');

        Http::assertNothingSent();
    }

    public function test_nomor_penerima_tidak_valid_tidak_memanggil_api(): void
    {
        Http::fake();

        $pesanan = $this->pesanan('jne', '123');

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonPath('data.pesan_tracking', 'Nomor penerima tidak valid untuk pelacakan');

        Http::assertNothingSent();
    }
}
