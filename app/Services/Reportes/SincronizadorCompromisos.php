<?php

namespace App\Services\Reportes;

use App\Models\Tenant;
use App\Support\Moneda\ConversorUsd;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Guarda en la tabla `compromisos` (una fila por persona, categoria prometida y mes) lo que calcula
 * el motor unico. Es el UNICO lugar que escribe esa tabla (regla 2.4 de 04-DECISIONES.md, QA-025):
 * las pantallas solo leen. La tabla la leen el Dashboard de promesas, el Excel de promesas y
 * Servidores; los reportes por persona calculan al vuelo con el motor.
 *
 * - Acotado: solo los meses del anio que tienen al menos un culto de la iglesia hasta hoy.
 * - Idempotente: compara con lo guardado y solo inserta, actualiza o borra lo que cambio; una
 *   segunda corrida no escribe nada.
 * - Personas: las activas de la iglesia con promesa. Las filas de esos meses que ya no corresponden
 *   (promesa borrada, persona inactiva) se borran.
 * - Borra las filas de meses posteriores al mes en curso (las creaba la pantalla de Compromisos al
 *   "mirar" un mes futuro, QA-025).
 *
 * Columnas: monto_prometido (moneda de la promesa, por la frecuencia), monto_dado (como se anoto en
 * los sobres), *_crc en colones con las reglas del motor (lo prometido del mes aunque este en curso,
 * solo informativo), saldo_actual = dado_crc - prometido_crc del mes (cada mes independiente).
 *
 * Lo llaman: el comando `compromisos:sincronizar`, el cierre de un culto (su mes) y el boton
 * "Recalcular compromisos" del admin.
 */
final class SincronizadorCompromisos
{
    private const COLUMNAS = ['monto_prometido', 'monto_dado', 'saldo_anterior', 'saldo_actual', 'moneda_promesa', 'monto_prometido_crc', 'monto_dado_crc', 'tipo_cambio_usado'];

    public function __construct(
        private readonly MotorReportes $motor,
        private readonly ConversorUsd $conversor,
    ) {}

    /**
     * @param  int|null  $mes  solo ese mes (si tiene cultos); null = todos los meses del anio con cultos
     * @return array{meses: list<int>, creados: int, actualizados: int, borrados: int, sin_cambios: int}
     */
    public function sincronizar(Tenant $tenant, int $anio, ?int $mes = null): array
    {
        $anterior = app()->bound('current_tenant') ? app('current_tenant') : null;
        app()->instance('current_tenant', $tenant);

        try {
            return DB::transaction(fn () => $this->ejecutar($tenant, $anio, $mes));
        } finally {
            if ($anterior !== null) {
                app()->instance('current_tenant', $anterior);
            } else {
                app()->forgetInstance('current_tenant');
            }
        }
    }

    private function ejecutar(Tenant $tenant, int $anio, ?int $mes): array
    {
        $hoy = CarbonImmutable::today();
        $resumen = ['meses' => [], 'creados' => 0, 'actualizados' => 0, 'borrados' => 0, 'sin_cambios' => 0];

        $idsIglesia = DB::table('personas')->where('tenant_id', $tenant->id)->pluck('id')->all();
        if ($idsIglesia === []) {
            return $resumen;
        }

        $resumen['borrados'] += $this->borrarFuturos($idsIglesia, $hoy);

        $meses = $this->mesesConCultos($tenant->id, $anio, $mes, $hoy);
        $resumen['meses'] = $meses;
        if ($meses === []) {
            return $resumen;
        }

        // Lo que deberia haber, calculado por el motor.
        $personas = DB::table('personas')
            ->where('tenant_id', $tenant->id)
            ->where('activo', true)
            ->whereIn('id', DB::table('promesas')->select('persona_id'))
            ->orderBy('id')
            ->get(['id', 'nombre', 'activo']);
        $deseadas = $personas->isEmpty() ? [] : $this->filasDeseadas($personas, $anio, $meses);

        // Lo que hay guardado en esos meses.
        $existentes = [];
        foreach (array_chunk($idsIglesia, 1000) as $ids) {
            $filas = DB::table('compromisos')->whereIn('persona_id', $ids)->where('año', $anio)->whereIn('mes', $meses)->get();
            foreach ($filas as $fila) {
                $existentes[$fila->persona_id.'|'.$fila->categoria.'|'.$fila->mes] = $fila;
            }
        }

        $ahora = now();
        $nuevas = [];
        foreach ($deseadas as $clave => $fila) {
            $guardada = $existentes[$clave] ?? null;
            unset($existentes[$clave]);

            if ($guardada === null) {
                $nuevas[] = $fila + ['created_at' => $ahora, 'updated_at' => $ahora];

                continue;
            }

            $cambios = [];
            foreach (self::COLUMNAS as $columna) {
                if (self::normalizar($guardada->{$columna}) !== self::normalizar($fila[$columna])) {
                    $cambios[$columna] = $fila[$columna];
                }
            }
            if ($cambios === []) {
                $resumen['sin_cambios']++;
            } else {
                DB::table('compromisos')->where('id', $guardada->id)->update($cambios + ['updated_at' => $ahora]);
                $resumen['actualizados']++;
            }
        }

        foreach (array_chunk($nuevas, 500) as $lote) {
            DB::table('compromisos')->insert($lote);
        }
        $resumen['creados'] = count($nuevas);

        $sobrantes = array_map(fn ($fila) => $fila->id, array_values($existentes));
        foreach (array_chunk($sobrantes, 1000) as $ids) {
            $resumen['borrados'] += DB::table('compromisos')->whereIn('id', $ids)->delete();
        }

        return $resumen;
    }

    /** @return list<int> meses (1-12) del anio con al menos un culto de la iglesia hasta hoy */
    private function mesesConCultos(int $tenantId, int $anio, ?int $mes, CarbonImmutable $hoy): array
    {
        if ($anio > $hoy->year) {
            return [];
        }

        $hasta = $anio === $hoy->year ? $hoy->toDateString() : $anio.'-12-31';
        $fechas = DB::table('cultos')
            ->where('tenant_id', $tenantId)
            ->whereBetween('fecha', [$anio.'-01-01', $hasta])
            ->pluck('fecha');

        $meses = [];
        foreach ($fechas as $fecha) {
            $meses[(int) substr((string) $fecha, 5, 2)] = true;
        }
        if ($mes !== null) {
            $meses = isset($meses[$mes]) ? [$mes => true] : [];
        }
        $meses = array_keys($meses);
        sort($meses);

        return $meses;
    }

    /**
     * @param  list<int>  $meses
     * @return array<string, array<string, mixed>> "persona|categoria|mes" => columnas
     */
    private function filasDeseadas(iterable $personas, int $anio, array $meses): array
    {
        $periodo = PeriodoReporte::deMeses($anio, $meses[0], $meses[count($meses) - 1]);
        $resultado = $this->motor->calcular($periodo, $personas, conDetalle: true);
        $quieroMes = array_flip($meses);

        $filas = [];
        foreach ($resultado->cuentas() as $cuenta) {
            // Lo dado tal como se anoto (sin convertir), por mes y categoria.
            $crudo = [];
            foreach ($cuenta->detalle as $renglon) {
                $clave = substr($renglon['fecha'], 0, 7).'|'.$renglon['categoria'];
                $crudo[$clave] = ($crudo[$clave] ?? 0) + (int) round($renglon['monto'] * 100);
            }

            foreach ($resultado->periodo->meses() as $mes) {
                if (! isset($quieroMes[$mes->mes])) {
                    continue;
                }
                foreach ($cuenta->promesas() as $categoria => $promesa) {
                    $prometidoCrc = $cuenta->esperadoDelMesCentimos($mes->clave, $categoria);
                    $dadoCrc = $cuenta->dadoCentimos($mes->clave, [$categoria]);
                    $filas[$cuenta->id.'|'.$categoria.'|'.$mes->mes] = [
                        'persona_id' => $cuenta->id,
                        'categoria' => $categoria,
                        'año' => $anio,
                        'mes' => $mes->mes,
                        // Si mezcla colones y dolares no hay una sola moneda: se guarda en colones.
                        'monto_prometido' => $promesa->moneda === PromesaVigente::MIXTA ? Dinero::colones($prometidoCrc) : round($promesa->montoDelMes($mes->domingos), 2),
                        'monto_dado' => Dinero::colones($crudo[$mes->clave.'|'.$categoria] ?? 0),
                        'saldo_anterior' => 0,
                        'saldo_actual' => Dinero::colones($dadoCrc - $prometidoCrc),
                        'moneda_promesa' => $promesa->moneda === PromesaVigente::MIXTA ? 'CRC' : $promesa->moneda,
                        'monto_prometido_crc' => Dinero::colones($prometidoCrc),
                        'monto_dado_crc' => Dinero::colones($dadoCrc),
                        'tipo_cambio_usado' => $promesa->tieneUsd() ? ($this->conversor->tipoCambioDePromesa($anio, $mes->mes) ?: null) : null,
                    ];
                }
            }
        }

        return $filas;
    }

    /** @param  list<int>  $idsIglesia */
    private function borrarFuturos(array $idsIglesia, CarbonImmutable $hoy): int
    {
        $borrados = 0;
        foreach (array_chunk($idsIglesia, 1000) as $ids) {
            $borrados += DB::table('compromisos')
                ->whereIn('persona_id', $ids)
                ->where(fn ($q) => $q->where('año', '>', $hoy->year)
                    ->orWhere(fn ($q) => $q->where('año', $hoy->year)->where('mes', '>', $hoy->month)))
                ->delete();
        }

        return $borrados;
    }

    private static function normalizar(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        return is_numeric($valor) ? number_format((float) $valor, 4, '.', '') : (string) $valor;
    }
}
