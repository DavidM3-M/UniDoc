<?php
// Importa los controladores necesarios para manejar las rutas relacionadas con Talento Humano
use App\Http\Controllers\TalentoHumano\ContratacionController;
use App\Http\Controllers\TalentoHumano\ConvocatoriaController;
use App\Http\Controllers\TalentoHumano\ConvocatoriaAvalController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TalentoHumano\PostulacionController;

// RUTAS PÚBLICAS PARA ASPIRANTES Y DOCENTES (FUERA DEL GRUPO DE TALENTO HUMANO)
Route::middleware(['api', 'auth:api'])->group(function () {
    // Para Aspirantes
    Route::get('/aspirante/convocatoria/{id_convocatoria}', [ConvocatoriaController::class, 'obtenerConvocatoriaPublicaPorId']);

    // Para Docentes
    Route::get('/docente/convocatoria/{id_convocatoria}', [ConvocatoriaController::class, 'obtenerConvocatoriaPublicaPorId']);
});

// Define un grupo de rutas con configuraciones específicas para el rol "Talento Humano"
Route::group([
    // Aplica los middlewares 'api', 'auth:api' y 'role:Talento Humano' para proteger las rutas
    'middleware' =>[ 'api', 'auth:api', 'role:Talento Humano'],
    // Establece un prefijo 'talentoHumano' para las rutas dentro de este grupo
    'prefix' => 'talentoHumano'
], function () {

    // Rutas relacionadas con convocatorias
    Route::get('obtener-convocatorias',[ConvocatoriaController::class, 'obtenerConvocatorias']);
    Route::get('obtener-tipos-cargo',[ConvocatoriaController::class, 'obtenerTiposCargo']);
    Route::get('obtener-convocatoria/{id}',[ConvocatoriaController::class, 'obtenerConvocatoriaPorId']);
    Route::post('crear-convocatoria',[ConvocatoriaController::class, 'crearConvocatoria']);
    Route::put('actualizar-convocatoria/{id}',[ConvocatoriaController::class, 'actualizarConvocatoria']);
    Route::delete('eliminar-convocatoria/{id}',[ConvocatoriaController::class, 'eliminarConvocatoria']);

    // Rutas para gestionar experiencias requeridas (Talento Humano)
    Route::get('experiencias-requeridas',[ExperienciaRequeridaController::class, 'index']);
    Route::post('experiencias-requeridas',[ExperienciaRequeridaController::class, 'store']);
    Route::get('experiencias-requeridas/{id}',[ExperienciaRequeridaController::class, 'show']);
    Route::put('experiencias-requeridas/{id}',[ExperienciaRequeridaController::class, 'update']);
    Route::delete('experiencias-requeridas/{id}',[ExperienciaRequeridaController::class, 'destroy']);

    // Rutas relacionadas con postulaciones
    Route::get('obtener-postulaciones',[PostulacionController::class, 'obtenerPostulaciones']);
    // Route::get('obtener-postulaciones-convocatoria/{idConvocatoria}',[PostulacionController::class, 'obtenerPorConvocatoria']);
    Route::delete('eliminar-postulacion/{idPostulacion}',[PostulacionController::class, 'eliminarPostulacion']);
    Route::put('actualizar-postulacion/{idPostulacion}',[PostulacionController::class, 'actualizarEstadoPostulacion']);
    Route::get('hoja-de-vida-pdf/{idConvocatoria}/{idUsuario}', [PostulacionController::class, 'generarHojaDeVidaPDF']);

    // Rutas relacionadas con contrataciones
    Route::post('crear-contratacion/{user_id}',[ContratacionController::class, 'crearContratacion']);
    Route::put('actualizar-contratacion/{id_contratacion}',[ContratacionController::class, 'actualizarContratacion']);
    Route::delete('eliminar-contratacion/{id}',[ContratacionController::class, 'eliminarContratacion']);
    Route::get('obtener-contratacion/{id_contratacion}',[ContratacionController::class, 'obtenerContratacionPorId']);
    Route::get('obtener-contrataciones',[ContratacionController::class, 'obtenerTodasLasContrataciones']);

    // Rutas para gestionar avales por convocatoria
    Route::get('avales',[ConvocatoriaAvalController::class, 'index']);
    Route::post('avales',[ConvocatoriaAvalController::class, 'store']);
    Route::put('avales/{id}',[ConvocatoriaAvalController::class, 'update']);

    // Nueva Ruta para exportar convocatorias a Excel (Brayan Cuellar)
    Route::get('exportar-convocatorias-excel',[ConvocatoriaController::class, 'exportarConvocatoriasExcel']);

});
