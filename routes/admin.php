<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Ubicaciones\UbicacionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Aspirante\NormativaController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\AspiranteAdminController;
use App\Http\Controllers\Admin\ProductoAcademicoController;
use App\Http\Controllers\Admin\AmbitoDivulgacionController;
use App\Http\Controllers\Admin\TipoExperienciaController;
use App\Http\Controllers\Admin\NivelFormacionAcademicaController;
use App\Http\Controllers\Admin\FormacionEducativaController;
use App\Http\Controllers\Admin\SniesImportacionController;
use App\Http\Controllers\Admin\IdiomaController;
use App\Http\Controllers\Admin\ExamenIdiomaController;
use App\Http\Controllers\Admin\RangoExamenIdiomaController;
use App\Http\Controllers\Admin\EscalonDocenteController;
use App\Http\Controllers\Admin\ReglaExcepcionEscalonController;

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

    // Catálogo de niveles de formación académica. nivel_academico y nivel_formacion son texto
    // libre a propósito (sin lista fija SNIES ni cruce entre campos). Catálogo independiente por
    // ahora: nada lo referencia todavía.
    Route::get('niveles-formacion-academica', [NivelFormacionAcademicaController::class, 'listar']);
    Route::get('niveles-formacion-academica/{id}', [NivelFormacionAcademicaController::class, 'obtenerPorId']);
    Route::post('niveles-formacion-academica', [NivelFormacionAcademicaController::class, 'crear']);
    Route::put('niveles-formacion-academica/{id}', [NivelFormacionAcademicaController::class, 'actualizar']);
    Route::delete('niveles-formacion-academica/{id}', [NivelFormacionAcademicaController::class, 'eliminar']);

    // "Formación educativa" (Fase 2 del catálogo de Formación académica): programas académicos.
    // Alta manual aquí; la carga masiva va por SniesImportacionController.
    Route::get('formacion-educativa', [FormacionEducativaController::class, 'listar']);
    Route::post('formacion-educativa', [FormacionEducativaController::class, 'crear']);
    Route::put('formacion-educativa/{id}', [FormacionEducativaController::class, 'actualizar']);
    Route::delete('formacion-educativa/{id}', [FormacionEducativaController::class, 'eliminar']);

    // Importación masiva de programas SNIES: sube el Excel, lo procesa ImportarSniesJob en
    // segundo plano (requiere el servicio queue-worker corriendo).
    Route::post('snies/importaciones', [SniesImportacionController::class, 'subir']);
    Route::get('snies/importaciones/historial', [SniesImportacionController::class, 'historial']);
    Route::get('snies/importaciones/{id}', [SniesImportacionController::class, 'estado']);

    // Catálogo de idiomas (ej. Inglés, Francés). Todavía no conectado con `idiomas.idioma`
    // (el registro de aspirante/docente); esa conexión es una fase posterior.
    // Borrar un idioma con exámenes asociados responde 409; para retirarlo use activo=false.
    Route::get('idiomas', [IdiomaController::class, 'listar']);
    Route::get('idiomas/{id}', [IdiomaController::class, 'obtenerPorId']);
    Route::post('idiomas', [IdiomaController::class, 'crear']);
    Route::put('idiomas/{id}', [IdiomaController::class, 'actualizar']);
    Route::delete('idiomas/{id}', [IdiomaController::class, 'eliminar']);

    // Exámenes de certificación de idioma (IELTS, TOEFL iBT, Cambridge FCE...), cada uno bajo un
    // idioma del catálogo. `?idioma_id=` filtra el listado por idioma.
    Route::get('examenes-idioma', [ExamenIdiomaController::class, 'listar']);
    Route::get('examenes-idioma/{id}', [ExamenIdiomaController::class, 'obtenerPorId']);
    Route::post('examenes-idioma', [ExamenIdiomaController::class, 'crear']);
    Route::put('examenes-idioma/{id}', [ExamenIdiomaController::class, 'actualizar']);
    Route::delete('examenes-idioma/{id}', [ExamenIdiomaController::class, 'eliminar']);

    // Rangos de puntaje de un examen y su nivel MCER equivalente (ej. IELTS 5.5-6.5 = B2).
    // `?examen_idioma_id=` filtra el listado por examen.
    Route::get('rangos-examen-idioma', [RangoExamenIdiomaController::class, 'listar']);
    Route::post('rangos-examen-idioma', [RangoExamenIdiomaController::class, 'crear']);
    Route::put('rangos-examen-idioma/{id}', [RangoExamenIdiomaController::class, 'actualizar']);
    Route::delete('rangos-examen-idioma/{id}', [RangoExamenIdiomaController::class, 'eliminar']);

    // Escalones del escalafón docente (Auxiliar, Asistente, Asociado, Titular...) y sus
    // requisitos de ascenso. Borrar un escalón con excepciones apuntándole responde 409; para
    // retirarlo use activo=false.
    Route::get('escalones-docente', [EscalonDocenteController::class, 'listar']);
    Route::get('escalones-docente/{id}', [EscalonDocenteController::class, 'obtenerPorId']);
    Route::post('escalones-docente', [EscalonDocenteController::class, 'crear']);
    Route::put('escalones-docente/{id}', [EscalonDocenteController::class, 'actualizar']);
    Route::delete('escalones-docente/{id}', [EscalonDocenteController::class, 'eliminar']);

    // Reglas de excepción del escalafón (ej. "tiene Doctorado aprobado -> mínimo Asociado").
    Route::get('reglas-excepcion-escalon', [ReglaExcepcionEscalonController::class, 'listar']);
    Route::post('reglas-excepcion-escalon', [ReglaExcepcionEscalonController::class, 'crear']);
    Route::put('reglas-excepcion-escalon/{id}', [ReglaExcepcionEscalonController::class, 'actualizar']);
    Route::delete('reglas-excepcion-escalon/{id}', [ReglaExcepcionEscalonController::class, 'eliminar']);

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
