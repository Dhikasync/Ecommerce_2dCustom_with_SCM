<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** FR-SCM-01 #6: Admin SCM menginput stok awal varian baru dan kartu stoknya. */
class StokAwalTest extends TestCase
{
    use RefreshDatabase;

    private function adminScm(): User
    {
        return User::factory()->create(['role' => 'admin_scm']);
    }

    private function varian(int $stokFisik = 0): Variant
    {
        $produk = Product::create([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tote Bag',
            'deskripsi' => 'Tas kanvas',
            'tipe' => 'jadi',
            'harga_dasar' => 20000,
            'is_aktif' => true,
        ]);

        return $produk->variants()->create([
            'sku' => 'TOTE-'.strtoupper((string) Str::ulid()),
            'harga' => 20000,
            'berat' => 250,
            'moq' => 1,
            'stok_fisik' => $stokFisik,
            'stok_direservasi' => 0,
        ]);
    }

    public function test_admin_scm_menginput_stok_awal(): void
    {
        $varian = $this->varian();

        $this->actingAs($this->adminScm())
            ->postJson('/admin/stok/varian/'.$varian->id.'/awal', [
                'stok_fisik' => 25,
                'alasan' => 'Stok awal gudang',
            ])
            ->assertCreated()
            ->assertJsonPath('data.stok_fisik', 25)
            ->assertJsonPath('data.stok_tersedia', 25);

        $varian->refresh();
        $this->assertSame(25, $varian->stok_fisik);

        $gerakan = StockMovement::where('variant_id', $varian->id)->sole();
        $this->assertSame('stok_awal', $gerakan->tipe);
        $this->assertSame(25, $gerakan->jumlah_masuk);
        $this->assertSame(25, $gerakan->stok_fisik_setelah);
        $this->assertSame('Stok awal gudang', $gerakan->alasan);
    }

    public function test_stok_awal_wajib_positif(): void
    {
        $varian = $this->varian();

        $this->actingAs($this->adminScm())
            ->postJson('/admin/stok/varian/'.$varian->id.'/awal', [
                'stok_fisik' => 0,
                'alasan' => 'Stok awal gudang',
            ])
            ->assertStatus(422);

        $this->assertSame(0, StockMovement::count());
    }

    public function test_stok_awal_ditolak_bila_varian_sudah_punya_stok(): void
    {
        $varian = $this->varian(10);

        $this->actingAs($this->adminScm())
            ->postJson('/admin/stok/varian/'.$varian->id.'/awal', [
                'stok_fisik' => 5,
                'alasan' => 'Stok awal gudang',
            ])
            ->assertStatus(422);

        $this->assertSame(10, $varian->refresh()->stok_fisik);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_stok_awal_hanya_admin_scm(): void
    {
        $varian = $this->varian();

        $this->actingAs(User::factory()->create(['role' => 'admin_ecommerce']))
            ->postJson('/admin/stok/varian/'.$varian->id.'/awal', [
                'stok_fisik' => 5,
                'alasan' => 'Stok awal',
            ])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->postJson('/admin/stok/varian/'.$varian->id.'/awal', [
                'stok_fisik' => 5,
                'alasan' => 'Stok awal',
            ])
            ->assertForbidden();
    }
}
