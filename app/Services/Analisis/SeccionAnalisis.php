<?php

namespace App\Services\Analisis;

/**
 * Resultado de una verificación: qué se revisó, cuántos casos encontró, las tablas con los casos y la
 * corrección que se propone. Una verificación puede traer varias tablas (subsecciones).
 *
 * Cada subsección es ['titulo' => string, 'columnas' => list<string>, 'filas' => list<list<mixed>>,
 * 'total_filas' => int, 'nota' => ?string]; en filas ya vienen solo las que caben en el límite.
 */
final class SeccionAnalisis
{
    public const OK = 'ok';

    /** Conviene revisarlo, pero no mueve dinero por sí solo. */
    public const AVISO = 'aviso';

    /** Cambia cifras que ve la gente (totales, estados de cuenta) o dinero que podría estar mal. */
    public const ALERTA = 'alerta';

    /**
     * @param  list<array<string, mixed>>  $subsecciones
     * @param  array<string, string>  $totales  concepto => valor ya formateado
     * @param  list<string>  $notas
     */
    public function __construct(
        public readonly string $id,
        public readonly string $titulo,
        public readonly string $que,
        public readonly int $revisados,
        public readonly int $hallazgos,
        public readonly string $severidad,
        public readonly array $subsecciones = [],
        public readonly array $totales = [],
        public readonly ?string $propuesta = null,
        public readonly array $notas = [],
    ) {
    }

    /** Estado para el resumen: «OK» si no hay hallazgos, y si no, su severidad. */
    public function estado(): string
    {
        return $this->hallazgos === 0 ? self::OK : $this->severidad;
    }

    /**
     * Arma una subsección recortando las filas al límite (0 = todas).
     *
     * @param  list<string>  $columnas
     * @param  list<list<mixed>>  $filas
     * @return array<string, mixed>
     */
    public static function tabla(string $titulo, array $columnas, array $filas, int $limite, ?string $nota = null): array
    {
        return [
            'titulo' => $titulo,
            'columnas' => $columnas,
            'filas' => $limite > 0 ? array_slice($filas, 0, $limite) : $filas,
            'total_filas' => count($filas),
            'nota' => $nota,
        ];
    }
}
