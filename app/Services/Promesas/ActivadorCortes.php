<?php

namespace App\Services\Promesas;

use App\Models\AuditLog;
use App\Models\CortePromesas;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reportes\SincronizadorCompromisos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pone en vigor un corte de promesas ("promesas futuras", docs/plan-oct-2026/06-PROMESAS-FUTURAS.md 3.1):
 * borron y cuenta nueva. Las promesas que regian pasan a `promesas_historial` con su vigencia (para que
 * los reportes de meses anteriores den exactamente lo mismo) y `promesas` queda con las del corte.
 *
 * - Transaccional e idempotente: el corte se bloquea (FOR UPDATE) y si ya esta activado no se hace nada.
 * - Un corte sin promesas programadas no se activa solo (activarPendientes lo salta).
 * - Funciona sin iglesia enlazada (consola, cron): todo por tenant_id explicito y sin scopes globales.
 *
 * Lo llaman el comando `promesas:activar-cortes` (planificador, cada diez minutos) y el middleware
 * ActivarCortesPromesas (disparo perezoso en las peticiones web autenticadas).
 */
final class ActivadorCortes
{
    public function __construct(private readonly SincronizadorCompromisos $sincronizador) {}

    /** @return bool si lo activo ahora (false si ya estaba activado) */
    public function activar(CortePromesas $corte, ?User $por = null): bool
    {
        $resumen = DB::transaction(function () use ($corte, $por) {
            /** @var CortePromesas|null $bloqueado */
            $bloqueado = CortePromesas::withoutGlobalScopes()->whereKey($corte->id)->lockForUpdate()->first();
            if ($bloqueado === null || ! $bloqueado->estaProgramado()) {
                return null;
            }

            $tenantId = (int) $bloqueado->tenant_id;
            $inicio = CarbonImmutable::parse($bloqueado->inicia_el)->startOfDay();

            // Los tramos se encadenan hacia adelante: un corte anterior a uno ya activado no se activa.
            $posterior = CortePromesas::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNotNull('activado_at')
                ->where('inicia_el', '>=', $inicio->toDateString())->exists();
            if ($posterior) {
                Log::warning('Corte de promesas '.$bloqueado->id.' no activado: ya hay un corte activado con la misma fecha o posterior.');

                return null;
            }
            $anterior = CortePromesas::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('activado_at')
                ->where('inicia_el', '<', $inicio->toDateString())
                ->orderByDesc('inicia_el')
                ->first();

            $idsIglesia = DB::table('personas')->where('tenant_id', $tenantId)->pluck('id')->all();
            $ahora = now();

            // 1. Las promesas que regian pasan al historial, con su tramo de vigencia.
            $archivadas = 0;
            foreach (array_chunk($idsIglesia, 1000) as $ids) {
                $filas = DB::table('promesas')->whereIn('persona_id', $ids)->orderBy('id')
                    ->get(['persona_id', 'categoria', 'monto', 'moneda', 'frecuencia']);
                $historial = [];
                foreach ($filas as $fila) {
                    $historial[] = [
                        'tenant_id' => $tenantId,
                        'corte_id' => $anterior?->id,
                        'persona_id' => $fila->persona_id,
                        'categoria' => $fila->categoria,
                        'monto' => $fila->monto,
                        'moneda' => $fila->moneda ?: 'CRC',
                        'frecuencia' => $fila->frecuencia,
                        'vigente_desde' => $anterior ? CarbonImmutable::parse($anterior->inicia_el)->toDateString() : null,
                        'vigente_hasta' => $inicio->subDay()->toDateString(),
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
                foreach (array_chunk($historial, 500) as $lote) {
                    DB::table('promesas_historial')->insert($lote);
                }
                $archivadas += count($historial);

                // 2. Borron: se borran las que regian...
                DB::table('promesas')->whereIn('persona_id', $ids)->delete();
            }

            // ... y cuenta nueva: las del corte (las programadas se conservan como registro).
            $nuevas = [];
            foreach (DB::table('promesas_programadas')->where('corte_id', $bloqueado->id)->orderBy('id')
                ->get(['persona_id', 'categoria', 'monto', 'moneda', 'frecuencia']) as $fila) {
                $nuevas[] = [
                    'persona_id' => $fila->persona_id,
                    'categoria' => $fila->categoria,
                    'monto' => $fila->monto,
                    'moneda' => $fila->moneda ?: 'CRC',
                    'frecuencia' => $fila->frecuencia,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
            foreach (array_chunk($nuevas, 500) as $lote) {
                DB::table('promesas')->insert($lote);
            }

            // 3. Las categorias creadas para este corte entran en vigor con el.
            $categorias = array_values(array_filter(array_map('intval', (array) ($bloqueado->categorias_nuevas ?? []))));
            if ($categorias !== []) {
                DB::table('tenant_categories')->where('tenant_id', $tenantId)->whereIn('id', $categorias)
                    ->update(['activa' => true, 'updated_at' => $ahora]);
            }

            // 4. Queda activado.
            $bloqueado->forceFill(['activado_at' => $ahora, 'activado_por' => $por?->id])->save();

            return [
                'tenant_id' => $tenantId,
                'inicio' => $inicio,
                'detalles' => [
                    'corte_id' => $bloqueado->id,
                    'nombre' => $bloqueado->nombre,
                    'inicia_el' => $inicio->toDateString(),
                    'promesas_archivadas' => $archivadas,
                    'promesas_nuevas' => count($nuevas),
                    'categorias_activadas' => $categorias,
                    'corte_anterior_id' => $anterior?->id,
                    'activado_por' => $por ? $por->name : 'sistema (fecha de inicio cumplida)',
                ],
            ];
        });

        if ($resumen === null) {
            return false;
        }

        $corte->refresh();
        $this->despuesDeActivar($resumen['tenant_id'], $resumen['inicio'], $resumen['detalles']);

        return true;
    }

    /**
     * Activa, del mas antiguo al mas nuevo, los cortes programados cuya fecha ya llego y que tienen al
     * menos una promesa programada (un corte vacio nunca se activa solo: dejaria a la iglesia sin promesas).
     *
     * @return int cuantos activo
     */
    public function activarPendientes(?int $tenantId = null): int
    {
        $cortes = $this->pendientes($tenantId);

        $activados = 0;
        foreach ($cortes as $corte) {
            if ($this->activar($corte)) {
                $activados++;
            }
        }

        return $activados;
    }

    /**
     * Cortes programados con la fecha cumplida y con promesas, del mas antiguo al mas nuevo. Una sola
     * consulta (la usa tambien el disparo perezoso para saber si hay algo que hacer).
     *
     * @return \Illuminate\Support\Collection<int, CortePromesas>
     */
    public function pendientes(?int $tenantId = null)
    {
        return CortePromesas::withoutGlobalScopes()
            ->whereNull('activado_at')
            ->where('inicia_el', '<=', CarbonImmutable::today()->toDateString())
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('promesas_programadas')->whereColumn('promesas_programadas.corte_id', 'cortes_promesas.id'))
            ->orderBy('inicia_el')
            ->orderBy('id')
            ->get();
    }

    /**
     * Tras el commit: la tabla `compromisos` desde el mes de inicio hasta el mes actual (la escribe solo
     * el SincronizadorCompromisos) y la auditoria. Si la sincronizacion falla no se deshace la
     * activacion: queda en el log y se arregla con `compromisos:sincronizar` o el boton Recalcular.
     *
     * @param  array<string, mixed>  $detalles
     */
    private function despuesDeActivar(int $tenantId, CarbonImmutable $inicio, array $detalles): void
    {
        $tenant = Tenant::find($tenantId);
        $hoy = CarbonImmutable::today();
        if ($tenant !== null) {
            try {
                for ($mes = $inicio->startOfMonth(); $mes->lte($hoy); $mes = $mes->addMonth()) {
                    $this->sincronizador->sincronizar($tenant, $mes->year, $mes->month);
                }
            } catch (Throwable $e) {
                Log::warning('Corte de promesas '.$detalles['corte_id'].' activado, pero no se pudieron sincronizar los compromisos: '.$e->getMessage());
            }
        }

        try {
            $entrada = AuditLog::registrar('Activar corte de promesas', $detalles, 'promesas.activar-corte');
            if ((int) $entrada->tenant_id !== $tenantId) {
                // Sin sesion (cron) o un super admin: la entrada va a la iglesia del corte.
                $entrada->forceFill(['tenant_id' => $tenantId])->save();
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo auditar la activacion del corte de promesas '.$detalles['corte_id'].': '.$e->getMessage());
        }
    }
}
