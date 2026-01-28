<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Coordinador\ProcesoAprobacionController;
use App\Http\Controllers\Convocatoria\CoordinadorVerificacionDocumentosController;

Route::group([
    'middleware' => ['api', 'auth:api', 'role:Coordinador'],
    'prefix' => 'coordinador'
], function () {
    Route::get('evaluaciones', [ProcesoAprobacionController::class, 'index']);
    Route::get('evaluaciones-con-usuarios', [ProcesoAprobacionController::class, 'evaluacionesConUsuarios']);
    Route::post('evaluaciones', [ProcesoAprobacionController::class, 'store']);
    Route::get('evaluaciones/{id}', [ProcesoAprobacionController::class, 'show']);
    Route::put('evaluaciones/{id}', [ProcesoAprobacionController::class, 'update']);

    Route::get('postulaciones', [ProcesoAprobacionController::class, 'listarPostulacionesPorConvocatoria']);
    Route::get('aspirantes', [ProcesoAprobacionController::class, 'listarAspirantesTalentoHumano']);
    Route::get('aspirantes/{id}', [ProcesoAprobacionController::class, 'verAspirante']);
    Route::get('convocatorias', [ProcesoAprobacionController::class, 'listarConvocatoriasConAspirantes']);

    // Documentos de aspirantes
    Route::get('obtener-documentos/{estado}', [CoordinadorVerificacionDocumentosController::class, 'obtenerDocumentosPorEstado']);
    Route::put('actualizar-documento/{id}', [CoordinadorVerificacionDocumentosController::class, 'actualizarEstadoDocumento']);
    Route::get('listar-docentes', [CoordinadorVerificacionDocumentosController::class, 'listarDocentes']);
    Route::get('ver-documentos-docente/{id}', [CoordinadorVerificacionDocumentosController::class, 'verDocumentosPorDocente']);
    Route::get('ver-documento/{id}', [CoordinadorVerificacionDocumentosController::class, 'verDocumento']);
    Route::get('documentos/{userId}/{categoria}', [CoordinadorVerificacionDocumentosController::class, 'verDocumentosPorCategoria']);

    Route::get('plantillas', [ProcesoAprobacionController::class, 'listarPlantillas']);
    Route::post('plantillas', [ProcesoAprobacionController::class, 'crearPlantilla']);
    Route::get('plantillas/{id}', [ProcesoAprobacionController::class, 'verPlantilla']);
    Route::put('plantillas/{id}', [ProcesoAprobacionController::class, 'actualizarPlantilla']);
});
