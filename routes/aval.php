<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Convocatoria\AvalController;
use App\Http\Controllers\TalentoHumano\PostulacionController;
use App\Http\Controllers\Convocatoria\RectoriaVerificacionDocumentosController;
use App\Http\Controllers\Convocatoria\VicerrectoriaVerificacionDocumentosController;

// Grupo Rectoría
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Rectoria'],
    'prefix' => 'rectoria'
], function () {
    Route::post('aval-hoja-vida/{userId}', [AvalController::class, 'avalHojaVida']);
    Route::get('usuarios/{userId}/avales', [AvalController::class, 'verAvales']);
    Route::get('usuarios', [AvalController::class, 'listarUsuarios']);

    // Rutas para la verificación de documentos
    Route::get('obtener-documentos/{estado}', [RectoriaVerificacionDocumentosController::class, 'obtenerDocumentosPorEstado']);
    Route::put('actualizar-documento/{id}', [RectoriaVerificacionDocumentosController::class, 'actualizarEstadoDocumento']);
    Route::get('listar-docentes', [RectoriaVerificacionDocumentosController::class, 'listarDocentes']);
    Route::get('ver-documentos-docente/{id}', [RectoriaVerificacionDocumentosController::class, 'verDocumentosPorDocente']);
    Route::get('ver-documento/{id}', [RectoriaVerificacionDocumentosController::class, 'verDocumento']);
    Route::get('documentos/{userId}/{categoria}', [RectoriaVerificacionDocumentosController::class, 'verDocumentosPorCategoria']);

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

    // Rutas para la verificación de documentos
    Route::get('obtener-documentos/{estado}', [VicerrectoriaVerificacionDocumentosController::class, 'obtenerDocumentosPorEstado']);
    Route::put('actualizar-documento/{id}', [VicerrectoriaVerificacionDocumentosController::class, 'actualizarEstadoDocumento']);
    Route::get('listar-docentes', [VicerrectoriaVerificacionDocumentosController::class, 'listarDocentes']);
    Route::get('ver-documentos-docente/{id}', [VicerrectoriaVerificacionDocumentosController::class, 'verDocumentosPorDocente']);
    Route::get('ver-documento/{id}', [VicerrectoriaVerificacionDocumentosController::class, 'verDocumento']);
    Route::get('documentos/{userId}/{categoria}', [VicerrectoriaVerificacionDocumentosController::class, 'verDocumentosPorCategoria']);

    // Agregado para la hoja de vida
    Route::get('hoja-de-vida-pdf/{idUsuario}', [PostulacionController::class, 'generarHojaDeVidaPDFSimple']);
});

// Grupo Talento Humano
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Talento Humano'],
    'prefix' => 'talento-humano'
], function () {
    Route::post('aval-hoja-vida/{userId}', [AvalController::class, 'avalHojaVida']);
    Route::get('usuarios/{userId}/avales', [AvalController::class, 'verAvales']);
    Route::get('usuarios', [AvalController::class, 'listarUsuarios']); // <-- NUEVA RUTA
    Route::get('ver-documento/{id}', [RectoriaVerificacionDocumentosController::class, 'verDocumento']);
    Route::get('documentos/{userId}/{categoria}', [RectoriaVerificacionDocumentosController::class, 'verDocumentosPorCategoria']);
});

