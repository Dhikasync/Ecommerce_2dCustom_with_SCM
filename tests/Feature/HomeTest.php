<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeTest extends TestCase
{
    public function test_halaman_utama_dapat_diakses(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertJson(['message' => 'Halaman utama Nichibag']);
    }

    public function test_route_home_mengarah_ke_root(): void
    {
        $this->assertSame(url('/'), route('home'));
    }
}
