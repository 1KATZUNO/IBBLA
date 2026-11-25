<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reporte mensual por correo
    |--------------------------------------------------------------------------
    |
    | Lo envía `php artisan reporte:enviar-mensual` (el planificador lo corre el día 1 a las 08:00).
    | Estos valores se leen aquí y no con env() dentro del comando: con `php artisan config:cache`,
    | que es como queda producción después de cada deploy, env() devuelve vacío fuera de config/ y
    | el comando abortaba por «sin destinatarios» sin enviar nada.
    |
    | REPORTE_MENSUAL_TO: correos separados por coma. Vacío = no se envía.
    | REPORTE_MENSUAL_TENANT_ID: id de la iglesia del reporte.
    |
    */

    'mensual' => [
        'para' => (string) env('REPORTE_MENSUAL_TO', ''),
        'iglesia_id' => (int) env('REPORTE_MENSUAL_TENANT_ID', 7),
    ],

];
