<?php

namespace App\Support\Navegacion;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Fuente unica de las pantallas de la app: alimenta el menu lateral, el buscador global y
 * (por sus rutas) el boton "?" de ayuda.
 *
 * Cada entrada se muestra con la misma regla de permisos que protege su ruta, para que nadie
 * vea un enlace que despues le responde 403. Si una ruta todavia no existe (otro equipo la esta
 * construyendo) la entrada simplemente no aparece: no hace falta tocar nada mas.
 *
 * Para agregar una pantalla al menu: sumar una fila en definicion() con su ruta, etiqueta, icono
 * (ver App\Support\Iconos), patron de rutas activas, regla de visibilidad y palabras clave para
 * el buscador. Para que el buscador la encuentre sin ponerla en el menu: sumarla en extras().
 */
class Menu
{
    /**
     * Grupos del menu lateral visibles para el usuario.
     *
     * @return list<array{titulo: ?string, items: list<array<string, mixed>>}>
     */
    public static function grupos(User $u): array
    {
        $grupos = [];

        foreach (self::definicion($u) as [$titulo, $items]) {
            $visibles = array_values(array_filter($items, fn (array $it) => $it['visible'] && Route::has($it['ruta'])));

            if ($visibles !== []) {
                $grupos[] = ['titulo' => $titulo, 'items' => $visibles];
            }
        }

        return $grupos;
    }

    /**
     * Todas las pantallas que el usuario puede abrir (las del menu y otras que no estan en el menu),
     * listas para buscar por palabras. No incluye lo que le respondería 403.
     *
     * @return list<array{titulo: string, ruta: string, url: string, icono: string, grupo: string, tipo: string, claves: string}>
     */
    public static function pantallas(User $u): array
    {
        $porRuta = [];

        foreach (self::grupos($u) as $grupo) {
            foreach ($grupo['items'] as $it) {
                $porRuta[$it['ruta']] = [
                    'titulo' => $it['etiqueta'],
                    'ruta' => $it['ruta'],
                    'url' => route($it['ruta']),
                    'icono' => $it['icono'],
                    'grupo' => $grupo['titulo'] ?? 'General',
                    'tipo' => $it['tipo'] ?? 'pantalla',
                    'claves' => $it['claves'] ?? '',
                ];
            }
        }

        foreach (self::extras($u, self::permisos($u)) as $it) {
            if (! $it['visible'] || ! Route::has($it['ruta'])) {
                continue;
            }

            // Si la ruta ya esta en el menu con un nombre mas general (al asistente "Reportes" lo lleva
            // directo al reporte de asistencia), el buscador la muestra con el nombre especifico
            $porRuta[$it['ruta']] = [
                'titulo' => $it['etiqueta'],
                'ruta' => $it['ruta'],
                'url' => route($it['ruta']),
                'icono' => $it['icono'],
                'grupo' => $it['grupo'] ?? 'General',
                'tipo' => $it['tipo'] ?? 'pantalla',
                'claves' => trim(($porRuta[$it['ruta']]['claves'] ?? '').' '.($it['claves'] ?? '')),
            ];
        }

        return array_values($porRuta);
    }

    /**
     * Reglas de acceso del usuario, calculadas una vez por peticion.
     *
     * @return array{recuento: bool, asistencia: bool, admin: bool, appClase: bool, miPerfil: bool}
     */
    public static function permisos(User $u): array
    {
        return [
            'recuento' => $u->canAccessRecuento(),
            'asistencia' => $u->canAccessAsistencia(),
            'admin' => $u->canAccessAdmin(),
            'appClase' => $u->isAdmin() || $u->isSuperAdmin() || $u->clase_asistencia_id
                || ($u->persona && $u->persona->clasesAsistencia()->wherePivot('es_maestro', true)->exists()),
            'miPerfil' => $u->isMiembro() || in_array($u->rol, ['servidor', 'tesorero', 'asistente']),
        ];
    }

    /**
     * Definicion del menu: [titulo del grupo | null, [entradas]].
     *
     * @return array<int, array{0: ?string, 1: list<array<string, mixed>>}>
     */
    private static function definicion(User $u): array
    {
        $p = self::permisos($u);
        $centroReportes = Route::has('reportes.index');
        $rutaAccesos = Route::has('admin.accesos.index') ? 'admin.accesos.index' : 'accesos.index';

        $def = [
            [null, [
                self::it('principal', 'Principal', 'home', 'principal', true, 'inicio bienvenida accesos rapidos mision vision'),
                self::it('cumpleaneros.index', 'Cumpleañeros', 'regalo', 'cumpleaneros.*', true, 'cumpleanos fecha de nacimiento cumple felicitar'),
                self::it('mi-perfil.index', 'Yo', 'usuario', 'mi-perfil.*', $p['miPerfil'], 'mi perfil mis promesas mis compromisos mi estado de cuenta informe whatsapp'),
                self::it('marcar-asistencia.index', 'Marcar Asistencia', 'check', 'marcar-asistencia.*', $u->hasPermission('marcar_asistencia'), 'servidor qr escanear codigo marcar mi asistencia'),
                self::it('clase-app.index', 'App de Clases', 'libro', 'clase-app.*', $p['appClase'], 'clase maestra alumnos asistencia por alumno visitacion'),
            ]],
            // Lo que se usa cada culto va primero
            ['Registro', [
                self::it('recuento.index', 'Recuento', 'documento', 'recuento.*', $p['recuento'], 'sobres ofrendas diezmos transferencias cerrar culto firmas egresos dinero contar'),
                self::it('asistencia.index', 'Asistencia', 'grupo', 'asistencia.index|asistencia.create|asistencia.edit|asistencia.show', $p['asistencia'], 'asistencia capilla clases salvos visitas conteo de personas'),
            ]],
            ['Consultas', [
                self::it('dashboard', 'Dashboard', 'barras', 'dashboard', $p['recuento'], 'resumen graficos ingresos del mes estadisticas indicadores'),
                self::it('dashboard.promesas', 'Dashboard de promesas', 'barras', 'dashboard.promesas', $p['recuento'], 'promesas cumplimiento compromisos graficos'),
                self::it('dashboard.asistencia', 'Dashboard de asistencia', 'barras', 'dashboard.asistencia', $p['recuento'], 'asistencia graficos promedio tendencia'),
                // Reportes: tesoreria entra al indice (o al Centro de Reportes cuando exista); quien solo maneja asistencia va directo a su reporte.
                $centroReportes && $p['recuento']
                    ? self::it('reportes.index', 'Reportes', 'reporte', 'reportes.*|ingresos-asistencia.index|ingresos-asistencia.ingresos|ingresos-asistencia.asistencia|ingresos-asistencia.promesas*|ingresos-asistencia.pdf-*|ingresos-asistencia.excel-*', true, 'reportes informes pdf excel estado de cuenta centro de reportes', ['tipo' => 'reporte'])
                    : self::it($p['recuento'] ? 'ingresos-asistencia.index' : 'ingresos-asistencia.asistencia', 'Reportes', 'reporte', 'ingresos-asistencia.index|ingresos-asistencia.ingresos|ingresos-asistencia.asistencia|ingresos-asistencia.promesas*|ingresos-asistencia.pdf-*|ingresos-asistencia.excel-*', $p['recuento'] || $p['asistencia'], 'reportes informes pdf excel descargar ingresos asistencia promesas', ['tipo' => 'reporte']),
                // Reporte por clase y rango: es de admin/asistente, el asistente no entra al indice de Reportes
                self::it('ingresos-asistencia.asistencia-clases', 'Asistencia por clase', 'grupo', 'ingresos-asistencia.asistencia-clases*', $p['asistencia'], 'reporte por clase rango de fechas promedio ninos ninas maestros csv excel pdf', ['tipo' => 'reporte']),
                // Panel de avisos de WhatsApp a lideres: admin y tesorero, con contador de pendientes
                self::it('avisos.index', 'Avisos de WhatsApp', 'mensaje', 'avisos.*', $p['recuento'], 'whatsapp avisos lideres mensajes enviar pendientes culto creado sobres'),
            ]],
            ['Super Admin', [
                self::it('super-admin.dashboard', 'Panel Super Admin', 'escudo', 'super-admin.*', $u->isSuperAdmin(), 'iglesias plataforma tenants super administrador'),
            ]],
            ['Administración', [
                self::it('cultos.index', 'Gestionar Cultos', 'calendario', 'cultos.*', $p['admin'], 'cultos servicios crear culto fecha domingo miercoles editar eliminar'),
                self::it('admin.clases.index', 'Gestionar Clases', 'ajustes', 'admin.clases.*', $p['admin'], 'clases escuela dominical grupos maestros alumnos'),
                self::it('personas.index', 'Gestionar Personas', 'personas', 'personas.*|compromisos.*', $p['admin'], 'personas miembros hermanos pin telefono promesas compromisos estado de cuenta ficha'),
                self::it('admin.ministerios.index', 'Ministerios', 'estrella', 'admin.ministerios.*', $p['admin'], 'ministerios areas de servicio lideres'),
                self::it('usuarios.index', 'Gestionar Usuarios', 'personas', 'usuarios.*', $p['admin'], 'usuarios roles permisos contrasena acceso cuentas'),
                self::it($rutaAccesos, 'Accesos', 'candado', 'admin.accesos.*|accesos.*', $p['admin'], 'accesos permisos de edicion culto tesorero conceder revocar'),
                self::it('admin.servidores.index', 'Servidores', 'grupo', 'admin.servidores.*', $p['admin'], 'servidores qr asistencia de servidores reporte de servidores'),
                self::it('asistencia.importar.index', 'Cargar Histórico', 'subir', 'asistencia.importar.*', $p['admin'], 'importar historico excel asistencias antiguas plantilla'),
                self::it('admin.auditoria.index', 'Auditoría', 'lupa', 'admin.auditoria.index', $p['admin'], 'auditoria historial de cambios quien modifico bitacora'),
                // Historial de ingresos a la plataforma
                self::it('admin.auditoria.ingresos', 'Historial de ingresos', 'usuario', 'admin.auditoria.ingresos*', $p['admin'], 'historial de ingresos quien entro cuando hora accesos al sistema'),
            ]],
        ];

        return $def;
    }

    /**
     * Pantallas que no estan en el menu pero que conviene poder encontrar con el buscador.
     *
     * @return list<array<string, mixed>>
     */
    private static function extras(User $u, array $p): array
    {
        return [
            self::extra('cultos.create', 'Nuevo culto', 'calendario', $p['admin'], 'crear agregar culto servicio nuevo', 'Administración'),
            self::extra('personas.create', 'Nueva persona', 'personas', $p['admin'], 'crear agregar persona miembro nuevo hermano', 'Administración'),
            self::extra('usuarios.create', 'Nuevo usuario', 'personas', $p['admin'], 'crear agregar usuario cuenta nueva rol', 'Administración'),
            self::extra('admin.ministerios.create', 'Nuevo ministerio', 'estrella', $p['admin'], 'crear agregar ministerio nuevo area de servicio', 'Administración'),
            self::extra('admin.clases.create', 'Nueva clase', 'ajustes', $p['admin'], 'crear agregar clase nueva grupo', 'Administración'),
            self::extra('asistencia.create', 'Nueva asistencia', 'grupo', $p['asistencia'], 'registrar asistencia de un culto nueva capilla clases', 'Registro'),
            self::extra('ingresos-asistencia.ingresos', 'Reporte de ingresos', 'reporte', $p['recuento'], 'reporte ingresos dinero ofrendas diezmos por culto pdf excel', 'Reportes', 'reporte'),
            self::extra('ingresos-asistencia.asistencia', 'Reporte de asistencia', 'reporte', $p['asistencia'], 'reporte asistencia por culto mes pdf excel', 'Reportes', 'reporte'),
            self::extra('ingresos-asistencia.promesas', 'Reporte de promesas', 'reporte', $p['recuento'], 'reporte promesas cumplimiento pdf excel anual', 'Reportes', 'reporte'),
            self::extra('ingresos-asistencia.promesas-por-clase', 'Promesas por clase', 'reporte', $p['recuento'], 'reporte promesas por clase grupo', 'Reportes', 'reporte'),
            self::extra('admin.servidores.reporte', 'Reporte de servidores', 'reporte', $p['admin'], 'reporte servidores asistencia de servidores pdf', 'Reportes', 'reporte'),
            self::extra('profile.edit', 'Mi cuenta', 'usuario', true, 'mi cuenta perfil nombre correo contrasena clave cambiar whatsapp avisos', 'General'),
        ];
    }

    /** @return array<string, mixed> */
    private static function it(string $ruta, string $etiqueta, string $icono, string $patron, bool $visible, string $claves = '', array $extra = []): array
    {
        return array_merge([
            'ruta' => $ruta,
            'etiqueta' => $etiqueta,
            'icono' => $icono,
            'patron' => $patron,
            'visible' => $visible,
            'claves' => $claves,
        ], $extra);
    }

    /** @return array<string, mixed> */
    private static function extra(string $ruta, string $etiqueta, string $icono, bool $visible, string $claves, string $grupo, string $tipo = 'pantalla'): array
    {
        return [
            'ruta' => $ruta,
            'etiqueta' => $etiqueta,
            'icono' => $icono,
            'visible' => $visible,
            'claves' => $claves,
            'grupo' => $grupo,
            'tipo' => $tipo,
        ];
    }
}
