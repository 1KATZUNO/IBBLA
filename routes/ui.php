<?php

use App\Http\Controllers\BuscadorController;
use App\Http\Controllers\ManualController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la barra superior: buscador global y manual de usuario
|--------------------------------------------------------------------------
| Las puede abrir cualquier usuario con sesion (cada rol ve lo suyo en el buscador).
| Se cargan desde routes/web.php.
*/

Route::middleware(['auth'])->group(function () {
    // Buscador global: JSON para la barra superior, pagina de resultados para el formulario
    Route::get('/buscar', [BuscadorController::class, 'index'])->middleware('throttle:120,1')->name('buscar');

    // Manual de usuario: indice, una pagina por modulo y PDF
    Route::prefix('manual')->name('manual.')->group(function () {
        Route::get('/', [ManualController::class, 'index'])->name('index');
        Route::get('/pdf', [ManualController::class, 'pdfCompleto'])->name('pdf');
        Route::get('/{slug}', [ManualController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('pagina');
        Route::get('/{slug}/pdf', [ManualController::class, 'pdfPagina'])->where('slug', '[a-z0-9\-]+')->name('pagina.pdf');
    });
});
