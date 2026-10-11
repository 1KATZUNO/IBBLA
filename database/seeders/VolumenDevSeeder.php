<?php

namespace Database\Seeders;

use App\Models\Culto;
use App\Models\Tenant;
use App\Services\CalculoTotalesCultoService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de volumen SOLO para desarrollo local (no se llama desde DatabaseSeeder).
 *
 *   php artisan db:seed --class=VolumenDevSeeder
 *
 * Sirve para reproducir con un anio completo los reportes pesados (p. ej. el
 * "Reporte General" dado vs esperado de Gestion de Personas, que daba 504).
 * Crea en el primer tenant (el de AdminUserSeeder):
 *  - 150 personas (140 activas, 10 inactivas),
 *  - ~70 cultos del anio actual (todos los domingos + un miercoles de cada tres)
 *    y 4 domingos de diciembre del anio anterior (para probar el filtro por anio),
 *  - ~2500 sobres del anio actual con 1-3 detalles (5% anonimos, 5% en USD,
 *    algunos montos con centimos),
 *  - promesas para ~60% de las personas (incluye un par de categorias repetidas
 *    y alguna promesa de diezmo, como pasa en datos reales),
 *  - compromisos mensuales sincronizados de enero al mes actual con la misma
 *    formula que CompromisoController (prometido por frecuencia, dado del mes),
 *  - totales_culto recalculados con CalculoTotalesCultoService.
 *
 * Es determinista (semilla fija) y re-ejecutable: antes de sembrar borra lo que
 * dejo una corrida anterior (personas y cultos con la marca en `notas`; el resto
 * cae por ON DELETE CASCADE).
 */
class VolumenDevSeeder extends Seeder
{
    public const MARCA = '[volumen-dev]';

    private const SEMILLA = 20261011;

    private const PERSONAS = 150;

    private const PERSONAS_INACTIVAS = 10;

    private const CATEGORIAS_PROMESA = ['misiones', 'seminario', 'construccion', 'campa', 'prestamo', 'micro'];

    private const TIPO_CAMBIO = 515.0;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('VolumenDevSeeder es solo para desarrollo local.');

            return;
        }

        $tenant = Tenant::query()->orderBy('id')->first();
        if (! $tenant) {
            $this->command?->error('No hay tenant: corre primero php artisan migrate --seed.');

            return;
        }

        mt_srand(self::SEMILLA);

        DB::transaction(function () use ($tenant) {
            $this->limpiarCorridaAnterior();

            $anio = (int) now()->year;
            $personas = $this->crearPersonas($tenant->id);
            $cultos = $this->crearCultos($tenant->id, $anio);
            $sobres = $this->crearSobres($cultos, $personas);
            $promesas = $this->crearPromesas($personas);
            $this->crearCompromisos($promesas, $sobres, $anio, (int) now()->month);
        });

        // Totales por culto (dashboards e ingresos) con el mismo servicio que usa la app.
        $servicio = app(CalculoTotalesCultoService::class);
        Culto::where('notas', self::MARCA)->get()->each(fn (Culto $culto) => $servicio->recalcular($culto));

        $cultoIds = DB::table('cultos')->where('notas', self::MARCA)->pluck('id');
        $personaIds = DB::table('personas')->where('notas', self::MARCA)->pluck('id');
        $sobreIds = DB::table('sobres')->whereIn('culto_id', $cultoIds)->pluck('id');
        $this->command?->info(sprintf(
            'VolumenDevSeeder: %d personas, %d cultos, %d sobres, %d detalles, %d promesas, %d compromisos.',
            $personaIds->count(),
            $cultoIds->count(),
            $sobreIds->count(),
            DB::table('sobre_detalles')->whereIn('sobre_id', $sobreIds)->count(),
            DB::table('promesas')->whereIn('persona_id', $personaIds)->count(),
            DB::table('compromisos')->whereIn('persona_id', $personaIds)->count(),
        ));
    }

    private function limpiarCorridaAnterior(): void
    {
        // cultos -> sobres -> sobre_detalles / totales_culto y personas -> promesas / compromisos
        // se borran en cascada por las llaves foraneas.
        DB::table('cultos')->where('notas', self::MARCA)->delete();
        DB::table('personas')->where('notas', self::MARCA)->delete();
    }

    /**
     * @return array<int, array{id:int, activo:bool, peso:int}>
     */
    private function crearPersonas(int $tenantId): array
    {
        $nombres = ['Ana', 'Luis', 'Maria', 'Jose', 'Carmen', 'Carlos', 'Rosa', 'Jorge', 'Elena', 'Miguel',
            'Lucia', 'Pedro', 'Sofia', 'Andres', 'Laura', 'Diego', 'Marta', 'Pablo', 'Isabel', 'Javier',
            'Paula', 'Daniel', 'Teresa', 'Manuel', 'Silvia', 'Rafael', 'Patricia', 'Fernando', 'Gloria', 'Ricardo'];
        $apellidos = ['Rodriguez', 'Vargas', 'Jimenez', 'Mora', 'Rojas', 'Solano', 'Araya', 'Chaves', 'Quesada', 'Herrera',
            'Castro', 'Alvarado', 'Ramirez', 'Sanchez', 'Hernandez', 'Campos', 'Gonzalez', 'Salazar', 'Arias', 'Brenes',
            'Calderon', 'Villalobos', 'Murillo', 'Segura', 'Fallas', 'Cordero', 'Matamoros', 'Valverde', 'Ulate', 'Zuniga'];

        $ahora = now();
        $filas = [];
        $usados = [];
        while (count($filas) < self::PERSONAS) {
            $nombre = $nombres[mt_rand(0, 29)].' '.$apellidos[mt_rand(0, 29)].' '.$apellidos[mt_rand(0, 29)];
            if (isset($usados[$nombre])) {
                continue;
            }
            $usados[$nombre] = true;
            $filas[] = [
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'telefono' => '8'.mt_rand(1000000, 9999999),
                'activo' => count($filas) >= self::PERSONAS_INACTIVAS,
                'notas' => self::MARCA,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }
        DB::table('personas')->insert($filas);

        return DB::table('personas')->where('notas', self::MARCA)->orderBy('id')->get(['id', 'activo'])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'activo' => (bool) $p->activo,
                // Cuantas "boletas" tiene en la tombola de cada culto: unos dan casi siempre, otros poco.
                'peso' => $p->activo ? mt_rand(1, 10) : 1,
            ])->all();
    }

    /**
     * @return array<int, array{id:int, fecha:Carbon, domingo:bool, anterior:bool}>
     */
    private function crearCultos(int $tenantId, int $anio): array
    {
        $tipos = DB::table('tenant_service_types')->where('tenant_id', $tenantId)->pluck('id', 'slug');
        $hoy = Carbon::today();

        $fechas = [];
        $d = Carbon::create($anio, 1, 1);
        while ($d->dayOfWeek !== Carbon::SUNDAY) {
            $d->addDay();
        }
        for (; $d->year === $anio; $d->addWeek()) {
            $fechas[] = [$d->copy(), true, false];
        }
        $w = Carbon::create($anio, 1, 1);
        while ($w->dayOfWeek !== Carbon::WEDNESDAY) {
            $w->addDay();
        }
        for ($i = 0; $w->year === $anio; $w->addWeek(), $i++) {
            if ($i % 3 === 0) {
                $fechas[] = [$w->copy(), false, false];
            }
        }
        $dic = Carbon::create($anio - 1, 12, 31);
        while ($dic->dayOfWeek !== Carbon::SUNDAY) {
            $dic->subDay();
        }
        for ($k = 0; $k < 4; $k++, $dic->subWeek()) {
            $fechas[] = [$dic->copy(), true, true];
        }
        usort($fechas, fn ($a, $b) => $a[0] <=> $b[0]);

        $filas = [];
        foreach ($fechas as [$fecha, $domingo]) {
            $cerrado = $fecha->lt($hoy);
            $filas[] = [
                'tenant_id' => $tenantId,
                'fecha' => $fecha->toDateString(),
                'hora' => $domingo ? '10:00:00' : '19:00:00',
                'tipo_culto' => $domingo ? 'domingo' : 'miércoles',
                'service_type_id' => $tipos[$domingo ? 'domingo' : 'miercoles'] ?? null,
                'cerrado' => $cerrado,
                'cerrado_at' => $cerrado ? $fecha->copy()->setTime(13, 0) : null,
                'tipo_cambio_venta' => $cerrado ? self::TIPO_CAMBIO : null,
                'notas' => self::MARCA,
                'created_at' => $fecha->copy()->setTime(8, 0),
                'updated_at' => $fecha->copy()->setTime(13, 0),
            ];
        }
        DB::table('cultos')->insert($filas);

        return DB::table('cultos')->where('notas', self::MARCA)->orderBy('fecha')->orderBy('id')->get(['id', 'fecha', 'hora'])
            ->map(function ($c) use ($anio) {
                $fecha = Carbon::parse($c->fecha);

                return [
                    'id' => (int) $c->id,
                    'fecha' => $fecha,
                    'domingo' => $fecha->dayOfWeek === Carbon::SUNDAY,
                    'anterior' => $fecha->year !== $anio,
                ];
            })->all();
    }

    /**
     * Inserta sobres y detalles. Devuelve los detalles con persona/fecha/moneda
     * para poder sincronizar compromisos sin volver a consultar.
     *
     * @return array<int, array{persona_id:int, anio:int, mes:int, categoria:string, monto:float, usd:bool}>
     */
    private function crearSobres(array $cultos, array $personas): array
    {
        $tombola = [];
        foreach ($personas as $p) {
            for ($i = 0; $i < $p['peso']; $i++) {
                $tombola[] = $p['id'];
            }
        }
        $tipoCambioId = DB::table('tipo_cambios')->orderByDesc('fecha')->value('id');

        $sobres = [];
        $detallesPorSobre = [];
        foreach ($cultos as $culto) {
            if ($culto['anterior']) {
                $cantidad = 25;
            } else {
                $cantidad = $culto['domingo'] ? 42 + mt_rand(-5, 5) : 17 + mt_rand(-3, 3);
            }
            $elegidos = [];
            $intentos = 0;
            while (count($elegidos) < $cantidad && $intentos < 5000) {
                $intentos++;
                $id = $tombola[mt_rand(0, count($tombola) - 1)];
                $elegidos[$id] = true;
            }
            $elegidos = array_keys($elegidos);

            $numero = 0;
            foreach ($elegidos as $personaId) {
                $numero++;
                $anonimo = mt_rand(1, 100) <= 5;
                $usd = mt_rand(1, 100) <= 5;
                $transferencia = mt_rand(1, 100) <= 20;
                $detalles = $this->generarDetalles($usd);
                $momento = $culto['fecha']->copy()->setTime($culto['domingo'] ? 11 : 20, mt_rand(0, 59));

                $sobres[] = [
                    'culto_id' => $culto['id'],
                    'persona_id' => $anonimo ? null : $personaId,
                    'numero_sobre' => $numero,
                    'metodo_pago' => $transferencia ? 'transferencia' : 'efectivo',
                    'comprobante_numero' => $transferencia ? (string) mt_rand(10000000, 99999999) : null,
                    'total_declarado' => round(array_sum(array_column($detalles, 'monto')), 2),
                    'moneda' => $usd ? 'USD' : 'CRC',
                    'tipo_cambio_venta' => $usd ? self::TIPO_CAMBIO : null,
                    'tipo_cambio_id' => $usd ? $tipoCambioId : null,
                    'created_at' => $momento,
                    'updated_at' => $momento,
                ];
                $detallesPorSobre[$culto['id'].'-'.$numero] = $detalles;
            }
        }
        foreach (array_chunk($sobres, 500) as $lote) {
            DB::table('sobres')->insert($lote);
        }

        $ids = DB::table('sobres')
            ->whereIn('culto_id', array_column($cultos, 'id'))
            ->get(['id', 'culto_id', 'numero_sobre', 'persona_id', 'moneda', 'created_at']);
        $fechaCulto = [];
        foreach ($cultos as $culto) {
            $fechaCulto[$culto['id']] = $culto['fecha'];
        }

        $filas = [];
        $resumen = [];
        foreach ($ids as $sobre) {
            foreach ($detallesPorSobre[$sobre->culto_id.'-'.$sobre->numero_sobre] as $detalle) {
                $filas[] = [
                    'sobre_id' => $sobre->id,
                    'categoria' => $detalle['categoria'],
                    'monto' => $detalle['monto'],
                    'created_at' => $sobre->created_at,
                    'updated_at' => $sobre->created_at,
                ];
                if ($sobre->persona_id) {
                    $fecha = $fechaCulto[$sobre->culto_id];
                    $resumen[] = [
                        'persona_id' => (int) $sobre->persona_id,
                        'anio' => $fecha->year,
                        'mes' => $fecha->month,
                        'categoria' => $detalle['categoria'],
                        'monto' => $detalle['monto'],
                        'usd' => $sobre->moneda === 'USD',
                    ];
                }
            }
        }
        foreach (array_chunk($filas, 1000) as $lote) {
            DB::table('sobre_detalles')->insert($lote);
        }

        return $resumen;
    }

    /**
     * @return array<int, array{categoria:string, monto:float}>
     */
    private function generarDetalles(bool $usd): array
    {
        $categorias = [];
        if (mt_rand(1, 100) <= 70) {
            $categorias[] = 'diezmo';
        }
        if (mt_rand(1, 100) <= 10) {
            $categorias[] = 'ofrenda_especial';
        }
        $extra = self::CATEGORIAS_PROMESA;
        shuffle($extra);
        $categorias = array_merge($categorias, array_slice($extra, 0, mt_rand(empty($categorias) ? 1 : 0, 2)));

        $detalles = [];
        foreach ($categorias as $categoria) {
            if ($usd) {
                $monto = mt_rand(5, 100);
            } else {
                $monto = $categoria === 'diezmo' ? mt_rand(2, 120) * 500 : mt_rand(1, 40) * 500;
            }
            // Algunos montos con centimos para que la comparacion antes/despues cubra decimales.
            if (mt_rand(1, 100) <= 4) {
                $monto += mt_rand(1, 99) / 100;
            }
            $detalles[] = ['categoria' => $categoria, 'monto' => round($monto, 2)];
        }

        return $detalles;
    }

    /**
     * Devuelve persona_id => categoria => promesa vigente (la ultima, que es la que usa el reporte).
     *
     * @return array<int, array<string, array{monto:float, moneda:string, frecuencia:string}>>
     */
    private function crearPromesas(array $personas): array
    {
        $ahora = now();
        $filas = [];
        $vigentes = [];
        $conDuplicado = 0;
        $conDiezmo = 0;
        foreach ($personas as $p) {
            // ~60% de las activas y alguna inactiva (que el reporte debe ignorar).
            if (mt_rand(1, 100) > ($p['activo'] ? 62 : 30)) {
                continue;
            }
            $categorias = self::CATEGORIAS_PROMESA;
            shuffle($categorias);
            $categorias = array_slice($categorias, 0, mt_rand(1, 4));
            if ($conDuplicado < 2) {
                $categorias[] = $categorias[0];
                $conDuplicado++;
            } elseif ($conDiezmo < 2) {
                $categorias[] = 'diezmo';
                $conDiezmo++;
            }
            foreach ($categorias as $categoria) {
                $usd = mt_rand(1, 100) <= 5;
                $r = mt_rand(1, 100);
                $frecuencia = $r <= 50 ? 'mensual' : ($r <= 80 ? 'semanal' : 'quincenal');
                $monto = $usd ? mt_rand(10, 50) : mt_rand(4, 100) * 500;
                if ($frecuencia === 'semanal' && ! $usd) {
                    $monto = mt_rand(2, 30) * 500;
                }
                $filas[] = [
                    'persona_id' => $p['id'],
                    'categoria' => $categoria,
                    'monto' => $monto,
                    'moneda' => $usd ? 'USD' : 'CRC',
                    'frecuencia' => $frecuencia,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
                $vigentes[$p['id']][$categoria] = ['monto' => (float) $monto, 'moneda' => $usd ? 'USD' : 'CRC', 'frecuencia' => $frecuencia];
            }
        }
        DB::table('promesas')->insert($filas);

        return $vigentes;
    }

    /**
     * Misma formula que CompromisoController::calcularCompromisos (cada mes independiente, saldo en CRC).
     */
    private function crearCompromisos(array $promesas, array $detalles, int $anio, int $mesActual): void
    {
        $dado = [];
        $dadoCrc = [];
        foreach ($detalles as $d) {
            if ($d['anio'] !== $anio) {
                continue;
            }
            $k = $d['persona_id'].'|'.$d['categoria'].'|'.$d['mes'];
            $dado[$k] = ($dado[$k] ?? 0) + $d['monto'];
            $dadoCrc[$k] = ($dadoCrc[$k] ?? 0) + ($d['usd'] ? round($d['monto'] * self::TIPO_CAMBIO, 2) : $d['monto']);
        }

        $ahora = now();
        $filas = [];
        foreach ($promesas as $personaId => $porCategoria) {
            foreach ($porCategoria as $categoria => $promesa) {
                for ($mes = 1; $mes <= $mesActual; $mes++) {
                    $prometido = $this->montoPrometido($promesa, $anio, $mes);
                    $prometidoCrc = $promesa['moneda'] === 'USD' ? round($prometido * self::TIPO_CAMBIO, 2) : $prometido;
                    $k = $personaId.'|'.$categoria.'|'.$mes;
                    $filas[] = [
                        'persona_id' => $personaId,
                        'categoria' => $categoria,
                        'año' => $anio,
                        'mes' => $mes,
                        'monto_prometido' => $prometido,
                        'monto_dado' => $dado[$k] ?? 0,
                        'saldo_anterior' => 0,
                        'saldo_actual' => round(($dadoCrc[$k] ?? 0) - $prometidoCrc, 2),
                        'moneda_promesa' => $promesa['moneda'],
                        'monto_prometido_crc' => $prometidoCrc,
                        'monto_dado_crc' => $dadoCrc[$k] ?? 0,
                        'tipo_cambio_usado' => self::TIPO_CAMBIO,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }
        }
        foreach (array_chunk($filas, 1000) as $lote) {
            DB::table('compromisos')->insert($lote);
        }
    }

    private function montoPrometido(array $promesa, int $anio, int $mes): float
    {
        return match ($promesa['frecuencia']) {
            'semanal' => $promesa['monto'] * $this->domingosDelMes($anio, $mes),
            'quincenal' => $promesa['monto'] * 2,
            default => $promesa['monto'],
        };
    }

    private function domingosDelMes(int $anio, int $mes): int
    {
        $domingos = 0;
        $fecha = Carbon::create($anio, $mes, 1);
        $fin = $fecha->copy()->endOfMonth();
        for (; $fecha->lte($fin); $fecha->addDay()) {
            if ($fecha->dayOfWeek === Carbon::SUNDAY) {
                $domingos++;
            }
        }

        return $domingos;
    }
}
