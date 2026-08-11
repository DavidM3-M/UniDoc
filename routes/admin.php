<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Ubicaciones\UbicacionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Aspirante\NormativaController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\AspiranteAdminController;
use App\Http\Controllers\Admin\UmbralEvaluacionController;
use App\Http\Controllers\Admin\ProductoAcademicoController;
use App\Http\Controllers\Admin\AmbitoDivulgacionController;
use App\Http\Controllers\Admin\TipoExperienciaController;

Route::group([
    'middleware' => [ 'api','auth:api', 'role:Administrador'],
    'prefix' => 'admin'
], function () {
    // Rutas de roles
    Route::get('listar-roles', [RoleController::class, 'listarRoles']);
    // crearRol y eliminarRol están deshabilitados (métodos no implementados)
    Route::post('asignar-rol', [RoleController::class, 'asignarRol']);
    Route::post('remover-rol/{id}', [RoleController::class, 'removerRol']);
    Route::put('actualizar-rol/{id}', [RoleController::class, 'actualizarRol']);

    // Rutas de subir un archivo CSV para ubicaciones
    Route::post('uploadCsv', [UbicacionController::class, 'uploadCsv']);

    // Rutas de usuarios
    Route::get('listar-usuarios', [UserController::class, 'listarUsuarios']);
    Route::put('/usuarios/{id}/cambiar-rol', [UserController::class, 'cambiarRol']);
    Route::get('/usuarios/exportar-excel', [UserController::class, 'exportarUsuariosExcel']);
    Route::put('editar-usuario/{id}', [UserController::class, 'editarUsuario']);
    Route::delete('eliminar-usuario/{id}', [UserController::class, 'eliminarUsuario']);

    // Normativas
    Route::post('crear-normativa', [NormativaController::class, 'crearNormativa']);
    Route::get('obtener-normativas', [NormativaController::class, 'obtenerNormativas']);
    Route::get('obtener-normativa/{id}', [NormativaController::class, 'obtenerNormativaPorId']);
    Route::put('actualizar-normativa/{id}', [NormativaController::class, 'actualizarNormativa']);
    Route::delete('eliminar-normativa/{id}', [NormativaController::class, 'eliminarNormativa']);

    // Umbral minimo de evaluacion docente exigido para ascender de categoria.
    // No se edita ni se borra: registrar uno nuevo cierra el anterior y conserva el historico.
    Route::get('umbral-evaluacion', [UmbralEvaluacionController::class, 'obtenerUmbralVigente']);
    Route::get('umbral-evaluacion/historico', [UmbralEvaluacionController::class, 'obtenerHistoricoUmbrales']);
    Route::post('umbral-evaluacion', [UmbralEvaluacionController::class, 'crearUmbral']);

    // Catálogo de tipos de producto académico.
    // Antes solo se poblaba desde database/data/tipo_producto_academico.csv.
    // Borrar un producto con ámbitos asociados responde 409; para retirarlo use activo=false.
    Route::get('productos-academicos', [ProductoAcademicoController::class, 'listar']);
    Route::get('productos-academicos/{id}', [ProductoAcademicoController::class, 'obtenerPorId']);
    Route::post('productos-academicos', [ProductoAcademicoController::class, 'crear']);
    Route::put('productos-academicos/{id}', [ProductoAcademicoController::class, 'actualizar']);
    Route::delete('productos-academicos/{id}', [ProductoAcademicoController::class, 'eliminar']);

    // Catálogo de ámbitos de divulgación, cada uno bajo un tipo de producto académico.
    // Borrar un ámbito usado por producciones académicas responde 409.
    Route::get('ambitos-divulgacion', [AmbitoDivulgacionController::class, 'listar']);
    Route::get('ambitos-divulgacion/{id}', [AmbitoDivulgacionController::class, 'obtenerPorId']);
    Route::post('ambitos-divulgacion', [AmbitoDivulgacionController::class, 'crear']);
    Route::put('ambitos-divulgacion/{id}', [AmbitoDivulgacionController::class, 'actualizar']);
    Route::delete('ambitos-divulgacion/{id}', [AmbitoDivulgacionController::class, 'eliminar']);

    // Catálogo de tipos de experiencia profesional.
    // Antes era la constante PHP TiposExperiencia. Renombrar propaga el cambio a
    // experiencias y convocatorias; borrar un tipo en uso responde 409.
    Route::get('tipos-experiencia', [TipoExperienciaController::class, 'listar']);
    Route::get('tipos-experiencia/{id}', [TipoExperienciaController::class, 'obtenerPorId']);
    Route::post('tipos-experiencia', [TipoExperienciaController::class, 'crear']);
    Route::put('tipos-experiencia/{id}', [TipoExperienciaController::class, 'actualizar']);
    Route::delete('tipos-experiencia/{id}', [TipoExperienciaController::class, 'eliminar']);

    // Rutas de reportes
    Route::get('usuarios-excel', [ReporteController::class, 'usuariosExcel']);
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
