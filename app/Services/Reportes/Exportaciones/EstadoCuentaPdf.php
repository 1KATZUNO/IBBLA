<?php

namespace App\Services\Reportes\Exportaciones;

use App\Services\Reportes\Dinero;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\LoteEstadosCuenta;
use App\Support\Pdf\DocumentoPdf;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Estados de cuenta en PDF con la plantilla unica (HU-R5):
 *  - lote(): un solo PDF con portada, indice (nombre y pagina) y una seccion por persona, cada una
 *    desde una pagina nueva;
 *  - zip(): un PDF por persona dentro de un ZIP.
 *
 * Cada seccion trae lo mismo que el informe individual (datos de InformePersonaService): resumen,
 * compromisos y contribuciones por mes. Se dibuja sobre el canvas, asi que 50 personas salen en un
 * par de segundos (con la vista Blade del informe serian ~1 s por persona: el 504 de antes).
 */
class EstadoCuentaPdf
{
    /**
     * Tope del ZIP: cada PDF se arma por separado (~0,15 s cada uno con las fuentes reducidas), asi que
     * 40 quedan bajo los 10 s por peticion de Hostinger. Para todo el grupo esta el PDF en un archivo.
     */
    public const MAX_ZIP = 40;

    public function lote(LoteEstadosCuenta $lote): string
    {
        $doc = $this->documento($lote, 'Estados de cuenta');
        $doc->portada(array_filter([
            $lote->cantidad().' '.($lote->cantidad() === 1 ? 'persona' : 'personas'),
            $lote->recortado() ? 'Se incluyen las primeras '.$lote->cantidad().' de '.$lote->totalPersonas.' (tope por documento)' : null,
        ]));

        if ($lote->estados === []) {
            $doc->saltoDePagina()->parrafo('No hay personas que cumplan el filtro.', 11, DocumentoPdf::GRIS);

            return $doc->salida();
        }

        // Primero se mide cuantas paginas ocupa cada seccion para poner el numero en el indice.
        $indicePrueba = array_map(fn ($datos) => [$datos['persona']->nombre, 9999], $lote->estados);
        $pagina = 1 + $doc->medirPaginas(fn () => $this->indice($doc, $indicePrueba), desdeArriba: true) + 1;
        $indice = [];
        foreach ($lote->estados as $datos) {
            $indice[] = [$datos['persona']->nombre, $pagina];
            $pagina += $doc->medirPaginas(fn () => $this->seccion($doc, $datos, $lote), desdeArriba: true);
        }

        $doc->saltoDePagina();
        $this->indice($doc, $indice);
        foreach ($lote->estados as $datos) {
            $doc->saltoDePagina();
            $this->seccion($doc, $datos, $lote);
        }

        return $doc->salida();
    }

    /** El estado de cuenta de una persona del lote, en su propio PDF. */
    public function individual(array $datos, LoteEstadosCuenta $lote, ?array $iglesia = null): string
    {
        $doc = $this->documento($lote, 'Estado de cuenta', $iglesia);
        $doc->encabezado('Estado de cuenta - '.$datos['persona']->nombre);
        $this->seccion($doc, $datos, $lote, conEncabezado: false);

        return $doc->salida();
    }

    /**
     * Un PDF por persona en un ZIP. Devuelve la ruta de un archivo temporal (quien lo usa lo borra).
     */
    public function zip(LoteEstadosCuenta $lote): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('El servidor no tiene la extensión zip de PHP.');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'estados');
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP.');
        }

        // Los datos de la iglesia (y su logo) se leen una sola vez para todos los PDF.
        $iglesia = tenant_pdf_data();
        $usados = [];
        foreach ($lote->estados as $datos) {
            $base = 'estado-cuenta_'.(Str::slug($datos['persona']->nombre) ?: 'persona');
            $nombre = isset($usados[$base]) ? $base.'-'.$datos['persona']->id : $base;
            $usados[$base] = true;
            $zip->addFromString($nombre.'_'.$lote->periodo->sufijoArchivo().'.pdf', $this->individual($datos, $lote, $iglesia));
        }
        $zip->close();

        return $ruta;
    }

    public static function nombreArchivo(LoteEstadosCuenta $lote, string $extension = 'pdf'): string
    {
        return 'estados-de-cuenta_'.$lote->periodo->sufijoArchivo().'.'.$extension;
    }

    // ------------------------------------------------------------------

    private function documento(LoteEstadosCuenta $lote, string $titulo, ?array $iglesia = null): DocumentoPdf
    {
        $filtros = $lote->filtro->grupo === FiltroPersonas::TODAS ? [] : [$lote->filtro->descripcion()];

        return new DocumentoPdf($titulo, $lote->periodo->etiqueta(), $filtros, iglesia: $iglesia, generadoEn: $lote->generadoEn);
    }

    /** @param  list<array{0: string, 1: int}>  $entradas */
    private function indice(DocumentoPdf $doc, array $entradas): void
    {
        $doc->tituloSeccion('Índice', DocumentoPdf::AZUL_OSCURO, 20);
        $doc->indice($entradas);
    }

    /** Una persona: encabezado, resumen, compromisos, contribuciones por mes y nota. */
    private function seccion(DocumentoPdf $doc, array $datos, LoteEstadosCuenta $lote, bool $conEncabezado = true): void
    {
        if ($conEncabezado) {
            $doc->encabezadoSeccion($datos['persona']->nombre, $datos['periodo']);
        }

        $tarjetas = [['etiqueta' => 'Total contribuido', 'valor' => Dinero::formato($datos['total_dado'], 2)]];
        if ($datos['tiene_compromisos']) {
            $tarjetas[] = ['etiqueta' => 'Le corresponde al '.$datos['corte'], 'valor' => Dinero::formato($datos['total_esperado'], 2)];
            $tarjetas[] = ['etiqueta' => 'Ha dado en compromisos', 'valor' => Dinero::formato($datos['dado_en_compromisos'], 2)];
            $tarjetas[] = ['etiqueta' => 'Diferencia', 'valor' => Dinero::formato($datos['diferencia'], 2, true)];
            $tarjetas[] = $datos['al_dia']
                ? ['etiqueta' => 'Estado', 'valor' => 'AL DÍA', 'color' => DocumentoPdf::VERDE]
                : ['etiqueta' => 'Estado', 'valor' => 'PENDIENTE', 'color' => DocumentoPdf::ROJO];
        }
        $doc->tarjetas($tarjetas);

        if ($datos['tiene_compromisos']) {
            $doc->tituloSeccion('Compromisos', DocumentoPdf::AZUL_OSCURO, 40);
            $filas = [];
            foreach ($datos['compromisos'] as $c) {
                $filas[] = [
                    $c['nombre'],
                    $c['detalle'],
                    Dinero::formato($c['esperado'], 2),
                    Dinero::formato($c['dado'], 2),
                    Dinero::formato($c['diferencia'], 2, true),
                    ['texto' => $c['al_dia'] ? 'Al día' : 'Pendiente', 'color' => $c['al_dia'] ? DocumentoPdf::VERDE : DocumentoPdf::AMBAR, 'negrita' => true],
                ];
            }
            $doc->tabla([
                ['titulo' => 'Categoría', 'ancho' => 2.4],
                ['titulo' => 'Compromiso', 'ancho' => 3.2],
                ['titulo' => 'Le corresponde', 'ancho' => 2.2, 'alinear' => 'derecha'],
                ['titulo' => 'Dado', 'ancho' => 2.2, 'alinear' => 'derecha'],
                ['titulo' => 'Diferencia', 'ancho' => 2.2, 'alinear' => 'derecha'],
                ['titulo' => 'Estado', 'ancho' => 1.6, 'alinear' => 'centro'],
            ], $filas, [
                'Total', '',
                Dinero::formato($datos['total_esperado'], 2),
                Dinero::formato($datos['dado_en_compromisos'], 2),
                Dinero::formato($datos['diferencia'], 2, true),
                '',
            ]);
        }

        if ($datos['categorias'] !== []) {
            $doc->tituloSeccion('Contribuciones por mes', DocumentoPdf::AZUL_OSCURO, 40);
            $columnas = [['titulo' => 'Mes', 'ancho' => 1.6]];
            foreach ($datos['categorias'] as $nombre) {
                $columnas[] = ['titulo' => $nombre, 'ancho' => 1.5, 'alinear' => 'derecha'];
            }
            $columnas[] = ['titulo' => 'Total', 'ancho' => 1.7, 'alinear' => 'derecha'];

            $filas = [];
            foreach ($datos['meses'] as $mes) {
                $fila = [['texto' => $mes['nombre'], 'negrita' => true]];
                foreach (array_keys($datos['categorias']) as $slug) {
                    $fila[] = $mes['por_categoria'][$slug] > 0 ? Dinero::formato($mes['por_categoria'][$slug], 2) : '-';
                }
                $fila[] = ['texto' => $mes['total'] > 0 ? Dinero::formato($mes['total'], 2) : '-', 'negrita' => true];
                $filas[] = $fila;
            }
            $total = ['TOTAL'];
            foreach (array_keys($datos['categorias']) as $slug) {
                $total[] = Dinero::formato($datos['totales_categoria'][$slug], 2);
            }
            $total[] = Dinero::formato($datos['total_dado'], 2);

            $doc->tabla($columnas, $filas, $total, tam: count($columnas) > 8 ? 7.0 : 8.0);
        } else {
            $doc->parrafo('No hay contribuciones registradas en el periodo.', 9, DocumentoPdf::GRIS);
        }

        $doc->nota(trim(
            'Este estado de cuenta resume lo registrado en el periodo indicado ('.$datos['periodo'].'). '
            .($datos['hay_usd'] ? 'Los sobres en dólares se muestran convertidos a colones con el tipo de cambio guardado en cada sobre. ' : '')
            .($datos['tiene_compromisos'] ? $lote->explicacionEsperado().' ' : '')
            .'Si nota alguna diferencia, comuníquese con la iglesia.'
        ));
    }
}
