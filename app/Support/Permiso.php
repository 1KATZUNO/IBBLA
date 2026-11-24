<?php

namespace App\Support;

use Illuminate\Auth\Access\Response;

/**
 * Regla unica de la app para lo que un usuario no puede hacer (ver docs/ROLES.md):
 *
 *  - Si su ROL nunca lo puede hacer (p. ej. un tesorero no gestiona usuarios), el boton o campo
 *    NO SE MUESTRA: no tiene sentido ensenar algo que nunca va a estar disponible.
 *  - Si su rol si lo puede hacer pero AHORA no (el culto esta cerrado, le falta un acceso de
 *    edicion vigente...), se muestra DESHABILITADO, en gris, con el motivo en un globo de ayuda.
 *
 * Las policies devuelven estas respuestas y el componente <x-permiso> las pinta. El backend
 * responde 403 con el mismo motivo si alguien intenta la accion igual.
 */
final class Permiso
{
    /** Codigo de la respuesta cuando el rol nunca puede: la accion se oculta. */
    public const OCULTAR = 'ocultar';

    /** Codigo de la respuesta cuando el rol puede pero no ahora: la accion se deshabilita con su motivo. */
    public const DESHABILITAR = 'deshabilitar';

    public static function permitir(): Response
    {
        return Response::allow();
    }

    public static function ocultar(string $motivo): Response
    {
        return Response::deny($motivo, self::OCULTAR);
    }

    public static function deshabilitar(string $motivo): Response
    {
        return Response::deny($motivo, self::DESHABILITAR);
    }

    /** true si la respuesta pide ocultar la accion (negada por el rol). */
    public static function seOculta(Response $respuesta): bool
    {
        return $respuesta->denied() && $respuesta->code() === self::OCULTAR;
    }
}
