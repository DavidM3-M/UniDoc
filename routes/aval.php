<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AvalController;


// Grupo Rectoría
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Rectoría'],
    'prefix' => 'rectoria'
], function () {
    // Registrar aval desde Rectoría
    Route::post('aval-hoja-vida/{userId}', [AvalController::class, 'avalHojaVida']);
    // Consultar avales de un usuario
    Route::get('usuarios/{userId}/avales', [AvalController::class, 'verAvales']);
});

// Grupo Vicerrectoría
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Vicerrectoría'],
    'prefix' => 'vicerrectoria'
], function () {
    // Registrar aval desde Vicerrectoría
    Route::post('aval-hoja-vida/{userId}', [AvalController::class, 'avalHojaVida']);
    // Consultar avales de un usuario
    Route::get('usuarios/{userId}/avales', [AvalController::class, 'verAvales']);
});

// Grupo Talento Humano
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Talento Humano'],
    'prefix' => 'talento-humano'
], function () {
    // Registrar aval desde Talento Humano
    Route::post('aval-hoja-vida/{userId}', [AvalController::class, 'avalHojaVida']);
    // Consultar avales de un usuario
    Route::get('usuarios/{userId}/avales', [AvalController::class, 'verAvales']);
});