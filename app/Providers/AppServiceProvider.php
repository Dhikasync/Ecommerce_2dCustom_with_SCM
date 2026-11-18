<?php

namespace App\Providers;

use App\Services\Pembayaran\Midtrans;
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

        $this->app->bind(Midtrans::class, fn () => new Midtrans(
            (string) config('services.midtrans.server_key'),
            (bool) config('services.midtrans.is_production'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
