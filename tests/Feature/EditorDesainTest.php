<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Design;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FR-CDSN-02 #1 dan #10, UC-17: pembuka editor. Guest boleh membuka (tanpa
 * draft), User yang sudah login menerima draft terakhir untuk template itu
 * beserta catatan revisinya.
 */
class EditorDesainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders(['Accept' => 'application/json']);
        Storage::fake('public');
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    private function produkCustom(array $ganti = []): Product
    {
        return Product::create(array_merge([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tas Rotan Custom',
            'deskripsi' => 'Tas anyaman rotan',
            'tipe' => 'custom',
            'harga_dasar' => 150000,
            'is_aktif' => true,
            'sla_hari' => 7,
        ], $ganti));
    }

    private function template(Product $produk, array $ganti = []): Template
    {
        return Template::create(array_merge([
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
        ], $ganti));
    }

    private function desain(User $user, Template $template, array $ganti = []): Design
    {
        return Design::create(array_merge([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'data_desain' => ['elemen' => [[
                'tipe' => 'teks', 'isi' => 'Nichibag',
                'x' => 60, 'y' => 50, 'lebar' => 120, 'tinggi' => 30,
            ]]],
            'status' => 'draft',
            'versi' => 1,
        ], $ganti));
    }

    // ---------- FR-CDSN-02 #1: editor menampilkan template dan area ----------

    public function test_guest_membuka_editor_dan_mendapat_template_dan_area(): void
    {
        $produk = $this->produkCustom();
        $template = $this->template($produk);

        $this->getJson('/desain/editor/'.$produk->id)
            ->assertOk()
            ->assertJsonPath('data.produk.id', $produk->id)
            ->assertJsonPath('data.produk.nama', 'Tas Rotan Custom')
            ->assertJsonPath('data.produk.tipe', 'custom')
            ->assertJsonPath('data.template.id', $template->id)
            ->assertJsonPath('data.template.file_url', Storage::disk('public')->url('template/tas.png'))
            ->assertJsonPath('data.template.lebar_kanvas', 800)
            ->assertJsonPath('data.template.tinggi_kanvas', 600)
            ->assertJsonPath('data.template.area_x', 50)
            ->assertJsonPath('data.template.area_lebar', 300)
            ->assertJsonPath('data.template.area_tinggi', 200)
            ->assertJsonPath('data.draft', null);
    }

    public function test_editor_produk_bukan_custom_ditolak(): void
    {
        $produk = $this->produkCustom(['tipe' => 'jadi', 'sla_hari' => null]);

        $this->getJson('/desain/editor/'.$produk->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Editor hanya tersedia untuk produk custom');
    }

    public function test_editor_produk_tanpa_template_aktif_ditolak(): void
    {
        $produk = $this->produkCustom();
        $this->template($produk, ['is_aktif' => false]);

        $this->getJson('/desain/editor/'.$produk->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Produk custom belum punya template desain aktif');
    }

    public function test_editor_produk_tidak_ada_dijawab_404(): void
    {
        $this->getJson('/desain/editor/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Produk tidak ditemukan');
    }

    public function test_editor_bisa_memilih_template_tertentu(): void
    {
        $produk = $this->produkCustom();
        $this->template($produk);
        $kedua = $this->template($produk, ['nama' => 'Template Kedua', 'file' => 'template/kedua.png']);

        $this->getJson('/desain/editor/'.$produk->id.'?template_id='.$kedua->id)
            ->assertOk()
            ->assertJsonPath('data.template.id', $kedua->id)
            ->assertJsonPath('data.template.nama', 'Template Kedua');
    }

    public function test_editor_menolak_template_nonaktif_atau_milik_produk_lain(): void
    {
        $produk = $this->produkCustom();
        $this->template($produk);
        $nonaktif = $this->template($produk, ['is_aktif' => false]);
        $produkLain = $this->produkCustom(['nama' => 'Topi Custom']);
        $milikLain = $this->template($produkLain);

        $this->getJson('/desain/editor/'.$produk->id.'?template_id='.$nonaktif->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Template desain tidak tersedia');

        $this->getJson('/desain/editor/'.$produk->id.'?template_id='.$milikLain->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Template desain tidak tersedia');
    }

    // ---------- FR-CDSN-02 #10: draft terakhir ikut dimuat ----------

    public function test_editor_memuat_draft_terakhir_milik_user(): void
    {
        $user = $this->user();
        $produk = $this->produkCustom();
        $template = $this->template($produk);

        $lama = $this->desain($user, $template);
        $lama->update(['updated_at' => now()->subDay()]);

        $baru = $this->desain($user, $template, [
            'status' => 'perlu_revisi',
            'catatan_revisi' => 'Logo terlalu kecil',
            'versi' => 2,
        ]);
        $baru->update(['updated_at' => now()]);

        $this->actingAs($user)
            ->getJson('/desain/editor/'.$produk->id)
            ->assertOk()
            ->assertJsonPath('data.draft.id', $baru->id)
            ->assertJsonPath('data.draft.status', 'perlu_revisi')
            ->assertJsonPath('data.draft.catatan_revisi', 'Logo terlalu kecil')
            ->assertJsonPath('data.draft.versi', 2)
            ->assertJsonPath('data.draft.data_desain.elemen.0.isi', 'Nichibag');
    }

    public function test_editor_tidak_memakai_draft_user_lain(): void
    {
        $produk = $this->produkCustom();
        $template = $this->template($produk);
        $this->desain($this->user(), $template);

        $this->actingAs($this->user())
            ->getJson('/desain/editor/'.$produk->id)
            ->assertOk()
            ->assertJsonPath('data.draft', null);
    }

    public function test_editor_tidak_memuat_desain_yang_sudah_disetujui(): void
    {
        $user = $this->user();
        $produk = $this->produkCustom();
        $template = $this->template($produk);
        $this->desain($user, $template, ['status' => 'disetujui']);

        $this->actingAs($user)
            ->getJson('/desain/editor/'.$produk->id)
            ->assertOk()
            ->assertJsonPath('data.draft', null);
    }
}
