<?php

/*
|--------------------------------------------------------------------------
| Lista base de ministerios / áreas de servicio
|--------------------------------------------------------------------------
|
| Este es el ÚNICO lugar que hay que editar para cambiar la lista que carga
| `Database\Seeders\MinisterioSeeder`.
|
| PENDIENTE DEL CLIENTE: la lista real de los 30 ministerios la entrega el
| cliente (Roy). Mientras no llegue, aquí quedan los 7 que el sistema ya traía
| desde la fase 5.1. Cuando llegue:
|
|   1. Reemplazar / agregar líneas en 'lista' (un ministerio por línea).
|   2. Si el servidor usa `config:cache`, correr `php artisan config:clear`.
|   3. Cargarlos:  php artisan db:seed --class=MinisterioSeeder --force
|
| El seeder es idempotente: busca cada ministerio por NOMBRE dentro de la
| iglesia y solo crea los que faltan. Nunca modifica ni borra los que ya
| existen, así que lo que el administrador haya cambiado desde la pantalla
| Ministerios (color, orden, desactivados, descripción) se respeta. Si un
| ministerio se renombró desde la app, hay que renombrarlo también aquí (o
| quitarlo de la lista); de lo contrario el seeder lo volvería a crear con el
| nombre viejo.
|
| Para agregar UN ministerio suelto no hace falta tocar este archivo: se crea
| desde Administración > Ministerios > "Nuevo ministerio".
|
| Formato de cada línea (todo lo que no sea el nombre es opcional):
|
|     'Nombre del ministerio',
|     ['nombre' => 'Nombre', 'descripcion' => 'Texto corto', 'color' => '#8B5CF6'],
|
| El orden de la lista es el orden en pantalla. Si no se indica color, se
| reparte uno de 'paleta'.
|
*/

return [

    'lista' => [
        ['nombre' => 'Alabanza', 'descripcion' => 'Músicos y cantantes del ministerio de adoración.', 'color' => '#8B5CF6'],
        ['nombre' => 'Transmisión', 'descripcion' => 'Sonido, video, streaming y técnicos.', 'color' => '#F59E0B'],
        ['nombre' => 'Ujieres', 'descripcion' => 'Recepción, orden y atención al asistente.', 'color' => '#10B981'],
        ['nombre' => 'Niños / Escuela Dominical', 'descripcion' => 'Maestros y ayudantes de clases por edad.', 'color' => '#EC4899'],
        ['nombre' => 'Diaconado', 'descripcion' => 'Diáconos y diaconisas.', 'color' => '#3B82F6'],
        ['nombre' => 'Misiones', 'descripcion' => 'Comité y equipo de misiones.', 'color' => '#EF4444'],
        ['nombre' => 'Limpieza / Mantenimiento', 'descripcion' => 'Equipo de limpieza y mantenimiento.', 'color' => '#6B7280'],
    ],

    /*
    | Iglesias (campo `slug` de la tabla tenants) a las que se les siembra la
    | lista. null = todas las iglesias existentes. Útil si la plataforma aloja
    | más de una iglesia y la lista del cliente solo aplica a una:
    |     'tenant_slugs' => ['ibbla'],
    */
    'tenant_slugs' => null,

    /*
    | Colores que se reparten (en orden, de forma cíclica) a los ministerios de
    | la lista que no indican color propio.
    */
    'paleta' => [
        '#8B5CF6', '#F59E0B', '#10B981', '#EC4899', '#3B82F6',
        '#EF4444', '#14B8A6', '#6366F1', '#F97316', '#84CC16',
    ],

];
