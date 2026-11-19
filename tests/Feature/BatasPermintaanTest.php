<?php

namespace Tests\Feature;

use App\Support\BatasPermintaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NFR-LOC-01: antarmuka berbahasa Indonesia, termasuk saat batas permintaan
 * terlampaui. Sebelum handler ini ada, middleware `throttle` membalas
 * "Too Many Attempts." milik Laravel.
 */
class BatasPermintaanTest extends TestCase
{
    use RefreshDatabase;

    public function test_batas_permintaan_dijawab_429_berbahasa_indonesia(): void
    {
        // `routes/auth.php:37-39`: forgot-password dibatasi 6 permintaan/menit.
        for ($i = 1; $i <= 6; $i++) {
            $this->postJson('/forgot-password', ['email' => 'tidak-ada@example.test'])
                ->assertOk();
        }

        $this->postJson('/forgot-password', ['email' => 'tidak-ada@example.test'])
            ->assertStatus(429)
            ->assertJson(['message' => BatasPermintaan::PESAN])
            ->assertJsonStructure(['message', 'retry_after']);
    }

    public function test_header_retry_after_tetap_dikirim(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->postJson('/forgot-password', ['email' => 'tidak-ada@example.test']);
        }

        $respons = $this->postJson('/forgot-password', ['email' => 'tidak-ada@example.test'])
            ->assertStatus(429);

        // Angka ini yang dipakai UI untuk menghitung mundur; tanpa header,
        // pengguna hanya tahu "coba lagi" tanpa tahu kapan.
        $this->assertGreaterThan(0, (int) $respons->headers->get('Retry-After'));
        $this->assertGreaterThan(0, $respons->json('retry_after'));
    }

    public function test_pesan_laravel_berbahasa_inggris_tidak_bocor(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->postJson('/forgot-password', ['email' => 'tidak-ada@example.test']);
        }

        $pesan = $this->postJson('/forgot-password', ['email' => 'tidak-ada@example.test'])
            ->assertStatus(429)
            ->json('message');

        $this->assertStringNotContainsString('Too Many Attempts', $pesan);
    }
}
