<?php

// Importa la clase Route desde el espacio de nombres Illuminate\Support\Facades

use App\Http\Controllers\ApoyoProfesoral\EscalafonDocenteController;
use App\Http\Controllers\ApoyoProfesoral\EvaluacionDocenteController;
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

    // Ruta para listar docentes con su puntaje total y categoría (escalafón)
    Route::get('listar-docentes-puntaje', [FiltrarDocentesController::class, 'listarDocentesConPuntaje']);

    // Rutas para asignar y consultar la evaluación docente.
    // Apoyo Profesoral es quien asigna la calificación; el docente solo puede consultarla.
    Route::get('listar-evaluaciones', [EvaluacionDocenteController::class, 'listarEvaluaciones']);
    Route::get('ver-evaluacion/{userId}', [EvaluacionDocenteController::class, 'verEvaluacionDocente']);
    Route::post('asignar-evaluacion/{userId}', [EvaluacionDocenteController::class, 'asignarEvaluacionDocente']);
    Route::put('actualizar-evaluacion/{userId}', [EvaluacionDocenteController::class, 'actualizarEvaluacionDocente']);

    // Escalafón docente. El ascenso es un acto de Apoyo Profesoral: el motor solo dice quién es
    // elegible, y la categoría vigente vive en `historial_escalon_docente`.
    // Los escalones y sus requisitos los administra el rol Administrador, no este grupo.
    Route::get('escalafon/periodos', [EscalafonDocenteController::class, 'listarPeriodos']);
    Route::post('escalafon/periodos', [EscalafonDocenteController::class, 'crearPeriodo']);
    Route::put('escalafon/periodos/{id}', [EscalafonDocenteController::class, 'actualizarPeriodo']);
    Route::post('escalafon/periodos/{id}/cerrar', [EscalafonDocenteController::class, 'cerrarPeriodo']);

    // Bandeja: ?estado_antiguedad= filtra por el semáforo, ?periodo_ascenso_id= cambia el corte.
    Route::get('escalafon/docentes', [EscalafonDocenteController::class, 'listarDocentes']);
    Route::get('escalafon/docentes/{userId}', [EscalafonDocenteController::class, 'verDocente']);
    // No hay ruta de ingreso: entrar al escalafón no lo decide nadie. `ContratacionObserver` mete al
    // docente en el primer escalón en cuanto Talento Humano le registra la contratación de planta.
    Route::post('escalafon/docentes/{userId}/ascender', [EscalafonDocenteController::class, 'ascender']);
    Route::post('escalafon/historial/{id}/revertir', [EscalafonDocenteController::class, 'revertir']);

    // Rutas para generar certificados
    Route::post('crear-certificados-masivos', [GenerarCertificadosController::class, 'crearCertificadosMasivos']);
    // Editar / eliminar certificados ya generados (solo registros marcados como certificado)
    Route::put('actualizar-certificado/{id}', [GenerarCertificadosController::class, 'actualizarCertificado']);
    Route::delete('eliminar-certificado/{id}', [GenerarCertificadosController::class, 'eliminarCertificado']);





});
