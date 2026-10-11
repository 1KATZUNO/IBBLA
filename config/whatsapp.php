<?php

/*
|--------------------------------------------------------------------------
| WhatsApp
|--------------------------------------------------------------------------
|
| La iglesia no pudo verificar su cuenta de Facebook, así que no hay acceso a la
| API de Meta. Mientras tanto el sistema trabaja con enlaces directos de WhatsApp
| (wa.me): abren el chat con el mensaje ya escrito y la persona solo toca «enviar».
|
| La capa de proveedor (App\Services\WhatsApp) deja listo el cambio a la API
| oficial: basta con WHATSAPP_DRIVER=meta y las credenciales, sin tocar el resto.
|
| Importante: en producción el .env se cachea (php artisan config:cache), así que el
| código lee siempre config('whatsapp.*') y nunca env() directamente.
|
*/

return [

    /*
    | Proveedor de envío.
    |   link  Enlaces wa.me. No envía nada solo: alguien toca «enviar» en su WhatsApp.
    |   meta  WhatsApp Cloud API (Meta). Envía solo; necesita las credenciales de abajo.
    */
    'driver' => env('WHATSAPP_DRIVER', 'link'),

    /*
    | WhatsApp Cloud API. Solo se usa con WHATSAPP_DRIVER=meta.
    |
    | Ojo: Meta solo deja mandar texto libre a quien escribió al número en las últimas
    | 24 horas. Para avisar por iniciativa propia hay que crear y aprobar una plantilla
    | (con un solo parámetro en el cuerpo, {{1}}) y poner su nombre en WHATSAPP_META_PLANTILLA:
    | entonces el mensaje completo viaja como ese parámetro.
    */
    'meta' => [
        'token' => env('WHATSAPP_META_TOKEN'),
        'phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID'),
        'version' => env('WHATSAPP_META_VERSION', 'v21.0'),
        'base_url' => env('WHATSAPP_META_BASE_URL', 'https://graph.facebook.com'),
        'plantilla' => env('WHATSAPP_META_PLANTILLA'),
        'idioma_plantilla' => env('WHATSAPP_META_IDIOMA', 'es'),
        'timeout' => 15,
    ],

    /*
    | Informe individual por enlace firmado (botón «Enviar por WhatsApp» de Personas).
    |
    | Variables de la plantilla: {nombre} {iglesia} {enlace} {vence}
    */
    'informe' => [
        'vigencia_dias' => (int) env('WHATSAPP_INFORME_DIAS', 7),
        'plantilla' => "Hola {nombre}, le saluda {iglesia}. "
            ."Le compartimos su informe de contribuciones y compromisos: {enlace}\n\n"
            ."El enlace vence el {vence}. ¡Dios le bendiga!",
    ],

    /*
    | Avisos a los líderes (panel /avisos).
    |
    | roles          Slugs de rol que reciben los avisos (deben tener teléfono en su usuario).
    | sobres_umbral  Cantidad de sobres con la que un culto dispara el aviso (una sola vez por culto).
    |
    | Variables de las plantillas: {nombre} {iglesia} {tipo} {fecha} {hora} {cantidad} {enlace}
    */
    'avisos' => [
        'roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('WHATSAPP_AVISOS_ROLES', 'admin,tesorero'))))),
        'sobres_umbral' => (int) env('WHATSAPP_AVISOS_SOBRES', 10),
        'plantillas' => [
            'culto_creado' => "Hola {nombre}. Se creó un culto en {iglesia}: {tipo}, {fecha}, {hora}.\n\n"
                ."Recuento: {enlace}",
            'sobres_alcanzados' => "Hola {nombre}. El culto {tipo} del {fecha} ya tiene {cantidad} sobres registrados en {iglesia}.\n\n"
                ."Recuento: {enlace}",
        ],
    ],

];
