<?php

namespace App\Services;

use App\Models\Persona;
use App\Models\Promesa;
use App\Models\TipoCambio;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfPdf;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Informe individual de una persona (contribuciones y compromisos del año en curso) y su
 * enlace firmado, que es lo que se comparte por WhatsApp.
 *
 * Hasta ahora no existía un PDF por persona: «Reporte PDF» y «Reporte General» de Gestión de
 * Personas son colectivos. Este informe sigue la misma lógica de esos reportes (de enero al mes
 * en curso, promesas semanales por domingos del mes, quincenales x2, mensuales x1) y la misma
 * regla de la pantalla de Compromisos (se compara en colones, los dólares se convierten con el
 * tipo de cambio del sobre). Es de solo lectura: no escribe nada, porque lo abre cualquiera que
 * tenga el enlace.
 *
 * Usa nombre, logo y categorías de la iglesia actual (tenant()); el controlador público fija la
 * de la persona porque ahí no hay sesión.
 */
class InformePersonaService
{
    public const RUTA = 'informe.persona';

    public const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    /** Fecha en que vence un enlace generado ahora (config whatsapp.informe.vigencia_dias, 7 por defecto). */
    public function vence(): Carbon
    {
        return now()->addDays(max(1, (int) config('whatsapp.informe.vigencia_dias', 7)));
    }

    /** Enlace público al PDF, firmado y con vencimiento: sin sesión, pero imposible de adivinar o alterar. */
    public function urlFirmada(Persona $persona, ?DateTimeInterface $vence = null): string
    {
        return URL::temporarySignedRoute(self::RUTA, $vence ?? $this->vence(), ['persona' => $persona->id]);
    }

    public function nombreArchivo(Persona $persona, ?DateTimeInterface $hasta = null): string
    {
        $anio = ($hasta ? Carbon::instance($hasta) : now())->year;

        return 'informe-'.(Str::slug($persona->nombre) ?: 'persona').'-'.$anio.'.pdf';
    }

    public function pdf(Persona $persona, ?DateTimeInterface $hasta = null): DompdfPdf
    {
        $datos = $this->datos($persona, $hasta);

        $pdf = Pdf::loadView('pdfs.informe-persona', $datos);
        // Muchas categorías no caben en vertical; lo normal (pocas) se lee mejor en el celular así.
        $pdf->setPaper('letter', count($datos['categorias']) > 6 ? 'landscape' : 'portrait');

        return $pdf;
    }

    /**
     * Cifras del informe, de enero al mes de $hasta (hoy por defecto), todo en colones.
     *
     * @return array<string, mixed>
     */
    public function datos(Persona $persona, ?DateTimeInterface $hasta = null): array
    {
        $hasta = $hasta ? Carbon::instance($hasta) : now();
        $anio = $hasta->year;
        $mesActual = $hasta->month;
        $tcHoy = (float) (TipoCambio::hoy()?->venta ?? 0);

        [$dadoPorMes, $hayUsd] = $this->dadoPorMes($persona, $anio, $hasta, $tcHoy);
        $promesas = $persona->promesas()->orderBy('id')->get();

        // Columnas: categorías de la iglesia que la persona usó o prometió, en el orden de la iglesia;
        // las que ya no existen en la configuración van al final para que los totales cuadren.
        $nombresIglesia = tenant_categories()->pluck('nombre', 'slug')->all();
        $usadas = [];
        foreach ($dadoPorMes as $categorias) {
            foreach ($categorias as $slug => $monto) {
                if ($monto > 0) {
                    $usadas[$slug] = true;
                }
            }
        }
        foreach ($promesas as $promesa) {
            $usadas[$promesa->categoria] = true;
        }

        $categorias = [];
        foreach ($nombresIglesia as $slug => $nombre) {
            if (isset($usadas[$slug])) {
                $categorias[$slug] = $nombre;
            }
        }
        foreach (array_keys($usadas) as $slug) {
            $categorias[$slug] ??= Str::headline((string) $slug);
        }

        // Tabla mensual de lo dado.
        $meses = [];
        $totalesCategoria = array_fill_keys(array_keys($categorias), 0.0);
        $totalDado = 0.0;
        for ($mes = 1; $mes <= $mesActual; $mes++) {
            $fila = ['numero' => $mes, 'nombre' => self::MESES[$mes], 'por_categoria' => [], 'total' => 0.0];
            foreach (array_keys($categorias) as $slug) {
                $monto = round($dadoPorMes[$mes][$slug] ?? 0, 2);
                $fila['por_categoria'][$slug] = $monto;
                $fila['total'] += $monto;
                $totalesCategoria[$slug] += $monto;
            }
            $totalDado += $fila['total'];
            $meses[] = $fila;
        }

        // Compromisos: una fila por categoría prometida, de enero al mes en curso.
        $compromisos = [];
        foreach ($promesas->groupBy('categoria') as $slug => $delaCategoria) {
            $esperado = 0.0;
            foreach ($delaCategoria as $promesa) {
                for ($mes = 1; $mes <= $mesActual; $mes++) {
                    $esperado += $this->aColones($this->esperadoMes($promesa, $anio, $mes), $promesa->moneda, null, $tcHoy);
                }
            }
            $dado = $totalesCategoria[$slug] ?? 0.0;
            $compromisos[] = [
                'categoria' => $slug,
                'nombre' => $categorias[$slug] ?? Str::headline((string) $slug),
                'detalle' => $delaCategoria->map(fn (Promesa $p) => $this->describirPromesa($p))->implode(' + '),
                'esperado' => round($esperado, 2),
                'dado' => round($dado, 2),
                'diferencia' => round($dado - $esperado, 2),
                'al_dia' => $dado + 0.005 >= $esperado,
            ];
        }

        $totalEsperado = array_sum(array_column($compromisos, 'esperado'));
        $dadoEnCompromisos = array_sum(array_column($compromisos, 'dado'));

        return [
            'persona' => $persona,
            'anio' => $anio,
            'mes_actual' => $mesActual,
            'periodo' => 'Enero - '.self::MESES[$mesActual].' '.$anio,
            'categorias' => $categorias,
            'meses' => $meses,
            'totales_categoria' => array_map(fn ($m) => round($m, 2), $totalesCategoria),
            'total_dado' => round($totalDado, 2),
            'compromisos' => $compromisos,
            'tiene_compromisos' => $compromisos !== [],
            'total_esperado' => round($totalEsperado, 2),
            'dado_en_compromisos' => round($dadoEnCompromisos, 2),
            'diferencia' => round($dadoEnCompromisos - $totalEsperado, 2),
            'hay_datos' => $totalDado > 0 || $compromisos !== [],
            'hay_usd' => $hayUsd,
        ];
    }

    /**
     * Lo dado por mes y categoría, en colones, de los sobres de la persona en cultos del año
     * hasta el mes de $hasta. Se lee con joins y no con relaciones de Eloquent para no depender
     * de los scopes por iglesia (aquí puede no haber sesión) y hacerlo en una sola consulta.
     *
     * @return array{0: array<int, array<string, float>>, 1: bool}
     */
    private function dadoPorMes(Persona $persona, int $anio, Carbon $hasta, float $tcHoy): array
    {
        $filas = DB::table('sobre_detalles as d')
            ->join('sobres as s', 's.id', '=', 'd.sobre_id')
            ->join('cultos as c', 'c.id', '=', 's.culto_id')
            ->where('s.persona_id', $persona->id)
            ->whereBetween('c.fecha', [$anio.'-01-01', $hasta->copy()->endOfMonth()->toDateString()])
            ->get(['c.fecha', 'd.categoria', 'd.monto', 's.moneda', 's.tipo_cambio_venta']);

        $dado = [];
        $hayUsd = false;

        foreach ($filas as $fila) {
            $mes = Carbon::parse($fila->fecha)->month;
            $hayUsd = $hayUsd || $fila->moneda === 'USD';
            $dado[$mes][$fila->categoria] = ($dado[$mes][$fila->categoria] ?? 0)
                + $this->aColones((float) $fila->monto, $fila->moneda, $fila->tipo_cambio_venta, $tcHoy);
        }

        return [$dado, $hayUsd];
    }

    /** Dólares a colones con el tipo de cambio del sobre (o el de hoy si no lo guardó); sin tipo de cambio, el monto tal cual. */
    private function aColones(float $monto, ?string $moneda, mixed $tipoCambioSobre, float $tcHoy): float
    {
        if ($moneda !== 'USD') {
            return $monto;
        }

        $tc = (float) $tipoCambioSobre > 0 ? (float) $tipoCambioSobre : $tcHoy;

        return $tc > 0 ? round($monto * $tc, 2) : $monto;
    }

    /** Monto que corresponde a un mes completo según la frecuencia (misma regla que Reporte General y Compromisos). */
    private function esperadoMes(Promesa $promesa, int $anio, int $mes): float
    {
        $monto = (float) $promesa->monto;

        return match ($promesa->frecuencia) {
            'semanal' => $monto * $this->domingosEnMes($anio, $mes),
            'quincenal' => $monto * 2,
            default => $monto,
        };
    }

    private function domingosEnMes(int $anio, int $mes): int
    {
        $dia = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $dia->copy()->endOfMonth();
        $domingos = 0;

        for (; $dia->lte($fin); $dia->addDay()) {
            if ($dia->isSunday()) {
                $domingos++;
            }
        }

        return $domingos;
    }

    private function describirPromesa(Promesa $promesa): string
    {
        $simbolo = $promesa->moneda === 'USD' ? '$' : '₡';
        $frecuencia = match ($promesa->frecuencia) {
            'semanal' => 'cada domingo',
            'quincenal' => 'cada quincena',
            default => 'mensual',
        };

        return $simbolo.number_format((float) $promesa->monto, 2).' '.$frecuencia;
    }
}
