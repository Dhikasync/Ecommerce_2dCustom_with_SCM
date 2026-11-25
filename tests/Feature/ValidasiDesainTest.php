<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Design;
use App\Models\DesignRevision;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FR-CDSN-05 #2/#3/#5, UC-18: Admin Ecommerce memvalidasi desain yang masuk
 * antrean — menyetujui atau meminta revisi dengan catatan — dan setiap
 * keputusan meninggalkan satu baris riwayat.
 *
 * Efek ke pesanan (#2/#3 status pesanan, #8 kedaluwarsa) belum diuji di sini
 * karena modul pesanan belum ada.
 */
class ValidasiDesainTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_BUKAN_ANTREAN = 'Desain ini tidak sedang menunggu validasi';

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

    private function adminEcommerce(): User
    {
        return User::factory()->create(['role' => 'admin_ecommerce']);
    }

    private function produkCustom(): Product
    {
        return Product::create([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tas Rotan Custom',
            'deskripsi' => 'Tas anyaman rotan',
            'tipe' => 'custom',
            'harga_dasar' => 150000,
            'is_aktif' => true,
            'sla_hari' => 7,
        ]);
    }

    private function template(Product $produk): Template
    {
        return Template::create([
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
    }

    private function desain(User $user, Template $template, array $ganti = []): Design
    {
        return Design::create(array_merge([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'data_desain' => ['elemen' => [[
                'tipe' => 'teks', 'isi' => 'Nichibag',
                'x' => 60, 'y' => 50, 'lebar' => 120, 'tinggi' => 30,
                'rotasi' => 0, 'z' => 1,
            ]]],
            'status' => 'menunggu_validasi',
            'versi' => 1,
        ], $ganti));
    }

    // ---------- FR-CDSN-05 #1: daftar validasi ----------

    public function test_admin_melihat_daftar_desain_menunggu_validasi(): void
    {
        $template = $this->template($this->produkCustom());
        $lama = $this->desain($this->user(), $template, ['preview_gambar' => 'desain/preview/1.png']);
        $lama->update(['updated_at' => now()->subHours(5)]);
        $baru = $this->desain($this->user(), $template);
        $this->desain($this->user(), $template, ['status' => 'draft']);

        $this->actingAs($this->adminEcommerce())
            ->getJson('/admin/validasi-desain')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $lama->id)
            ->assertJsonPath('data.0.user.name', $lama->user->name)
            ->assertJsonPath('data.0.preview_gambar', Storage::disk('public')->url('desain/preview/1.png'))
            ->assertJsonPath('data.1.id', $baru->id)
            ->assertJsonPath('data.1.preview_gambar', null);
    }

    public function test_daftar_validasi_hanya_untuk_admin_ecommerce(): void
    {
        $this->actingAs($this->user())->getJson('/admin/validasi-desain')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin_scm']))
            ->getJson('/admin/validasi-desain')
            ->assertForbidden();
    }

    public function test_guest_tidak_bisa_membuka_daftar_validasi(): void
    {
        // Dipisah dari test di atas: `actingAs` bertahan sampai test selesai.
        $this->getJson('/admin/validasi-desain')->assertUnauthorized();
    }

    // ---------- FR-CDSN-05 #5: detail dan riwayat ----------

    public function test_admin_melihat_detail_desain_dan_riwayat_validasi(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()), ['versi' => 2]);
        $admin = $this->adminEcommerce();

        DesignRevision::create([
            'design_id' => $desain->id,
            'versi' => 1,
            'status' => 'perlu_revisi',
            'catatan_revisi' => 'Logo terlalu kecil',
            'divalidasi_oleh' => $admin->id,
        ]);

        $this->actingAs($this->adminEcommerce())
            ->getJson('/admin/validasi-desain/'.$desain->id)
            ->assertOk()
            ->assertJsonPath('data.id', $desain->id)
            ->assertJsonPath('data.status', 'menunggu_validasi')
            ->assertJsonPath('data.versi', 2)
            ->assertJsonPath('data.data_desain.elemen.0.isi', 'Nichibag')
            ->assertJsonPath('data.riwayat.0.versi', 1)
            ->assertJsonPath('data.riwayat.0.status', 'perlu_revisi')
            ->assertJsonPath('data.riwayat.0.catatan_revisi', 'Logo terlalu kecil')
            ->assertJsonPath('data.riwayat.0.divalidasi_oleh.name', $admin->name);
    }

    public function test_detail_desain_tidak_ada_dijawab_404(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->getJson('/admin/validasi-desain/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Desain tidak ditemukan');
    }

    // ---------- FR-CDSN-05 #2: menyetujui ----------

    public function test_admin_menyetujui_desain(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()));
        $admin = $this->adminEcommerce();

        $this->actingAs($admin)
            ->postJson('/admin/validasi-desain/'.$desain->id.'/setujui')
            ->assertOk()
            ->assertJsonPath('data.status', 'disetujui')
            ->assertJsonPath('data.catatan_revisi', null);

        $desain->refresh();

        $this->assertSame('disetujui', $desain->status);

        $riwayat = DesignRevision::where('design_id', $desain->id)->sole();

        $this->assertSame('disetujui', $riwayat->status);
        $this->assertSame(1, $riwayat->versi);
        $this->assertSame($admin->id, $riwayat->divalidasi_oleh);
        $this->assertEquals($desain->data_desain, $riwayat->data_desain);
    }

    // ---------- FR-CDSN-05 #3: meminta revisi ----------

    public function test_admin_meminta_revisi_menyimpan_catatan_dan_batas_tiga_hari(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()));
        $admin = $this->adminEcommerce();

        $this->actingAs($admin)
            ->postJson('/admin/validasi-desain/'.$desain->id.'/revisi', [
                'catatan_revisi' => 'Logo terlalu kecil dan posisinya melewati batas cetak',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'perlu_revisi')
            ->assertJsonPath('data.catatan_revisi', 'Logo terlalu kecil dan posisinya melewati batas cetak');

        $desain->refresh();

        $this->assertSame('perlu_revisi', $desain->status);
        $this->assertSame(now()->addDays(3)->toDateString(), $desain->batas_revisi_pada->toDateString());

        $riwayat = DesignRevision::where('design_id', $desain->id)->sole();

        $this->assertSame('perlu_revisi', $riwayat->status);
        $this->assertSame('Logo terlalu kecil dan posisinya melewati batas cetak', $riwayat->catatan_revisi);
        $this->assertSame($admin->id, $riwayat->divalidasi_oleh);
    }

    public function test_catatan_revisi_wajib_diisi(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()));

        $this->actingAs($this->adminEcommerce())
            ->postJson('/admin/validasi-desain/'.$desain->id.'/revisi', ['catatan_revisi' => '   '])
            ->assertStatus(422)
            ->assertJsonPath('errors.catatan_revisi.0', 'Catatan revisi wajib diisi');

        $this->assertSame('menunggu_validasi', $desain->refresh()->status);
        $this->assertSame(0, DesignRevision::count());
    }

    // ---------- Batas status ----------

    public function test_desain_yang_bukan_menunggu_validasi_tidak_bisa_divalidasi(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()), [
            'status' => 'perlu_revisi',
        ]);
        $admin = $this->adminEcommerce();

        $this->actingAs($admin)
            ->postJson('/admin/validasi-desain/'.$desain->id.'/setujui')
            ->assertStatus(422)
            ->assertJsonPath('message', self::PESAN_BUKAN_ANTREAN);

        $this->actingAs($admin)
            ->postJson('/admin/validasi-desain/'.$desain->id.'/revisi', ['catatan_revisi' => 'Ulangi'])
            ->assertStatus(422)
            ->assertJsonPath('message', self::PESAN_BUKAN_ANTREAN);

        $this->assertSame('perlu_revisi', $desain->refresh()->status);
        $this->assertSame(0, DesignRevision::count());
    }

    public function test_riwayat_bertambah_setiap_putaran_validasi(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()));
        $admin = $this->adminEcommerce();

        $this->actingAs($admin)
            ->postJson('/admin/validasi-desain/'.$desain->id.'/revisi', ['catatan_revisi' => 'Perbesar logo'])
            ->assertOk();

        // User memperbaiki lalu mengajukan ulang; versi naik di sisi pengajuan.
        // `refresh()` wajib: instance ini masih memegang status lama, sehingga
        // tanpa refresh Eloquent menganggap `status` tidak berubah.
        $desain->refresh()->update(['versi' => 2, 'status' => 'menunggu_validasi']);

        $this->actingAs($admin)
            ->postJson('/admin/validasi-desain/'.$desain->id.'/setujui')
            ->assertOk();

        $riwayat = DesignRevision::where('design_id', $desain->id)->orderBy('id')->get();

        $this->assertCount(2, $riwayat);
        $this->assertSame(['perlu_revisi', 1], [$riwayat[0]->status, $riwayat[0]->versi]);
        $this->assertSame(['disetujui', 2], [$riwayat[1]->status, $riwayat[1]->versi]);
    }
}
