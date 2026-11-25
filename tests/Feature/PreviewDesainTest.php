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
 * FR-CDSN-04 #1 dan #4, UC-19: data preview desain — template, kanvas, dan
 * elemen siap gambar — plus penolakan saat berkas gambar elemen tidak lagi
 * bisa dibaca (render gagal).
 */
class PreviewDesainTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_GAGAL_RENDER = 'Preview gagal dimuat, periksa kembali elemen desain';

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

    /**
     * @param  array<int, array<string, mixed>>  $elemen
     */
    private function desain(User $user, Template $template, array $elemen, array $ganti = []): Design
    {
        return Design::create(array_merge([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'data_desain' => ['elemen' => $elemen],
            'status' => 'draft',
            'versi' => 1,
        ], $ganti));
    }

    /** @return array<string, mixed> */
    private function teks(array $ganti = []): array
    {
        return array_merge([
            'tipe' => 'teks', 'isi' => 'Nichibag',
            'x' => 60, 'y' => 50, 'lebar' => 120, 'tinggi' => 30,
            'rotasi' => 0, 'z' => 1,
        ], $ganti);
    }

    /** @return array<string, mixed> */
    private function gambar(string $berkas, array $ganti = []): array
    {
        return array_merge([
            'tipe' => 'gambar', 'berkas' => $berkas,
            'x' => 60, 'y' => 100, 'lebar' => 80, 'tinggi' => 80,
            'rotasi' => 0, 'z' => 2,
        ], $ganti);
    }

    // ---------- FR-CDSN-04 #1: render desain final ----------

    public function test_user_melihat_preview_desain_miliknya(): void
    {
        // PNG 1x1 asli: `getimagesize()` membaca header berkas, bukan namanya.
        Storage::disk('public')->put(
            'desain/gambar/1/logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='),
        );

        $desain = $this->desain($this->user(), $this->template($this->produkCustom()), [
            $this->teks(),
            $this->gambar('desain/gambar/1/logo.png'),
        ]);

        $this->actingAs($desain->user)
            ->getJson('/desain/'.$desain->id.'/preview')
            ->assertOk()
            ->assertJsonPath('data.id', $desain->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.kanvas.lebar', 800)
            ->assertJsonPath('data.kanvas.tinggi', 600)
            ->assertJsonPath('data.template.nama', 'Template Tas')
            ->assertJsonPath('data.template.file_url', Storage::disk('public')->url('template/tas.png'))
            ->assertJsonPath('data.elemen.0.isi', 'Nichibag')
            ->assertJsonPath('data.elemen.1.url', Storage::disk('public')->url('desain/gambar/1/logo.png'))
            ->assertJsonPath('data.preview_gambar', null);
    }

    public function test_elemen_preview_diurutkan_menurut_layer(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()), [
            $this->teks(['isi' => 'Belakang', 'z' => 1]),
            $this->teks(['isi' => 'Depan', 'z' => 5]),
            $this->teks(['isi' => 'Tengah', 'z' => 3]),
        ]);

        $this->actingAs($desain->user)
            ->getJson('/desain/'.$desain->id.'/preview')
            ->assertOk()
            ->assertJsonPath('data.elemen.0.isi', 'Belakang')
            ->assertJsonPath('data.elemen.1.isi', 'Tengah')
            ->assertJsonPath('data.elemen.2.isi', 'Depan');
    }

    public function test_preview_menampilkan_gambar_preview_yang_tersimpan(): void
    {
        Storage::disk('public')->put('desain/preview/1.png', 'isi preview');

        $desain = $this->desain($this->user(), $this->template($this->produkCustom()), [$this->teks()], [
            'preview_gambar' => 'desain/preview/1.png',
        ]);

        $this->actingAs($desain->user)
            ->getJson('/desain/'.$desain->id.'/preview')
            ->assertOk()
            ->assertJsonPath('data.preview_gambar', Storage::disk('public')->url('desain/preview/1.png'));
    }

    // ---------- Batas akses ----------

    public function test_guest_tidak_bisa_membuka_preview(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()), [$this->teks()]);

        $this->getJson('/desain/'.$desain->id.'/preview')->assertUnauthorized();
    }

    public function test_preview_desain_milik_user_lain_dijawab_404(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()), [$this->teks()]);

        $this->actingAs($this->user())
            ->getJson('/desain/'.$desain->id.'/preview')
            ->assertNotFound()
            ->assertJsonPath('message', 'Desain tidak ditemukan');
    }

    // ---------- FR-CDSN-04 #4: rendering gagal ----------

    public function test_preview_gagal_kalau_berkas_gambar_hilang(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()), [
            $this->teks(),
            $this->gambar('desain/gambar/1/hilang.png'),
        ]);

        $this->actingAs($desain->user)
            ->getJson('/desain/'.$desain->id.'/preview')
            ->assertStatus(422)
            ->assertJsonPath('message', self::PESAN_GAGAL_RENDER);
    }

    public function test_preview_gagal_kalau_berkas_gambar_rusak(): void
    {
        Storage::disk('public')->put('desain/gambar/1/rusak.png', 'bukan gambar sama sekali');

        $desain = $this->desain($this->user(), $this->template($this->produkCustom()), [
            $this->gambar('desain/gambar/1/rusak.png'),
        ]);

        $this->actingAs($desain->user)
            ->getJson('/desain/'.$desain->id.'/preview')
            ->assertStatus(422)
            ->assertJsonPath('message', self::PESAN_GAGAL_RENDER);
    }

    public function test_preview_tetap_terbuka_kalau_hanya_ada_teks(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()), [$this->teks()]);

        $this->actingAs($desain->user)
            ->getJson('/desain/'.$desain->id.'/preview')
            ->assertOk();
    }
}
