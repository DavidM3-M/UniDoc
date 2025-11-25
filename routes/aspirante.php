<?php
// Importa la clase Route desde el espacio de nombres Illuminate\Support\Facades
use Illuminate\Support\Facades\Route;
// Importa los controladores necesarios para manejar las rutas del módulo "Aspirante"
use App\Http\Controllers\Aspirante\InformacionContactoController;
use App\Http\Controllers\Aspirante\EpsController;
use App\Http\Controllers\Aspirante\IdiomaController;
use App\Http\Controllers\Aspirante\ExperienciaController;
use App\Http\Controllers\Aspirante\ProduccionAcademicaController;
use App\Http\Controllers\Aspirante\EstudioController;
use App\Http\Controllers\Aspirante\RutController;
use App\Http\Controllers\TalentoHumano\ConvocatoriaController;
use App\Http\Controllers\TalentoHumano\PostulacionController;
use App\Http\Controllers\Aspirante\FotoPerfilController;
use App\Http\Controllers\Aspirante\AptitudController;
use App\Http\Controllers\Aspirante\NormativaController;
// Define un grupo de rutas con configuraciones específicas para el rol "Aspirante"
Route::group([
    // Aplica los middlewares 'api', 'auth:api' y 'role:Aspirante' para proteger las rutas
    'middleware' => [ 'api', 'auth:api', 'role:Aspirante'],
    // Establece un prefijo 'aspirante' para las rutas dentro de este grupo
    'prefix' => 'aspirante'
], function () {
    // Rutas relacionadas con el manejo del RUT
    Route::get('obtener-rut', [RutController::class, 'obtenerRut']);
    Route::post('crear-rut', [RutController::class, 'crearRut']);
    Route::put('actualizar-rut', [RutController::class, 'actualizarRut']);
    
    // Rutas relacionadas con la información de contacto
    Route::get('obtener-informacion-contacto', [InformacionContactoController::class, 'obtenerInformacionContacto']);
    Route::post('crear-informacion-contacto', [InformacionContactoController::class, 'crearInformacionContacto']);
    Route::put('actualizar-informacion-contacto', [InformacionContactoController::class, 'actualizarInformacionContacto']);

    // Rutas relacionadas con EPS (Entidad Prestadora de Salud)
    Route::get('obtener-eps', [EpsController::class, 'obtenerEps']);
    Route::post('crear-eps', [EpsController::class, 'crearEps']);
    Route::put('actualizar-eps', [EpsController::class, 'actualizarEps']);

    // Rutas relacionadas con idiomas
    Route::post('crear-idioma', [IdiomaController::class, 'crearIdioma']);
    Route::get('obtener-idiomas', [IdiomaController::class, 'obtenerIdiomas']);
    Route::get('obtener-idioma/{id}', [IdiomaController::class, 'obtenerIdiomaPorId']);
    Route::put('actualizar-idioma/{id}', [IdiomaController::class, 'actualizarIdioma']);
    Route::delete('eliminar-idioma/{id}', [IdiomaController::class, 'eliminarIdioma']);

    // Rutas relacionadas con la experiencia laboral
    Route::post('crear-experiencia', [ExperienciaController::class, 'crearExperiencia']);
    Route::get('obtener-experiencias', [ExperienciaController::class, 'obtenerExperiencias']);
    Route::get('obtener-experiencia/{id}', [ExperienciaController::class, 'obtenerExperienciaPorId']);
    Route::put('actualizar-experiencia/{id}', [ExperienciaController::class, 'actualizarExperiencia']);
    Route::delete('eliminar-experiencia/{id}', [ExperienciaController::class, 'eliminarExperiencia']);

    // Rutas relacionadas con la producción académica
    Route::post('crear-produccion', [ProduccionAcademicaController::class, 'crearProduccion']);
    Route::get('obtener-producciones', [ProduccionAcademicaController::class, 'obtenerProducciones']);
    Route::get('obtener-produccion/{id}', [ProduccionAcademicaController::class, 'obtenerProduccionPorId']);
    Route::put('actualizar-produccion/{id}', [ProduccionAcademicaController::class, 'actualizarProduccion']);
    Route::delete('eliminar-produccion/{id}', [ProduccionAcademicaController::class, 'eliminarProduccion']);

    // Rutas relacionadas con los estudios
    Route::post('crear-estudio', [EstudioController::class, 'crearEstudio']);
    Route::get('obtener-estudios', [EstudioController::class, 'obtenerEstudios']);
    Route::get('obtener-estudio/{id}', [EstudioController::class, 'obtenerEstudioPorId']);
    Route::put('actualizar-estudio/{id}', [EstudioController::class, 'actualizarEstudio']);
    Route::delete('eliminar-estudio/{id}', [EstudioController::class, 'eliminarEstudio']);

    // Rutas relacionadas con convocatorias y postulaciones
    Route::get('ver-convocatorias', [ConvocatoriaController::class, 'obtenerConvocatorias']);
    Route::get('ver-convocatoria/{id}', [ConvocatoriaController::class, 'obtenerConvocatoriaPorId']);
    Route::post('crear-postulacion/{convocatoriaId}', [PostulacionController::class, 'crearPostulacion']);
    Route::get('ver-postulaciones', [PostulacionController::class, 'obtenerPostulacionesUsuario']);
    Route::delete('eliminar-postulacion/{id}', [PostulacionController::class, 'eliminarPostulacionUsuario']);

    // Rutas relacionadas con la foto de perfil
    Route::post('crear-foto-perfil', [FotoPerfilController::class, 'crearFotoPerfil']);
    Route::delete('eliminar-foto-perfil', [FotoPerfilController::class, 'eliminarFotoPerfil']);
    Route::get('obtener-foto-perfil', [FotoPerfilController::class, 'obtenerFotoPerfil']);

    // Rutas relacionadas con aptitudes
    Route::post('crear-aptitud', [AptitudController::class, 'crearAptitud']);
    Route::get('obtener-aptitudes', [AptitudController::class, 'obtenerAptitudes']);
    Route::get('obtener-aptitud/{id}', [AptitudController::class, 'obtenerAptitudesPorId']);
    Route::put('actualizar-aptitud/{id}', [AptitudController::class, 'actualizarAptitudPorId']);
    Route::delete('eliminar-aptitud/{id}', [AptitudController::class, 'eliminarAptitudPorId']);
    

    // Rutas relacionadas con normativas
    Route::get('obtener-normativas', [NormativaController::class, 'obtenerNormativas']);
    Route::get('obtener-normativa/{id}', [NormativaController::class, 'obtenerNormativaPorId']);



    

    


});