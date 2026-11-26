<?php

namespace App\Services\Tarjetas;

use App\Models\Persona;
use App\Models\PromesaProgramada;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tarjetas con QR de acceso personal (docs/plan-oct-2026/07-TARJETAS-QR.md): que lleva cada tarjeta (nombre,
 * clase, direccion del QR), el estado de la cuenta de la persona y que personas entran en la descarga masiva.
 * Lo usan la tarjeta flotante de Personas, el PDF de una tarjeta y el PDF masivo, asi los tres muestran lo mismo.
 */
class TarjetasAcceso
{
    public const FILTRO_PROMESA = 'promesa';

    public const FILTRO_ACTIVAS = 'activas';

    public const FILTRO_CLASE = 'clase';

    public const FILTROS = [self::FILTRO_PROMESA, self::FILTRO_ACTIVAS, self::FILTRO_CLASE];

    /** La direccion que lleva el QR: /t/{token}, en el dominio por el que se entro (ibbla.eu en produccion). */
    public function url(Persona $persona): string
    {
        return route('acceso.tarjeta', ['token' => $persona->asegurarTokenAcceso()]);
    }

    /**
     * La clase que muestra la tarjeta: la de la persona como miembro antes que como visita, luego por el orden de
     * las clases; sin clase, null (la tarjeta no la muestra).
     */
    public function clasePrincipal(Persona $persona): ?string
    {
        return $this->clasesPrincipales([$persona->id])[$persona->id] ?? null;
    }

    /**
     * La clase principal de varias personas en una sola consulta.
     *
     * @param  array<int, int>  $personaIds
     * @return array<int, string> persona_id => nombre de la clase
     */
    public function clasesPrincipales(array $personaIds): array
    {
        if ($personaIds === []) {
            return [];
        }

        $filas = DB::table('clase_persona')
            ->join('clases_asistencia', 'clases_asistencia.id', '=', 'clase_persona.clase_asistencia_id')
            ->whereIn('clase_persona.persona_id', $personaIds)
            ->orderByDesc('clases_asistencia.activa')
            ->orderByRaw("CASE WHEN clase_persona.tipo = 'visita' THEN 1 ELSE 0 END")
            ->orderBy('clases_asistencia.orden')
            ->orderBy('clases_asistencia.nombre')
            ->get(['clase_persona.persona_id', 'clases_asistencia.nombre']);

        $clases = [];
        foreach ($filas as $fila) {
            $clases[(int) $fila->persona_id] ??= (string) $fila->nombre;
        }

        return $clases;
    }

    /**
     * Estado de la cuenta de la persona para la tarjeta flotante.
     *
     * @return array{tiene: bool, identificador: ?string, tipo: ?string, rol: ?string, esMiembro: bool, debeCrear: bool, hayCopia: bool, usuario: ?User}
     */
    public function estadoCuenta(Persona $persona): array
    {
        $usuario = $persona->user_id ? User::find($persona->user_id) : null;

        return [
            'tiene' => $usuario !== null,
            'identificador' => $usuario?->identificador(),
            'tipo' => $usuario ? ($usuario->email ? 'correo' : 'usuario') : null,
            'rol' => $usuario?->rol_nombre,
            'esMiembro' => $usuario?->esCuentaMiembro() ?? false,
            'debeCrear' => (bool) $usuario?->debe_crear_contrasena,
            'hayCopia' => $usuario?->contrasenaVisible() !== null,
            'usuario' => $usuario,
        ];
    }

    /**
     * Personas de la descarga masiva, ordenadas por clase y nombre, con su clase y su direccion.
     *  - promesa (por defecto): con promesa vigente hoy o programada en un corte de Promesas futuras que todavia
     *    no se activo, esten activas o no (la promesa manda).
     *  - activas: todas las personas activas.
     *  - clase: las personas activas de esa clase (la tarjeta muestra esa clase).
     *
     * @return Collection<int, array{id: int, nombre: string, clase: ?string, url: string}>
     */
    public function tarjetas(string $filtro, ?int $claseId = null, ?string $claseNombre = null): Collection
    {
        $personas = $this->consulta($filtro, $claseId)->get(['id', 'nombre', 'token_acceso']);
        $this->asegurarTokens($personas);

        $clases = $filtro === self::FILTRO_CLASE ? [] : $this->clasesPrincipales($personas->pluck('id')->all());

        return $personas
            ->map(fn (Persona $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'clase' => $filtro === self::FILTRO_CLASE ? $claseNombre : ($clases[$p->id] ?? null),
                'url' => route('acceso.tarjeta', ['token' => $p->token_acceso]),
            ])
            ->sortBy(fn (array $t) => [
                $t['clase'] === null ? 1 : 0,
                Str::lower(Str::ascii((string) $t['clase'])),
                Str::lower(Str::ascii($t['nombre'])),
            ])
            ->values();
    }

    /** Las personas que entran con cada filtro (ver tarjetas()); la pantalla de la descarga las cuenta con esto. */
    public function consulta(string $filtro, ?int $claseId = null): Builder
    {
        $consulta = Persona::query();

        return match ($filtro) {
            self::FILTRO_ACTIVAS => $consulta->where('activo', true),
            self::FILTRO_CLASE => $consulta->where('activo', true)
                ->whereHas('clasesAsistencia', fn ($q) => $q->where('clases_asistencia.id', $claseId)),
            default => $consulta->where(function ($q) {
                $q->whereHas('promesas')
                    ->orWhereIn('id', PromesaProgramada::query()->select('persona_id')
                        ->whereHas('corte', fn ($c) => $c->whereNull('activado_at')));
            }),
        };
    }

    /** Datos de una sola tarjeta (tarjeta flotante y PDF individual). */
    public function tarjeta(Persona $persona): array
    {
        return [
            'id' => $persona->id,
            'nombre' => $persona->nombre,
            'clase' => $this->clasePrincipal($persona),
            'url' => $this->url($persona),
        ];
    }

    /** A quien todavia no tiene QR se le genera ahora (la tarjeta nace la primera vez que se pide). */
    private function asegurarTokens(Collection $personas): void
    {
        $sinToken = $personas->filter(fn (Persona $p) => ! $p->token_acceso);
        if ($sinToken->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($sinToken) {
            foreach ($sinToken as $persona) {
                $persona->token_acceso = Persona::nuevoTokenAcceso();
                Persona::withoutGlobalScopes()->whereKey($persona->id)->update(['token_acceso' => $persona->token_acceso]);
            }
        });
    }

    /**
     * Marca de la iglesia para la tarjeta: siglas, nombre, colores y logo (el del PDF de la iglesia si lo tiene).
     *
     * @return array{siglas: string, nombre: string, color: string, colorOscuro: string, logoBase64: string}
     */
    public function marca(?Tenant $iglesia): array
    {
        $datos = tenant_pdf_data();
        $colores = $iglesia?->colors ?? [];

        return [
            'siglas' => $iglesia?->siglas ?: $datos['tenantSiglas'],
            'nombre' => $iglesia?->nombre ?: $datos['tenantNombre'],
            'color' => $this->hex($colores['600'] ?? null) ?? '#2563eb',
            'colorOscuro' => $this->hex($colores['800'] ?? null) ?? '#1e40af',
            'logoBase64' => $datos['tenantLogoBase64'],
        ];
    }

    private function hex(?string $color): ?string
    {
        $limpio = ltrim(trim((string) $color), '#');
        if (preg_match('/^[0-9a-f]{3}$/i', $limpio)) {
            $limpio = $limpio[0].$limpio[0].$limpio[1].$limpio[1].$limpio[2].$limpio[2];
        }

        return preg_match('/^[0-9a-f]{6}$/i', $limpio) ? '#'.strtolower($limpio) : null;
    }
}
