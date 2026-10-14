<?php

namespace App\Services\Buscador;

use App\Models\Culto;
use App\Models\Persona;
use App\Models\User;
use App\Support\Iconos;
use App\Support\Manual\Manual;
use App\Support\Navegacion\Menu;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Buscador global de la barra superior: busca por palabras en personas, cultos, pantallas,
 * reportes y temas del manual, y devuelve los resultados agrupados.
 *
 * Respeta el rol: solo aparece lo que el usuario puede abrir.
 *  - Personas: quien administra (la ficha es de administracion).
 *  - Cultos: quien maneja recuento (abre el recuento) o asistencia (abre la asistencia).
 *  - Pantallas y reportes: las mismas del menu (App\Support\Navegacion\Menu).
 *  - Manual: todos.
 *
 * Cada resultado trae: titulo, detalle, url e icono (nombre de App\Support\Iconos).
 */
class BuscadorGlobal
{
    public const MINIMO = 2;

    private const MESES = [
        'enero' => 1, 'ene' => 1, 'febrero' => 2, 'feb' => 2, 'marzo' => 3, 'mar' => 3, 'abril' => 4, 'abr' => 4,
        'mayo' => 5, 'may' => 5, 'junio' => 6, 'jun' => 6, 'julio' => 7, 'jul' => 7, 'agosto' => 8, 'ago' => 8,
        'septiembre' => 9, 'setiembre' => 9, 'sept' => 9, 'sep' => 9, 'set' => 9, 'octubre' => 10, 'oct' => 10,
        'noviembre' => 11, 'nov' => 11, 'diciembre' => 12, 'dic' => 12,
    ];

    private const DIAS = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];

    /**
     * @return array{q: string, total: int, grupos: list<array{clave: string, titulo: string, items: list<array<string, string>>}>, ver_todo: string}
     */
    public function buscar(User $usuario, string $consulta, int $porGrupo = 6): array
    {
        $q = trim(preg_replace('/\s+/u', ' ', $consulta) ?? $consulta);
        $q = mb_substr($q, 0, 100);
        $grupos = [];

        if (mb_strlen($q) >= self::MINIMO) {
            $candidatos = [
                ['personas', 'Personas', $this->personas($usuario, $q, $porGrupo)],
                ['cultos', 'Cultos', $this->cultos($usuario, $q, $porGrupo)],
                ['pantallas', 'Pantallas', $this->pantallas($usuario, $q, $porGrupo, 'pantalla')],
                ['reportes', 'Reportes', $this->pantallas($usuario, $q, $porGrupo, 'reporte')],
                ['manual', 'Manual de usuario', $this->manual($q, $porGrupo)],
            ];

            foreach ($candidatos as [$clave, $titulo, $items]) {
                if ($items !== []) {
                    // El trazo del icono viaja con cada resultado: la barra lo dibuja sin llevar un catalogo en la pagina
                    $items = array_map(fn (array $it) => $it + ['trazo' => Iconos::trazo($it['icono'])], $items);
                    $grupos[] = ['clave' => $clave, 'titulo' => $titulo, 'items' => $items];
                }
            }
        }

        return [
            'q' => $q,
            'total' => array_sum(array_map(fn ($g) => count($g['items']), $grupos)),
            'grupos' => $grupos,
            'ver_todo' => route('buscar', ['q' => $q]),
        ];
    }

    /** @return list<array<string, string>> */
    private function personas(User $u, string $q, int $limite): array
    {
        // La ficha de la persona es de administracion; el super admin no tiene iglesia: no se mezclan iglesias
        if (! $u->tenant_id || ! $u->canAccessAdmin()) {
            return [];
        }

        // "12/10" es una fecha de culto, no un telefono ni un PIN
        if (preg_match('#\d\s*/\s*\d#', $q)) {
            return [];
        }

        $tokens = Manual::tokens($q);
        if ($tokens === []) {
            return [];
        }

        $personas = Persona::query()
            ->where(function ($consulta) use ($tokens) {
                foreach ($tokens as $t) {
                    $consulta->where(function ($palabra) use ($t) {
                        $palabra->where('nombre', 'like', "%{$t}%")
                            ->orWhere('pin', 'like', "%{$t}%")
                            ->orWhere('telefono', 'like', "%{$t}%");
                    });
                }
            })
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->limit($limite)
            ->get(['id', 'nombre', 'pin', 'telefono', 'activo']);

        return $personas->map(function (Persona $p) {
            $partes = [];
            if ($p->pin) {
                $partes[] = 'PIN '.$p->pin;
            }
            if ($p->telefono) {
                $partes[] = $p->telefono;
            }
            $partes[] = $p->activo ? 'Activa' : 'Inactiva';

            return [
                'titulo' => $p->nombre,
                'detalle' => implode(' · ', $partes),
                'url' => route('personas.show', $p),
                'icono' => 'personas',
            ];
        })->values()->all();
    }

    /** @return list<array<string, string>> */
    private function cultos(User $u, string $q, int $limite): array
    {
        if (! $u->tenant_id) {
            return [];
        }

        $puedeRecuento = $u->canAccessRecuento();
        $puedeAsistencia = $u->canAccessAsistencia();
        if (! $puedeRecuento && ! $puedeAsistencia) {
            return [];
        }

        $f = $this->condicionesFecha($q);
        if (! $f['hay'] && $f['resto'] === []) {
            return [];
        }

        $consulta = Culto::query()->with(['serviceType', 'asistencia'])->withCount('sobres');

        if ($f['anio']) {
            $consulta->whereYear('fecha', $f['anio']);
        }
        if ($f['mes']) {
            $consulta->whereMonth('fecha', $f['mes']);
        }
        if ($f['dia']) {
            $consulta->whereDay('fecha', $f['dia']);
        }
        if ($f['exacta']) {
            $consulta->whereDate('fecha', $f['exacta']);
        }

        // Palabras que no son fecha ("domingo", "miercoles", "especial"): tipo de culto o notas
        foreach ($f['resto'] as $palabra) {
            $consulta->where(function ($w) use ($palabra) {
                $w->where('tipo_culto', 'like', "%{$palabra}%")
                    ->orWhere('notas', 'like', "%{$palabra}%")
                    ->orWhereHas('serviceType', fn ($s) => $s->where('nombre', 'like', "%{$palabra}%"));
            });
        }

        $items = [];
        foreach ($consulta->orderByDesc('fecha')->limit($limite)->get() as $culto) {
            $url = $this->urlCulto($u, $culto);
            if ($url === null) {
                continue;
            }

            $fecha = Carbon::parse($culto->fecha);
            $dia = ucfirst($fecha->locale('es')->isoFormat('dddd'));
            $estado = $culto->cerrado ? 'Cerrado' : 'Abierto';
            $detalle = $estado;
            if ($puedeRecuento) {
                $detalle .= ' · '.$culto->sobres_count.' '.($culto->sobres_count === 1 ? 'sobre' : 'sobres');
            }

            $items[] = [
                'titulo' => $dia.' '.$fecha->format('d/m/Y').' · '.$culto->tipo_nombre,
                'detalle' => $detalle,
                'url' => $url,
                'icono' => 'calendario',
            ];
        }

        return $items;
    }

    /** Donde se abre un culto segun el rol, o null si el usuario no puede abrirlo. */
    private function urlCulto(User $u, Culto $culto): ?string
    {
        if ($u->canAccessRecuento()) {
            return $culto->cerrado && Route::has('recuento.ver-culto-cerrado')
                ? route('recuento.ver-culto-cerrado', $culto)
                : route('recuento.index', ['culto_id' => $culto->id]);
        }

        if ($u->canAccessAsistencia()) {
            return $culto->asistencia
                ? route('asistencia.show', $culto->asistencia)
                : route('asistencia.create', ['culto_id' => $culto->id]);
        }

        return null;
    }

    /**
     * Pantallas o reportes del menu (y algunos extras) que coinciden con las palabras.
     *
     * @return list<array<string, string>>
     */
    private function pantallas(User $u, string $q, int $limite, string $tipo): array
    {
        $tokens = Manual::tokens($q);
        if ($tokens === []) {
            return [];
        }

        $hallazgos = [];
        foreach (Menu::pantallas($u) as $p) {
            if ($p['tipo'] !== $tipo) {
                continue;
            }

            $titulo = Manual::normalizar($p['titulo']);
            $resto = Manual::normalizar($p['claves'].' '.$p['grupo']);
            $puntos = 0;
            foreach ($tokens as $t) {
                if (Manual::contiene($titulo, $t)) {
                    $puntos += 8;
                } elseif (Manual::contiene($resto, $t)) {
                    $puntos += 2;
                } else {
                    continue 2;
                }
            }

            $hallazgos[] = [
                'puntos' => $puntos,
                'titulo' => $p['titulo'],
                'detalle' => $p['grupo'],
                'url' => $p['url'],
                'icono' => $p['icono'],
            ];
        }

        usort($hallazgos, fn ($a, $b) => [$b['puntos'], $a['titulo']] <=> [$a['puntos'], $b['titulo']]);

        return array_map(fn ($h) => Arr::except($h, 'puntos'), array_slice($hallazgos, 0, $limite));
    }

    /** @return list<array<string, string>> */
    private function manual(string $q, int $limite): array
    {
        return array_map(fn ($h) => [
            'titulo' => $h['titulo'],
            'detalle' => $h['detalle'],
            'url' => $h['url'],
            'icono' => $h['icono'],
        ], Manual::buscar($q, $limite));
    }

    /**
     * Fecha(s) que pide la consulta: "12/10", "12/10/2026", "2026-10-12", "octubre", "oct 2026",
     * "12 de octubre", "hoy", "ayer", "domingo". Lo que no es fecha queda en "resto".
     *
     * @return array{hay: bool, dia: ?int, mes: ?int, anio: ?int, exacta: ?string, resto: list<string>}
     */
    private function condicionesFecha(string $q): array
    {
        $t = ' '.Manual::normalizar($q).' ';
        $dia = $mes = $anio = null;
        $exacta = null;

        if (preg_match('/(?<!\d)((?:19|20)\d{2})[\/\-.](\d{1,2})[\/\-.](\d{1,2})(?!\d)/', $t, $m)) {
            [$anio, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            $t = str_replace($m[0], ' ', $t);
        } elseif (preg_match('/(?<!\d)(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4}|\d{2})(?!\d)/', $t, $m)) {
            [$dia, $mes, $anio] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            $anio = $anio < 100 ? 2000 + $anio : $anio;
            $t = str_replace($m[0], ' ', $t);
        } elseif (preg_match('/(?<!\d)(\d{1,2})[\/\-.](\d{1,2})(?![\d\/\-.])/', $t, $m)) {
            [$dia, $mes] = [(int) $m[1], (int) $m[2]];
            $t = str_replace($m[0], ' ', $t);
        }

        if (preg_match('/\b(hoy|ayer|manana)\b/', $t, $m)) {
            $exacta = match ($m[1]) {
                'hoy' => now()->toDateString(),
                'ayer' => now()->subDay()->toDateString(),
                default => now()->addDay()->toDateString(),
            };
            $t = str_replace($m[0], ' ', $t);
        }

        foreach (self::MESES as $nombre => $numero) {
            if (preg_match('/\b'.$nombre.'\b/', $t)) {
                $mes ??= $numero;
                $t = preg_replace('/\b'.$nombre.'\b/', ' ', $t) ?? $t;
            }
        }

        if ($anio === null && preg_match('/(?<!\d)((?:19|20)\d{2})(?!\d)/', $t, $m)) {
            $anio = (int) $m[1];
            $t = str_replace($m[0], ' ', $t);
        }

        if ($dia === null && preg_match('/(?<![\d\/])([0-3]?\d)(?![\d\/])/', $t, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 31) {
            $dia = (int) $m[1];
            $t = preg_replace('/(?<![\d\/])'.preg_quote($m[1], '/').'(?![\d\/])/', ' ', $t, 1) ?? $t;
        }

        if ($mes !== null && ($mes < 1 || $mes > 12)) {
            $mes = null;
        }
        if ($dia !== null && ($dia < 1 || $dia > 31)) {
            $dia = null;
        }

        // Lo que sobra: solo cuentan los dias de la semana y tipos de culto; "de", "culto"... no
        $resto = [];
        foreach (preg_split('/[^a-z0-9]+/', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
            if (in_array($palabra, ['de', 'del', 'la', 'el', 'culto', 'cultos', 'en', 'dia', 'el'], true) || strlen($palabra) < 3) {
                continue;
            }
            $resto[] = Str::of($palabra)->when(strlen($palabra) > 4 && str_ends_with($palabra, 's'), fn ($s) => $s->beforeLast('s'))->toString();
        }

        $hay = $dia !== null || $mes !== null || $anio !== null || $exacta !== null;
        // Sin fecha, solo se buscan cultos si lo que escribio es un dia de la semana o un tipo de culto
        if (! $hay) {
            $resto = array_values(array_filter($resto, fn ($p) => $this->esTipoDeCulto($p)));
        }

        return ['hay' => $hay, 'dia' => $dia, 'mes' => $mes, 'anio' => $anio, 'exacta' => $exacta, 'resto' => array_values($resto)];
    }

    private function esTipoDeCulto(string $palabra): bool
    {
        foreach (array_merge(self::DIAS, ['especial', 'domingo', 'miercoles', 'sabado']) as $dia) {
            if (str_starts_with($dia, $palabra) && strlen($palabra) >= 4 || $dia === $palabra) {
                return true;
            }
        }

        return false;
    }
}
