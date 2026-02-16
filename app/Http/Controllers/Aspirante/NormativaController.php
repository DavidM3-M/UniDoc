<?php

namespace App\Http\Controllers\Aspirante;

use App\Models\Aspirante\Normativa;
use App\Services\ArchivoService;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\RequestNormativa\CrearNormativaRequest;
use App\Http\Requests\RequestNormativa\ActualizarNormativaRequest;

// Este controlador maneja las operaciones CRUD para la entidad Normativa
// Incluye la creación, actualización, eliminación y obtención de normativas
class NormativaController
{
    protected $archivoService;

    /**
     * Constructor del controlador.
     *
     * Inyecta el servicio `ArchivoService`, el cual se encarga de gestionar las operaciones
     * relacionadas con archivos (guardar, actualizar y eliminar) asociados a la entidad normativa.
     *
     * @param ArchivoService $archivoService Servicio responsable de la gestión de archivos adjuntos.
     */
    public function __construct(ArchivoService $archivoService)
    {
        $this->archivoService = $archivoService;
    }

    /**
     * Crear una nueva normativa para el usuario autenticado.
     *
     * Este método permite registrar una normativa asociada al usuario autenticado. La creación del registro 
     * se realiza dentro de una transacción para garantizar la integridad de los datos. Si se adjunta un archivo 
     * (como soporte o documento normativo), se guarda utilizando el servicio `ArchivoService`. 
     * En caso de ocurrir un error durante el proceso de creación o almacenamiento del archivo, 
     * se captura una excepción y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param CrearNormativaRequest $request Solicitud validada con los datos de la normativa y archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function crearNormativa(CrearNormativaRequest $request)
    {
        try {

            DB::transaction(function () use ($request) { // Inicia una transacción para asegurar integridad en la base de datos
                $datos = $request->validated(); // Obtiene los datos validados del request
                $datos['user_id'] = $request->user()->id; // Asigna el ID del usuario autenticado al campo user_id
                $normativa = Normativa::create($datos); // Crea la normativa en la base de datos

                if ($request->hasFile('archivo')) { // Si el request contiene un archivo, se guarda mediante el servicio
                    $this->archivoService->guardarArchivoDocumento($request->file('archivo'), $normativa, 'Normativas');
                }
            });

            return response()->json([ // Devuelve respuesta de éxito con los datos creados
                'message' => 'Normativa y documento guardados correctamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([ // En caso de error, devuelve mensaje de fallo y el detalle del error
                'message' => 'Error al crear la normativa.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todas las normativas registradas en el sistema.
     *
     * Este método recupera todas las normativas disponibles, incluyendo los documentos asociados a cada una.
     * Si no existen normativas registradas, se retorna una respuesta exitosa con un mensaje informativo y valor nulo.
     * Para cada documento adjunto, se genera una URL accesible al archivo almacenado.
     * En caso de error durante la consulta, se captura la excepción y se retorna un mensaje con el detalle del error.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de normativas o mensaje de error.
     */
    public function obtenerNormativas()
    {
        try {

            $normativas = Normativa::with(['documentosNormativa:id_documento,documentable_id,archivo,estado']) // Consulta todas las normativas incluyendo los documentos asociados
                ->orderBy('created_at')
                ->get();

            if ($normativas->isEmpty()) { // Verifica si hay normativas registradas
                return response()->json([
                    'mensaje' => 'No se encontraron normativas',
                    'normativas' => null
                ], 200);
            }

            $normativas->each(function ($normativa) { // Agrega URL completa del archivo a cada documento
                $normativa->documentosNormativa->each(function ($documento) {
                    if (!empty($documento->archivo)) {
                        $documento->archivo_url = asset('storage/' . $documento->archivo);
                    }
                });
            });

            return response()->json(['normativas' => $normativas], 200); // Devuelve las normativas encontradas

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'message' => 'Error al obtener las normativas.',
                'error' => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Obtener una normativa específica por su ID.
     *
     * Este método permite consultar una normativa registrada en el sistema, identificada por su ID.
     * También incluye los documentos asociados a la normativa, y genera una URL accesible para cada archivo adjunto.
     * Si la normativa no existe, se lanza una excepción y se retorna una respuesta con el mensaje de error correspondiente.
     * Se garantiza un manejo adecuado del código de error devuelto.
     *
     * @param int $id ID de la normativa que se desea consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los datos de la normativa o mensaje de error.
     */
    public function obtenerNormativaPorId($id)
    {
        try {

            $normativa = Normativa::where('id_normativa', $id) // Busca la normativa por ID
                ->with(['documentosNormativa:id_documento,documentable_id,archivo,estado'])
                ->firstOrFail();


            $normativa->documentosNormativa->each(function ($documento) { // Agrega URL del archivo si existe
                if (!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo);
                }
            });

            return response()->json(['normativa' => $normativa], 200); // Devuelve la normativa encontrada

        } catch (\Exception $e) {
            $codigo = (is_int($e->getCode()) && $e->getCode() !== 0) ? $e->getCode() : 500; // Manejo de errores
            return response()->json([
                'message' => 'Error al obtener la normativa',
                'error'   => $e->getMessage()
            ], $codigo);
        }
    }

    /**
     * Actualizar una normativa existente por su ID.
     *
     * Este método permite modificar los datos de una normativa previamente registrada en el sistema. 
     * La operación se ejecuta dentro de una transacción para garantizar la integridad de los datos.
     * Si se adjunta un nuevo archivo (como una versión actualizada de la normativa), este se actualiza
     * utilizando el servicio `ArchivoService`. En caso de que la normativa no se encuentre o ocurra un 
     * error durante la operación, se captura una excepción y se retorna una respuesta con el mensaje correspondiente.
     *
     * @param ActualizarNormativaRequest $request Solicitud validada con los nuevos datos y archivo opcional.
     * @param int $id ID de la normativa que se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarNormativa(ActualizarNormativaRequest $request, $id)
    {
        try {

            DB::transaction(function () use ($request, $id) { // Inicia transacción para la operación de actualización
                $normativa = Normativa::findOrFail($id); // Busca la normativa a actualizar
                $datos = $request->validated(); // Obtiene los datos validados del request
                $normativa->update($datos); // Actualiza los campos de la normativa

                if ($request->hasFile('archivo')) { // Si hay un archivo nuevo, se actualiza mediante el servicio
                    $this->archivoService->actualizarArchivoDocumento($request->file('archivo'), $normativa, 'Normativas');
                }
            });
            // Devuelve respuesta de éxito con la normativa actualizada (recargada)
            return response()->json([
                'mensaje' => 'Normativa actualizada correctamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores en la actualización
                'message' => 'Error al actualizar la normativa.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar una normativa del sistema por su ID.
     *
     * Este método permite eliminar una normativa específica, identificada por su ID. Antes de eliminar el
     * registro en la base de datos, se eliminan también los archivos asociados utilizando el servicio `ArchivoService`.
     * La operación se ejecuta dentro de una transacción para asegurar que ambas acciones (archivo y registro)
     * se completen de manera atómica. En caso de que la normativa no exista o ocurra un error, se captura la excepción
     * y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param int $id ID de la normativa que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function eliminarNormativa($id)
    {
        try {
            $normativa = Normativa::where('id_normativa', $id) // Busca la normativa por su ID, lanza error si no existe
                ->firstOrFail();

            DB::transaction(function () use ($normativa) { // Realiza la eliminación dentro de una transacción
                $this->archivoService->eliminarArchivoDocumento($normativa); // Elimina el archivo relacionado mediante el servicio
                $normativa->delete(); // Elimina la normativa de la base de datos
            });

            return response()->json(['mensaje' => 'Normativa eliminada correctamente'], 200); // Devuelve mensaje de éxito

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores en la eliminación
                'message' => 'Error al eliminar la normativa.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
