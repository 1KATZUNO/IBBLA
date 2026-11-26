<?php

namespace App\Providers;

use App\Listeners\RegistrarAccesoSubscriber;
use App\Models\Culto;
use App\Observers\CierreCultoObserver;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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

        // Al cerrar un culto, compromisos de su mes recalculados con el motor (QA-025)
        Culto::observe(CierreCultoObserver::class);

        // Tarjetas QR (/t/{token}): el limite corto cuenta por tarjeta. El de la IP es holgado a proposito: el dia
        // que se reparten las tarjetas escanea toda la iglesia desde el mismo Wi-Fi (una sola IP para todos) y
        // con un limite corto por IP los primeros dejarian afuera al resto. Adivinar un token de 40 caracteres
        // no es viable, asi que el tope por IP solo esta para frenar un abuso burdo.
        RateLimiter::for('tarjeta-qr', fn (Request $request) => $this->limitesTarjetaQr($request, 'ver', 20, 300));
        RateLimiter::for('tarjeta-qr-guardar', fn (Request $request) => $this->limitesTarjetaQr($request, 'guardar', 10, 150));
    }

    /**
     * Limites por minuto de las rutas publicas de la tarjeta QR: uno por tarjeta y otro por IP. Quien se pasa ve
     * una pagina en espanol que le pide esperar un minuto (no el «429 Too Many Requests» del framework).
     *
     * @return list<Limit>
     */
    private function limitesTarjetaQr(Request $request, string $accion, int $porTarjeta, int $porIp): array
    {
        $espera = fn (Request $request, array $headers) => response()->view('acceso.espera', [], 429, $headers);

        return [
            Limit::perMinute($porTarjeta)->by("{$accion}:tarjeta:".sha1((string) $request->route('token')))->response($espera),
            Limit::perMinute($porIp)->by("{$accion}:ip:".$request->ip())->response($espera),
        ];
    }
}
