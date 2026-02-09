<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PrincipalController;
use App\Http\Controllers\RecuentoController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PromesaController;
use App\Http\Controllers\CultoController;
use App\Http\Controllers\IngresosAsistenciaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Principal - Para todos los usuarios autenticados
Route::middleware(['auth'])->group(function () {
    Route::get('/principal', [PrincipalController::class, 'index'])->name('principal');
});

// Dashboard - Solo para Admin y Tesorero
Route::middleware(['auth', 'role:admin,tesorero', 'audit'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Ruta para miembros - Mi Perfil
Route::middleware(['auth', 'role:miembro'])->group(function () {
    Route::get('/mi-perfil', [App\Http\Controllers\MiPerfilController::class, 'index'])->name('mi-perfil.index');
});

// Rutas para Admin y Tesorero - Recuento e Ingresos
Route::middleware(['auth', 'role:admin,tesorero'])->group(function () {
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
        Route::get('/culto-cerrado/{culto}', [RecuentoController::class, 'verCultoCerrado'])->name('ver-culto-cerrado');
        Route::get('/{sobre}/edit', [RecuentoController::class, 'edit'])->name('edit');
        Route::put('/{sobre}', [RecuentoController::class, 'update'])->name('update');
        Route::delete('/{sobre}', [RecuentoController::class, 'destroy'])->name('destroy');
        // Firmas del recuento
        Route::post('/firmas/{culto}', function (\Illuminate\Http\Request $request, \App\Models\Culto $culto) {
            $data = $request->validate([
                'firma_pastor' => 'nullable|string|max:255',
                'firma_pastor_imagen' => 'nullable|string',
                'firmas_tesoreros' => 'nullable|array',
                'firmas_tesoreros.*.nombre' => 'nullable|string|max:255',
                'firmas_tesoreros.*.imagen' => 'nullable|string',
            ]);

            // Procesar tesoreros (filtrar vacíos)
            $tesoreros = [];
            $tesorerosImagenes = [];
            if (!empty($data['firmas_tesoreros'])) {
                foreach ($data['firmas_tesoreros'] as $t) {
                    $nombre = $t['nombre'] ?? '';
                    $imagen = $t['imagen'] ?? '';
                    if (!empty($nombre) || !empty($imagen)) {
                        $tesoreros[] = $nombre;
                        $tesorerosImagenes[] = [
                            'nombre' => $nombre,
                            'imagen' => $imagen,
                        ];
                    }
                }
            }

            $culto->update([
                'firma_pastor' => $data['firma_pastor'] ?? null,
                'firma_pastor_imagen' => $data['firma_pastor_imagen'] ?? null,
                'firmas_tesoreros' => $tesoreros,
                'firmas_tesoreros_imagenes' => $tesorerosImagenes,
            ]);
            return redirect()->route('recuento.index', ['culto_id' => $culto->id])
                ->with('success', 'Firmas de recuento actualizadas.');
        })->name('firmas.update');
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
});

// Rutas para Admin y Asistente - Asistencia
Route::middleware(['auth', 'role:admin,asistente', 'audit'])->group(function () {
    // Asistencia
    Route::post('asistencia/{asistencium}/cerrar', [AsistenciaController::class, 'cerrarAsistencia'])->name('asistencia.cerrar');
    Route::resource('asistencia', AsistenciaController::class);
    
    // Reportes de asistencia
    Route::get('/ingresos-asistencia/asistencia', [IngresosAsistenciaController::class, 'asistencia'])->name('ingresos-asistencia.asistencia');
    Route::get('/ingresos-asistencia/pdf-asistencia', [IngresosAsistenciaController::class, 'pdfAsistencia'])->name('ingresos-asistencia.pdf-asistencia');
    Route::get('/ingresos-asistencia/pdf-asistencia-culto/{culto}', [IngresosAsistenciaController::class, 'pdfAsistenciaCulto'])->name('ingresos-asistencia.pdf-asistencia-culto');
    Route::get('/ingresos-asistencia/pdf-asistencia-mes', [IngresosAsistenciaController::class, 'pdfAsistenciaMes'])->name('ingresos-asistencia.pdf-asistencia-mes');
});

// Solo admin
Route::middleware(['auth', 'role:admin', 'audit'])->group(function () {
    // Cultos
    Route::resource('cultos', CultoController::class);
    
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
    Route::post('personas/quick-store', [PersonaController::class, 'quickStore'])->name('personas.quick-store');
    Route::post('personas/{persona}/reiniciar-compromisos', [PersonaController::class, 'reiniciarCompromisos'])->name('personas.reiniciar-compromisos');
    Route::post('personas/{persona}/limpiar-todo', [PersonaController::class, 'limpiarTodo'])->name('personas.limpiar-todo');
    Route::get('personas/reporte-pdf', [PersonaController::class, 'reportePdf'])->name('personas.reporte-pdf');
    Route::get('personas/reporte-general', [PersonaController::class, 'reporteGeneral'])->name('personas.reporte-general');
    Route::resource('personas', PersonaController::class);
    Route::resource('promesas', PromesaController::class);
    
    // Compromisos
    Route::get('personas/{persona}/compromisos', [App\Http\Controllers\CompromisoController::class, 'show'])->name('compromisos.show');
    Route::post('compromisos/recalcular', [App\Http\Controllers\CompromisoController::class, 'recalcular'])->name('compromisos.recalcular');
    
    // Limpieza de personas inactivas (temporal)
    Route::post('personas/limpiar-inactivas', [PersonaController::class, 'limpiarInactivas'])->name('personas.limpiar-inactivas');
    Route::post('personas/resetear-promesas', [PersonaController::class, 'resetearPromesas'])->name('personas.resetear-promesas');
    
    // Gestión de Usuarios
    Route::resource('usuarios', App\Http\Controllers\UserController::class);

    // Auditoría
    Route::get('/admin/auditoria', [App\Http\Controllers\AuditLogController::class, 'index'])->name('admin.auditoria.index');
});

require __DIR__.'/auth.php';
