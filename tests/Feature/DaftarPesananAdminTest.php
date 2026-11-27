<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Design;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FR-ORDR-03 #1: Admin melihat daftar pesanan Diproses/Sedang Dicetak/Siap Kirim
 * lengkap dengan item, preview desain, alamat, dan estimasi SLA.
 */
class DaftarPesananAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin_ecommerce']);
    }

    /** @param array<string, mixed> $gantiItem */
    private function pesanan(string $status, bool $custom = false, array $gantiItem = [], array $gantiPembayaran = []): Order
    {
        $user = User::factory()->create();

        $produk = Product::create([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => $custom ? 'Tas Rotan Custom' : 'Tote Bag',
            'deskripsi' => 'Tas',
            'tipe' => $custom ? 'custom' : 'jadi',
            'harga_dasar' => 150000,
            'is_aktif' => true,
            'sla_hari' => $custom ? 7 : null,
        ]);

        $varian = $produk->variants()->create([
            'sku' => 'SKU-'.strtoupper((string) Str::ulid()),
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
        ]);

        $desain = $custom ? Design::create([
            'user_id' => $user->id,
            'variant_id' => $varian->id,
            'data_desain' => ['elemen' => []],
            'preview_gambar' => 'desain/preview/admin.png',
            'status' => 'disetujui',
            'versi' => 1,
        ]) : null;

        $pesanan = Order::create([
            'user_id' => $user->id,
            'nomor_pesanan' => 'INV-ADM-'.strtoupper((string) Str::ulid()),
            'status' => $status,
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'total' => 150000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
            'label_lokasi' => 'GUBENG, SURABAYA',
        ]);

        $pesanan->orderItems()->create(array_merge([
            'variant_id' => $varian->id,
            'design_id' => $desain?->id,
            'nama_produk' => $produk->nama,
            'nama_varian' => '30x20',
            'sku' => $varian->sku,
            'quantity' => 2,
            'harga_satuan' => 150000,
            'sla_hari' => $custom ? 3 : null,
        ], $gantiItem));

        Payment::create(array_merge([
            'order_id' => $pesanan->id,
            'snap_token' => 'token-admin',
            'jumlah' => 150000,
            'status' => 'lunas',
            'dibayar_pada' => now()->subDay(),
        ], $gantiPembayaran));

        return $pesanan;
    }

    public function test_admin_melihat_hanya_pesanan_produksi(): void
    {
        $diproses = $this->pesanan('diproses', true);
        $dicetak = $this->pesanan('sedang_dicetak', true);
        $siap = $this->pesanan('siap_kirim');
        $this->pesanan('menunggu_validasi', true);
        $this->pesanan('selesai');

        $this->actingAs($this->admin())
            ->getJson('/admin/pesanan')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['nomor_pesanan' => $diproses->nomor_pesanan])
            ->assertJsonFragment(['nomor_pesanan' => $dicetak->nomor_pesanan])
            ->assertJsonFragment(['nomor_pesanan' => $siap->nomor_pesanan]);
    }

    public function test_daftar_memuat_item_preview_alamat_dan_sla(): void
    {
        $pesanan = $this->pesanan('diproses', true);

        $this->actingAs($this->admin())
            ->getJson('/admin/pesanan')
            ->assertOk()
            ->assertJsonPath('data.0.nomor_pesanan', $pesanan->nomor_pesanan)
            ->assertJsonPath('data.0.alamat.nama_penerima', 'Andhi')
            ->assertJsonPath('data.0.alamat.alamat_pengiriman', 'Jl. Sumur Bandung No. 1')
            ->assertJsonPath('data.0.item.0.nama_produk', 'Tas Rotan Custom')
            ->assertJsonPath('data.0.item.0.preview', Storage::disk('public')->url('desain/preview/admin.png'))
            ->assertJsonPath('data.0.item.0.sla_hari', 3)
            ->assertJsonStructure(['data' => [['estimasi_selesai']]]);
    }

    public function test_daftar_hanya_untuk_admin_ecommerce(): void
    {
        $this->pesanan('diproses');

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->getJson('/admin/pesanan')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin_scm']))
            ->getJson('/admin/pesanan')
            ->assertForbidden();
    }

    public function test_guest_tidak_bisa_membuka_daftar_admin(): void
    {
        $this->getJson('/admin/pesanan')->assertUnauthorized();
    }

    /** FR-ORDR-03 #3: tenggat terdekat dulu, yang lewat ditandai. */
    public function test_antrian_diurutkan_berdasarkan_tenggat_sla_dan_lewat(): void
    {
        $lewat = $this->pesanan('diproses', true, ['sla_hari' => 1], ['dibayar_pada' => now()->subDays(10)]);
        $dekat = $this->pesanan('diproses', true, ['sla_hari' => 2], ['dibayar_pada' => now()->subDay()]);
        $jauh = $this->pesanan('diproses', true, ['sla_hari' => 10], ['dibayar_pada' => now()->subDay()]);

        $this->actingAs($this->admin())
            ->getJson('/admin/pesanan')
            ->assertOk()
            ->assertJsonPath('data.0.nomor_pesanan', $lewat->nomor_pesanan)
            ->assertJsonPath('data.0.lewat_sla', true)
            ->assertJsonPath('data.1.nomor_pesanan', $dekat->nomor_pesanan)
            ->assertJsonPath('data.1.lewat_sla', false)
            ->assertJsonPath('data.2.nomor_pesanan', $jauh->nomor_pesanan);
    }
}
