<?php

namespace App\Http\Controllers\Keranjang;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Design;
use App\Models\Produk\Variant;
use App\Support\GambarPublik;
use App\Support\MasalahItemKeranjang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * FR-CART-01, UC-24: tambah, ubah jumlah, hapus item. Satu keranjang per User
 * berisi dua bagian terpisah; stok tidak dikunci sebelum checkout.
 */
class KeranjangController extends Controller
{
    private const PESAN_KOSONG = 'Keranjang Anda kosong';

    private const PESAN_ITEM_TIDAK_DITEMUKAN = 'Item keranjang tidak ditemukan';

    private const PESAN_CUSTOM_TANPA_DESAIN = 'Produk custom harus ditambahkan dari preview desain';

    private const PESAN_DESAIN_TIDAK_DITEMUKAN = 'Desain tidak ditemukan';

    /** srs.md:471: "hanya dari Preview inilah User dapat menambahkan desain ke keranjang". */
    private const PESAN_CUSTOM_TANPA_PREVIEW = 'Buka preview desain terlebih dahulu sebelum menambahkan ke keranjang';

    /** FR-CART-01 #1/#5, srs.md:513-514: produk jadi dan custom tidak dicampur. */
    public function tampil(Request $request): JsonResponse
    {
        $item = CartItem::query()
            ->whereHas('cart', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('variant.product')
            ->orderBy('id')
            ->get();

        $data = [
            'jadi' => $this->bagian($item->whereNull('design_id')->values()),
            'custom' => $this->bagian($item->whereNotNull('design_id')->values()),
        ];

        if ($item->isEmpty()) {
            return response()->json(['message' => self::PESAN_KOSONG, 'data' => $data]);
        }

        return response()->json(['data' => $data]);
    }

    /** FR-CART-01 #1/#2, srs.md:513-514: periksa MOQ dan Stok Tersedia saat masuk. */
    public function tambah(Request $request): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'design_id' => ['nullable', 'integer'],
        ]);

        $varian = Variant::with('product')->find($data['variant_id']);

        if ($varian === null || ! $varian->product->is_aktif) {
            return $this->tolak(MasalahItemKeranjang::PRODUK_TIDAK_TERSEDIA);
        }

        $desain = null;

        if ($varian->product->isCustom()) {
            // FR-CART-01 #2: item custom wajib menunjuk desain milik User sendiri.
            if (($data['design_id'] ?? null) === null) {
                return $this->tolak(self::PESAN_CUSTOM_TANPA_DESAIN);
            }

            $desain = Design::query()
                ->whereKey($data['design_id'])
                ->where('user_id', $request->user()->id)
                ->where('variant_id', $varian->id)
                ->first();

            if ($desain === null) {
                return response()->json(['message' => self::PESAN_DESAIN_TIDAK_DITEMUKAN], 404);
            }

            // FR-CDSN-04 + srs.md:471: desain yang belum pernah dirender preview
            // belum boleh masuk keranjang; preview yang tersimpan jadi buktinya.
            if ($desain->preview_gambar === null) {
                return $this->tolak(self::PESAN_CUSTOM_TANPA_PREVIEW);
            }
        }

        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        // Partial unique index: satu baris per varian jadi, atau per
        // (varian, desain) untuk custom, jadi baris lama wajib digabung.
        $item = $cart->cartItems()
            ->where('variant_id', $varian->id)
            ->when(
                $desain,
                fn ($query) => $query->where('design_id', $desain->id),
                fn ($query) => $query->whereNull('design_id')
            )
            ->first();

        $jumlah = $item === null ? $data['quantity'] : $item->quantity + $data['quantity'];

        $tolak = $this->periksaJumlah($varian, $jumlah);

        if ($tolak !== null) {
            return $tolak;
        }

        if ($item === null) {
            $item = $cart->cartItems()->create([
                'variant_id' => $varian->id,
                'design_id' => $desain?->id,
                'quantity' => $jumlah,
                // FR-CART-01 #6: harga dikunci saat item masuk, bukan saat checkout.
                'harga_satuan' => $varian->harga,
            ]);
        } else {
            // Baris lama mempertahankan harganya walau harga katalog berubah.
            $item->update(['quantity' => $jumlah]);
        }

        $item->setRelation('variant', $varian);

        return response()->json(['data' => $this->ringkas($item)], 201);
    }

    /** FR-CART-01 #3, srs.md:515: MOQ dan Stok Tersedia ditegakkan saat diubah. */
    public function ubah(Request $request, int $id): JsonResponse
    {
        $item = $this->itemMilik($request->user()->id, $id);

        if ($item === null) {
            return $this->tidakDitemukan();
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $tolak = $this->periksaJumlah($item->variant, $data['quantity']);

        if ($tolak !== null) {
            return $tolak;
        }

        // Harga tidak dihitung ulang: yang diubah hanya jumlah (FR-CART-01 #6).
        $item->update(['quantity' => $data['quantity']]);

        return response()->json(['data' => $this->ringkas($item)]);
    }

    /** FR-CART-01 #4, srs.md:516. */
    public function hapus(Request $request, int $id): JsonResponse
    {
        $item = $this->itemMilik($request->user()->id, $id);

        if ($item === null) {
            return $this->tidakDitemukan();
        }

        $item->delete();

        return response()->json(['message' => 'Item keranjang berhasil dihapus']);
    }

    /** MOQ diukur dari isi baris setelah digabung, bukan dari jumlah yang dikirim. */
    private function periksaJumlah(Variant $varian, int $jumlah): ?JsonResponse
    {
        if ($jumlah < $varian->moq) {
            return $this->tolak(MasalahItemKeranjang::moq($varian->moq));
        }

        if ($jumlah > $varian->stok_tersedia) {
            return $this->tolak(MasalahItemKeranjang::STOK);
        }

        return null;
    }

    /** NFR-SEC-03: item User lain dijawab 404, bukan 403. */
    private function itemMilik(int $userId, int $id): ?CartItem
    {
        return CartItem::query()
            ->whereKey($id)
            ->whereHas('cart', fn ($query) => $query->where('user_id', $userId))
            ->with('variant.product')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkas(CartItem $item): array
    {
        $varian = $item->variant;

        return [
            'id' => $item->id,
            'variant_id' => $item->variant_id,
            'design_id' => $item->design_id,
            'nama' => $varian->product->nama,
            'gambar_url' => GambarPublik::url($varian->product->gambar),
            'sku' => $varian->sku,
            'ukuran' => $varian->ukuran,
            'warna' => $varian->warna,
            'ketebalan' => $varian->ketebalan,
            'quantity' => $item->quantity,
            'harga_satuan' => $item->harga_satuan,
            'subtotal' => $item->quantity * $item->harga_satuan,
            // FR-CART-02 #1: item custom ditandai "Custom" di tampilan.
            'is_custom' => $item->design_id !== null,
            'label' => MasalahItemKeranjang::label($item),
        ];
    }

    /**
     * Satu bagian keranjang beserta total dan kesiapannya untuk checkout.
     *
     * @param  Collection<int, CartItem>  $item
     * @return array<string, mixed>
     */
    private function bagian(Collection $item): array
    {
        $baris = $item->map(fn (CartItem $satu) => $this->ringkas($satu))->values()->all();

        return [
            'items' => $baris,
            'total' => array_sum(array_column($baris, 'subtotal')),
            // Skenario #2: satu item bermasalah menahan checkout seluruh bagian.
            'bisa_checkout' => $baris !== []
                && collect($baris)->every(fn (array $satu) => $satu['label'] === null),
        ];
    }

    private function tolak(string $pesan): JsonResponse
    {
        return response()->json(['message' => $pesan], 422);
    }

    private function tidakDitemukan(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_ITEM_TIDAK_DITEMUKAN], 404);
    }
}
