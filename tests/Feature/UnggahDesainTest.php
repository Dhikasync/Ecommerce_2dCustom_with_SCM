<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FR-CDSN-03 #1-#5, NFR-FIL-03: gambar unggahan User masuk ke kanvas, dibatasi
 * JPG/PNG maks 5 MB, diberi peringatan (tetap diterima) bila resolusinya di
 * bawah 200 x 200 px, dan bisa dihapus dari daftar unggahan sementara.
 */
class UnggahDesainTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_FORMAT = 'Format file tidak didukung, gunakan JPG/PNG';

    private const PESAN_UKURAN = 'Ukuran file melebihi batas maksimum';

    private const PESAN_RESOLUSI = 'Resolusi gambar rendah, hasil cetak mungkin kurang tajam';

    private const PESAN_KOSONG = 'Pilih gambar yang akan diunggah';

    protected function setUp(): void
    {
        parent::setUp();

        // Unggah memakai multipart; tanpa header JSON, kegagalan jadi redirect 302.
        $this->withHeaders(['Accept' => 'application/json']);
        Storage::fake('public');
    }

    /**
     * GD tidak terpasang, jadi PNG dibentuk langsung dari signature + IHDR
     * (dimensi) + IDAT/IEND dengan CRC sendiri. Ukuran asli dibaca
     * `getimagesize()` dari header, bukan dari nama berkas.
     */
    private function png(int $lebar, int $tinggi, string $nama = 'logo.png', int $tambahan = 0): UploadedFile
    {
        $potong = fn (string $tipe, string $isi): string => pack('N', strlen($isi)).$tipe.$isi.pack('N', crc32($tipe.$isi));

        $isi = "\x89PNG\r\n\x1a\n"
            .$potong('IHDR', pack('NNCCCCC', $lebar, $tinggi, 8, 2, 0, 0, 0))
            .$potong('IDAT', gzcompress(str_repeat("\0", 3)))
            .$potong('IEND', '')
            .str_repeat("\0", $tambahan);

        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('referensi.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n");
    }

    /** @return array<string, mixed> */
    private function unggah(UploadedFile $berkas, ?string $pemilik = null): array
    {
        if ($pemilik !== null) {
            $this->withSession(['desain_unggah_owner' => $pemilik]);
        }

        return $this->post('/desain/unggahan', ['file' => $berkas])
            ->assertCreated()
            ->json('data');
    }

    // ---------- FR-CDSN-03 #1: unggah berhasil ----------

    public function test_guest_boleh_mengunggah_gambar_ke_editor(): void
    {
        $data = $this->unggah($this->png(800, 600));

        $this->assertStringStartsWith('desain/unggahan/', $data['berkas']);
        $this->assertStringEndsWith('.png', $data['berkas']);
        $this->assertSame(800, $data['lebar']);
        $this->assertSame(600, $data['tinggi']);
        $this->assertNull($data['peringatan']);
        $this->assertNotNull($data['url']);

        Storage::disk('public')->assertExists($data['berkas']);
    }

    public function test_gambar_jpg_diterima(): void
    {
        $data = $this->unggah($this->jpeg(100, 100));

        $this->assertStringEndsWith('.jpg', $data['berkas']);
        $this->assertSame(100, $data['lebar']);
        $this->assertSame(self::PESAN_RESOLUSI, $data['peringatan']);
        Storage::disk('public')->assertExists($data['berkas']);
    }

    // ---------- FR-CDSN-03 #2: format file tidak didukung ----------

    public function test_pdf_dan_docx_ditolak_dengan_pesan_format(): void
    {
        $this->post('/desain/unggahan', ['file' => $this->pdf()])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', self::PESAN_FORMAT);

        $this->post('/desain/unggahan', [
            'file' => UploadedFile::fake()->createWithContent('surat.docx', 'PK'."\x03\x04".str_repeat("\0", 20)),
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', self::PESAN_FORMAT);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    // ---------- FR-CDSN-03 #3: ukuran melebihi 5 MB ----------

    public function test_gambar_lebih_dari_lima_mb_ditolak(): void
    {
        $this->post('/desain/unggahan', [
            'file' => $this->png(800, 600, 'besar.png', 5 * 1024 * 1024),
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', self::PESAN_UKURAN);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_gambar_tepat_lima_mb_masih_diterima(): void
    {
        $data = $this->unggah($this->png(800, 600, 'pas.png', 5 * 1024 * 1024 - 1024));

        Storage::disk('public')->assertExists($data['berkas']);
    }

    // ---------- FR-CDSN-03 #4: peringatan resolusi rendah ----------

    public function test_gambar_resolusi_rendah_tetap_diterima_dengan_peringatan(): void
    {
        $data = $this->unggah($this->png(150, 120));

        $this->assertSame(self::PESAN_RESOLUSI, $data['peringatan']);
        $this->assertSame(150, $data['lebar']);
        Storage::disk('public')->assertExists($data['berkas']);
    }

    public function test_gambar_200x200_tidak_diberi_peringatan(): void
    {
        $data = $this->unggah($this->png(200, 200));

        $this->assertNull($data['peringatan']);
    }

    // ---------- FR-CDSN-03 #5: hapus dari daftar unggahan sementara ----------

    public function test_daftar_hanya_berisi_unggahan_pengirim(): void
    {
        $milikA = $this->unggah($this->png(800, 600), 'pemilik-a');

        $this->withSession(['desain_unggah_owner' => 'pemilik-a'])
            ->getJson('/desain/unggahan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.berkas', $milikA['berkas'])
            ->assertJsonPath('data.0.lebar', 800);

        $this->withSession(['desain_unggah_owner' => 'pemilik-b'])
            ->getJson('/desain/unggahan')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_menghapus_gambar_membuang_berkas_dari_daftar_sementara(): void
    {
        $data = $this->unggah($this->png(800, 600));

        $this->deleteJson('/desain/unggahan/'.basename($data['berkas']))
            ->assertOk()
            ->assertJsonPath('message', 'Gambar berhasil dihapus');

        Storage::disk('public')->assertMissing($data['berkas']);
        $this->getJson('/desain/unggahan')->assertJsonCount(0, 'data');
    }

    public function test_menghapus_gambar_milik_pengirim_lain_dijawab_404(): void
    {
        $data = $this->unggah($this->png(800, 600), 'pemilik-a');

        $this->withSession(['desain_unggah_owner' => 'pemilik-b'])
            ->deleteJson('/desain/unggahan/'.basename($data['berkas']))
            ->assertNotFound();

        Storage::disk('public')->assertExists($data['berkas']);
    }

    public function test_unggah_tanpa_berkas_dijawab_422(): void
    {
        $this->post('/desain/unggahan', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', self::PESAN_KOSONG);
    }

    /**
     * JPEG minimal: SOI + APP0 + DQT + SOF0 (dimensi) + DHT + SOS. Cukup untuk
     * `getimagesize()`, tanpa GD.
     */
    private function jpeg(int $lebar, int $tinggi, string $nama = 'foto.jpg'): UploadedFile
    {
        $isi = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            ."\xFF\xDB\x00C\x00".str_repeat("\x08", 64)
            ."\xFF\xC0\x00\x11\x08".pack('nn', $tinggi, $lebar)."\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01"
            ."\xFF\xC4\x00\x1F\x00\x00\x01\x05\x01\x01\x01\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B"
            ."\xFF\xDA\x00\x08\x01\x01\x00\x00?\x00\x00\xFF\xD9";

        return UploadedFile::fake()->createWithContent($nama, $isi);
    }
}
