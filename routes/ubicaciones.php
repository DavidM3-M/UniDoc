<?php
// Importa la clase Route desde el espacio de nombres Illuminate\Support\Facades
use Illuminate\Support\Facades\Route;
// Importa el controlador UbicacionController para manejar las rutas relacionadas con ubicaciones
use App\Http\Controllers\Ubicaciones\UbicacionController;

// Define un grupo de rutas con configuraciones específicas para ubicaciones
Route::group([
// Aplica el middleware 'api' para proteger las rutas
    'middleware' => 'api',
// Establece un prefijo 'ubicaciones' para las rutas dentro de este grupo
    'prefix' => 'ubicaciones'
], function () {
// Ruta para obtener la lista de países
    Route::get('paises', [UbicacionController::class, 'obtenerPaises']);
// Ruta para obtener la lista de departamentos
    Route::get('departamentos', [UbicacionController::class, 'obtenerDepartamentos']);
// Ruta para obtener los departamentos de un país específico
    Route::get('departamentos/{pais_id}', [UbicacionController::class, 'obtenerDepartamentosPorPais']);
// Ruta para obtener los municipios de un departamento específico
    Route::get('municipios/{departamento_id}', [UbicacionController::class, 'obtenerMunicipiosPorDepartamento']);
// Ruta para obtener la información completa de ubicación por municipio_id
    Route::get('municipio/{municipio_id}', [UbicacionController::class, 'obtenerUbicacionPorMunicipio']);
});