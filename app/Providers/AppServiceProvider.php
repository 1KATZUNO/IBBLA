<?php

namespace App\Providers;

use App\Listeners\RegistrarAccesoSubscriber;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configurar longitud máxima de strings para MySQL
        Schema::defaultStringLength(191);

        // Configurar Carbon en español
        Carbon::setLocale('es');
        setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'esp');

        // Configurar timezone de Costa Rica
        date_default_timezone_set('America/Costa_Rica');

        // Historial de ingresos: Login / Logout / Failed -> audit_logs
        Event::subscribe(RegistrarAccesoSubscriber::class);
    }
}
