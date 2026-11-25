<?php

namespace App\Http\Middleware;

use App\Services\Promesas\ActivadorCortes;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Disparo perezoso de los cortes de promesas ("promesas futuras"): para no depender solo del cron, la
 * primera peticion web autenticada de una iglesia en cada minuto revisa si hay un corte programado con
 * la fecha cumplida y lo activa. Barato: Cache::add hace de candado (como mucho una revision por
 * minuto y por iglesia) y la revision es una sola consulta liviana cuando no hay nada que activar.
 * Nunca interrumpe la peticion: si algo falla queda en el log y lo reintenta el comando.
 */
class ActivarCortesPromesas
{
    public const SEGUNDOS_CANDADO = 60;

    public function __construct(private readonly ActivadorCortes $activador) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->user()?->tenant_id;
        if ($tenantId) {
            $this->revisar((int) $tenantId);
        }

        return $next($request);
    }

    private function revisar(int $tenantId): void
    {
        try {
            if (! Cache::add(self::claveCandado($tenantId), now()->toIso8601String(), self::SEGUNDOS_CANDADO)) {
                return;
            }
            foreach ($this->activador->pendientes($tenantId) as $corte) {
                $this->activador->activar($corte);
            }
        } catch (Throwable $e) {
            Log::warning('Disparo perezoso de cortes de promesas (iglesia '.$tenantId.'): '.$e->getMessage());
        }
    }

    public static function claveCandado(int $tenantId): string
    {
        return 'promesas:activar-cortes:'.$tenantId;
    }
}
