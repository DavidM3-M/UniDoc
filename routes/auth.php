<?php
// Importa la clase Route desde el espacio de nombres Illuminate\Support\Facades
use Illuminate\Support\Facades\Route;
// Importa el controlador de autenticación
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
// Define un grupo de rutas con configuraciones específicas para autenticación
Route::group([
    // Aplica el middleware 'api' para proteger las rutas
    'middleware' => 'api',
    // Establece un prefijo 'auth' para las rutas dentro de este grupo
    'prefix' => 'auth'
], function () {
    // Ruta para registrar un nuevo usuario
    Route::post('registrar-usuario', [AuthController::class, 'registrar'])
        ->middleware('throttle:10,1');
    // Ruta para iniciar sesión (máx 10 intentos por minuto por IP)
    Route::post('iniciar-sesion', [AuthController::class, 'iniciarSesion'])
        ->middleware('throttle:10,1');
    // Ruta para restablecer la contraseña (máx 5 intentos por minuto)
    Route::post('restablecer-contrasena', [AuthController::class, 'restablecerContrasena'])
        ->middleware('throttle:5,1');
    // ruta para restablecer la contraseña cuando se te olvido
    Route::post('restablecer-contrasena-token', [AuthController::class, 'actualizarContrasenaConToken'])
        ->middleware('throttle:5,1');

    // Google OAuth (fuera del grupo auth:api — son rutas públicas)
    Route::get('google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('google/callback', [GoogleAuthController::class, 'callback']);

    // Define un subgrupo de rutas protegidas por el middleware 'auth:api'
    Route::group(['middleware' => 'auth:api'], function () {
        
        // Ruta para cerrar sesión
        Route::post('cerrar-sesion', [AuthController::class, 'cerrarSesion']);
        // Ruta para obtener los datos del usuario autenticado
        Route::get('obtener-usuario-autenticado', [AuthController::class, 'obtenerUsuarioAutenticado']);
        // Ruta para actualizar la contraseña del usuario autenticado (no se requiere ID externo)
        Route::post('actualizar-contrasena', [AuthController::class, 'actualizarContrasena']);
        // Ruta para actualizar los datos del usuario autenticado
        Route::post('actualizar-usuario', [AuthController::class, 'actualizarUsuario']);
        
    });
});