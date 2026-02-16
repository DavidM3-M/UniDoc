<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Publico\ConvocatoriaPublicaController;

/*
|--------------------------------------------------------------------------
| Rutas Públicas (Sin autenticación)
|--------------------------------------------------------------------------
|
| Estas rutas son accesibles sin necesidad de iniciar sesión
|
*/

Route::prefix('publico')->group(function () {
    // Obtener todas las convocatorias
    Route::get('convocatorias', [ConvocatoriaPublicaController::class, 'obtenerConvocatorias']);

    // Obtener una convocatoria específica por ID
    Route::get('convocatorias/{id}', [ConvocatoriaPublicaController::class, 'obtenerConvocatoriaPorId']);
});
