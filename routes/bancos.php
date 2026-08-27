<?php
// Importa la clase Route desde el espacio de nombres Illuminate\Support\Facades
use Illuminate\Support\Facades\Route;
// Importa el controlador BancoController para manejar las rutas relacionadas con bancos
use App\Http\Controllers\Bancos\BancoController;

// Define un grupo de rutas con configuraciones específicas para bancos
Route::group([
// Aplica el middleware 'api' para proteger las rutas
    'middleware' => 'api',
// Establece un prefijo 'bancos' para las rutas dentro de este grupo
    'prefix' => 'bancos'
], function () {
// Ruta para obtener el catálogo de bancos y corporaciones financieras (datos.gov.co)
    Route::get('/', [BancoController::class, 'obtenerBancos']);
});
