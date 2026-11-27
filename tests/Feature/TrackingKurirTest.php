<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FR-ORDR-04 #3: kurir tanpa dukungan AWB tidak dipanggil API-nya. User
 * mendapat pesan dan tautan homepage resmi yang sudah diverifikasi.
 */
class TrackingKurirTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN = 'tracking tidak tersedia untuk kurir ini';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Http::fake();
    }

    private function pesanan(?string $kodeKurir): Order
    {
        $kurir = $kodeKurir === null ? null : Courier::create([
            'kode' => $kodeKurir,
            'nama' => strtoupper($kodeKurir),
            'layanan' => 'REG',
            'is_aktif' => true,
        ]);

        return Order::create([
            'user_id' => $this->user->id,
            'courier_id' => $kurir?->id,
            'nomor_pesanan' => 'INV-KURIR-'.strtoupper($kodeKurir ?? 'KOSONG'),
            'status' => 'dikirim',
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'total' => 150000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+6281234567890',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
            'nomor_resi' => 'JP123456789',
            'dikirim_pada' => now()->subDay(),
        ]);
    }

    public function test_kurir_tanpa_dukungan_awb_menampilkan_pesan_dan_tautan_resmi(): void
    {
        foreach ([
            'sicepat' => 'https://www.sicepat.com/',
            'ide' => 'https://www.idexpress.com/',
            'rex' => 'https://www.rex.co.id/id',
            'sentral' => null,
        ] as $kode => $tautan) {
            $pesanan = $this->pesanan($kode);

            $this->actingAs($this->user)
                ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
                ->assertOk()
                ->assertJsonPath('data.pesan_tracking', self::PESAN)
                ->assertJsonPath('data.tautan_lacak', $tautan)
                ->assertJsonCount(0, 'data.riwayat_tracking');
        }

        Http::assertNothingSent();
    }

    public function test_pesanan_tanpa_kurir_tidak_memanggil_api(): void
    {
        $pesanan = $this->pesanan(null);

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonPath('data.pesan_tracking', self::PESAN)
            ->assertJsonPath('data.tautan_lacak', null);

        Http::assertNothingSent();
    }

    public function test_kurir_yang_mendukung_awb_belum_punya_tautan(): void
    {
        $pesanan = $this->pesanan('jne');

        $this->actingAs($this->user)
            ->getJson('/pesanan/'.$pesanan->nomor_pesanan)
            ->assertOk()
            ->assertJsonPath('data.tautan_lacak', null)
            ->assertJsonPath('data.pesan_tracking', null);

        Http::assertNothingSent();
    }
}
