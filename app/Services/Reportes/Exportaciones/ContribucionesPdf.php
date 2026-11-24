<?php

namespace App\Services\Reportes\Exportaciones;

use App\Services\Reportes\Dinero;
use App\Services\Reportes\ResultadoMotor;
use App\Support\Pdf\DocumentoPdf;

/**
 * "Reporte PDF" de contribuciones de Gestion de Personas: una fila por persona activa que dio algo
 * en el periodo, con lo dado en cada categoria de la iglesia (todas, tambien el diezmo) y el total,
 * mas los totales por categoria. Cifras del motor unico (dolares convertidos, nada posterior a hoy).
 *
 * Antes era una vista Blade con DomPDF (4-5 s con 150 personas, QA-030); ahora se dibuja con la
 * plantilla unica sobre el canvas, como el Reporte General y el cumplimiento.
 */
class ContribucionesPdf
{
    /**
     * @param  iterable<object>  $personas  personas en el orden en que se listan (id, nombre)
     */
    public function generar(ResultadoMotor $resultado, iterable $personas): string
    {
        $categorias = $resultado->categorias;
        $periodo = $resultado->periodo;
        $doc = new DocumentoPdf('Reporte de Contribuciones', 'Acumulado: '.$periodo->etiqueta(), orientacion: 'landscape');
        $doc->encabezado('Reporte de Contribuciones');

        $filas = [];
        $totales = array_fill_keys(array_keys($categorias), 0);
        $totalGeneral = 0;
        foreach ($personas as $persona) {
            $cuenta = $resultado->cuenta((int) $persona->id);
            if ($cuenta === null) {
                continue;
            }
            $fila = [$cuenta->nombre];
            $totalPersona = 0;
            foreach (array_keys($categorias) as $slug) {
                $centimos = $cuenta->dadoCentimos(null, [$slug]);
                $fila[] = Dinero::formato(Dinero::colones($centimos), 2);
                $totales[$slug] += $centimos;
                $totalPersona += $centimos;
            }
            if ($totalPersona <= 0) {
                continue;
            }
            $fila[] = ['texto' => Dinero::formato(Dinero::colones($totalPersona), 2), 'negrita' => true];
            $filas[] = $fila;
            $totalGeneral += $totalPersona;
        }

        $doc->tarjetas([
            ['etiqueta' => 'Personas que aportaron', 'valor' => (string) count($filas)],
            ['etiqueta' => 'Total recaudado', 'valor' => Dinero::formato(Dinero::colones($totalGeneral), 2), 'color' => DocumentoPdf::VERDE],
        ]);

        if ($filas === []) {
            $doc->parrafo('No hay contribuciones registradas en este período.', 10, DocumentoPdf::GRIS);

            return $doc->salida();
        }

        $columnas = [['titulo' => 'Nombre', 'ancho' => 3.2]];
        foreach ($categorias as $nombre) {
            $columnas[] = ['titulo' => $nombre, 'ancho' => 1.6, 'alinear' => 'derecha'];
        }
        $columnas[] = ['titulo' => 'Total', 'ancho' => 1.8, 'alinear' => 'derecha'];

        $total = ['TOTALES'];
        foreach ($totales as $centimos) {
            $total[] = Dinero::formato(Dinero::colones($centimos), 2);
        }
        $total[] = Dinero::formato(Dinero::colones($totalGeneral), 2);

        $doc->tabla($columnas, $filas, $total, tam: 7.0);
        $doc->nota('Lo dado en cada categoría del '.$periodo->desde->format('d/m/Y').' al '.$periodo->hasta->format('d/m/Y').'; los sobres en dólares, convertidos a colones con el tipo de cambio del sobre o del día del culto.');

        return $doc->salida();
    }
}
