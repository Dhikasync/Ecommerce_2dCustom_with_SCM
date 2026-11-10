<?php

namespace App\Providers;

use App\Services\Profil\RajaOngkir;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SI-02: client dibuat per pemakaian dari kunci di .env, bukan dari
        // argumen request, supaya kunci tidak pernah berpindah lewat input.
        $this->app->bind(RajaOngkir::class, fn () => new RajaOngkir(
            (string) config('services.rajaongkir.key'),
            (string) config('services.rajaongkir.base_url'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
