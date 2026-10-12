<?php

namespace App\Services\WhatsApp;

use App\Models\Culto;
use App\Models\Persona;
use App\Models\Tenant;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Texto de los mensajes de WhatsApp. Las plantillas viven en config/whatsapp.php (con
 * {variables}) para poder cambiar el tono sin tocar código; aquí solo se rellenan.
 */
class MensajesWhatsApp
{
    /**
     * Nombre de la iglesia que aparece en el mensaje: el mismo que usan los PDF
     * (tenant_pdf_data(): nombre de la iglesia actual, o el genérico si no hay).
     * Fuera de una petición (cola, consola) no hay iglesia actual: se pasa la del registro.
     */
    public static function nombreIglesia(?Tenant $iglesia = null): string
    {
        $iglesia ??= tenant();

        return $iglesia?->nombre ?? 'Sistema de Administracion';
    }

    /** «18 de octubre de 2026». */
    public static function fechaLarga(DateTimeInterface $fecha): string
    {
        return Carbon::instance($fecha)->translatedFormat('j \d\e F \d\e Y');
    }

    /** Mensaje que acompaña el enlace firmado al informe de una persona. */
    public static function informe(Persona $persona, string $enlace, DateTimeInterface $vence, ?Tenant $iglesia = null): string
    {
        return WhatsAppService::armarMensaje((string) config('whatsapp.informe.plantilla'), [
            'nombre' => $persona->nombre,
            'iglesia' => self::nombreIglesia($iglesia),
            'enlace' => $enlace,
            'vence' => self::fechaLarga($vence),
        ]);
    }

    /** «domingo 18 de octubre de 2026». */
    public static function fechaCulto(Culto $culto): string
    {
        return Carbon::parse($culto->fecha)->translatedFormat('l j \d\e F \d\e Y');
    }

    /** Aviso al líder de que se creó un culto. */
    public static function cultoCreado(Culto $culto, string $nombre, ?string $enlace, ?Tenant $iglesia = null): string
    {
        return WhatsAppService::armarMensaje((string) config('whatsapp.avisos.plantillas.culto_creado'), [
            'nombre' => $nombre,
            'iglesia' => self::nombreIglesia($iglesia),
            'tipo' => $culto->tipo_nombre,
            'fecha' => self::fechaCulto($culto),
            'hora' => $culto->hora?->format('g:i a') ?? '',
            'enlace' => $enlace ?? '',
        ]);
    }

    public static function tituloCultoCreado(Culto $culto): string
    {
        return 'Culto creado: '.$culto->tipo_nombre.' del '.Carbon::parse($culto->fecha)->format('d/m/Y');
    }

    /** Aviso al líder de que un culto llegó a la cantidad de sobres configurada. */
    public static function sobresAlcanzados(Culto $culto, int $cantidad, string $nombre, ?string $enlace, ?Tenant $iglesia = null): string
    {
        return WhatsAppService::armarMensaje((string) config('whatsapp.avisos.plantillas.sobres_alcanzados'), [
            'nombre' => $nombre,
            'iglesia' => self::nombreIglesia($iglesia),
            'tipo' => $culto->tipo_nombre,
            'fecha' => self::fechaCulto($culto),
            'cantidad' => $cantidad,
            'enlace' => $enlace ?? '',
        ]);
    }

    public static function tituloSobresAlcanzados(Culto $culto, int $cantidad): string
    {
        return $cantidad.' sobres en '.$culto->tipo_nombre.' del '.Carbon::parse($culto->fecha)->format('d/m/Y');
    }
}
