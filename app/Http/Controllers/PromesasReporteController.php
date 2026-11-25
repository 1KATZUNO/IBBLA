<?php

namespace App\Http\Controllers;

use App\Models\ClaseAsistencia;
use App\Models\Persona;
use App\Services\Reportes\AuditoriaReportes;
use App\Services\Reportes\Dinero;
use App\Services\Reportes\FiltroPersonas;
use App\Services\Reportes\PeriodoInvalido;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Reportes\ResumenPromesas;
use App\Services\Reportes\ResumenPromesasService;
use App\Support\Pdf\DocumentoPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Reporte de promesas por categoria: pantalla (un mes o el anio), PDF del mes, PDF anual mes a mes y
 * reporte por clase.
 *
 * QE-008: todas las cifras salen del motor unico (ResumenPromesasService), con su regla:
 *  - Prometido = meses COMPLETOS antes de hoy. El mes en curso se muestra aparte, rotulado "en curso,
 *    todavía no se cobra", y no entra en el faltante ni en el excedente.
 *  - Dado = lo que dieron las personas con promesa en las categorias que prometieron (la base del
 *    "ha dado" del Cumplimiento). Ya no se suman los anonimos ni lo de quien no prometio en esa
 *    categoria: eso es ingreso, no cumplimiento de promesas (esta en el reporte de ingresos).
 *
 * HU-R14: los PDF usan la plantilla unica (DocumentoPdf): encabezado con iglesia, periodo y filtros,
 * totales y pie con la fecha de generacion.
 */
class PromesasReporteController extends Controller
{
    public function __construct(private readonly ResumenPromesasService $servicio) {}

    public function index(Request $request)
    {
        [$año, $mes, $categoria] = $this->entrada($request);

        $añoActual = (int) date('Y');
        $añosDisponibles = range($añoActual - 2, $añoActual);
        $categoriasDisponibles = tenant_categories(['excluir_de_promesas' => false]);

        $totales = $this->totales($this->resumen($año, $mes, $categoria));

        return view('ingresos-asistencia.promesas', compact('totales', 'añosDisponibles', 'año', 'mes', 'categoria', 'categoriasDisponibles'));
    }

    public function pdfPromesas(Request $request)
    {
        [$año, $mes, $categoria] = $this->entrada($request);
        $resumen = $this->resumen($año, $mes, $categoria);

        $doc = new DocumentoPdf('Reporte de promesas', $this->etiquetaPeriodo($año, $mes), $this->filtros($categoria));
        $doc->encabezado();
        $doc->nota($this->explicacion($resumen));
        $this->tarjetas($doc, $resumen);
        $this->tablaCategorias($doc, $resumen);
        $this->auditar('promesas', $resumen, $categoria);

        return $this->respuestaPdf($doc, 'reporte_promesas_'.$año.'_'.$mes.'.pdf');
    }

    public function pdfAnual(Request $request)
    {
        [$año, , $categoria] = $this->entrada($request);
        $resumen = $this->resumen($año, 'todos', $categoria);

        $doc = new DocumentoPdf('Reporte anual de promesas', 'Año '.$año, $this->filtros($categoria));
        $doc->encabezado();
        $doc->nota($this->explicacion($resumen));
        $this->tarjetas($doc, $resumen);

        $doc->tituloSeccion('Mes a mes');
        $filas = [];
        foreach ($resumen->meses() as $m) {
            $prometido = $m['prometido'];
            $dado = $m['dado'];
            $nombre = $m['mes']->nombre.($m['en_curso'] ? ' (en curso, todavía no se cobra)' : '');
            $filas[] = [
                $nombre,
                Dinero::formato($prometido),
                Dinero::formato($dado),
                $m['en_curso'] ? '—' : Dinero::formato(max(0, $prometido - $dado)),
                $m['en_curso'] ? '—' : Dinero::formato(max(0, $dado - $prometido)),
            ];
        }
        $doc->tabla([
            ['titulo' => 'Mes', 'ancho' => 4.5],
            ['titulo' => 'Prometido', 'ancho' => 2.5, 'alinear' => 'derecha'],
            ['titulo' => 'Dado', 'ancho' => 2.5, 'alinear' => 'derecha'],
            ['titulo' => 'Faltante', 'ancho' => 2.5, 'alinear' => 'derecha'],
            ['titulo' => 'Excedente', 'ancho' => 2.5, 'alinear' => 'derecha'],
        ], $filas, [
            'Total (meses completos)',
            Dinero::formato($resumen->prometido()),
            Dinero::formato($resumen->dado()),
            Dinero::formato($resumen->faltante()),
            Dinero::formato($resumen->excedente()),
        ]);
        $doc->nota('El total de faltante y excedente se calcula por categoría sobre los meses completos; lo dado incluye el mes en curso.');

        $this->tablaCategorias($doc, $resumen);
        $this->auditar('promesas-anual', $resumen, $categoria);

        return $this->respuestaPdf($doc, 'reporte_promesas_anual_'.$año.'.pdf');
    }

    public function porClase(Request $request)
    {
        [$año, $mes] = $this->entrada($request);
        $claseId = $request->get('clase_id', null);

        $añoActual = (int) date('Y');
        $añosDisponibles = range($añoActual - 2, $añoActual);
        $clasesDisponibles = ClaseAsistencia::activas()->ordenadas()->get();
        $categoriasDisponibles = tenant_categories(['excluir_de_promesas' => false]);

        $totales = null;
        $claseNombre = 'Capilla (Adultos)';

        if ($claseId !== null) {
            if ($claseId === 'capilla') {
                // Capilla = personas sin clase asignada (via pivot)
                $personaIds = Persona::where('activo', true)->whereDoesntHave('clasesAsistencia')->pluck('id')->all();
            } else {
                $clase = ClaseAsistencia::find($claseId);
                $personaIds = $clase ? $clase->personas()->where('activo', true)->pluck('personas.id')->all() : [];
                $claseNombre = $clase?->nombre ?? $claseNombre;
            }

            $totales = $this->totales($this->resumen($año, $mes, null, ResumenPromesasService::personasDe($personaIds)));
            $totales['total_personas'] = count($personaIds);
        }

        return view('ingresos-asistencia.promesas-por-clase', compact(
            'totales', 'añosDisponibles', 'año', 'mes', 'claseId',
            'clasesDisponibles', 'categoriasDisponibles', 'claseNombre'
        ));
    }

    // ------------------------------------------------------------------

    /** @return array{0: int, 1: int|string, 2: ?string} anio, mes (1..12 o 'todos') y categoria (slug o null) */
    private function entrada(Request $request): array
    {
        $año = (int) $request->get('año', date('Y'));
        if ($año < 2000 || $año > 2100) {
            $año = (int) date('Y');
        }
        $mes = $request->get('mes', date('m'));
        $mes = $mes === 'todos' ? 'todos' : (ctype_digit((string) $mes) && (int) $mes >= 1 && (int) $mes <= 12 ? (int) $mes : (int) date('m'));
        $categoria = $request->get('categoria');

        return [$año, $mes, is_string($categoria) && $categoria !== '' ? $categoria : null];
    }

    private function resumen(int $año, int|string $mes, ?string $categoria, ?iterable $personas = null): ResumenPromesas
    {
        try {
            $periodo = $mes === 'todos' ? PeriodoReporte::deMeses($año, 1, 12) : PeriodoReporte::deMeses($año, (int) $mes, (int) $mes);

            return $this->servicio->generar($periodo, $personas, $categoria);
        } catch (PeriodoInvalido) {
            return ResumenPromesas::vacio(PeriodoReporte::deMeses((int) date('Y'), 1, 1));
        }
    }

    /** Totales con la forma que esperan las vistas de siempre, mas el mes en curso aparte. */
    private function totales(ResumenPromesas $resumen): array
    {
        return [
            'categorias' => $resumen->categorias(),
            'grand_total' => [
                'prometido' => $resumen->prometido(),
                'dado' => $resumen->dado(),
                'faltante' => $resumen->faltante(),
                'profit' => $resumen->excedente(),
                'en_curso' => $resumen->prometidoEnCurso(),
                'dado_en_curso' => $resumen->dadoEnCurso(),
            ],
            'en_curso' => $resumen->rotuloEnCurso(),
            'explicacion' => $this->explicacion($resumen),
        ];
    }

    private function explicacion(ResumenPromesas $resumen): string
    {
        $cobrados = $resumen->periodo->textoMesesCobrables();
        $texto = $cobrados === ''
            ? 'Prometido = nada todavía: no hay meses completos en el periodo.'
            : 'Prometido = lo prometido de '.$cobrados.' (meses completos).';
        if ($resumen->mesEnCurso()) {
            $texto .= ' '.$resumen->mesEnCurso()->nombre.' está en curso y todavía no se cobra: lo prometido de ese mes ('
                .Dinero::formato($resumen->prometidoEnCurso()).') va aparte y no entra en el faltante.';
        }

        return $texto.' Dado = lo que dieron las personas con promesa en las categorías que prometieron, hasta hoy (lo mismo que «Ha dado» en Cumplimiento de promesas).';
    }

    private function tarjetas(DocumentoPdf $doc, ResumenPromesas $resumen): void
    {
        $tarjetas = [
            ['etiqueta' => 'Prometido (meses completos)', 'valor' => Dinero::formato($resumen->prometido())],
            ['etiqueta' => 'Dado', 'valor' => Dinero::formato($resumen->dado())],
            ['etiqueta' => 'Faltante', 'valor' => Dinero::formato($resumen->faltante()), 'color' => DocumentoPdf::ROJO],
            ['etiqueta' => 'Excedente', 'valor' => Dinero::formato($resumen->excedente()), 'color' => DocumentoPdf::VERDE],
        ];
        if ($resumen->mesEnCurso()) {
            $tarjetas[] = ['etiqueta' => $resumen->mesEnCurso()->nombre.' en curso (no se cobra)', 'valor' => Dinero::formato($resumen->prometidoEnCurso())];
        }
        $doc->tarjetas($tarjetas);
    }

    private function tablaCategorias(DocumentoPdf $doc, ResumenPromesas $resumen): void
    {
        $doc->tituloSeccion('Por categoría');
        $categorias = $resumen->categorias();
        if ($categorias === []) {
            $doc->parrafo('No hay promesas ni aportes en este periodo.', 10, DocumentoPdf::GRIS);

            return;
        }

        $conCurso = $resumen->mesEnCurso() !== null;
        $columnas = [
            ['titulo' => 'Categoría', 'ancho' => 4],
            ['titulo' => 'Prometido', 'ancho' => 2.5, 'alinear' => 'derecha'],
            ['titulo' => 'Dado', 'ancho' => 2.5, 'alinear' => 'derecha'],
            ['titulo' => 'Faltante', 'ancho' => 2.5, 'alinear' => 'derecha'],
            ['titulo' => 'Excedente', 'ancho' => 2.5, 'alinear' => 'derecha'],
        ];
        if ($conCurso) {
            $columnas[] = ['titulo' => 'En curso (no se cobra)', 'ancho' => 2.8, 'alinear' => 'derecha'];
        }

        $filas = [];
        foreach ($categorias as $c) {
            $fila = [$c['categoria'], Dinero::formato($c['total_prometido']), Dinero::formato($c['total_dado']), Dinero::formato($c['faltante']), Dinero::formato($c['profit'])];
            if ($conCurso) {
                $fila[] = Dinero::formato($c['en_curso']);
            }
            $filas[] = $fila;
        }
        $total = ['TOTAL', Dinero::formato($resumen->prometido()), Dinero::formato($resumen->dado()), Dinero::formato($resumen->faltante()), Dinero::formato($resumen->excedente())];
        if ($conCurso) {
            $total[] = Dinero::formato($resumen->prometidoEnCurso());
        }
        $doc->tabla($columnas, $filas, $total);
    }

    /** @return list<string> */
    private function filtros(?string $categoria): array
    {
        if ($categoria === null) {
            return ['Personas activas con promesa'];
        }
        $nombre = tenant_categories()->firstWhere('slug', $categoria)?->nombre ?? $categoria;

        return ['Personas activas con promesa', 'Categoría: '.$nombre];
    }

    private function etiquetaPeriodo(int $año, int|string $mes): string
    {
        return $mes === 'todos' ? 'Año '.$año : ucfirst(Carbon::create($año, (int) $mes, 1)->locale('es')->translatedFormat('F Y'));
    }

    private function auditar(string $reporte, ResumenPromesas $resumen, ?string $categoria): void
    {
        app(AuditoriaReportes::class)->registrar($reporte, 'pdf', $resumen->periodo, FiltroPersonas::desde(['grupo' => FiltroPersonas::CON_PROMESA]), $resumen->cantidadPersonas(), ['categoria' => $categoria]);
    }

    private function respuestaPdf(DocumentoPdf $doc, string $nombre)
    {
        $contenido = $doc->salida();

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $nombre),
            'Content-Length' => strlen($contenido),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
