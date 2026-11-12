<?php

namespace App\Providers;

use App\Services\Profil\RajaOngkir;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Kunci hanya dari config, tidak pernah dari input request.
        $this->app->bind(RajaOngkir::class, fn () => new RajaOngkir(
            (string) config('services.rajaongkir.key'),
            (string) config('services.rajaongkir.base_url'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
