<?php

// Importa la clase Route desde el espacio de nombres Illuminate\Support\Facades

use App\Http\Controllers\ApoyoProfesoral\FiltrarDocentesController;
use App\Http\Controllers\ApoyoProfesoral\VerificacionDocumentosController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApoyoProfesoral\GenerarCertificadosController;

// Define un grupo de rutas con configuraciones específicas
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Apoyo Profesoral'],
    'prefix' => 'apoyoProfesoral',
], function () {
    // Rutas para la verificación de documentos
    Route::get('obtener-documentos/{estado}', [VerificacionDocumentosController::class, 'obtenerDocumentosPorEstado']);
    Route::put('actualizar-documento/{id}', [VerificacionDocumentosController::class, 'actualizarEstadoDocumento']);
    Route::get('listar-docentes', [VerificacionDocumentosController::class, 'listarDocentes']);
    Route::get('ver-documentos-docente/{id}', [VerificacionDocumentosController::class, 'verDocumentosPorDocente']);
    // Rutas para filtrar docentes por estudio
    Route::get('filtrar-docentes-estudio/{tipo}', [FiltrarDocentesController::class, 'filtrarPorTipoEstudio']);
    Route::get('mostrar-todos-estudios', [FiltrarDocentesController::class, 'mostrarTodosLosEstudios']);
    Route::get('filtrar-docentes-estudio-id/{id}', [FiltrarDocentesController::class, 'obtenerEstudiosPorDocente']);
     // Rutas para filtrar docentes por idioma
    Route::get('mostrar-todos-idioma', [FiltrarDocentesController::class, 'mostrarTodosLosIdiomas']);
    Route::get('filtrar-docentes-idioma/{idioma}', [FiltrarDocentesController::class, 'filtrarPorNivelIdioma']);
    Route::get('filtrar-docentes-idioma-id/{id}', [FiltrarDocentesController::class, 'obtenerIdiomasPorDocente']);
    // Rutas para filtrar docentes por producción académica
    Route::get('mostrar-todos-produccion', [FiltrarDocentesController::class, 'mostrarTodaLaProduccionAcademica']);
    Route::get('filtrar-docentes-produccion/{id}', [FiltrarDocentesController::class, 'obtenerProduccionAcademicaPorDocente']);
    Route::get('filtrar-docentes-ambito/{ambitoId}', [FiltrarDocentesController::class, 'filtrarPorAmbitoDivulgacion']);

    // Rutas para filtrar docentes por experiencia profesional
    Route::get('mostrar-todas-experiencia', [FiltrarDocentesController::class, 'obtenerTodasLasExperiencias']);
    Route::get('filtrar-docentes-experiencia-id/{id}', [FiltrarDocentesController::class, 'obtenerExperienciasPorDocente']);
    Route::get('filtrar-docentes-tipo-experiencia/{tipo}', [FiltrarDocentesController::class, 'filtrarPorTipoExperiencia']);


    // Rutas para generar certificados
    Route::post('crear-certificados-masivos', [GenerarCertificadosController::class, 'crearCertificadosMasivos']);





});
