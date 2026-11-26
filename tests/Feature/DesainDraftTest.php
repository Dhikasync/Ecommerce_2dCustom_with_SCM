<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Design;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FR-CDSN-02 #1/#9/#10, UC-17: draft desain disimpan, dibuka kembali, dan
 * diubah; rujukan gambar unggahan pindah ke folder desain supaya tidak hilang
 * saat daftar sementara dibersihkan. FR-CDSN-05 #4 menguji kirim ulang revisi.
 */
class DesainDraftTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_DI_LUAR_AREA = 'Elemen harus berada di dalam area kustomisasi';

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

    /** Kanvas 800 x 600, area kustomisasi 50,40 sampai 350,240. */
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

    private function varian(Product $produk, array $ganti = []): Variant
    {
        return Variant::create(array_merge([
            'product_id' => $produk->id,
            'sku' => 'TAS-'.$produk->id,
            'ukuran' => '30x20',
            'harga' => 150000,
            'berat' => 500,
            'moq' => 1,
        ], $ganti));
    }

    /** @return array<string, mixed> */
    private function elemenTeks(array $ganti = []): array
    {
        return array_merge([
            'tipe' => 'teks',
            'isi' => 'Nichibag',
            'x' => 60,
            'y' => 50,
            'lebar' => 120,
            'tinggi' => 30,
            'rotasi' => 0,
            'z' => 1,
        ], $ganti);
    }

    private function desain(User $user, Template $template, array $ganti = []): Design
    {
        return Design::create(array_merge([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'data_desain' => ['elemen' => [$this->elemenTeks()]],
            'status' => 'draft',
            'versi' => 1,
        ], $ganti));
    }

    /**
     * @param  array<int, array<string, mixed>>  $elemen
     * @return array<string, mixed>
     */
    private function dataDraft(Template $template, array $elemen, array $ganti = []): array
    {
        return array_merge([
            'template_id' => $template->id,
            'data_desain' => ['elemen' => $elemen],
        ], $ganti);
    }

    /** FR-CDSN-05 #4: pesanan yang sedang menunggu revisi desain. */
    private function pesananMenungguRevisi(Design $desain): Order
    {
        $pesanan = Order::create([
            'user_id' => $desain->user_id,
            'nomor_pesanan' => 'INV-'.strtoupper((string) Str::ulid()),
            'status' => 'menunggu_revisi_desain',
            'subtotal_barang' => 150000,
            'ongkir' => 0,
            'total' => 150000,
            'nama_penerima' => 'Andhi',
            'no_hp' => '+628123456789',
            'alamat_pengiriman' => 'Jl. Sumur Bandung No. 1',
        ]);

        OrderItem::create([
            'order_id' => $pesanan->id,
            'design_id' => $desain->id,
            'nama_produk' => 'Tas Rotan Custom',
            'quantity' => 1,
            'harga_satuan' => 150000,
            'sla_hari' => 7,
        ]);

        return $pesanan;
    }

    // ---------- FR-CDSN-02 #9: menyimpan progres sebagai draft ----------

    public function test_user_menyimpan_draft_desain(): void
    {
        $user = $this->user();
        $produk = $this->produkCustom();
        $template = $this->template($produk);
        $varian = $this->varian($produk);

        $this->actingAs($user)
            ->postJson('/desain', $this->dataDraft($template, [$this->elemenTeks()], [
                'variant_id' => $varian->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.versi', 1)
            ->assertJsonPath('data.variant_id', $varian->id)
            ->assertJsonPath('data.data_desain.elemen.0.isi', 'Nichibag');

        $desain = Design::sole();

        $this->assertSame($user->id, $desain->user_id);
        $this->assertSame($template->id, $desain->template_id);
        $this->assertSame('draft', $desain->status);
    }

    public function test_draft_boleh_kosong_dan_guest_tidak_bisa_menyimpannya(): void
    {
        $produk = $this->produkCustom();
        $template = $this->template($produk);

        $this->postJson('/desain', $this->dataDraft($template, []))->assertUnauthorized();

        $this->actingAs($this->user())
            ->postJson('/desain', $this->dataDraft($template, []))
            ->assertCreated()
            ->assertJsonCount(0, 'data.data_desain.elemen');
    }

    public function test_template_nonaktif_tidak_bisa_dipakai(): void
    {
        $template = $this->template($this->produkCustom(), ['is_aktif' => false]);

        $this->actingAs($this->user())
            ->postJson('/desain', $this->dataDraft($template, [$this->elemenTeks()]))
            ->assertStatus(422)
            ->assertJsonPath('errors.template_id.0', 'Template desain tidak tersedia');
    }

    public function test_varian_harus_milik_produk_template(): void
    {
        $template = $this->template($this->produkCustom());
        $varianLain = $this->varian($this->produkCustom(['nama' => 'Topi Custom']));

        $this->actingAs($this->user())
            ->postJson('/desain', $this->dataDraft($template, [$this->elemenTeks()], [
                'variant_id' => $varianLain->id,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.variant_id.0', 'Varian tidak sesuai produk template');
    }

    // ---------- FR-CDSN-02 #2/#3/#7: bentuk elemen dan batas area ----------

    public function test_elemen_teks_wajib_berisi_teks(): void
    {
        $template = $this->template($this->produkCustom());

        $respons = $this->actingAs($this->user())
            ->postJson('/desain', $this->dataDraft($template, [
                $this->elemenTeks(['isi' => '   ']),
            ]))
            ->assertStatus(422);

        $this->assertSame(
            ['Elemen teks wajib berisi teks'],
            $respons->json('errors')['data_desain.elemen.0.isi'],
        );
    }

    public function test_elemen_gambar_wajib_menyebut_berkas(): void
    {
        $template = $this->template($this->produkCustom());

        $respons = $this->actingAs($this->user())
            ->postJson('/desain', $this->dataDraft($template, [
                ['tipe' => 'gambar', 'x' => 60, 'y' => 50, 'lebar' => 80, 'tinggi' => 80],
            ]))
            ->assertStatus(422);

        $this->assertSame(
            ['Elemen gambar wajib menyebut berkas unggahan'],
            $respons->json('errors')['data_desain.elemen.0.berkas'],
        );
    }

    public function test_elemen_di_luar_area_ditolak(): void
    {
        $template = $this->template($this->produkCustom());

        $respons = $this->actingAs($this->user())
            ->postJson('/desain', $this->dataDraft($template, [
                $this->elemenTeks(['x' => 300, 'lebar' => 120]),
            ]))
            ->assertStatus(422);

        $this->assertSame(
            [self::PESAN_DI_LUAR_AREA],
            $respons->json('errors')['data_desain.elemen.0.x'],
        );
        $this->assertSame(0, Design::count());
    }

    public function test_elemen_di_batas_area_diterima(): void
    {
        $template = $this->template($this->produkCustom());

        $this->actingAs($this->user())
            ->postJson('/desain', $this->dataDraft($template, [
                $this->elemenTeks(['x' => 50, 'y' => 40, 'lebar' => 300, 'tinggi' => 200]),
            ]))
            ->assertCreated();
    }

    // ---------- FR-CDSN-03 + FR-CDSN-02: rujukan gambar jadi permanen ----------

    public function test_unggahan_dipindah_ke_folder_desain(): void
    {
        Storage::disk('public')->put('desain/unggahan/uji/logo.png', 'isi gambar');

        $template = $this->template($this->produkCustom());

        $respons = $this->withSession(['desain_unggah_owner' => 'uji'])
            ->actingAs($this->user())
            ->postJson('/desain', $this->dataDraft($template, [
                $this->elemenTeks(),
                ['tipe' => 'gambar', 'berkas' => 'desain/unggahan/uji/logo.png',
                    'x' => 60, 'y' => 100, 'lebar' => 80, 'tinggi' => 80],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.data_desain.elemen.0.isi', 'Nichibag');

        $tujuan = 'desain/gambar/'.$respons->json('data.id').'/logo.png';

        $this->assertSame($tujuan, $respons->json('data.data_desain.elemen.1.berkas'));
        $this->assertSame(Storage::disk('public')->url($tujuan), $respons->json('data.data_desain.elemen.1.url'));
        Storage::disk('public')->assertExists($tujuan);
        Storage::disk('public')->assertMissing('desain/unggahan/uji/logo.png');
    }

    public function test_berkas_di_luar_folder_sesi_ditolak(): void
    {
        Storage::disk('public')->put('desain/unggahan/orang-lain/logo.png', 'isi gambar');

        $template = $this->template($this->produkCustom());

        $respons = $this->withSession(['desain_unggah_owner' => 'uji'])
            ->actingAs($this->user())
            ->postJson('/desain', $this->dataDraft($template, [
                ['tipe' => 'gambar', 'berkas' => 'desain/unggahan/orang-lain/logo.png',
                    'x' => 60, 'y' => 100, 'lebar' => 80, 'tinggi' => 80],
            ]))
            ->assertStatus(422);

        $this->assertSame(
            ['Gambar tidak ditemukan'],
            $respons->json('errors')['data_desain.elemen.0.berkas'],
        );
        Storage::disk('public')->assertExists('desain/unggahan/orang-lain/logo.png');
    }

    // ---------- FR-CDSN-02 #10: daftar, buka kembali, dan ubah ----------

    public function test_daftar_draft_hanya_milik_user(): void
    {
        $template = $this->template($this->produkCustom());
        $pemilik = $this->user();
        $this->desain($pemilik, $template);
        $this->desain($this->user(), $template);

        $this->actingAs($pemilik)
            ->getJson('/desain')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'draft')
            ->assertJsonPath('data.0.template.nama', 'Template Tas');
    }

    public function test_membuka_draft_memuat_elemen_template_dan_catatan_revisi(): void
    {
        $template = $this->template($this->produkCustom());
        $desain = $this->desain($this->user(), $template, [
            'status' => 'perlu_revisi',
            'catatan_revisi' => 'Logo terlalu kecil',
            'versi' => 2,
        ]);

        $this->actingAs($desain->user)
            ->getJson('/desain/'.$desain->id)
            ->assertOk()
            ->assertJsonPath('data.id', $desain->id)
            ->assertJsonPath('data.status', 'perlu_revisi')
            ->assertJsonPath('data.catatan_revisi', 'Logo terlalu kecil')
            ->assertJsonPath('data.versi', 2)
            ->assertJsonPath('data.template.nama', 'Template Tas')
            ->assertJsonPath('data.template.area_lebar', 300)
            ->assertJsonPath('data.data_desain.elemen.0.isi', 'Nichibag');
    }

    public function test_mengubah_draft_menyimpan_elemen_baru(): void
    {
        $template = $this->template($this->produkCustom());
        $desain = $this->desain($this->user(), $template);

        $this->actingAs($desain->user)
            ->putJson('/desain/'.$desain->id, [
                'data_desain' => ['elemen' => [
                    $this->elemenTeks(['isi' => 'Nichibag 2']),
                    $this->elemenTeks(['isi' => 'Edisi Rotan', 'y' => 120]),
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.data_desain.elemen.1.isi', 'Edisi Rotan')
            ->assertJsonPath('data.versi', 1)
            ->assertJsonPath('data.status', 'draft');

        // jsonb tidak menyimpan urutan kunci, jadi dibandingkan dengan assertEquals.
        $this->assertEquals(
            ['elemen' => [$this->elemenTeks(['isi' => 'Nichibag 2']), $this->elemenTeks(['isi' => 'Edisi Rotan', 'y' => 120])]],
            $desain->refresh()->data_desain,
        );
    }

    public function test_ubah_draft_membuang_gambar_yang_tidak_dipakai(): void
    {
        $template = $this->template($this->produkCustom());
        $desain = $this->desain($this->user(), $template);

        $lama = 'desain/gambar/'.$desain->id.'/lama.png';
        Storage::disk('public')->put($lama, 'isi lama');
        Storage::disk('public')->put('desain/unggahan/uji/baru.png', 'isi baru');

        $desain->update(['data_desain' => ['elemen' => [
            ['tipe' => 'gambar', 'berkas' => $lama,
                'x' => 60, 'y' => 100, 'lebar' => 80, 'tinggi' => 80],
        ]]]);

        $this->withSession(['desain_unggah_owner' => 'uji'])
            ->actingAs($desain->user)
            ->putJson('/desain/'.$desain->id, [
                'data_desain' => ['elemen' => [
                    ['tipe' => 'gambar', 'berkas' => 'desain/unggahan/uji/baru.png',
                        'x' => 60, 'y' => 100, 'lebar' => 80, 'tinggi' => 80],
                ]],
            ])
            ->assertOk();

        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists('desain/gambar/'.$desain->id.'/baru.png');
    }

    /** FR-CDSN-05 #4: kirim ulang revisi mengembalikan desain dan pesanan ke antrean. */
    public function test_kirim_ulang_revisi_mengembalikan_status_desain_dan_pesanan(): void
    {
        $template = $this->template($this->produkCustom());
        $desain = $this->desain($this->user(), $template, [
            'status' => 'perlu_revisi',
            'catatan_revisi' => 'Logo terlalu kecil',
            'versi' => 2,
        ]);
        $pesanan = $this->pesananMenungguRevisi($desain);

        $this->actingAs($desain->user)
            ->putJson('/desain/'.$desain->id, $this->dataDraft($template, [
                $this->elemenTeks(['isi' => 'Nichibag revisi']),
            ]))
            ->assertOk()
            ->assertJsonPath('data.status', 'menunggu_validasi')
            ->assertJsonPath('data.versi', 3);

        $desain->refresh();
        $pesanan->refresh();

        $this->assertSame('menunggu_validasi', $desain->status);
        $this->assertSame('menunggu_validasi', $pesanan->status);
        $this->assertSame('Nichibag revisi', $desain->data_desain['elemen'][0]['isi']);
    }

    /** FR-CDSN-05 #4: menyimpan draft biasa tidak boleh dikirim ke validasi. */
    public function test_menyimpan_draft_tidak_mengubah_status_validasi(): void
    {
        $template = $this->template($this->produkCustom());
        $desain = $this->desain($this->user(), $template);

        $this->actingAs($desain->user)
            ->putJson('/desain/'.$desain->id, $this->dataDraft($template, [
                $this->elemenTeks(['isi' => 'Draft biasa']),
            ]))
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.versi', 1);

        $this->assertSame('draft', $desain->refresh()->status);
    }

    public function test_desain_milik_user_lain_dijawab_404(): void
    {
        $template = $this->template($this->produkCustom());
        $desain = $this->desain($this->user(), $template);

        $penyusup = $this->user();

        $this->actingAs($penyusup)->getJson('/desain/'.$desain->id)->assertNotFound();
        $this->actingAs($penyusup)
            ->putJson('/desain/'.$desain->id, ['data_desain' => ['elemen' => []]])
            ->assertNotFound();
    }

    public function test_desain_yang_sudah_disetujui_tidak_bisa_diubah(): void
    {
        $desain = $this->desain($this->user(), $this->template($this->produkCustom()), [
            'status' => 'disetujui',
        ]);

        $this->actingAs($desain->user)
            ->putJson('/desain/'.$desain->id, ['data_desain' => ['elemen' => []]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Desain ini tidak dapat diubah lagi');

        $this->assertEquals(
            ['elemen' => [$this->elemenTeks()]],
            $desain->refresh()->data_desain,
        );
    }
}
