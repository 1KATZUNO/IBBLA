<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Reporte "Cumplimiento de promesas con corte a la fecha" (HU-R13): quienes estan al dia y quienes
 * tienen pendiente, para presentar al pastor.
 *
 * Reglas (las del listado que el lider armo a mano el 12/10 a partir del Reporte General, ahora
 * con los numeros del motor unico):
 *  - Le correspondia = lo prometido en los meses completos antes del corte (el mes en curso no se
 *    cobra; si el corte es fin de mes, ese mes si).
 *  - Ha dado = todo lo entregado del 1 de enero hasta el dia del corte en categorias de
 *    compromiso, incluido el mes en curso.
 *  - Estado por acumulado: AL DÍA si lo dado alcanza lo que correspondia (quien no dio un mes pero
 *    repuso despues esta al dia); PENDIENTE si no; SIN PROMESA si no tiene promesa pero aporto.
 *  - Observacion: "sin aportes en Junio", "sin aportes en N meses (Enero a Septiembre)" (3 o mas),
 *    "aporta, pero menos de lo prometido" o "adelantado".
 *  - Orden: pendientes, al dia y sin promesa; dentro de cada grupo por nombre.
 *  - No se listan las personas sin promesa que tampoco aportaron (se cuentan aparte).
 */
final class CumplimientoPromesasService
{
    public function __construct(private readonly MotorReportes $motor) {}

    public function generar(PeriodoReporte $periodo, FiltroPersonas $filtro): ReporteCumplimiento
    {
        $resultado = $this->motor->calcular($periodo, $filtro->personas());

        $filas = [];
        $sinMovimiento = 0;
        foreach ($resultado->cuentas() as $cuenta) {
            if (! $cuenta->tieneMovimientoEnCompromisos()) {
                $sinMovimiento++;

                continue;
            }
            $filas[] = $this->fila($cuenta, $resultado);
        }

        $orden = [EstadoCuenta::PENDIENTE => 0, EstadoCuenta::AL_DIA => 1, EstadoCuenta::SIN_PROMESA => 2];
        usort($filas, fn (FilaCumplimiento $a, FilaCumplimiento $b) => [$orden[$a->estado], self::claveOrden($a->nombre), $a->personaId]
            <=> [$orden[$b->estado], self::claveOrden($b->nombre), $b->personaId]);

        return new ReporteCumplimiento($periodo, $filtro, $resultado->categoriasCompromiso, $filas, $sinMovimiento, CarbonImmutable::now());
    }

    private function fila(CuentaPersona $cuenta, ResultadoMotor $resultado): FilaCumplimiento
    {
        $estado = $cuenta->estado();
        $etiqueta = $estado->etiqueta();

        $meses = [];
        foreach ($resultado->periodo->meses() as $mes) {
            $meses[$mes->clave] = [
                'dado' => $cuenta->dadoEnCompromisosCentimos($mes->clave),
                'esperado' => $cuenta->esperadoCentimos($mes->clave),
            ];
        }

        $promesas = [];
        foreach ($cuenta->promesas() as $categoria => $promesa) {
            $promesas[] = $resultado->nombreCategoria($categoria).' '.$promesa->descripcion();
        }

        return new FilaCumplimiento(
            personaId: $cuenta->id,
            nombre: $cuenta->nombre,
            estado: $etiqueta,
            leCorrespondeCentimos: $estado->leCorrespondeCentimos,
            haDadoCentimos: $estado->haDadoCentimos,
            ultimoAporte: $cuenta->ultimoAporte,
            observacion: $this->observacion($etiqueta, $estado, $cuenta),
            promesa: implode(' · ', $promesas),
            meses: $meses,
        );
    }

    private function observacion(string $etiqueta, EstadoCuenta $estado, CuentaPersona $cuenta): string
    {
        if ($etiqueta === EstadoCuenta::PENDIENTE) {
            $sin = array_map(fn (MesPeriodo $m) => $m->nombre, $cuenta->mesesSinAporte());
            if (count($sin) >= 3) {
                return 'sin aportes en '.count($sin).' meses ('.$sin[0].' a '.$sin[count($sin) - 1].')';
            }

            return $sin !== [] ? 'sin aportes en '.implode(', ', $sin) : 'aporta, pero menos de lo prometido';
        }

        if ($etiqueta === EstadoCuenta::AL_DIA && $estado->diferenciaCentimos() > 0) {
            return 'adelantado';
        }

        return '';
    }

    /** Orden por nombre sin distinguir mayusculas ni tildes (Ángela junto a Ana). */
    private static function claveOrden(string $nombre): string
    {
        return mb_strtolower(Str::ascii($nombre));
    }
}
