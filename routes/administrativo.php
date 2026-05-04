<?php

use App\Http\Controllers\TalentoHumano\ContratacionController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas protegidas para el rol Administrativo.
 * Los usuarios administrativos pueden consultar sus propias contrataciones.
 */
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Administrativo'],
    'prefix'     => 'administrativo',
], function () {
    // Ver las contrataciones propias del usuario administrativo
    Route::get('ver-contratacion', [ContratacionController::class, 'obtenerContratacionUsuario']);

    // Ver la bitácora legal de una contratación propia
    Route::get('ver-contratacion/{id_contratacion}/bitacora', [ContratacionController::class, 'obtenerBitacora']);
});
