<?php
// Importa la clase Route desde el espacio de nombres Illuminate\Support\Facades
use Illuminate\Support\Facades\Route;
// Importa el controlador ConstantesController para manejar las rutas relacionadas con constantes
use App\Http\Controllers\Constantes\ConstantesController;
// Define un grupo de rutas con configuraciones específicas para constantes
Route::group([
// Aplica el middleware 'api' para proteger las rutas
    'middleware' => 'api',
// Establece un prefijo 'constantes' para las rutas dentro de este grupo
    'prefix' => 'constantes'
], function () {

    // Constantes relacionadas con el usuario
    Route::get('tipos-documento', [ConstantesController::class, 'obtenerTiposDocumento']);
    Route::get('estado-civil', [ConstantesController::class, 'obtenerEstadoCivil']);
    Route::get('genero', [ConstantesController::class, 'obtenerGenero']);

    // Constantes relacionadas con el RUT
    Route::get('tipo-persona', [ConstantesController::class, 'obtenerTipoPersona']);
    Route::get('codigo-ciiu', [ConstantesController::class, 'obtenerCodigoCiiu']);
    Route::get('responsabilidades-tributarias', [ConstantesController::class, 'obtenerResponsabilidadesTributarias']);

    // Constantes relacionadas con EPS (Entidad Prestadora de Salud)
    Route::get('estado-afiliacion', [ConstantesController::class, 'obtenerEstadoAfiliacionEps']);
    Route::get('tipo-afiliacion', [ConstantesController::class, 'obtenerTipoAfiliacionEps']);
    Route::get('tipo-afiliado', [ConstantesController::class, 'obtenerTipoAfiliadoEps']);

    // Constantes relacionadas con la información de contacto
    Route::get('categoria-libreta-militar', [ConstantesController::class, 'obtenerTipoLibretaMilitar']);

    // Constantes relacionadas con los estudios

    // Catálogo administrable de niveles de formación académica (reemplaza tipos-estudio).
    Route::get('niveles-formacion-academica', [ConstantesController::class, 'obtenerNivelFormacionAcademica']);

    // Constantes: perfiles profesionales (desplegable)
    Route::get('perfiles-profesionales', [ConstantesController::class, 'obtenerPerfilesProfesionales']);

    // Constantes relacionadas con la experiencia laboral
    Route::get('tipos-experiencia', [ConstantesController::class, 'obtenerTipoExperiencia']);

    // Constantes relacionadas con los idiomas
    Route::get('niveles-idioma', [ConstantesController::class, 'obtenerNivelIdioma']);

    // Catálogo administrable de idiomas y sus exámenes de certificación.
    Route::get('idiomas', [ConstantesController::class, 'obtenerIdiomas']);
    Route::get('examenes-idioma', [ConstantesController::class, 'obtenerExamenesIdioma']);

    // Escalafón docente: escalones y sus requisitos, para la tarjeta que ve el docente en su
    // hoja de vida. Misma tabla que administra el rol Administrador en Escalafón docente.
    Route::get('escalones-docente', [ConstantesController::class, 'obtenerEscalonesDocente']);

    // Constantes relacionadas con certificaciones bancarias
    Route::get('tipos-cuenta-bancaria', [ConstantesController::class, 'obtenerTipoCuenta']);

    // Constantes relacionadas con pensiones
    Route::get('tipos-pension', [ConstantesController::class, 'obtenerRegimenPensional']);

});
