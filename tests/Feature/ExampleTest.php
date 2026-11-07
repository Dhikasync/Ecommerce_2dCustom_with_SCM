<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Satu-satunya route yang ada saat ini masih route sementara untuk
     * memeriksa koneksi data produk. Test ini menjaga route tersebut tetap
     * bisa diakses sampai route sebenarnya dibangun.
     */
    public function test_katalog_produk_dapat_diakses(): void
    {
        $response = $this->get('/products');

        $response->assertStatus(200);
    }
}
