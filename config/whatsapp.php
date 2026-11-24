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
| Como paso intermedio existe el driver «callmebot» (el mismo enfoque que usa ARCA): un
| servicio gratuito de un tercero con el que cada persona o usuario activa el bot desde su
| propio WhatsApp y pega su enlace en su perfil. Ver la sección «callmebot» más abajo.
|
| Importante: en producción el .env se cachea (php artisan config:cache), así que el
| código lee siempre config('whatsapp.*') y nunca env() directamente.
|
*/

return [

    /*
    | Proveedor de envío.
    |   link       Enlaces wa.me. No envía nada solo: alguien toca «enviar» en su WhatsApp.
    |   callmebot  CallMeBot: gratis, de un tercero y sin garantía. La clave es de cada receptor
    |              (persona o usuario): quien la tiene recibe los mensajes solo; a quien no, se le
    |              sigue ofreciendo el enlace wa.me de siempre. Es seguro activarlo antes de que
    |              nadie tenga clave: no cambia nada hasta que alguien active su WhatsApp.
    |   meta       WhatsApp Cloud API (Meta). Envía solo; necesita las credenciales de abajo.
    */
    'driver' => env('WHATSAPP_DRIVER', 'link'),

    /*
    | CallMeBot (solo con WHATSAPP_DRIVER=callmebot). https://www.callmebot.com/blog/free-api-whatsapp-messages/
    |
    | Cómo funciona: cada persona agrega el contacto del bot, le manda desde su propio WhatsApp la
    | frase de activación y el bot le responde con un enlace que trae su número (phone=) y su clave
    | (apikey=). Esa persona pega el enlace en su perfil (Mi perfil, o Perfil si es usuario del
    | sistema). El sistema envía con GET {url}?phone=+506...&text=...&apikey=... y ya.
    |
    | Ojo, es un servicio gratuito de un tercero:
    |   - No tiene garantía de disponibilidad. Si falla, el mensaje no sale y el flujo manual (wa.me)
    |     sigue disponible. Nunca debe ser el único medio de aviso.
    |   - Sus propios términos dicen que la API gratuita es «solo para uso personal»: cada persona
    |     activa su número y recibe lo suyo; no sirve para escribirle a terceros ni a grupos.
    |   - Solo manda texto (sin PDF adjunto): el informe viaja como enlace firmado.
    |   - Responde HTTP 200 aunque falle: el éxito se reconoce por «Message queued» en la respuesta.
    |   - Para algo sostenido hay opciones de pago (Twilio, TextMeBot) o la API oficial de Meta.
    |
    | bot_numero: número del bot que se muestra en la guía. CallMeBot lo cambia de vez en cuando
    | (el 13/10/2026 el bot que respondió una activación real fue el +34 644 91 07 79; ARCA usó otro
    | en octubre y callmebot.com mostraba otro): si la gente dice que el bot no le contesta, verifique
    | el número vigente y cámbielo con WHATSAPP_CALLMEBOT_BOT_NUMERO sin tocar código.
    | frase_activacion: el mensaje exacto que se le manda al bot.
    |
    | Lo que contesta el bot: la primera vez «API Activated for your phone number. Your APIKEY is 1234567»
    | y, si ese número ya estaba activado, «You already have an apikey. Your apikey is 1234567 You can send
    | a test messages using the url https://api.callmebot.com/whatsapp.php?phone=506...&text=...&apikey=1234567».
    | La persona puede pegar el mensaje completo, el enlace o solo la clave: las tres formas se aceptan.
    */
    'callmebot' => [
        'url' => env('WHATSAPP_CALLMEBOT_URL', 'https://api.callmebot.com/whatsapp.php'),
        // Segundos máximos para conectar y para recibir la respuesta: un tercero sin SLA no debe colgar la pantalla.
        'connect_timeout' => (int) env('WHATSAPP_CALLMEBOT_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('WHATSAPP_CALLMEBOT_TIMEOUT', 10),
        'bot_numero' => env('WHATSAPP_CALLMEBOT_BOT_NUMERO', '+34 644 91 07 79'),
        'frase_activacion' => env('WHATSAPP_CALLMEBOT_FRASE', 'I allow callmebot to send me messages'),
        'ayuda_url' => 'https://www.callmebot.com/blog/free-api-whatsapp-messages/',
    ],

    /*
    | Activación de WhatsApp por la propia persona (consentimiento).
    |
    | version_texto  Se guarda junto a la fecha del consentimiento para saber qué texto aceptó. Si
    |                cambia el texto de la casilla, súbala (v2...) y quienes ya activaron quedan
    |                registrados con la versión que aceptaron.
    | plantilla_prueba  Mensaje de prueba. Variables: {nombre} {iglesia}
    | consentimiento    Texto de la casilla que la persona marca. Variables: {iglesia} {telefono}
    */
    'activacion' => [
        'version_texto' => 'v1',
        'consentimiento' => [
            'persona' => 'Autorizo a {iglesia} a enviarme por WhatsApp, al número {telefono}, mi estado de cuenta y los avisos '
                .'que me quieran compartir. Entiendo que el envío lo hace CallMeBot, un servicio gratuito de un tercero que puede '
                .'fallar, y que puedo desactivarlo cuando quiera desde esta misma pantalla.',
            'usuario' => 'Autorizo a {iglesia} a enviarme por WhatsApp, al número {telefono}, los avisos del sistema (culto creado, '
                .'sobres registrados). Entiendo que el envío lo hace CallMeBot, un servicio gratuito de un tercero que puede '
                .'fallar, y que puedo desactivarlo cuando quiera desde esta misma pantalla.',
        ],
        'plantilla_prueba' => "Hola {nombre}, esta es una prueba de {iglesia}. "
            ."Si usted lee este mensaje, sus avisos por WhatsApp quedaron activados. ¡Dios le bendiga!",
    ],

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
            'culto_creado' => "Hola {nombre}. Se creó un culto en {iglesia}: {tipo}, {fecha} a las {hora}.\n\n"
                ."Recuento: {enlace}",
            'sobres_alcanzados' => "Hola {nombre}. El culto {tipo} del {fecha} ya tiene {cantidad} sobres registrados en {iglesia}.\n\n"
                ."Recuento: {enlace}",
        ],
    ],

];
