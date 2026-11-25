<?php

namespace App\Services\Reportes\Cultos;

use App\Models\Culto;
use App\Support\ResumenCulto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Arma el reporte de cultos (HU-R10) y la base del de ingresos (HU-R11). No calcula nada propio:
 * cada culto pasa por ResumenCulto, la misma regla del encabezado del culto y del PDF de recuento
 * (USD al tipo de cambio del registro; si no lo tiene, al de la fecha del culto).
 *
 * Rendimiento: una consulta por tabla para todo el rango (filas simples, sin modelos) y sin las imagenes de las
 * firmas (pesan mucho; si firmaron se sabe con una expresion SQL). Un anio de cultos: ~0,6 s.
 */
class GeneradorReporteCultos
{
    /** Columnas del culto que hacen falta (sin las imagenes de las firmas). */
    private const COLUMNAS = ['id', 'tenant_id', 'fecha', 'hora', 'tipo_culto', 'service_type_id', 'cerrado', 'cerrado_at', 'tipo_cambio_venta'];

    public function generar(FiltroCultos $filtro, ?Collection $categorias = null): ReporteCultos
    {
        $categorias ??= tenant_categories(['es_ofrenda_suelta' => false]);
        $cultos = $this->consulta($filtro)->with('serviceType')->get();
        $ids = $cultos->modelKeys();

        // Sobres, detalles, sueltos y egresos como filas simples (sin modelos): con un anio de cultos
        // (~2.500 sobres) crear los modelos era lo mas lento. ResumenCulto solo lee sus campos.
        $sobres = $this->filas('sobres', $ids, ['id', 'culto_id', 'numero_sobre', 'metodo_pago', 'total_declarado', 'moneda', 'tipo_cambio_venta'], 'numero_sobre');
        $detalles = DB::table('sobre_detalles')->whereIn('sobre_id', $sobres->flatten(1)->pluck('id')->all() ?: [0])
            ->get(['sobre_id', 'categoria', 'monto'])->groupBy('sobre_id');
        $sueltos = $this->filas('ofrenda_suelta', $ids, ['culto_id', 'monto', 'moneda', 'tipo_cambio_venta']);
        $egresos = $this->filas('egresos', $ids, ['culto_id', 'monto', 'moneda', 'tipo_cambio_venta']);

        $filas = [];
        foreach ($cultos as $culto) {
            // ResumenCulto busca el tipo de cambio de respaldo en $registro->culto: se le da el ya cargado
            $conCulto = function ($registro) use ($culto) {
                $registro->culto = $culto;

                return $registro;
            };
            $sobresDelCulto = ($sobres->get($culto->id) ?? collect())->map(function ($sobre) use ($conCulto, $detalles) {
                $sobre->detalles = $detalles->get($sobre->id) ?? collect();

                return $conCulto($sobre);
            });
            $resumen = ResumenCulto::calcular(
                $sobresDelCulto,
                ($sueltos->get($culto->id) ?? collect())->map($conCulto),
                ($egresos->get($culto->id) ?? collect())->map($conCulto),
                $categorias,
            );
            $filas[] = FilaCulto::desde($culto, $resumen, (bool) $culto->firmas_completas);
        }

        return new ReporteCultos($filtro, $filas, $categorias);
    }

    /**
     * Filas de una tabla hija de cultos, agrupadas por culto_id.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $columnas
     * @return Collection<int, Collection<int, object>>
     */
    private function filas(string $tabla, array $ids, array $columnas, ?string $orden = null): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return DB::table($tabla)->whereIn('culto_id', $ids)
            ->when($orden, fn ($q) => $q->orderBy($orden))
            ->orderBy('id')
            ->get($columnas)
            ->groupBy('culto_id');
    }

    /**
     * Cultos del rango (y tipos) para el selector "Elegir cultos": fecha, tipo y estado, sin cifras.
     *
     * @return Collection<int, Culto>
     */
    public function cultosDelRango(FiltroCultos $filtro): Collection
    {
        return $this->consulta(new FiltroCultos($filtro->rango, $filtro->tipos))->with('serviceType')->get();
    }

    private function consulta(FiltroCultos $filtro): Builder
    {
        // Firmas completas = el pastor y al menos un tesorero con la firma dibujada
        $firmas = "(firma_pastor_imagen IS NOT NULL AND firma_pastor_imagen <> '' AND firmas_tesoreros_imagenes IS NOT NULL"
            ." AND JSON_SEARCH(firmas_tesoreros_imagenes, 'one', '_%', NULL, '$[*].imagen') IS NOT NULL) AS firmas_completas";

        return Culto::query()
            ->select(self::COLUMNAS)
            ->selectRaw($firmas)
            ->whereBetween('fecha', [$filtro->rango->inicio->toDateString(), $filtro->rango->fin->toDateString()])
            ->when($filtro->tipos !== [], fn (Builder $q) => $q->where(function (Builder $w) use ($filtro) {
                $w->whereHas('serviceType', fn (Builder $s) => $s->whereIn('slug', $filtro->tipos))
                    // Cultos viejos sin tipo por iglesia: se comparan por la columna tipo_culto
                    ->orWhere(fn (Builder $l) => $l->whereNull('service_type_id')->whereIn('tipo_culto', $filtro->tipos));
            }))
            ->when($filtro->cultos !== [], fn (Builder $q) => $q->whereIn('id', $filtro->cultos))
            ->orderBy('fecha')
            ->orderBy('hora');
    }
}
