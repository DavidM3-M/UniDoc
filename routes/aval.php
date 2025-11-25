<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Convocatoria\AvalController;

// Grupo Rectoría
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Rectoria'],
    'prefix' => 'rectoria'
], function () {
    Route::post('aval-hoja-vida/{userId}', [AvalController::class, 'avalHojaVida']);
    Route::get('usuarios/{userId}/avales', [AvalController::class, 'verAvales']);
    Route::get('usuarios', [AvalController::class, 'listarUsuarios']); // <-- NUEVA RUTA
});

// Grupo Vicerrectoría
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Vicerrectoria'],
    'prefix' => 'vicerrectoria'
], function () {
    Route::post('aval-hoja-vida/{userId}', [AvalController::class, 'avalHojaVida']);
    Route::get('usuarios/{userId}/avales', [AvalController::class, 'verAvales']);
    Route::get('usuarios', [AvalController::class, 'listarUsuarios']); // <-- NUEVA RUTA
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