<?php

namespace App\Services;

use App\Models\Persona;
use App\Services\Reportes\CuentaPersona;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\EstadoCuenta;
use App\Services\Reportes\MotorReportes;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Reportes\ResultadoMotor;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfPdf;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Informe individual de una persona (estado de cuenta: contribuciones y compromisos del año en
 * curso) y su enlace firmado, que es lo que se comparte por WhatsApp.
 *
 * Las cifras salen del motor único (Reportes\MotorReportes), el mismo del Reporte General, el
 * estado de cuenta en lote, el reporte de cumplimiento, Compromisos y Mi perfil: de enero a hoy
 * (corte), se cobran los meses completos antes del corte, lo dado cuenta hasta el corte y solo en
 * las categorías con promesa (docs/plan-oct-2026/REGLAS-CALCULO.md). Es de solo lectura: no
 * escribe nada, porque lo abre cualquiera que tenga el enlace.
 *
 * Usa nombre, logo y categorías de la iglesia actual (tenant()); el controlador público fija la
 * de la persona porque ahí no hay sesión.
 */
class InformePersonaService
{
    public const RUTA = 'informe.persona';

    public const MESES = PeriodoReporte::NOMBRES_MESES;

    public function __construct(private readonly MotorReportes $motor) {}

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

    /** El periodo del informe: de enero al mes de $hasta (hoy por defecto); el motor lo recorta a hoy (corte). */
    public function periodo(?DateTimeInterface $hasta = null): PeriodoReporte
    {
        $hasta = $hasta ? Carbon::instance($hasta) : now();

        return PeriodoReporte::deMeses($hasta->year, 1, $hasta->month);
    }

    /**
     * Cifras del informe, de enero al mes de $hasta (hoy por defecto), todo en colones.
     *
     * @return array<string, mixed>
     */
    public function datos(Persona $persona, ?DateTimeInterface $hasta = null): array
    {
        $resultado = $this->motor->calcular($this->periodo($hasta), [$persona]);

        return $this->datosDeCuenta($resultado->cuenta($persona->id), $resultado, $persona);
    }

    /**
     * Las cifras del informe a partir de una cuenta ya calculada por el motor: así el estado de
     * cuenta en lote calcula a todas las personas en una sola pasada y cada sección dice lo mismo
     * que el informe individual.
     *
     * @return array<string, mixed>
     */
    public function datosDeCuenta(CuentaPersona $cuenta, ResultadoMotor $resultado, ?Persona $persona = null): array
    {
        $periodo = $resultado->periodo;
        $variosAnios = $periodo->abarcaVariosAnios();
        $promesas = $cuenta->promesas();
        $dadoPorCategoria = $cuenta->dadoPorCategoria();

        // Columnas: categorías de la iglesia que la persona usó o prometió, en el orden de la iglesia;
        // las que ya no existen en la configuración van al final para que los totales cuadren.
        $usadas = [];
        foreach ($dadoPorCategoria as $slug => $monto) {
            if ($monto > 0) {
                $usadas[$slug] = true;
            }
        }
        foreach ($promesas as $slug => $promesa) {
            $usadas[$slug] = true;
        }

        $categorias = [];
        foreach ($resultado->categorias as $slug => $nombre) {
            if (isset($usadas[$slug])) {
                $categorias[$slug] = $nombre;
            }
        }
        foreach (array_keys($usadas) as $slug) {
            $categorias[$slug] ??= $resultado->nombreCategoria((string) $slug);
        }

        // Tabla mensual de lo dado.
        $meses = [];
        $totalesCategoria = array_fill_keys(array_keys($categorias), 0);
        $totalDado = 0;
        foreach ($periodo->meses() as $mes) {
            $fila = ['numero' => $mes->mes, 'nombre' => $mes->etiqueta($variosAnios), 'clave' => $mes->clave, 'por_categoria' => [], 'total' => 0.0];
            $totalMes = 0;
            foreach (array_keys($categorias) as $slug) {
                $monto = $cuenta->dadoCentimos($mes->clave, [$slug]);
                $fila['por_categoria'][$slug] = Dinero::colones($monto);
                $totalMes += $monto;
                $totalesCategoria[$slug] += $monto;
            }
            $fila['total'] = Dinero::colones($totalMes);
            $totalDado += $totalMes;
            $meses[] = $fila;
        }

        // Compromisos: una fila por categoría prometida, con lo esperado de los meses que se cobran
        // (completos antes del corte) y todo lo dado en esa categoría hasta el corte. El diezmo y las
        // categorías sin promesa salen en la tabla mensual pero no cuentan para el estado.
        $compromisos = [];
        foreach ($promesas as $slug => $promesa) {
            $esperado = $cuenta->esperadoCentimos(categoria: $slug);
            $dado = $cuenta->dadoCentimos(null, [$slug]);
            $compromisos[] = [
                'categoria' => $slug,
                'nombre' => $categorias[$slug] ?? $resultado->nombreCategoria($slug),
                'detalle' => $promesa->descripcion(),
                'esperado' => Dinero::colones($esperado),
                'dado' => Dinero::colones($dado),
                'diferencia' => Dinero::colones($dado - $esperado),
                'al_dia' => $dado + EstadoCuenta::TOLERANCIA_CENTIMOS >= $esperado,
            ];
        }

        // Mes a mes de cada compromiso, solo informativo (el estado nunca se decide mes a mes): lo
        // que corresponde al mes según la promesa, si ese mes se cobra, y lo dado en la categoría.
        $detalleMensual = [];
        if ($compromisos !== []) {
            foreach ($periodo->meses() as $mes) {
                $fila = ['clave' => $mes->clave, 'nombre' => $mes->etiqueta($variosAnios), 'cobrable' => $mes->cobrable, 'categorias' => []];
                foreach (array_keys($promesas) as $slug) {
                    $fila['categorias'][$slug] = [
                        'del_mes' => $cuenta->esperadoDelMes($mes->clave, $slug),
                        'dado' => $cuenta->dado($mes->clave, [$slug]),
                    ];
                }
                $detalleMensual[] = $fila;
            }
        }

        $estado = $cuenta->estado();
        $mesActual = $periodo->hasta->month;
        $tienePromesa = $cuenta->tienePromesa() && $compromisos !== [];

        return [
            'persona' => $persona ?? (object) ['id' => $cuenta->id, 'nombre' => $cuenta->nombre],
            'anio' => $periodo->hasta->year,
            'mes_actual' => $mesActual,
            'periodo' => $periodo->etiqueta(),
            'categorias' => $categorias,
            'meses' => $meses,
            'totales_categoria' => array_map(fn (int $c) => Dinero::colones($c), $totalesCategoria),
            'total_dado' => Dinero::colones($totalDado),
            'compromisos' => $compromisos,
            'tiene_compromisos' => $compromisos !== [],
            'total_esperado' => $tienePromesa ? $estado->leCorresponde() : 0.0,
            'dado_en_compromisos' => $tienePromesa ? $estado->haDado() : 0.0,
            'diferencia' => $tienePromesa ? $estado->diferencia() : 0.0,
            'al_dia' => $estado->alDia(),
            'estado' => $estado->etiqueta(),
            'corte' => $periodo->hasta->format('d/m/Y'),
            'meses_cobrados' => $periodo->textoMesesCobrables(),
            'mes_en_curso' => $periodo->mesEnCurso()?->nombre,
            'detalle_mensual' => $detalleMensual,
            'hay_datos' => $totalDado > 0 || $compromisos !== [],
            'hay_usd' => $cuenta->hayUsd,
        ];
    }
}
