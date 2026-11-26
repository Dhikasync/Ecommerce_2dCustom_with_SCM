<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Design;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FR-ORDR-03 #2: work order berisi spesifikasi produk, render final, quantity,
 * dan tenggat SLA untuk pesanan custom yang Diproses.
 */
class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_TIDAK_VALID = 'Work order hanya tersedia untuk pesanan custom yang Diproses';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** @return array{pesanan: Order, varian: Variant, desain: Design} */
    private function customDiproses(array $ganti = []): array
    {
        $user = User::factory()->create();

        $produk = Product::create([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tas Rotan Custom',
            'deskripsi' => 'Tas anyaman rotan',
            'tipe' => 'custom',
            'harga_dasar' => 150000,
            'is_aktif' => true,
            'sla_hari' => 7,
        ]);

        $varian = $produk->variants()->create([
            'sku' => 'TAS-WO',
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
            'stok_fisik' => 10,
            'stok_direservasi' => 0,
        ]);

        $desain = Design::create([
            'user_id' => $user->id,
            'variant_id' => $varian->id,
            'data_desain' => ['elemen' => []],
            'preview_gambar' => 'desain/preview/wo.png',
            'status' => 'disetujui',
            'versi' => 2,
        ]);

        $pesanan = Order::create(array_merge([
            'user_id' => $user->id,
            'nomor_pesanan' => 'INV-WO-1',
            'status' => 'diproses',
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'total' => 150000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
        ], $ganti));

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'design_id' => $desain->id,
            'nama_produk' => 'Tas Rotan Custom',
            'nama_varian' => '30x20',
            'sku' => 'TAS-WO',
            'quantity' => 2,
            'harga_satuan' => 150000,
            'sla_hari' => 3,
        ]);

        Payment::create([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-wo',
            'jumlah' => 150000,
            'status' => 'lunas',
            'dibayar_pada' => now()->subDay(),
        ]);

        return compact('pesanan', 'varian', 'desain');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin_ecommerce']);
    }

    public function test_admin_mendapat_work_order_lengkap(): void
    {
        ['pesanan' => $pesanan] = $this->customDiproses();

        $this->actingAs($this->admin())
            ->getJson('/admin/pesanan/'.$pesanan->nomor_pesanan.'/work-order')
            ->assertOk()
            ->assertJsonPath('data.nomor_pesanan', 'INV-WO-1')
            ->assertJsonPath('data.status', 'diproses')
            ->assertJsonPath('data.item.nama_produk', 'Tas Rotan Custom')
            ->assertJsonPath('data.item.sku', 'TAS-WO')
            ->assertJsonPath('data.item.quantity', 2)
            ->assertJsonPath('data.item.preview', Storage::disk('public')->url('desain/preview/wo.png'))
            ->assertJsonPath('data.item.sla_hari', 3)
            ->assertJsonStructure(['data' => ['estimasi_selesai']]);
    }

    public function test_work_order_menolak_pesanan_yang_tidak_diproses(): void
    {
        $this->customDiproses(['status' => 'menunggu_pembayaran']);

        $this->actingAs($this->admin())
            ->getJson('/admin/pesanan/INV-WO-1/work-order')
            ->assertStatus(422)
            ->assertJsonPath('message', self::PESAN_TIDAK_VALID);
    }

    public function test_work_order_menolak_pesanan_tanpa_desain(): void
    {
        ['pesanan' => $pesanan] = $this->customDiproses();
        $pesanan->orderItems()->first()->update(['design_id' => null]);

        $this->actingAs($this->admin())
            ->getJson('/admin/pesanan/INV-WO-1/work-order')
            ->assertStatus(422)
            ->assertJsonPath('message', self::PESAN_TIDAK_VALID);
    }

    public function test_work_order_hanya_untuk_admin_ecommerce(): void
    {
        $this->customDiproses();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->getJson('/admin/pesanan/INV-WO-1/work-order')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin_scm']))
            ->getJson('/admin/pesanan/INV-WO-1/work-order')
            ->assertForbidden();
    }
}
