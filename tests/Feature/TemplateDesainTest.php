<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FR-CDSN-01 #1/#2/#5, UC-15, NFR-FIL-02: Admin Ecommerce mengunggah gambar
 * dasar produk, menentukan ukuran kanvas dan batas area kustomisasi, lalu
 * mengaitkan template ke produk custom tertentu.
 */
class TemplateDesainTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_FORMAT = 'Format file template harus PNG, JPG, atau SVG';

    private const PESAN_UKURAN = 'Ukuran file template maksimal 10 MB';

    protected function setUp(): void
    {
        parent::setUp();

        // Unggah memakai multipart; tanpa header JSON, kegagalan jadi redirect 302.
        $this->withHeaders(['Accept' => 'application/json']);
        Storage::fake('public');
    }

    private function adminEcommerce(): User
    {
        return User::factory()->create(['role' => 'admin_ecommerce']);
    }

    private function produkCustom(array $ganti = []): Product
    {
        return Product::create(array_merge([
            'category_id' => Category::firstOrCreate(['nama' => 'Tas'])->id,
            'nama' => 'Tas Rotan Custom',
            'deskripsi' => 'Tas anyaman rotan',
            'tipe' => 'custom',
            'harga_dasar' => 150000,
            'is_aktif' => false,
            'sla_hari' => 7,
        ], $ganti));
    }

    /** GD tidak terpasang; berkas palsu hanya melaporkan ukuran dan MIME. */
    private function berkas(string $nama = 'template.png', int $kb = 200): UploadedFile
    {
        $mime = match (pathinfo($nama, PATHINFO_EXTENSION)) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            default => 'text/plain',
        };

        return UploadedFile::fake()->create($nama, $kb, $mime);
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    private function dataTemplate(Product $produk, array $ganti = []): array
    {
        return array_merge([
            'product_id' => $produk->id,
            'nama' => 'Template Paper Bag',
            'file' => $this->berkas(),
            'lebar_kanvas' => 800,
            'tinggi_kanvas' => 600,
            'area_x' => 50,
            'area_y' => 40,
            'area_lebar' => 300,
            'area_tinggi' => 200,
        ], $ganti);
    }

    public function test_admin_menambah_template_dengan_kanvas_dan_area_kustomisasi(): void
    {
        $produk = $this->produkCustom();

        $respons = $this->actingAs($this->adminEcommerce())
            ->post('/admin/template', $this->dataTemplate($produk))
            ->assertCreated()
            ->assertJsonPath('data.nama', 'Template Paper Bag')
            ->assertJsonPath('data.lebar_kanvas', 800)
            ->assertJsonPath('data.tinggi_kanvas', 600)
            ->assertJsonPath('data.area_x', 50)
            ->assertJsonPath('data.area_y', 40)
            ->assertJsonPath('data.area_lebar', 300)
            ->assertJsonPath('data.area_tinggi', 200)
            ->assertJsonPath('data.is_aktif', true);

        $template = Template::sole();

        $this->assertSame($produk->id, $template->product_id);
        $this->assertStringStartsWith('template/', $template->file);
        $this->assertStringEndsWith('.png', $template->file);
        Storage::disk('public')->assertExists($template->file);
        $respons->assertJsonPath('data.file_url', Storage::disk('public')->url($template->file));
    }

    public function test_file_svg_diterima(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->post('/admin/template', $this->dataTemplate($this->produkCustom(), [
                'file' => $this->berkas('template.svg'),
            ]))
            ->assertCreated();

        $this->assertStringEndsWith('.svg', Template::sole()->file);
    }

    /** FR-CDSN-01 #5 / NFR-FIL-02: hanya PNG, JPG, atau SVG. */
    public function test_format_file_tidak_didukung_ditolak(): void
    {
        $produk = $this->produkCustom();

        foreach (['template.pdf', 'template.gif', 'template.txt'] as $nama) {
            $this->actingAs($this->adminEcommerce())
                ->post('/admin/template', $this->dataTemplate($produk, [
                    'file' => $this->berkas($nama),
                ]))
                ->assertStatus(422)
                ->assertJsonPath('errors.file.0', self::PESAN_FORMAT);
        }

        $this->assertSame(0, Template::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_file_tepat_10_mb_diterima_dan_lebih_ditolak(): void
    {
        $produk = $this->produkCustom();
        $admin = $this->adminEcommerce();

        $this->actingAs($admin)
            ->post('/admin/template', $this->dataTemplate($produk, [
                'file' => $this->berkas('template.png', 10 * 1024),
            ]))
            ->assertCreated();

        $this->actingAs($admin)
            ->post('/admin/template', $this->dataTemplate($produk, [
                'file' => $this->berkas('besar.png', 10 * 1024 + 1),
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', self::PESAN_UKURAN);

        $this->assertSame(1, Template::count());
    }

    /** FR-CDSN-01 #6: template dikaitkan ke produk custom, bukan produk jadi. */
    public function test_template_hanya_boleh_dikaitkan_ke_produk_custom(): void
    {
        $produkJadi = $this->produkCustom(['tipe' => 'jadi', 'sla_hari' => null]);

        $this->actingAs($this->adminEcommerce())
            ->post('/admin/template', $this->dataTemplate($produkJadi))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.product_id.0',
                'Template hanya dapat dikaitkan ke produk custom',
            );

        $this->assertSame(0, Template::count());
    }

    /** FR-CDSN-01 #2: batas area hanya berarti kalau keempat angkanya ada. */
    public function test_area_kustomisasi_harus_lengkap(): void
    {
        $produk = $this->produkCustom();

        $this->actingAs($this->adminEcommerce())
            ->post('/admin/template', $this->dataTemplate($produk, [
                'area_y' => null,
                'area_lebar' => null,
                'area_tinggi' => null,
            ]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.area_x.0',
                'Batas area kustomisasi harus diisi lengkap: x, y, lebar, dan tinggi',
            );

        $this->assertSame(0, Template::count());
    }

    public function test_area_tanpa_batas_diterima(): void
    {
        $this->actingAs($this->adminEcommerce())
            ->post('/admin/template', $this->dataTemplate($this->produkCustom(), [
                'area_x' => null,
                'area_y' => null,
                'area_lebar' => null,
                'area_tinggi' => null,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.area_x', null);
    }

    public function test_area_tidak_boleh_keluar_dari_kanvas(): void
    {
        $produk = $this->produkCustom();

        $this->actingAs($this->adminEcommerce())
            ->post('/admin/template', $this->dataTemplate($produk, [
                'area_x' => 700,
                'area_lebar' => 300,
            ]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.area_x.0',
                'Batas area kustomisasi harus berada di dalam kanvas 800 x 600',
            );

        $this->assertSame(0, Template::count());
    }

    public function test_daftar_template_bisa_disaring_per_produk(): void
    {
        $admin = $this->adminEcommerce();
        $pertama = $this->produkCustom(['nama' => 'Paper Bag']);
        $kedua = $this->produkCustom(['nama' => 'Tote Bag']);

        $this->actingAs($admin)->post('/admin/template', $this->dataTemplate($pertama))->assertCreated();
        $this->actingAs($admin)->post('/admin/template', $this->dataTemplate($kedua, [
            'nama' => 'Template Tote',
            'file' => $this->berkas('tote.png'),
        ]))->assertCreated();

        $pertamaFile = Template::where('nama', 'Template Paper Bag')->sole()->file;

        $this->actingAs($admin)
            ->getJson('/admin/template')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.file_url', Storage::disk('public')->url($pertamaFile));

        $this->actingAs($admin)
            ->getJson('/admin/template?product_id='.$kedua->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Template Tote')
            ->assertJsonPath('data.0.produk.nama', 'Tote Bag');
    }

    public function test_daftar_menampilkan_template_nonaktif_dan_bisa_disaring(): void
    {
        $admin = $this->adminEcommerce();
        $produk = $this->produkCustom();

        $this->actingAs($admin)->post('/admin/template', $this->dataTemplate($produk))->assertCreated();
        $this->actingAs($admin)
            ->post('/admin/template', $this->dataTemplate($produk, [
                'nama' => 'Template Lama',
                'file' => $this->berkas('lama.png'),
                'is_aktif' => false,
            ]))
            ->assertCreated();

        // Panel admin melihat semua; penyaring tersedia untuk pemilih template.
        $this->actingAs($admin)->getJson('/admin/template')->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/admin/template?is_aktif=1')->assertJsonCount(1, 'data');
        $this->actingAs($admin)
            ->getJson('/admin/template?is_aktif=0')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Template Lama');
    }

    public function test_user_biasa_dan_admin_scm_tidak_boleh_mengelola_template(): void
    {
        $produk = $this->produkCustom();

        foreach (['user', 'admin_scm'] as $role) {
            $akun = User::factory()->create(['role' => $role]);

            $this->actingAs($akun)->getJson('/admin/template')->assertStatus(403);
            $this->actingAs($akun)
                ->post('/admin/template', $this->dataTemplate($produk))
                ->assertStatus(403);
        }

        $this->assertSame(0, Template::count());
    }

    public function test_guest_harus_login_dulu(): void
    {
        $this->getJson('/admin/template')->assertStatus(401);
    }
}
