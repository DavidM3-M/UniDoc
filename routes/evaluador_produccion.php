<?php
// Importa la clase Route desde el espacio de nombres Illuminate\Support\Facades
use Illuminate\Support\Facades\Route;
// Importa el controlador EvaluadorProduccionController para manejar las rutas relacionadas con evaluadores de producción
use App\Http\Controllers\EvaluadorProduccion\EvaluadorProduccionController;


// Define un grupo de rutas con configuraciones específicas para el rol "Evaluador Producción"
Route::group([
    // Aplica los middlewares 'api', 'auth:api' y 'role:Evaluador Produccion' para proteger las rutas
    'middleware' =>[ 'api', 'auth:api', 'role:Evaluador Produccion'],
    // Establece un prefijo 'evaluadorProduccion' para las rutas dentro de este grupo
    'prefix' => 'evaluadorProduccion'
], function () {
    // Ruta para obtener todas las producciones académicas
    Route::get('obtener-producciones', [EvaluadorProduccionController::class, 'obtenerProducciones']);
    // Ruta para ver las producciones académicas asociadas a un usuario específico
    Route::get('ver-producciones-por-usuario/{user_id}', [EvaluadorProduccionController::class, 'verProduccionesPorUsuario']);
    // Ruta para actualizar el estado de un documento de producción académica específico
    Route::put('actualizar-produccion/{documento_id}', [EvaluadorProduccionController::class, 'actualizarEstadoDocumento']);


});