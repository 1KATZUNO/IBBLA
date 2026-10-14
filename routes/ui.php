<?php

use App\Http\Controllers\BuscadorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la barra superior: buscador global
|--------------------------------------------------------------------------
| Las puede abrir cualquier usuario con sesion (cada rol ve lo suyo en el buscador).
| Se cargan desde routes/web.php.
*/

Route::middleware(['auth'])->group(function () {
    // Buscador global: JSON para la barra superior, pagina de resultados para el formulario
    Route::get('/buscar', [BuscadorController::class, 'index'])->middleware('throttle:120,1')->name('buscar');
});
