<?php

namespace App\Support\Manual;

/**
 * Una pagina del manual de usuario: un archivo Markdown en resources/views/manual/paginas.
 */
final class Pagina
{
    /**
     * @param  list<string>  $roles  claves de rol (todos, admin, tesorero, asistente, servidor, miembro, invitado)
     * @param  list<string>  $rutas  nombres de ruta (admite comodin *) cuya ayuda "?" abre esta pagina
     * @param  list<string>  $palabras  sinonimos para el buscador
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $titulo,
        public readonly int $orden,
        public readonly string $icono,
        public readonly string $resumen,
        public readonly array $roles,
        public readonly array $rutas,
        public readonly array $palabras,
        public readonly ?string $ancla,
        public readonly string $archivo,
        public readonly string $cuerpo,
    ) {}
}
