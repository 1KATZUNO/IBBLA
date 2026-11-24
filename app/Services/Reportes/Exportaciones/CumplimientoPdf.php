<?php

namespace App\Services\Reportes\Exportaciones;

use App\Services\Reportes\Dinero;
use App\Services\Reportes\FilaCumplimiento;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\ReporteCumplimiento;
use App\Support\Pdf\DocumentoPdf;

/**
 * PDF del cumplimiento de promesas con corte: el listado para el pastor. Carta vertical, mismo
 * orden que el listado hecho a mano del 12/10: explicacion de la regla, resumen, Pendientes, Al dia
 * y quienes aportan sin promesa registrada. Dibujado con la plantilla unica (DocumentoPdf).
 */
class CumplimientoPdf
{
    private const COLUMNAS = [
        ['titulo' => '#', 'ancho' => 0.8, 'alinear' => 'centro'],
        ['titulo' => 'Nombre', 'ancho' => 5.2],
        ['titulo' => 'Le correspondía', 'ancho' => 2.7, 'alinear' => 'derecha'],
        ['titulo' => 'Ha dado', 'ancho' => 2.5, 'alinear' => 'derecha'],
        ['titulo' => 'Diferencia', 'ancho' => 2.5, 'alinear' => 'derecha'],
        ['titulo' => FilaCumplimiento::TITULO_ULTIMO_APORTE, 'ancho' => 3.0, 'alinear' => 'centro'],
        ['titulo' => 'Observación', 'ancho' => 3.6],
    ];

    public function generar(ReporteCumplimiento $reporte): string
    {
        $corte = $reporte->corteTexto();
        $filtros = $reporte->filtro->grupo === FiltroPersonas::TODAS ? [] : [$reporte->filtro->descripcion()];
        $doc = new DocumentoPdf('Cumplimiento de promesas', 'Corte al '.$corte, $filtros, generadoEn: $reporte->generadoEn);

        $doc->encabezado('Cumplimiento de promesas - corte al '.$corte);
        $doc->nota($reporte->explicacion());

        $resumen = $reporte->resumen();
        $doc->tarjetas([
            ['etiqueta' => 'Con promesa', 'valor' => (string) $resumen['con_promesa']],
            ['etiqueta' => 'Al día', 'valor' => (string) $resumen['al_dia'], 'color' => DocumentoPdf::VERDE],
            ['etiqueta' => 'Pendientes', 'valor' => (string) $resumen['pendientes'], 'color' => DocumentoPdf::ROJO],
            ['etiqueta' => 'Correspondía ('.$reporte->periodo->abreviaturaMesesCobrables().')', 'valor' => Dinero::formato($resumen['le_correspondia'])],
            ['etiqueta' => 'Dado al corte', 'valor' => Dinero::formato($resumen['ha_dado'])],
            ['etiqueta' => 'Faltante de pendientes', 'valor' => Dinero::formato($resumen['faltante'])],
        ], [2.6, 2.2, 2.4, 3.6, 3.2, 3.6]);

        if ($reporte->filas === []) {
            $doc->parrafo('No hay personas con promesa ni aportes para este filtro.', 10, DocumentoPdf::GRIS);
        }

        $this->seccion($doc, $reporte->pendientes(), 'Pendientes', DocumentoPdf::ROJO);
        $this->seccion($doc, $reporte->alDia(), 'Al día', DocumentoPdf::VERDE);

        $sinPromesa = $reporte->sinPromesa();
        if ($sinPromesa !== []) {
            $doc->tituloSeccion('Aportan sin promesa registrada ('.count($sinPromesa).')', DocumentoPdf::AZUL_OSCURO, 20);
            $doc->parrafo(implode(', ', array_map(
                fn (FilaCumplimiento $f) => $f->nombre.' ('.Dinero::formato($f->haDado()).')',
                $sinPromesa,
            )));
        }

        if ($reporte->sinMovimiento > 0) {
            $doc->nota('No se listan '.$reporte->sinMovimiento.' '.($reporte->sinMovimiento === 1 ? 'persona' : 'personas').' sin promesa y sin aportes en el periodo.');
        }

        return $doc->salida();
    }

    /** @param  list<FilaCumplimiento>  $filas */
    private function seccion(DocumentoPdf $doc, array $filas, string $titulo, string $color): void
    {
        if ($filas === []) {
            return;
        }

        $doc->tituloSeccion($titulo.' ('.count($filas).')', $color);

        $cuerpo = [];
        foreach ($filas as $i => $fila) {
            $cuerpo[] = [
                (string) ($i + 1),
                $fila->nombre,
                Dinero::formato($fila->leCorresponde()),
                Dinero::formato($fila->haDado()),
                Dinero::formato($fila->diferencia(), conSigno: true),
                $fila->ultimoAporteTexto(),
                $fila->observacion,
            ];
        }

        [$correspondia, $dado, $diferencia] = ReporteCumplimiento::totales($filas);
        $doc->tabla(self::COLUMNAS, $cuerpo, ['', 'Total', Dinero::formato($correspondia), Dinero::formato($dado), Dinero::formato($diferencia, conSigno: true), '', ''], $color);
    }

    public static function nombreArchivo(ReporteCumplimiento $reporte): string
    {
        return 'cumplimiento-promesas_'.$reporte->periodo->sufijoArchivo().'.pdf';
    }
}
