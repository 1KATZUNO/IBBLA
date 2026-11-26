<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tarjetas con QR de acceso personal
    |--------------------------------------------------------------------------
    |
    | contrasena_visible: el administrador puede ver la contrasena de las cuentas de rol miembro (pedido
    | expreso del cliente, para ayudar a quien la olvida). Se guarda una copia cifrada con la llave de la
    | app, nunca para administrador, tesorero, asistente, servidor ni super admin, y cada consulta queda en
    | la auditoria. Encendido por defecto. Con IBBLA_CONTRASENA_VISIBLE=false no se guarda ninguna copia
    | nueva, las que hay dejan de mostrarse y el boton "Ver contrasena" desaparece; queda "Restablecer
    | acceso" como unica via.
    |
    */

    'tarjetas' => [
        'contrasena_visible' => (bool) env('IBBLA_CONTRASENA_VISIBLE', true),
    ],

];
