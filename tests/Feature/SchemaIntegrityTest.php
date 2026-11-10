<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Category;
use App\Models\Design;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Template;
use App\Models\Variant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mengunci aturan yang dijaga database, supaya regresi skema tidak lolos
 * hanya karena belum ada route atau controller yang memakainya.
 */
class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'uji@nichibag.test'): User
    {
        return User::create(['name' => 'Uji', 'email' => $email, 'password' => 'rahasia123']);
    }

    private function kategori(string $nama = 'Uji'): Category
    {
        return Category::create(['nama' => $nama, 'deskripsi' => 'Uji']);
    }

    private function produkJadi(Category $kategori): Product
    {
        return Product::create([
            'category_id' => $kategori->id,
            'nama' => 'Tote Bag',
            'deskripsi' => 'Uji',
            'tipe' => 'jadi',
            'harga_dasar' => 40000,
        ]);
    }

    private function pesanan(User $user, string $nomor): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'nomor_pesanan' => $nomor,
            'status' => 'menunggu_pembayaran',
            'subtotal_barang' => 100000,
            'ongkir' => 10000,
            'total' => 110000,
            'nama_penerima' => 'Uji',
            'no_hp' => '0800000000',
            'alamat_pengiriman' => 'Jl. Uji',
        ]);
    }

    /** Menjalankan aksi yang diharapkan gagal, lalu mengembalikan nama constraint. */
    private function namaConstraintYangDilanggar(callable $aksi): ?string
    {
        $sebelum = DB::transactionLevel();

        try {
            DB::transaction($aksi);
        } catch (QueryException $e) {
            // Pastikan savepoint sudah dilepas supaya transaksi test tetap sehat.
            while (DB::transactionLevel() > $sebelum) {
                DB::rollBack();
            }

            foreach (['payments_lunas_unique', 'products_custom_aktif_check', 'product_images_urutan_check'] as $nama) {
                if (str_contains($e->getMessage(), $nama)) {
                    return $nama;
                }
            }

            return 'lain: '.$e->getMessage();
        }

        return null;
    }

    public function test_pesanan_hanya_boleh_punya_satu_pembayaran_lunas(): void
    {
        $pesanan = $this->pesanan($this->user(), 'INV-1');

        Payment::create(['order_id' => $pesanan->id, 'jumlah' => 110000, 'status' => 'lunas']);

        $this->assertSame(
            'payments_lunas_unique',
            $this->namaConstraintYangDilanggar(
                fn () => Payment::create(['order_id' => $pesanan->id, 'jumlah' => 110000, 'status' => 'lunas'])
            )
        );
    }

    public function test_percobaan_pembayaran_gagal_boleh_berulang(): void
    {
        $pesanan = $this->pesanan($this->user(), 'INV-2');

        Payment::create(['order_id' => $pesanan->id, 'jumlah' => 110000, 'status' => 'gagal']);
        Payment::create(['order_id' => $pesanan->id, 'jumlah' => 110000, 'status' => 'gagal']);
        Payment::create(['order_id' => $pesanan->id, 'jumlah' => 110000, 'status' => 'lunas']);

        $this->assertSame(3, $pesanan->payments()->count());
    }

    public function test_produk_custom_aktif_wajib_punya_sla(): void
    {
        $kategori = $this->kategori();

        $this->assertSame('products_custom_aktif_check', $this->namaConstraintYangDilanggar(
            fn () => Product::create([
                'category_id' => $kategori->id,
                'nama' => 'Custom Tanpa SLA',
                'deskripsi' => 'Uji',
                'tipe' => 'custom',
                'harga_dasar' => 40000,
                'is_aktif' => true,
            ])
        ));

        // Nonaktif dulu boleh, untuk dilengkapi belakangan.
        $produk = Product::create([
            'category_id' => $kategori->id,
            'nama' => 'Custom Nonaktif',
            'deskripsi' => 'Uji',
            'tipe' => 'custom',
            'harga_dasar' => 40000,
            'is_aktif' => false,
        ]);

        $this->assertSame('products_custom_aktif_check', $this->namaConstraintYangDilanggar(
            fn () => $produk->update(['is_aktif' => true])
        ));
    }

    public function test_hapus_produk_tetap_menyimpan_riwayat_po_dan_desain(): void
    {
        $user = $this->user();
        $kategori = $this->kategori();

        $produk = Product::create([
            'category_id' => $kategori->id,
            'nama' => 'Tote Bag',
            'deskripsi' => 'Uji',
            'tipe' => 'custom',
            'harga_dasar' => 40000,
            'is_aktif' => true,
            'sla_hari' => 3,
        ]);

        $varian = Variant::create([
            'product_id' => $produk->id, 'sku' => 'TOTE-30-HITAM', 'ukuran' => '30x40',
            'warna' => 'Hitam', 'ketebalan' => '10 oz', 'harga' => 50000, 'berat' => 250, 'moq' => 1,
        ]);

        $template = Template::create([
            'product_id' => $produk->id, 'nama' => 'Kanvas 30x40', 'file' => 'tpl/30x40.png',
            'lebar_kanvas' => 1200, 'tinggi_kanvas' => 1600,
        ]);

        $desain = Design::create([
            'user_id' => $user->id, 'template_id' => $template->id, 'variant_id' => $varian->id,
            'data_desain' => ['layer' => ['teks' => 'Nichibag']], 'status' => 'draft',
        ]);

        $distributor = Distributor::create(['nama_toko' => 'Toko Bahan', 'no_hp' => '0812']);
        $po = PurchaseOrder::create([
            'distributor_id' => $distributor->id, 'nomor_po' => 'PO-1',
            'status' => 'dibuat', 'total_biaya' => 500000,
        ]);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id, 'variant_id' => $varian->id,
            'nama_produk' => $produk->nama, 'nama_varian' => '30x40 / Hitam', 'sku' => $varian->sku,
            'quantity' => 20, 'harga_beli' => 25000,
        ]);

        // FR-PROD-01 #5: tidak ada pesanan aktif, produk harus bisa dihapus.
        $produk->delete();

        $this->assertDatabaseMissing('products', ['id' => $produk->id]);
        $this->assertDatabaseMissing('variants', ['id' => $varian->id]);
        $this->assertDatabaseMissing('templates', ['id' => $template->id]);

        // Desain tetap ada, hanya tautan template-nya lepas.
        $desain->refresh();
        $this->assertNull($desain->template_id);
        $this->assertSame(['layer' => ['teks' => 'Nichibag']], $desain->data_desain);

        // Item PO tetap menyebut barang apa yang dipesan.
        $item->refresh();
        $this->assertNull($item->variant_id);
        $this->assertSame('Tote Bag', $item->nama_produk);
        $this->assertSame('TOTE-30-HITAM', $item->sku);
        $this->assertSame(20, $item->quantity);
    }

    public function test_galeri_produk_berurutan_dan_ikut_terhapus(): void
    {
        $produk = $this->produkJadi($this->kategori());

        foreach ([['b.jpg', 2], ['c.jpg', 0], ['a.jpg', 1]] as [$file, $pos]) {
            ProductImage::create(['product_id' => $produk->id, 'gambar' => $file, 'urutan' => $pos]);
        }

        $this->assertSame(['c.jpg', 'a.jpg', 'b.jpg'], $produk->images()->pluck('gambar')->all());
        $this->assertSame(
            'product_images_urutan_check',
            $this->namaConstraintYangDilanggar(
                fn () => ProductImage::create(['product_id' => $produk->id, 'gambar' => 'x.jpg', 'urutan' => -1])
            )
        );

        $produk->delete();
        $this->assertSame(0, ProductImage::count());
    }

    public function test_user_wajib_verifikasi_email(): void
    {
        $user = $this->user('verifikasi@nichibag.test');

        $this->assertInstanceOf(MustVerifyEmail::class, $user);
        $this->assertFalse($user->hasVerifiedEmail());

        $user->markEmailAsVerified();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
