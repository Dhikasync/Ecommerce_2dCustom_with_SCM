<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;

/**
 * FR-CKOT-04 #2, srs.md:520: daftar layanan yang didukung disiapkan saat setup
 * dan lahir Nonaktif; Admin Ecommerce memilih mana yang ditawarkan toko.
 */
class CourierSeeder extends Seeder
{
    private const LAYANAN = [
        ['kode' => 'jne', 'nama' => 'JNE', 'layanan' => 'reguler'],
        ['kode' => 'jne', 'nama' => 'JNE', 'layanan' => 'yes'],
        ['kode' => 'pos', 'nama' => 'POS Indonesia', 'layanan' => 'reguler'],
        ['kode' => 'tiki', 'nama' => 'TIKI', 'layanan' => 'reguler'],
        ['kode' => 'tiki', 'nama' => 'TIKI', 'layanan' => 'ons'],
    ];

    public function run(): void
    {
        foreach (self::LAYANAN as $baris) {
            // firstOrCreate: seeder aman dijalankan ulang tanpa menghidupkan
            // kembali layanan yang sudah dimatikan Admin.
            Courier::firstOrCreate(
                ['kode' => $baris['kode'], 'layanan' => $baris['layanan']],
                ['nama' => $baris['nama'], 'is_aktif' => false],
            );
        }
    }
}
