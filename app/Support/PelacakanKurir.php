<?php

namespace App\Support;

/**
 * FR-ORDR-04 #3: kurir yang tidak mendukung AWB tidak dipanggil API-nya.
 * URL di bawah sudah diverifikasi sebagai homepage resmi; sentral tidak dapat
 * dijangkau saat verifikasi, jadi sengaja null.
 */
class PelacakanKurir
{
    public const PESAN_TIDAK_TERSEDIA = 'tracking tidak tersedia untuk kurir ini';

    private const TIDAK_DIDUKUNG = [
        'sicepat' => 'https://www.sicepat.com/',
        'ide' => 'https://www.idexpress.com/',
        'rex' => 'https://www.rex.co.id/id',
        'sentral' => null,
    ];

    private const DIDUKUNG = ['jne', 'sap', 'ninja', 'jnt', 'tiki', 'wahana', 'pos', 'lion', 'spx'];

    public static function didukung(string $kode): bool
    {
        return in_array($kode, self::DIDUKUNG, true);
    }

    public static function tautanUntukTidakDidukung(string $kode): ?string
    {
        return array_key_exists($kode, self::TIDAK_DIDUKUNG)
            ? self::TIDAK_DIDUKUNG[$kode]
            : null;
    }

    public static function kodeTidakDikenal(string $kode): bool
    {
        return ! self::didukung($kode) && ! array_key_exists($kode, self::TIDAK_DIDUKUNG);
    }

    /** FR-ORDR-04 #3: lima digit terakhir sebagai string, bukan integer. */
    public static function limaDigitTerakhir(string $noHp): ?string
    {
        $digit = preg_replace('/\D+/', '', $noHp) ?? '';

        return strlen($digit) < 5 ? null : substr($digit, -5);
    }
}
