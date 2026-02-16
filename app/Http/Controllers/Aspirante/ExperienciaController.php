<?php

namespace App\Http\Controllers\Aspirante;

use App\Http\Requests\RequestAspirante\RequestExperiencia\ActualizarExperienciaRequest;
use Illuminate\Http\Request;
use App\Models\Aspirante\Experiencia;
use App\Services\ArchivoService;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\RequestAspirante\RequestExperiencia\CrearExperienciaRequest;

// Controlador para manejar las experiencias de los aspirantes.
// Este controlador permite crear, obtener, actualizar y eliminar experiencias de un aspirante.
class ExperienciaController
{
    protected $archivoService;

    /**
     * Constructor del controlador.
     *
     * Inyecta el servicio `ArchivoService`, que se encarga de gestionar las operaciones relacionadas
     * con archivos adjuntos (guardar, actualizar y eliminar) en los experiencias u otras entidades.
     *
     * @param ArchivoService $archivoService Servicio responsable de la gestión de archivos.
     */
    public function __construct(ArchivoService $archivoService)
    {
        $this->archivoService = $archivoService;
    }

    /**
     * Registrar una nueva experiencia laboral del usuario autenticado.
     *
     * Este método permite crear un nuevo registro de experiencia laboral asociado al usuario autenticado.
     * Los datos validados se procesan dentro de una transacción para garantizar la integridad de la operación.
     * Si se adjunta un archivo (como certificado laboral), se guarda mediante el servicio `ArchivoService`.
     * En caso de error durante la creación del registro o el guardado del archivo, se captura la excepción
     * y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param CrearExperienciaRequest $request Solicitud validada con los datos de la experiencia y archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function crearExperiencia(CrearExperienciaRequest $request)
    {
        try {
            DB::transaction(function () use ($request) { // Se ejecuta una transacción para asegurar que todos los cambios se hagan correctamente.
                $datos = $request->validated();
                $datos['user_id'] = $request->user()->id; // Se asigna el ID del usuario autenticado.
                $experiencia = Experiencia::create($datos); // Se crea el registro en la base de datos.

                if ($request->hasFile('archivo')) { // Si se sube un archivo, se guarda con el servicio correspondiente.
                    $this->archivoService->guardarArchivoDocumento($request->file('archivo'), $experiencia, 'Experiencias');
                }
            });

            return response()->json([ // Se retorna una respuesta exitosa con los datos de la experiencia creada.
                'message' => 'Experiencia creada exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([ // En caso de error, se retorna un mensaje con el detalle.
                'message' => 'Error al crear la experiencia.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener las experiencias laborales del usuario autenticado.
     *
     * Este método recupera todas las experiencias laborales registradas por el usuario, incluyendo 
     * los documentos asociados a cada una. Si no existen registros, se retorna una respuesta exitosa
     * con un mensaje informativo y valor nulo. Para cada documento, se genera una URL accesible al archivo
     * almacenado. En caso de errores durante la ejecución, se captura la excepción y se retorna un mensaje adecuado.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de experiencias o mensaje de error.
     */
    public function obtenerExperiencias(Request $request)
    {
        try {
            $user = $request->user(); // Se obtiene el usuario autenticado.
            $experiencias = Experiencia::where('user_id', $user->id) // Se consultan las experiencias del usuario, incluyendo los documentos asociados.
                ->with(['documentosExperiencia:id_documento,documentable_id,archivo,estado'])
                ->orderBy('created_at')
                ->get();

            if ($experiencias->isEmpty()) { // Si no hay experiencias, se lanza una excepción.
                return response()->json([
                    'mensaje' => 'No se encontraron experiencias',
                    'experiencias' => null
                ], 200);
            }

            $experiencias->each(function ($experiencia) { // Se recorre cada experiencia para agregar la URL del archivo si existe.
                $experiencia->documentosExperiencia->each(function ($documento) {
                    if (!empty($documento->archivo)) {
                        $documento->archivo_url = asset('storage/' . $documento->archivo);
                    }
                });
            });

            return response()->json(['experiencias' => $experiencias], 200); // Se retorna la lista de experiencias.

        } catch (\Exception $e) {
            return response()->json([ // En caso de error, se retorna una respuesta con el mensaje.
                'message' => 'Error al obtener las experiencias.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Obtener una experiencia laboral específica del usuario autenticado por su ID.
     *
     * Este método permite consultar el detalle de una experiencia laboral registrada por el usuario,
     * identificada por su ID. También incluye los documentos asociados, a los cuales se les genera
     * una URL accesible si el archivo existe. Si la experiencia no es encontrada o ocurre un error,
     * se captura la excepción y se retorna una respuesta adecuada con el mensaje correspondiente.
     *
     * @param Request $request Solicitud HTTP que contiene la información del usuario autenticado.
     * @param int $id ID de la experiencia que se desea consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la experiencia encontrada o mensaje de error.
     */
    public function obtenerExperienciaPorId(Request $request, $id)
    {
        try {

            $user = $request->user(); // Se obtiene el usuario autenticado.
            $experiencia = Experiencia::where('id_experiencia', $id) // Se busca la experiencia por ID y usuario.
                ->where('user_id', $user->id)
                ->with(['documentosExperiencia:id_documento,documentable_id,archivo,estado'])
                ->firstOrFail();

            $experiencia->documentosExperiencia->each(function ($documento) { // Se agrega la URL de los archivos si existen.
                if (!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo);
                }
            });

            return response()->json(['experiencia' => $experiencia], 200); // Se retorna la experiencia encontrada.

        } catch (\Exception $e) {
            return response()->json([ // En caso de error, se retorna un mensaje detallado.
                'message' => 'Error al obtener la experiencia.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }


    /**
     * Actualizar una experiencia laboral del usuario autenticado.
     *
     * Este método permite modificar los datos de una experiencia específica registrada por el usuario,
     * identificada por su ID. La operación se realiza dentro de una transacción para asegurar la integridad
     * de los datos. Si se adjunta un nuevo archivo, este se actualiza utilizando el servicio `ArchivoService`.
     * Si la experiencia no se encuentra o ocurre un error durante el proceso, se captura la excepción y se 
     * responde con un mensaje de error adecuado.
     *
     * @param ActualizarExperienciaRequest $request Solicitud validada con los nuevos datos y archivo opcional.
     * @param int $id ID de la experiencia que se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarExperiencia(ActualizarExperienciaRequest $request, $id)
    {
        try {
            DB::transaction(function () use ($request, $id) { // Se ejecuta una transacción para asegurar la integridad de los datos.
                $user = $request->user(); // Se obtiene el usuario autenticado.
                $experiencia = Experiencia::where('id_experiencia', $id) // Se busca la experiencia por ID y usuario.
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                $datos = $request->validated(); // Se validan los datos enviados en la solicitud.
                $experiencia->update($datos); // Se actualiza la experiencia con los nuevos datos.

                if ($request->hasFile('archivo')) { // Si hay un nuevo archivo, se actualiza el documento.
                    $this->archivoService->actualizarArchivoDocumento($request->file('archivo'), $experiencia, 'Experiencias');
                }
            });

            return response()->json([ // Se retorna la experiencia actualizada y un mensaje de éxito.
                'message' => 'Experiencia actualizada correctamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([ // En caso de error, se retorna el mensaje correspondiente.
                'message' => 'Error al actualizar la experiencia.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una experiencia laboral del usuario autenticado.
     *
     * Este método permite eliminar una experiencia específica registrada por el usuario, identificada por su ID.
     * Antes de eliminar el registro de la base de datos, también se elimina cualquier archivo asociado utilizando
     * el servicio `ArchivoService`. Toda la operación se realiza dentro de una transacción para garantizar que
     * los cambios se apliquen de manera atómica. En caso de que la experiencia no exista o se produzca un error,
     * se captura la excepción y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param Request $request Solicitud HTTP con la información del usuario autenticado.
     * @param int $id ID de la experiencia que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o de error.
     */
    public function eliminarExperiencia(Request $request, $id)
    {
        try {

            $user = $request->user(); // Obtener el usuario autenticado desde la solicitud.
            $experiencia = Experiencia::where('id_experiencia', $id) // Buscar la experiencia por su ID y asegurarse de que pertenezca al usuario autenticado.
                ->where('user_id', $user->id)
                ->firstOrFail();

            DB::transaction(function () use ($experiencia) { // Iniciar una transacción para garantizar que las operaciones se realicen de manera atómica.
                $this->archivoService->eliminarArchivoDocumento($experiencia); // Eliminar el archivo asociado a la experiencia utilizando el servicio de archivos.
                $experiencia->delete(); // Eliminar el registro de la experiencia de la base de datos.
            });

            return response()->json(['message' => 'Experiencia eliminada correctamente'], 200); // Retornar una respuesta JSON indicando que la experiencia fue eliminada correctamente.

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores: retornar una respuesta JSON con el mensaje de error y el código 500.
                'message' => 'Error al eliminar la experiencia.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
