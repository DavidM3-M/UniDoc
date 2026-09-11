<?php
// Rutas públicas (sin auth de Admin) del catálogo de Formación educativa/SNIES, consumidas por
// el Aspirante/Docente en la cascada Institución → Programa del formulario de Estudio.
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Publico\FormacionEducativaPublicoController;

Route::group([
    'middleware' => 'api',
], function () {
    Route::get('instituciones-snies', [FormacionEducativaPublicoController::class, 'obtenerInstituciones']);
    Route::get('programas-formacion-educativa', [FormacionEducativaPublicoController::class, 'buscarProgramas']);
});
