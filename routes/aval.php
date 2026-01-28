<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Convocatoria\AvalController;
use App\Http\Controllers\TalentoHumano\PostulacionController;
use App\Http\Controllers\TalentoHumano\ConvocatoriaController;
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

    // Usuarios con convocatorias (como Vicerrectoría)
    Route::get('usuarios-convocatorias', [App\Http\Controllers\Convocatoria\VicerrectoriaController::class, 'index']);

    // Rutas para la verificación de documentos
    Route::get('obtener-documentos/{estado}', [RectoriaVerificacionDocumentosController::class, 'obtenerDocumentosPorEstado']);
    Route::put('actualizar-documento/{id}', [RectoriaVerificacionDocumentosController::class, 'actualizarEstadoDocumento']);
    Route::get('listar-docentes', [RectoriaVerificacionDocumentosController::class, 'listarDocentes']);
    Route::get('ver-documentos-docente/{id}', [RectoriaVerificacionDocumentosController::class, 'verDocumentosPorDocente']);
    Route::get('ver-documento/{id}', [RectoriaVerificacionDocumentosController::class, 'verDocumento']);
    Route::get('documentos/{userId}/{categoria}', [RectoriaVerificacionDocumentosController::class, 'verDocumentosPorCategoria']);

    // Convocatorias (lectura)
    Route::get('obtener-convocatorias', [ConvocatoriaController::class, 'obtenerConvocatorias']);
    Route::get('obtener-convocatoria/{id}', [ConvocatoriaController::class, 'obtenerConvocatoriaPorId']);

    // Convocatorias con aspirantes
    Route::get('convocatorias', [App\Http\Controllers\Coordinador\ProcesoAprobacionController::class, 'listarConvocatoriasConAspirantes']);
    // Convocatorias con aspirantes
    Route::get('convocatorias', [App\Http\Controllers\Coordinador\ProcesoAprobacionController::class, 'listarConvocatoriasConAspirantes']);

    // Agregado para la hoja de vida

    Route::get('/hoja-de-vida-pdf/{idUsuario}', [PostulacionController::class, 'generarHojaDeVidaPDFSimple']);

    // Evaluaciones del coordinador
    Route::get('evaluaciones/{userId}', [App\Http\Controllers\Coordinador\ProcesoAprobacionController::class, 'show']);
    Route::get('evaluaciones-con-usuarios', [App\Http\Controllers\Coordinador\ProcesoAprobacionController::class, 'evaluacionesConUsuarios']);
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

    // Postulaciones (lectura)
    Route::get('obtener-postulaciones', [PostulacionController::class, 'obtenerPostulaciones']);
    // Postulaciones de un usuario específico
    Route::get('usuarios/{userId}/postulaciones', [App\Http\Controllers\Convocatoria\VicerrectoriaController::class, 'postulacionesPorUsuario']);

    // Evaluaciones del coordinador
    Route::get('evaluaciones/{userId}', [App\Http\Controllers\Coordinador\ProcesoAprobacionController::class, 'show']);
    Route::get('evaluaciones-con-usuarios', [App\Http\Controllers\Coordinador\ProcesoAprobacionController::class, 'evaluacionesConUsuarios']);
    Route::get('obtener-convocatorias', [ConvocatoriaController::class, 'obtenerConvocatorias']);
    Route::get('obtener-convocatoria/{id}', [ConvocatoriaController::class, 'obtenerConvocatoriaPorId']);
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

// Grupo Coordinación
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Coordinador'],
    'prefix' => 'coordinador'
], function () {
    Route::post('aval-hoja-vida/{userId}', [AvalController::class, 'avalHojaVida']);
    Route::get('usuarios/{userId}/avales', [AvalController::class, 'verAvales']);
    Route::get('usuarios', [AvalController::class, 'listarUsuarios']);
});

