<?php

namespace App\Http\Controllers\Aspirante;

use App\Http\Requests\RequestAspirante\RequestEps\ActualizarEpsRequest;
use Illuminate\Http\Request;
use App\Models\Aspirante\Eps;
use App\Services\ArchivoService;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\RequestAspirante\RequestEps\CrearEpsRequest;

// Este controlador maneja las operaciones relacionadas con la EPS (Entidad Promotora de Salud)
// de los aspirantes. Permite crear, obtener y actualizar la EPS asociada a un usuario autenticado.
class EpsController
{

    protected $archivoService;

    /**
     * Constructor del controlador.
     *
     * Inyecta una instancia del servicio ArchivoService mediante inyección de dependencias,
     * permitiendo gestionar la carga, almacenamiento o eliminación de archivos en los métodos del controlador.
     *
     * @param ArchivoService $archivoService Instancia del servicio de gestión de archivos.
     */
    public function __construct(ArchivoService $archivoService) // Constructor: inyecta el servicio de archivos
    {
        $this->archivoService = $archivoService;
    }

    /**
     * Registrar una nueva EPS para el usuario autenticado.
     *
     * Este método permite al usuario registrar una única EPS. Verifica si ya tiene una registrada y,
     * en caso afirmativo, retorna un error 409 (conflicto). Si no existe una EPS previa, se crea una
     * nueva dentro de una transacción de base de datos para asegurar la consistencia, y opcionalmente
     * se asocia un archivo (como soporte o certificado). Se utiliza el servicio `ArchivoService` para
     * gestionar la carga del archivo.
     *
     * @param CrearEpsRequest $request Solicitud validada que contiene los datos de la EPS y el archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o error.
     */
    public function crearEps(CrearEpsRequest $request)
    {
        try {
            $usuarioId = $request->user()->id; // Obtener el ID del usuario autenticado
            $epsExistente = Eps::where('user_id', $usuarioId)->first(); // Verificar si el usuario ya tiene una EPS registrada

            if ($epsExistente) { // Si ya existe una EPS para este usuario, retornar error 409 (conflicto)
                return response()->json([
                    'message' => 'Ya tienes una EPS registrada. No puedes crear otra.',
                ], 409);
            }

            DB::transaction(function () use ($request) { // Ejecutar la creación dentro de una transacción para asegurar consistencia

                $datos = $request->validated(); // Obtener datos validados del request
                $datos['user_id'] = $request->user()->id; // Asociar la EPS al usuario autenticado
                $eps = Eps::create($datos);

                if ($request->hasFile('archivo')) { // Si hay archivo, guardarlo
                    $this->archivoService->guardarArchivoDocumento($request->file('archivo'), $eps, 'Eps');
                }
            });

            return response()->json([ // Respuesta si llega hacer  exitoso
                'message' => 'EPS y documento creado exitosamente',
            ], 201); // Código 201: es igual a que esta creado

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'message' => 'Error al crear la EPS o subir el archivo.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener la información de EPS registrada por el usuario autenticado.
     *
     * Este método recupera la EPS asociada al usuario actual, incluyendo sus documentos relacionados.
     * Si no existe ninguna EPS registrada, retorna una respuesta exitosa con valor nulo. Además,
     * genera una URL accesible para cada archivo adjunto en los documentos asociados a la EPS.
     * En caso de excepción, se captura y se retorna un mensaje de error con el código HTTP correspondiente.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la información de la EPS o mensaje de error.
     */
    public function obtenerEps(Request $request)
    {
        try {
            $user = $request->user(); //obtener el usuario autenticado
            $eps = Eps::where('user_id', $user->id) // Buscar EPS con documentos asociados
                ->with(['documentosEps:id_documento,documentable_id,archivo,estado'])
                ->first(); //error si no existe

            if (!$eps) {
                return response()->json([
                    'message' => 'No tienes EPS registrada aún.',
                    'eps' => null
                ], 200); // No es error, simplemente no tiene EPS aún
            }

            foreach ($eps->documentosEps as $documento) { // Generar URL accesible para cada archivo
                if (!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo);
                }
            }
            return response()->json(['eps' => $eps], 200); // Retornar información de EPS

        } catch (\Exception $e) { // Manejo de errores
            return response()->json([
                'message' => 'Error al obtener la información de EPS',
                'error'   => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Actualizar la EPS del usuario autenticado.
     *
     * Este método permite modificar la información de la EPS previamente registrada por el usuario.
     * Utiliza una transacción para asegurar que tanto los datos como el archivo (si se proporciona)
     * se actualicen de forma consistente. Si se envía un nuevo archivo, se reemplaza mediante el
     * servicio `ArchivoService`. En caso de que no exista una EPS, se lanza una excepción.
     *
     * @param ActualizarEpsRequest $request Solicitud validada que contiene los nuevos datos y el archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarEps(ActualizarEpsRequest $request)
    {
        try {

            DB::transaction(function () use ($request) { // Iniciar transacción para actualizar EPS
                $user = $request->user(); // Obtener usuario autenticado
                $eps = Eps::where('user_id', $user->id)->firstOrFail(); // Buscar EPS actual
                $datos = $request->validated(); // Validar datos nuevos
                $eps->update($datos); // Actualizar EPS

                if ($request->hasFile('archivo')) { // Si hay archivo nuevo, actualizarlo
                    $this->archivoService->actualizarArchivoDocumento($request->file('archivo'), $eps, 'Eps');
                }
            });
            // Respuesta con datos actualizados
            return response()->json([
                'message' => 'EPS actualizado exitosamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([ //manejo de errores
                'message' => 'Error al actualizar el EPS',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
