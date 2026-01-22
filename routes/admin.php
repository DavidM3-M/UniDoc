<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Ubicaciones\UbicacionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Aspirante\NormativaController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\AspiranteAdminController;

Route::group([
    'middleware' => [ 'api','auth:api', 'role:Administrador'],
    'prefix' => 'admin'
], function () {
    // // Rutas de roles
    Route::get('listar-roles', [RoleController::class, 'listarRoles']);
    Route::post('crear-rol', [RoleController::class, 'crearRol']);
    Route::post('asignar-rol', [RoleController::class, 'asignarRol']);
    Route::post('remover-rol/{id}', [RoleController::class, 'removerRol']);
    Route::put('actualizar-rol', [RoleController::class, 'actualizarRol']);
    Route::delete('eliminar-rol', [RoleController::class, 'eliminarRol']);

    // Rutas de subir un archivo CSV para ubicaciones
    Route::post('uploadCsv', [UbicacionController::class, 'uploadCsv']);
    // // Rutas de usuarios
    Route::get('listar-usuarios', [UserController::class, 'listarUsuarios']);
    Route::put('editar-usuario/{id}', [UserController::class, 'editarUsuario']);
    Route::delete('eliminar-usuario/{id}', [UserController::class, 'eliminarUsuario']);


    //normativas
    Route::post('crear-normativa', [NormativaController::class, 'crearNormativa']);
    Route::get('obtener-normativas', [NormativaController::class, 'obtenerNormativas']);
    Route::get('obtener-normativa/{id}', [NormativaController::class, 'obtenerNormativaPorId']);
    Route::put('actualizar-normativa/{id}', [NormativaController::class, 'actualizarNormativa']);
    Route::delete('eliminar-normativa/{id}', [NormativaController::class, 'eliminarNormativa']);

    // Rutas de reportes
    Route::get('usuarios-excel', [ReporteController::class, 'usuariosExcel']);




    //cree estos nuevos endpoints (Brayan Cuellar)
     // Gestión de usuarios
    Route::get('/usuarios', [UserController::class, 'listarUsuarios']);
    Route::put('/usuarios/{id}/cambiar-rol', [UserController::class, 'cambiarRol']);
    Route::get('/usuarios/exportar-excel', [UserController::class, 'exportarUsuariosExcel']);
    Route::put('/usuarios/{id}', [UserController::class, 'editarUsuario']);
    Route::delete('/usuarios/{id}', [UserController::class, 'eliminarUsuario']);

    // Gestión de roles
    Route::get('/roles', [RoleController::class, 'listarRoles']);
    Route::post('/roles/asignar', [RoleController::class, 'asignarRol']);
    Route::post('/roles/remover', [RoleController::class, 'removerRol']);
    Route::put('/roles/{id}', [RoleController::class, 'actualizarRol']);


// Rutas compartidas para Vicerrectoria y Rectoria
});
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Vicerrectoria|Rectoria|Talento Humano'],
    'prefix' => 'admin'
], function () {

    // Gestión de Aspirantes
    Route::get('aspirantes', [AspiranteAdminController::class, 'obtenerAspirantes']);
    Route::get('aspirantes/estadisticas', [AspiranteAdminController::class, 'obtenerEstadisticas']);
    Route::get('aspirantes/{id}', [AspiranteAdminController::class, 'obtenerAspirantePorId']);
    Route::get('aspirantes/{id}/hoja-vida-pdf', [AspiranteAdminController::class, 'descargarHojaDeVida']);
    Route::post('aspirantes/{id}/dar-aval', [AspiranteAdminController::class, 'darAval']);
});
