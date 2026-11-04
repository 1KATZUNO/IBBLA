<?php

use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\CultoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IngresosAsistenciaController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PrincipalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromesaController;
use App\Http\Controllers\RecuentoController;
use App\Http\Controllers\SuperAdmin\DashboardController as SADashboardController;
use App\Http\Controllers\SuperAdmin\TenantController as SATenantController;
use App\Services\BccrExchangeRateService;
use Illuminate\Support\Facades\Route;

// Con sesion, cada rol a su inicio (QA-011: la app instalada abre "/" y antes daba 403)
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route(auth()->user()->rutaDeInicio())
        : redirect()->route('login');
});

// Informe individual de una persona por enlace firmado: se comparte por WhatsApp, no requiere
// sesión y vence (7 días por defecto). Va fuera de los grupos con auth a propósito.
Route::get('/informe/{persona}', App\Http\Controllers\InformePublicoController::class)
    ->whereNumber('persona')
    ->middleware(['throttle:30,1', 'signed'])
    ->name('informe.persona');

// API interna - Tipo de cambio
Route::middleware(['auth'])->get('/api/tipo-cambio', function (BccrExchangeRateService $service) {
    $tipoCambio = $service->obtenerHoy();

    if (! $tipoCambio) {
        return response()->json([
            'disponible' => false,
            'mensaje' => 'No hay tipo de cambio disponible. Configure las credenciales BCCR o ingrese uno manualmente.',
        ]);
    }

    return response()->json([
        'disponible' => true,
        'fecha' => $tipoCambio->fecha->format('Y-m-d'),
        'compra' => (float) $tipoCambio->compra,
        'venta' => (float) $tipoCambio->venta,
        'source' => $tipoCambio->source,
    ]);
})->name('api.tipo-cambio');

// Principal - Para todos los usuarios autenticados
Route::middleware(['auth'])->group(function () {
    Route::get('/principal', [PrincipalController::class, 'index'])->name('principal');
    Route::get('/cumpleaneros', [\App\Http\Controllers\CumpleanerosController::class, 'index'])->name('cumpleaneros.index');
});

// Dashboard - Solo para Admin y Tesorero
Route::middleware(['auth', 'role:admin,tesorero', 'audit'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Dashboards específicos (Fase 4) — además del general, no en su lugar
    Route::get('/dashboard/promesas', [App\Http\Controllers\DashboardPromesasController::class, 'index'])->name('dashboard.promesas');
});

// Dashboard de asistencia: admin, tesorero y asistente (decision 2.7, QA-015)
Route::middleware(['auth', 'role:admin,tesorero,asistente', 'audit'])->group(function () {
    Route::get('/dashboard/asistencia', [App\Http\Controllers\DashboardAsistenciaController::class, 'index'])->name('dashboard.asistencia');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Mi Perfil: cualquier rol con persona vinculada, tambien el admin (QA-014)
Route::middleware(['auth', 'role:miembro,servidor,tesorero,asistente,admin'])->group(function () {
    Route::get('/mi-perfil', [App\Http\Controllers\MiPerfilController::class, 'index'])->name('mi-perfil.index');
    Route::get('/mi-perfil/informe', [App\Http\Controllers\MiPerfilController::class, 'informe'])->name('mi-perfil.informe');
});

// Ruta para servidores - Marcar Asistencia
Route::middleware(['auth', 'role:servidor'])->group(function () {
    Route::get('/marcar-asistencia', [App\Http\Controllers\MarcarAsistenciaController::class, 'index'])->name('marcar-asistencia.index');
    Route::post('/marcar-asistencia', [App\Http\Controllers\MarcarAsistenciaController::class, 'store'])->name('marcar-asistencia.store');
});

// Rutas para Admin y Tesorero - Recuento e Ingresos
Route::middleware(['auth', 'role:admin,tesorero'])->group(function () {
    // Alta rapida de personas (desde el sobre o desde su pantalla): el tesorero no entra a Personas
    Route::get('personas/alta-rapida', [PersonaController::class, 'altaRapida'])->name('personas.alta-rapida');
    Route::post('personas/quick-store', [PersonaController::class, 'quickStore'])->name('personas.quick-store');

    // Enviar el informe de una persona por WhatsApp (abre wa.me con el enlace firmado al PDF)
    Route::get('personas/{persona}/whatsapp', App\Http\Controllers\PersonaWhatsAppController::class)->name('personas.whatsapp');

    // Avisos de WhatsApp a los líderes (culto creado, sobres alcanzados): panel con pendientes e historial
    Route::get('avisos', [App\Http\Controllers\AvisoWhatsAppController::class, 'index'])->name('avisos.index');
    Route::post('avisos/{aviso}/enviado', [App\Http\Controllers\AvisoWhatsAppController::class, 'marcarEnviado'])->name('avisos.enviado');
    Route::post('avisos/{aviso}/descartar', [App\Http\Controllers\AvisoWhatsAppController::class, 'descartar'])->name('avisos.descartar');

    // Recuento (Sobres)
    Route::prefix('recuento')->name('recuento.')->group(function () {
        Route::get('/', [RecuentoController::class, 'index'])->name('index');
        Route::get('/create', [RecuentoController::class, 'create'])->name('create');
        Route::post('/', [RecuentoController::class, 'store'])->name('store');
        Route::post('/suelto', [RecuentoController::class, 'storeSuelto'])->name('store-suelto');
        Route::get('/suelto/{suelto}/edit', [RecuentoController::class, 'editSuelto'])->name('edit-suelto');
        Route::put('/suelto/{suelto}', [RecuentoController::class, 'updateSuelto'])->name('update-suelto');
        Route::delete('/suelto/{suelto}', [RecuentoController::class, 'destroySuelto'])->name('destroy-suelto');
        // Egresos (restas)
        Route::post('/egreso', [RecuentoController::class, 'storeEgreso'])->name('store-egreso');
        Route::get('/egreso/{egreso}/edit', [RecuentoController::class, 'editEgreso'])->name('edit-egreso');
        Route::put('/egreso/{egreso}', [RecuentoController::class, 'updateEgreso'])->name('update-egreso');
        Route::delete('/egreso/{egreso}', [RecuentoController::class, 'destroyEgreso'])->name('destroy-egreso');
        Route::post('/{culto}/cerrar', [RecuentoController::class, 'cerrarCulto'])->name('cerrar-culto');
        // Pantalla de un culto (abierto o cerrado). La vieja ruta del modal de cerrados redirige aqui.
        Route::get('/culto/{culto}', [RecuentoController::class, 'culto'])->name('culto');
        Route::get('/culto-cerrado/{culto}', [RecuentoController::class, 'verCultoCerrado'])->name('ver-culto-cerrado');
        Route::get('/{sobre}/edit', [RecuentoController::class, 'edit'])->name('edit');
        Route::put('/{sobre}', [RecuentoController::class, 'update'])->name('update');
        Route::delete('/{sobre}', [RecuentoController::class, 'destroy'])->name('destroy');
        Route::patch('/{sobre}/persona', [RecuentoController::class, 'asignarPersona'])->name('asignar-persona');
        // Firmas del recuento
        Route::post('/firmas/{culto}', [RecuentoController::class, 'actualizarFirmas'])->name('firmas.update');
    });

    // Reportes de ingresos
    Route::get('/ingresos-asistencia', [IngresosAsistenciaController::class, 'index'])->name('ingresos-asistencia.index');
    Route::get('/ingresos-asistencia/ingresos', [IngresosAsistenciaController::class, 'ingresos'])->name('ingresos-asistencia.ingresos');
    Route::get('/ingresos-asistencia/pdf-ingresos', [IngresosAsistenciaController::class, 'pdfIngresos'])->name('ingresos-asistencia.pdf-ingresos');
    Route::get('/ingresos-asistencia/pdf-ingresos-transferencias', [IngresosAsistenciaController::class, 'pdfIngresosTransferencias'])->name('ingresos-asistencia.pdf-ingresos-transferencias');
    Route::get('/ingresos-asistencia/pdf-recuento/{culto}', [IngresosAsistenciaController::class, 'pdfRecuentoIndividual'])->name('ingresos-asistencia.pdf-recuento-individual');
    Route::get('/ingresos-asistencia/pdf-recuento-transferencias/{culto}', [IngresosAsistenciaController::class, 'pdfRecuentoTransferencias'])->name('ingresos-asistencia.pdf-recuento-transferencias');

    // Reporte de Promesas
    Route::get('/ingresos-asistencia/promesas', [App\Http\Controllers\PromesasReporteController::class, 'index'])->name('ingresos-asistencia.promesas');
    Route::get('/ingresos-asistencia/pdf-promesas', [App\Http\Controllers\PromesasReporteController::class, 'pdfPromesas'])->name('ingresos-asistencia.pdf-promesas');
    Route::get('/ingresos-asistencia/pdf-promesas-anual', [App\Http\Controllers\PromesasReporteController::class, 'pdfAnual'])->name('ingresos-asistencia.pdf-promesas-anual');
    Route::get('/ingresos-asistencia/promesas-por-clase', [App\Http\Controllers\PromesasReporteController::class, 'porClase'])->name('ingresos-asistencia.promesas-por-clase');
});

// Descargas financieras (regla 2.5 de 04-DECISIONES.md, QA-010): admin y tesorero. Reporte General,
// contribuciones y estado de cuenta de cualquier persona; Excel de ingresos y de promesas.
Route::middleware(['auth', 'role:admin,tesorero', 'audit'])->group(function () {
    Route::get('/ingresos-asistencia/excel-ingresos', [IngresosAsistenciaController::class, 'excelIngresos'])->name('ingresos-asistencia.excel-ingresos');
    Route::get('/ingresos-asistencia/excel-promesas', [IngresosAsistenciaController::class, 'excelPromesas'])->name('ingresos-asistencia.excel-promesas');
    Route::get('personas/reporte-pdf', [PersonaController::class, 'reportePdf'])->name('personas.reporte-pdf');
    Route::get('personas/reporte-general', [PersonaController::class, 'reporteGeneral'])->name('personas.reporte-general');
    // Estado de cuenta de la persona en PDF (el mismo informe que se manda por WhatsApp), ?accion=ver|descargar
    Route::get('personas/{persona}/informe', [App\Http\Controllers\InformePersonaController::class, 'show'])->name('personas.informe');
});

// Reportes de asistencia (regla 2.5): admin, tesorero y asistente. Registrar asistencia sigue en el grupo de abajo.
Route::middleware(['auth', 'role:admin,tesorero,asistente', 'audit'])->group(function () {
    // Reportes de asistencia
    Route::get('/ingresos-asistencia/asistencia', [IngresosAsistenciaController::class, 'asistencia'])->name('ingresos-asistencia.asistencia');
    Route::get('/ingresos-asistencia/pdf-asistencia', [IngresosAsistenciaController::class, 'pdfAsistencia'])->name('ingresos-asistencia.pdf-asistencia');
    Route::get('/ingresos-asistencia/pdf-asistencia-culto/{culto}', [IngresosAsistenciaController::class, 'pdfAsistenciaCulto'])->name('ingresos-asistencia.pdf-asistencia-culto');
    Route::get('/ingresos-asistencia/pdf-asistencia-mes', [IngresosAsistenciaController::class, 'pdfAsistenciaMes'])->name('ingresos-asistencia.pdf-asistencia-mes');

    // Asistencia por clase en un rango de fechas, con promedio (pantalla, PDF, CSV y Excel)
    Route::get('/ingresos-asistencia/asistencia-clases', [App\Http\Controllers\AsistenciaClasesReporteController::class, 'index'])->name('ingresos-asistencia.asistencia-clases');
    Route::get('/ingresos-asistencia/asistencia-clases/pdf', [App\Http\Controllers\AsistenciaClasesReporteController::class, 'pdf'])->name('ingresos-asistencia.asistencia-clases.pdf');
    Route::get('/ingresos-asistencia/asistencia-clases/csv', [App\Http\Controllers\AsistenciaClasesReporteController::class, 'csv'])->name('ingresos-asistencia.asistencia-clases.csv');
    Route::get('/ingresos-asistencia/asistencia-clases/excel', [App\Http\Controllers\AsistenciaClasesReporteController::class, 'excel'])->name('ingresos-asistencia.asistencia-clases.excel');

    // Excel exports (Fase 2): paralelos a los PDFs
    Route::get('/ingresos-asistencia/excel-asistencia', [IngresosAsistenciaController::class, 'excelAsistencia'])->name('ingresos-asistencia.excel-asistencia');
    Route::get('/ingresos-asistencia/excel-asistencia-mes', [IngresosAsistenciaController::class, 'excelAsistenciaMes'])->name('ingresos-asistencia.excel-asistencia-mes');
});

// Reportes de dinero por persona con el motor unico (equipo 3a): admin y tesorero.
Route::middleware(['auth', 'role:admin,tesorero'])->prefix('reportes')->name('reportes.')->group(function () {
    // Cumplimiento de promesas con corte a la fecha (HU-R13): pantalla, PDF (?accion=ver|descargar) y Excel
    Route::get('/cumplimiento', [App\Http\Controllers\Reportes\CumplimientoController::class, 'index'])->name('cumplimiento');
    Route::get('/cumplimiento/pdf', [App\Http\Controllers\Reportes\CumplimientoController::class, 'pdf'])->name('cumplimiento.pdf');
    Route::get('/cumplimiento/excel', [App\Http\Controllers\Reportes\CumplimientoController::class, 'excel'])->name('cumplimiento.excel');

    // Estados de cuenta de un grupo (HU-R5): pantalla, PDF con portada e indice, ZIP con un PDF por persona y Excel
    Route::get('/estados-de-cuenta', [App\Http\Controllers\Reportes\EstadosCuentaController::class, 'index'])->name('estados-cuenta');
    Route::get('/estados-de-cuenta/pdf', [App\Http\Controllers\Reportes\EstadosCuentaController::class, 'pdf'])->name('estados-cuenta.pdf');
    Route::get('/estados-de-cuenta/zip', [App\Http\Controllers\Reportes\EstadosCuentaController::class, 'zip'])->name('estados-cuenta.zip');
    Route::get('/estados-de-cuenta/excel', [App\Http\Controllers\Reportes\EstadosCuentaController::class, 'excel'])->name('estados-cuenta.excel');
});

// Rutas para Admin y Asistente - Asistencia
Route::middleware(['auth', 'role:admin,asistente', 'audit'])->group(function () {
    // Asistencia
    Route::post('asistencia/{asistencium}/cerrar', [AsistenciaController::class, 'cerrarAsistencia'])->name('asistencia.cerrar');
    Route::resource('asistencia', AsistenciaController::class);
});

// Crear cultos: el administrador y quien tenga un acceso de edicion vigente (CultoPolicy::create).
// Va antes del grupo de admin para que 'cultos/create' no lo tome la ruta cultos/{culto}.
Route::middleware(['auth', 'can:create,App\Models\Culto', 'audit'])->group(function () {
    Route::get('cultos/create', [CultoController::class, 'create'])->name('cultos.create');
    Route::post('cultos', [CultoController::class, 'store'])->name('cultos.store');
});

// Solo admin
Route::middleware(['auth', 'role:admin', 'audit'])->group(function () {
    // Cultos (crear esta arriba, con su propia regla)
    Route::resource('cultos', CultoController::class)->except(['create', 'store']);

    // Accesos de edicion: quien puede corregir cultos cerrados, hasta cuando, y lo que cambio con ellos
    Route::get('/admin/accesos', [App\Http\Controllers\AccesoEdicionController::class, 'index'])->name('admin.accesos.index');
    Route::post('/admin/accesos', [App\Http\Controllers\AccesoEdicionController::class, 'store'])->name('admin.accesos.store');
    Route::delete('/admin/accesos/{permiso}', [App\Http\Controllers\AccesoEdicionController::class, 'destroy'])->name('admin.accesos.destroy');

    // Administración de Ministerios / Áreas de Servicio
    Route::prefix('admin/ministerios')->name('admin.ministerios.')->group(function () {
        Route::get('/', [App\Http\Controllers\MinisterioController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\MinisterioController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\MinisterioController::class, 'store'])->name('store');
        Route::get('/{ministerio}/edit', [App\Http\Controllers\MinisterioController::class, 'edit'])->name('edit');
        Route::put('/{ministerio}', [App\Http\Controllers\MinisterioController::class, 'update'])->name('update');
        Route::patch('/{ministerio}/toggle', [App\Http\Controllers\MinisterioController::class, 'toggle'])->name('toggle');
        Route::delete('/{ministerio}', [App\Http\Controllers\MinisterioController::class, 'destroy'])->name('destroy');
    });

    // Administración de Clases de Asistencia
    Route::prefix('admin/clases')->name('admin.clases.')->group(function () {
        Route::get('/', [App\Http\Controllers\ClaseAsistenciaController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\ClaseAsistenciaController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\ClaseAsistenciaController::class, 'store'])->name('store');
        Route::get('/{clase}/edit', [App\Http\Controllers\ClaseAsistenciaController::class, 'edit'])->name('edit');
        Route::put('/{clase}', [App\Http\Controllers\ClaseAsistenciaController::class, 'update'])->name('update');
        Route::delete('/{clase}', [App\Http\Controllers\ClaseAsistenciaController::class, 'destroy'])->name('destroy');
    });

    // Personas y Promesas
    Route::post('personas/{persona}/reiniciar-compromisos', [PersonaController::class, 'reiniciarCompromisos'])->name('personas.reiniciar-compromisos');
    Route::post('personas/{persona}/limpiar-todo', [PersonaController::class, 'limpiarTodo'])->name('personas.limpiar-todo');
    Route::resource('personas', PersonaController::class);
    Route::resource('promesas', PromesaController::class);

    // Compromisos
    Route::get('personas/{persona}/compromisos', [App\Http\Controllers\CompromisoController::class, 'show'])->name('compromisos.show');
    Route::post('compromisos/recalcular', [App\Http\Controllers\CompromisoController::class, 'recalcular'])->name('compromisos.recalcular');

    // Limpieza de personas inactivas (temporal)
    Route::post('personas/limpiar-inactivas', [PersonaController::class, 'limpiarInactivas'])->name('personas.limpiar-inactivas');
    Route::post('personas/resetear-promesas', [PersonaController::class, 'resetearPromesas'])->name('personas.resetear-promesas');

    // Gestión de Usuarios
    // Sin "show": no hay pantalla de detalle (antes daba 500, QA-012)
    Route::resource('usuarios', App\Http\Controllers\UserController::class)->except(['show']);

    // Carga retroactiva de asistencias (import Excel)
    Route::get('/admin/asistencia/importar', [App\Http\Controllers\AsistenciaImportController::class, 'index'])->name('asistencia.importar.index');
    Route::get('/admin/asistencia/importar/plantilla', [App\Http\Controllers\AsistenciaImportController::class, 'descargarPlantilla'])->name('asistencia.importar.plantilla');
    Route::post('/admin/asistencia/importar', [App\Http\Controllers\AsistenciaImportController::class, 'procesar'])->name('asistencia.importar.procesar');

    // Servidores
    Route::get('/admin/servidores', [App\Http\Controllers\ServidorController::class, 'index'])->name('admin.servidores.index');
    Route::get('/admin/servidores/reporte', [App\Http\Controllers\ServidorController::class, 'reporte'])->name('admin.servidores.reporte');
    Route::get('/admin/servidores/reporte/pdf', [App\Http\Controllers\ServidorController::class, 'pdfReporteGeneral'])->name('admin.servidores.reporte.pdf');
    Route::get('/admin/servidores/{servidor}/pdf', [App\Http\Controllers\ServidorController::class, 'pdfIndividual'])->name('admin.servidores.pdf-individual');
    Route::get('/admin/servidores/qr/{culto}', [App\Http\Controllers\ServidorController::class, 'qrCulto'])->name('admin.servidores.qr');

    // Auditoría
    Route::get('/admin/auditoria', [App\Http\Controllers\AuditLogController::class, 'index'])->name('admin.auditoria.index');

    // Historial de ingresos a la plataforma: quién entró, cuándo y a qué hora (+ CSV)
    Route::get('/admin/auditoria/ingresos', [App\Http\Controllers\HistorialIngresosController::class, 'index'])->name('admin.auditoria.ingresos');
    Route::get('/admin/auditoria/ingresos/exportar', [App\Http\Controllers\HistorialIngresosController::class, 'exportar'])->name('admin.auditoria.ingresos.exportar');
});

// Super Admin Panel
Route::middleware(['auth', 'super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/', [SADashboardController::class, 'index'])->name('dashboard');
    Route::get('ingresos-globales', [SADashboardController::class, 'ingresosGlobales'])->name('ingresos-globales');
    Route::get('promesas-globales', [SADashboardController::class, 'promesasGlobales'])->name('promesas-globales');
    Route::resource('tenants', SATenantController::class)->except(['show']);
    Route::get('tenants/{tenant}/branding', [SATenantController::class, 'branding'])->name('tenants.branding');
    Route::put('tenants/{tenant}/branding', [SATenantController::class, 'updateBranding'])->name('tenants.update-branding');
    Route::get('tenants/{tenant}/categories', [SATenantController::class, 'categories'])->name('tenants.categories');
    Route::post('tenants/{tenant}/categories', [SATenantController::class, 'storeCategory'])->name('tenants.store-category');
    Route::put('tenants/{tenant}/categories/{category}', [SATenantController::class, 'updateCategory'])->name('tenants.update-category');
    Route::delete('tenants/{tenant}/categories/{category}', [SATenantController::class, 'destroyCategory'])->name('tenants.destroy-category');
    Route::get('tenants/{tenant}/domains', [SATenantController::class, 'domains'])->name('tenants.domains');
    Route::post('tenants/{tenant}/domains', [SATenantController::class, 'storeDomain'])->name('tenants.store-domain');
    Route::delete('tenants/{tenant}/domains/{domain}', [SATenantController::class, 'destroyDomain'])->name('tenants.destroy-domain');
    Route::get('tenants/{tenant}/users', [SATenantController::class, 'users'])->name('tenants.users');
    Route::post('tenants/{tenant}/toggle', [SATenantController::class, 'toggle'])->name('tenants.toggle');
});

// App de Clase (Registro Horeb genérico - Fase 6)
Route::middleware(['auth'])->group(function () {
    Route::get('/app/clase', [App\Http\Controllers\ClaseAppController::class, 'index'])->name('clase-app.index');
    Route::get('/app/clase/{slug}', [App\Http\Controllers\ClaseAppController::class, 'shell'])->name('clase-app.shell');
    Route::prefix('clase-app/{slug}')->group(function () {
        Route::get('/data', [App\Http\Controllers\ClaseAppController::class, 'data']);
        Route::post('/personas/{tipo}', [App\Http\Controllers\ClaseAppController::class, 'storePersona']);
        Route::put('/personas/{personaId}', [App\Http\Controllers\ClaseAppController::class, 'updatePersona']);
        Route::delete('/personas/{personaId}', [App\Http\Controllers\ClaseAppController::class, 'destroyPersona']);
        Route::post('/personas/{personaId}/convertir', [App\Http\Controllers\ClaseAppController::class, 'convertirVisita']);
        Route::post('/asistencia/toggle', [App\Http\Controllers\ClaseAppController::class, 'toggleAsistencia']);
        Route::post('/visitacion', [App\Http\Controllers\ClaseAppController::class, 'upsertVisitacion']);
    });
});

// Asistencia App (React SPA)
Route::get('/app/asistencia', fn () => view('asistencia-app.shell'));

// Mismo limite de intentos que el login web (QA-013)
Route::post('/asistencia-app/login', [App\Http\Controllers\AsistenciaAppController::class, 'login'])->middleware('throttle:5,1');

Route::middleware(['auth'])->prefix('asistencia-app')->group(function () {
    Route::get('/user', [App\Http\Controllers\AsistenciaAppController::class, 'user']);
    Route::get('/cultos', [App\Http\Controllers\AsistenciaAppController::class, 'cultos']);
    Route::get('/cultos/{culto}/resumen', [App\Http\Controllers\AsistenciaAppController::class, 'resumenCulto']);
    Route::get('/clases', [App\Http\Controllers\AsistenciaAppController::class, 'clases']);
    Route::get('/clases/{clase}/cumpleaneros', [App\Http\Controllers\AsistenciaAppController::class, 'cumpleaneros']);
    Route::post('/asistencia/clase', [App\Http\Controllers\AsistenciaAppController::class, 'guardarAsistenciaClase']);
    Route::post('/asistencia/capilla', [App\Http\Controllers\AsistenciaAppController::class, 'guardarAsistenciaCapilla']);
    Route::post('/personas/quick-add', [App\Http\Controllers\AsistenciaAppController::class, 'quickAddPersona']);
});

require __DIR__.'/auth.php';
