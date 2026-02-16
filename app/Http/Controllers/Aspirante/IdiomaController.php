<?php

namespace App\Http\Controllers\Aspirante;

use App\Http\Requests\RequestAspirante\RequestIdioma\ActualizarIdiomaRequest;
use Illuminate\Http\Request;
use App\Models\Aspirante\Idioma;
use App\Services\ArchivoService;
use App\Http\Requests\RequestAspirante\RequestIdioma\CrearIdiomaRequest;
use Illuminate\Support\Facades\DB;



class IdiomaController
{
    protected $archivoService;

    /**
     * Constructor del controlador.
     *
     * Inyecta el servicio `ArchivoService`, utilizado para gestionar las operaciones relacionadas
     * con archivos (guardar, actualizar y eliminar) asociados a el idioma del usuario.
     *
     * @param ArchivoService $archivoService Servicio encargado de la gestión de archivos adjuntos.
     */
    public function __construct(ArchivoService $archivoService)
    {
        $this->archivoService = $archivoService;
    }

    /**
     * Registrar un nuevo idioma para el usuario autenticado.
     *
     * Este método permite crear un registro de idioma asociado al usuario autenticado.
     * La operación se ejecuta dentro de una transacción para garantizar la consistencia de los datos.
     * Si se adjunta un archivo (por ejemplo, un certificado de idioma), se guarda utilizando el servicio `ArchivoService`.
     * En caso de ocurrir un error durante la creación del registro o el guardado del archivo, se captura la excepción
     * y se retorna una respuesta adecuada.
     *
     * @param CrearIdiomaRequest $request Solicitud validada con los datos del idioma y archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function crearIdioma(CrearIdiomaRequest $request)
    {
        try {

            DB::transaction(function () use ($request) { // Se ejecuta dentro de una transacción para asegurar consistencia

                $datos = $request->validated();
                $datos['user_id'] = $request->user()->id; // Se añade el ID del usuario autenticado
                $idioma = Idioma::create($datos); // Crea el registro del idioma en la base de datos

                if ($request->hasFile('archivo')) { // Si se envió un archivo, lo guarda usando el servicio
                    $this->archivoService->guardarArchivoDocumento($request->file('archivo'), $idioma, 'Idiomas');
                }
            });
            return response()->json([ // Retorna respuesta exitosa con el idioma creado
                'mensaje' => 'Idioma y documento guardados correctamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([ // Captura y retorna cualquier error que ocurra
                'message' => 'Error al crear el idioma.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener los idiomas registrados por el usuario autenticado.
     *
     * Este método consulta todos los idiomas asociados al usuario actual, incluyendo los documentos
     * relacionados. Si no se encuentran registros, se retorna una respuesta exitosa con un mensaje
     * informativo y valor nulo. Para cada documento, se genera una URL accesible al archivo almacenado
     * en el sistema. En caso de que ocurra un error durante la consulta, se captura la excepción y se
     * retorna un mensaje de error adecuado.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de idiomas o mensaje de error.
     */
    public function obtenerIdiomas(Request $request)
    {
        try {

            $user = $request->user(); // Obtiene el usuario autenticado

            $idiomas = Idioma::where('user_id', $user->id) // Consulta los idiomas asociados al usuario
                ->with(['documentosIdioma:id_documento,documentable_id,archivo,estado'])
                ->orderBy('created_at')
                ->get();

            if ($idiomas->isEmpty()) { // Si no se encuentran idiomas, retorna un mensaje
                return response()->json([
                    'mensaje' => 'No se encontraron idiomas para el usuario.',
                    'idiomas' => null
                ], 200);
            }

            $idiomas->each(function ($idioma) { // Agrega la URL completa al archivo en cada documento
                $idioma->documentosIdioma->each(function ($documento) {
                    if (!empty($documento->archivo)) {
                        $documento->archivo_url = asset('storage/' . $documento->archivo);
                    }
                });
            });

            return response()->json(['idiomas' => $idiomas], 200); // Retorna los idiomas en formato JSON

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'message' => 'Error al obtener los idiomas.',
                'error'   => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Obtener un idioma específico del usuario autenticado por su ID.
     *
     * Este método permite consultar un idioma registrado por el usuario, identificado por su ID.
     * También carga los documentos asociados al idioma y genera una URL accesible para cada archivo adjunto.
     * Si el idioma no se encuentra o no pertenece al usuario, se lanza una excepción y se retorna un mensaje de error.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @param int $id ID del idioma que se desea consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los datos del idioma o mensaje de error.
     */
    public function obtenerIdiomaPorId(Request $request, $id)
    {
        try {
            $user = $request->user();

            $idioma = Idioma::where('id_idioma', $id) // Busca el idioma por ID e ID del usuario
                ->where('user_id', $user->id)
                ->with(['documentosIdioma:id_documento,documentable_id,archivo,estado'])
                ->firstOrFail(); // Falla si no encuentra el idioma

            $idioma->documentosIdioma->each(function ($documento) { // Añade URL completa a cada archivo
                if (!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo);
                }
            });

            return response()->json(['idioma' => $idioma], 200); // Retorna el idioma encontrado

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'message' => 'Error al obtener el idioma.',
                'error'   => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Actualizar un idioma registrado por el usuario autenticado.
     *
     * Este método permite modificar los datos de un idioma específico identificado por su ID,
     * siempre y cuando pertenezca al usuario autenticado. La operación se ejecuta dentro de una
     * transacción para asegurar la consistencia de los datos. Si se adjunta un nuevo archivo
     * (por ejemplo, un certificado actualizado), se reemplaza utilizando el servicio `ArchivoService`.
     * En caso de error, se captura la excepción y se retorna una respuesta adecuada.
     *
     * @param ActualizarIdiomaRequest $request Solicitud validada con los nuevos datos y archivo opcional.
     * @param int $id ID del idioma que se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarIdioma(ActualizarIdiomaRequest $request, $id)
    {
        try {

            DB::transaction(function () use ($request, $id) { // Ejecuta dentro de una transacción
                $user = $request->user();

                $idioma = Idioma::where('id_idioma', $id) // Busca el idioma por ID e ID del usuario
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                $datos = $request->validated(); // Valida los datos y actualiza el idioma
                $idioma->update($datos);

                if ($request->hasFile('archivo')) { // Si hay nuevo archivo, lo actualiza
                    $this->archivoService->actualizarArchivoDocumento($request->file('archivo'), $idioma, 'Idiomas');
                }
            });
            // Retorna idioma actualizado
            return response()->json([
                'mensaje' => 'Idioma actualizado correctamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'message' => 'Error al actualizar el idioma.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un idioma registrado por el usuario autenticado.
     *
     * Este método permite eliminar un idioma específico identificado por su ID, siempre que pertenezca
     * al usuario autenticado. La eliminación se realiza dentro de una transacción para garantizar la integridad
     * del proceso. Antes de eliminar el registro, se eliminan los archivos asociados utilizando el servicio `ArchivoService`.
     * En caso de que el idioma no se encuentre o se produzca un error durante la eliminación, se captura una excepción
     * y se retorna una respuesta adecuada.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @param int $id ID del idioma que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function eliminarIdioma(Request $request, $id)
    {
        try {
            $user = $request->user();

            $idioma = Idioma::where('id_idioma', $id) // Busca el idioma por ID e ID del usuario
                ->where('user_id', $user->id)
                ->firstOrFail();

            DB::transaction(function () use ($idioma) { // Ejecuta eliminación dentro de una transacción
                $this->archivoService->eliminarArchivoDocumento($idioma); // Elimina archivo
                $idioma->delete(); // Elimina el registro
            });

            return response()->json(['mensaje' => 'Idioma eliminado correctamente'], 200); // Retorna respuesta exitosa

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'message' => 'Error al eliminar el idioma.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
