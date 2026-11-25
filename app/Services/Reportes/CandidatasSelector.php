<?php

namespace App\Services\Reportes;

use App\Models\Ministerio;
use App\Models\Persona;
use Illuminate\Support\Facades\DB;

/**
 * Datos del selector de personas de los reportes (HU-R2): todas las personas de la iglesia con lo
 * necesario para filtrar en el navegador sin ir al servidor (activa, ministerios en que sirve hoy,
 * si tiene promesa, si aporto en el periodo) y buscar por nombre, PIN o telefono. Las mismas reglas
 * que FiltroPersonas::consulta(), que es la que manda al generar el reporte.
 *
 * Cuatro consultas en total, sin modelos por fila: con 150 personas son milisegundos.
 */
final class CandidatasSelector
{
    /**
     * @return array{personas: list<array<string, mixed>>, ministerios: list<array{id: int, nombre: string}>}
     */
    public function datos(PeriodoReporte $periodo): array
    {
        $personas = Persona::query()->orderBy('nombre')->orderBy('id')->get(['id', 'nombre', 'activo', 'pin', 'telefono']);
        $ids = $personas->pluck('id')->all();

        $ministerios = [];
        $conPromesa = [];
        $aportaron = [];
        if ($ids !== []) {
            foreach (DB::table('persona_ministerio')->whereIn('persona_id', $ids)->whereNull('hasta')->get(['persona_id', 'ministerio_id']) as $fila) {
                $ministerios[(int) $fila->persona_id][] = (int) $fila->ministerio_id;
            }

            $categorias = tenant_categories(['excluir_de_promesas' => false])->pluck('slug')->all();
            // "Con promesa" = tuvo alguna promesa que rigio en algun mes del periodo (promesas futuras:
            // antes de un corte activado cuentan las del historial). Sin cortes, las de hoy, como siempre.
            $cortes = tenant() ? app(PromesasPorPeriodo::class)->conPromesaEnPeriodo(tenant()->id, $periodo, $categorias) : null;
            $actuales = $cortes === null || $cortes['actuales'];
            $conPromesa = $actuales ? array_flip(DB::table('promesas')->whereIn('persona_id', $ids)->whereIn('categoria', $categorias)
                ->where('monto', '>', 0)->distinct()->pluck('persona_id')->map(fn ($id) => (int) $id)->all()) : [];
            if ($cortes !== null) {
                foreach ($cortes['historial']->distinct()->pluck('persona_id') as $id) {
                    $conPromesa[(int) $id] = true;
                }
            }

            $recortado = $periodo->recortadoAHoy();
            $aportaron = array_flip(DB::table('sobres as s')
                ->join('cultos as c', 'c.id', '=', 's.culto_id')
                ->join('sobre_detalles as d', 'd.sobre_id', '=', 's.id')
                ->whereIn('s.persona_id', $ids)
                ->where('d.monto', '>', 0)
                ->whereBetween('c.fecha', [$recortado->desde->toDateString(), $recortado->hasta->toDateString()])
                ->distinct()->pluck('s.persona_id')->map(fn ($id) => (int) $id)->all());
        }

        return [
            'personas' => $personas->map(fn (Persona $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'activo' => (bool) $p->activo,
                'pin' => (string) ($p->pin ?? ''),
                'telefono' => (string) ($p->telefono ?? ''),
                'ministerios' => $ministerios[$p->id] ?? [],
                'promesa' => isset($conPromesa[$p->id]),
                'aporto' => isset($aportaron[$p->id]),
            ])->values()->all(),
            'ministerios' => Ministerio::where('activo', true)->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn ($m) => ['id' => (int) $m->id, 'nombre' => $m->nombre])->values()->all(),
        ];
    }
}
