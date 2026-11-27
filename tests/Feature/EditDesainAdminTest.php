<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Design;
use App\Models\DesignRevision;
use App\Models\Order;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FR-ORDR-03 #4: Admin menyunting desain pesanan sebelum status Sedang Dicetak.
 * Versi lama wajib tersimpan di riwayat.
 */
class EditDesainAdminTest extends TestCase
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

    /** @return array{pesanan: Order, desain: Design} */
    private function siap(string $status = 'diproses'): array
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
            'sku' => 'TAS-ADMIN-'.strtoupper((string) Str::ulid()),
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
        ]);

        $template = Template::create([
            'product_id' => $produk->id,
            'nama' => 'Template Tas',
            'file' => 'template/tas.png',
            'lebar_kanvas' => 800,
            'tinggi_kanvas' => 600,
            'area_x' => 50,
            'area_y' => 40,
            'area_lebar' => 300,
            'area_tinggi' => 200,
            'is_aktif' => true,
        ]);

        $desain = Design::create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'variant_id' => $varian->id,
            'data_desain' => ['elemen' => [[
                'tipe' => 'teks', 'isi' => 'Desain lama',
                'x' => 60, 'y' => 50, 'lebar' => 120, 'tinggi' => 30,
                'rotasi' => 0, 'z' => 1,
            ]]],
            'status' => 'disetujui',
            'versi' => 2,
        ]);

        $pesanan = Order::create([
            'user_id' => $user->id,
            'nomor_pesanan' => 'INV-ADM-DESAIN',
            'status' => $status,
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'total' => 150000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+6281234567890',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
        ]);

        $pesanan->orderItems()->create([
            'variant_id' => $varian->id,
            'design_id' => $desain->id,
            'nama_produk' => 'Tas Rotan Custom',
            'quantity' => 1,
            'harga_satuan' => 150000,
        ]);

        return compact('pesanan', 'desain');
    }

    public function test_admin_menyunting_dan_versi_lama_tersimpan(): void
    {
        ['pesanan' => $pesanan, 'desain' => $desain] = $this->siap();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/admin/pesanan/'.$pesanan->nomor_pesanan.'/desain', [
                'data_desain' => ['elemen' => [[
                    'tipe' => 'teks', 'isi' => 'Desain revisi admin',
                    'x' => 60, 'y' => 50, 'lebar' => 120, 'tinggi' => 30,
                    'rotasi' => 0, 'z' => 1,
                ]]],
            ])
            ->assertOk()
            ->assertJsonPath('data.versi', 3);

        $desain->refresh();
        $this->assertSame('Desain revisi admin', $desain->data_desain['elemen'][0]['isi']);
        $this->assertSame('disetujui', $desain->status);

        $riwayat = DesignRevision::where('design_id', $desain->id)->sole();
        $this->assertSame(2, $riwayat->versi);
        $this->assertSame('Desain lama', $riwayat->data_desain['elemen'][0]['isi']);
        $this->assertSame($admin->id, $riwayat->divalidasi_oleh);
    }

    public function test_admin_tidak_bisa_menyunting_saat_sedang_dicetak(): void
    {
        ['pesanan' => $pesanan, 'desain' => $desain] = $this->siap('sedang_dicetak');

        $this->actingAs($this->admin())
            ->putJson('/admin/pesanan/'.$pesanan->nomor_pesanan.'/desain', [
                'data_desain' => ['elemen' => []],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Desain tidak dapat diubah karena pesanan sedang dalam proses cetak');

        $this->assertSame('Desain lama', $desain->refresh()->data_desain['elemen'][0]['isi']);
        $this->assertSame(0, DesignRevision::count());
    }

    public function test_edit_desain_hanya_admin_ecommerce(): void
    {
        ['pesanan' => $pesanan] = $this->siap();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->putJson('/admin/pesanan/'.$pesanan->nomor_pesanan.'/desain', ['data_desain' => ['elemen' => []]])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin_scm']))
            ->putJson('/admin/pesanan/'.$pesanan->nomor_pesanan.'/desain', ['data_desain' => ['elemen' => []]])
            ->assertForbidden();
    }
}
