<?php
// Importa la clase Route desde el espacio de nombres Illuminate\Support\Facades
use Illuminate\Support\Facades\Route;
// Importa el controlador InstitucionController para manejar las rutas relacionadas con instituciones educativas
use App\Http\Controllers\Instituciones\InstitucionController;

// Define un grupo de rutas con configuraciones específicas para instituciones
Route::group([
// Aplica el middleware 'api' para proteger las rutas
    'middleware' => 'api',
// Establece un prefijo 'instituciones' para las rutas dentro de este grupo
    'prefix' => 'instituciones'
], function () {
// Ruta para obtener el catálogo oficial de instituciones educativas (MEN / datos.gov.co)
    Route::get('/', [InstitucionController::class, 'obtenerInstituciones']);
});
