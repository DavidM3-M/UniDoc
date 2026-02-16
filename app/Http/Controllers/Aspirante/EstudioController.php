<?php

namespace App\Http\Controllers\Aspirante;

use App\Http\Requests\RequestAspirante\RequestEstudio\ActualizarEstudioRequest;
use App\Http\Requests\RequestAspirante\RequestEstudio\CrearEstudioRequest;
use Illuminate\Http\Request;
use App\Models\Aspirante\Estudio;
use App\Services\ArchivoService;
use Illuminate\Support\Facades\DB;


// Controlador para manejar los estudios de un aspirante
// Este controlador permite crear, obtener, actualizar y eliminar estudios
class EstudioController
{
    protected $archivoService;

    /**
     * Constructor del controlador.
     *
     * Inyecta una instancia del servicio ArchivoService, que se encarga de gestionar
     * las operaciones relacionadas con archivos (guardar, actualizar, eliminar) asociadas
     * a entidades como estudio u otras del sistema.
     *
     * @param ArchivoService $archivoService Instancia del servicio encargado del manejo de archivos.
     */
    public function __construct(ArchivoService $archivoService)
    {
        $this->archivoService = $archivoService;
    }

    /**
     * Registrar un nuevo estudio académico para el usuario autenticado.
     *
     * Este método permite crear un nuevo registro de estudio asociado al usuario que realiza la solicitud.
     * Los datos son validados y procesados dentro de una transacción para asegurar la integridad de la información.
     * Si se adjunta un archivo (como diploma o certificado), se guarda utilizando el servicio ArchivoService.
     * En caso de error durante el proceso de creación o almacenamiento del archivo, se captura la excepción y
     * se retorna un mensaje de error.
     *
     * @param CrearEstudioRequest $request Solicitud validada con los datos del estudio y el archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o de error.
     */
    public function crearEstudio(CrearEstudioRequest $request)
    {
        try {

            DB::transaction(function () use ($request) { // Ejecuta dentro de una transacción para asegurar integridad de datos

                $datos = $request->validated();
                $datos['user_id'] = $request->user()->id; // Asigna el ID del usuario autenticado al estudio
                $estudio = Estudio::create($datos); // Crea el registro del estudio

                if ($request->hasFile('archivo')) { // Verifica si se adjuntó un archivo y lo guarda
                    $this->archivoService->guardarArchivoDocumento($request->file('archivo'), $estudio, 'Estudios');
                }
            });
            // Retorna respuesta exitosa
            return response()->json([
                'message' => 'Estudio y documento creados exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'message' => 'Error al crear el estudio o subir el archivo.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener los estudios académicos registrados por el usuario autenticado.
     *
     * Este método recupera todos los estudios asociados al usuario actual, incluyendo los documentos adjuntos
     * a cada uno. Si no se encuentran estudios, retorna una respuesta exitosa con un mensaje indicativo y valor nulo.
     * Para cada documento asociado, se genera una URL accesible al archivo almacenado en el sistema de archivos público.
     * Cualquier error durante la ejecución se captura y se responde con un mensaje de error y el código correspondiente.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de estudios o mensaje de error.
     */
    public function obtenerEstudios(Request $request)
    {
        try {
            $user = $request->user(); // Obtiene el usuario autenticado

            $estudios = Estudio::where('user_id', $user->id) // Consulta los estudios del usuario con sus documentos
                ->with(['documentosEstudio:id_documento,documentable_id,archivo,estado'])
                ->orderBy('created_at')
                ->get();

            if ($estudios->isEmpty()) { // Verifica si hay estudios registrados
                return response()->json([
                    'mesaje' => 'No se encontraron estudios',
                    'estudios' => null
                ], 200);
            }

            $estudios->each(function ($estudio) { // Agrega URL completa del archivo a cada documento
                $estudio->documentosEstudio->each(function ($documento) {
                    if (!empty($documento->archivo)) {
                        $documento->archivo_url = asset('storage/' . $documento->archivo);
                    }
                });
            });

            return response()->json(['estudios' => $estudios], 200); // Devuelve los estudios encontrados
        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'message' => 'Error al obtener los estudios',
                'error' => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Obtener un estudio académico específico del usuario autenticado por su ID.
     *
     * Este método busca un registro de estudio perteneciente al usuario autenticado según el ID proporcionado.
     * Si el usuario no está autenticado, lanza una excepción con código 401. Si el estudio existe, incluye 
     * los documentos relacionados y genera una URL accesible para cada archivo adjunto. En caso de error 
     * o si el estudio no es encontrado, se captura la excepción y se retorna una respuesta adecuada.
     *
     * @param Request $request Solicitud HTTP con la información del usuario autenticado.
     * @param int $id ID del estudio que se desea consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los datos del estudio o mensaje de error.
     */
    public function obtenerEstudioPorId(Request $request, $id)
    {
        try {
            $user = $request->user(); // Obtiene el usuario autenticado

            if (!$user) { // Verifica autenticación
                throw new \Exception('Usuario no autenticado', 401);
            }

            $estudio = Estudio::where('id_estudio', $id) // Busca el estudio por ID y por usuario
                ->where('user_id', $user->id)
                ->with(['documentosEstudio:id_documento,documentable_id,archivo,estado'])
                ->firstOrFail();

            $estudio->documentosEstudio->each(function ($documento) { // Agrega URL del archivo si existe
                if (!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo);
                }
            });

            return response()->json(['estudio' => $estudio], 200); // Devuelve el estudio encontrado
        } catch (\Exception $e) {

            return response()->json([ // Manejo de errores
                'message' => 'Error al obtener el estudio',
                'error'   => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Actualizar un estudio académico del usuario autenticado.
     *
     * Este método permite modificar los datos de un estudio previamente registrado, asegurando que el
     * registro pertenezca al usuario autenticado. La operación se realiza dentro de una transacción para
     * garantizar la integridad de los datos. Si se adjunta un nuevo archivo, se actualiza utilizando el
     * servicio `ArchivoService`. En caso de que el estudio no se encuentre o ocurra un error, se captura
     * la excepción y se responde con el mensaje correspondiente.
     *
     * @param ActualizarEstudioRequest $request Solicitud validada con los nuevos datos y archivo opcional.
     * @param int $id ID del estudio que se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarEstudio(ActualizarEstudioRequest $request, $id)
    {
        try {
            DB::transaction(function () use ($request, $id) {
                $user = $request->user(); // Usuario autenticado

                $estudio = Estudio::where('id_estudio', $id) // Busca el estudio del usuario
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                $datos = $request->validated(); // Valida y obtiene los datos
                $estudio->update($datos); // Actualiza los datos del estudio

                if ($request->hasFile('archivo')) {
                    $this->archivoService->actualizarArchivoDocumento($request->file('archivo'), $estudio, 'Estudios'); // Si se adjuntó nuevo archivo, lo actualiza
                }
            });
            // Respuesta exitosa con datos actualizados
            return response()->json([
                'message' => 'Estudio actualizado correctamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'message' => 'Error al actualizar el estudio',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un estudio académico del usuario autenticado.
     *
     * Este método elimina un estudio específico que pertenece al usuario autenticado. Antes de eliminar
     * el registro, se eliminan también los archivos asociados utilizando el servicio `ArchivoService`.
     * La operación se realiza dentro de una transacción para asegurar la integridad del proceso.
     * Si el estudio no existe o ocurre un error durante la eliminación, se captura la excepción y se 
     * retorna una respuesta con el mensaje de error.
     *
     * @param Request $request Solicitud HTTP con la información del usuario autenticado.
     * @param int $id ID del estudio que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function eliminarEstudio(Request $request, $id)
    {
        try {
            $user = $request->user(); // Usuario autenticado

            $estudio = Estudio::where('id_estudio', $id) // Busca el estudio del usuario
                ->where('user_id', $user->id)
                ->firstOrFail();

            DB::transaction(function () use ($estudio) { // Elimina el estudio y sus archivos dentro de una transacción
                $this->archivoService->eliminarArchivoDocumento($estudio);
                $estudio->delete();
            });

            return response()->json(['message' => 'Estudio eliminado correctamente'], 200); // Respuesta exitosa

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'message' => 'Error al eliminar el estudio',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
