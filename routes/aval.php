<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Convocatoria\AvalController;
use App\Http\Controllers\TalentoHumano\PostulacionController;

// Grupo Rectoría
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Rectoria'],
    'prefix' => 'rectoria'
], function () {
    Route::post('aval-hoja-vida/{userId}', [AvalController::class, 'avalHojaVida']);
    Route::get('usuarios/{userId}/avales', [AvalController::class, 'verAvales']);
    Route::get('usuarios', [AvalController::class, 'listarUsuarios']);

    // Agregado para la hoja de vida

    Route::get('/hoja-de-vida-pdf/{idUsuario}', [PostulacionController::class, 'generarHojaDeVidaPDFSimple']);
});

// Grupo Vicerrectoría
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Vicerrectoria'],
    'prefix' => 'vicerrectoria'
], function () {
    Route::post('aval-hoja-vida/{userId}', [AvalController::class, 'avalHojaVida']);
    Route::get('usuarios/{userId}/avales', [AvalController::class, 'verAvales']);
    Route::get('usuarios', [AvalController::class, 'listarUsuarios']);

    // Agregado para la hoja de vida
    Route::get('hoja-de-vida-pdf/{idConvocatoria}/{idUsuario}', [PostulacionController::class, 'generarHojaDeVidaPDF']);
});

// Grupo Talento Humano
Route::group([
    'middleware' => ['api', 'auth:api', 'role:TalentoHumano'],
    'prefix' => 'talento-humano'
], function () {
    Route::post('aval-hoja-vida/{userId}', [AvalController::class, 'avalHojaVida']);
    Route::get('usuarios/{userId}/avales', [AvalController::class, 'verAvales']);
    Route::get('usuarios', [AvalController::class, 'listarUsuarios']); // <-- NUEVA RUTA
});
