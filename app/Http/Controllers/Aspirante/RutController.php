<?php

namespace App\Http\Controllers\Aspirante;

use App\Http\Requests\RequestAspirante\RequestRut\ActualizarRutRequest;
use Illuminate\Http\Request;
use App\Models\Aspirante\Rut;
use App\Services\ArchivoService;
use App\Http\Requests\RequestAspirante\RequestRut\CrearRutRequest;
use Illuminate\Support\Facades\DB;

// Controlador para gestionar el RUT de los aspirantes
// Este controlador permite crear, obtener y actualizar el RUT de un aspirante
class RutController
{
    protected $archivoService;

    /**
     * Constructor del controlador.
     *
     * Inyecta el servicio `ArchivoService`, el cual se encarga de gestionar las operaciones de archivos
     * (guardar, actualizar y eliminar) relacionados conel rut del usuario.
     *
     * @param ArchivoService $archivoService Servicio responsable de la gestión de archivos adjuntos.
     */
    public function __construct(ArchivoService $archivoService)
    {
        $this->archivoService = $archivoService;
    }

    /**
     * Crear un nuevo RUT para el usuario autenticado.
     *
     * Este método permite registrar un único RUT por usuario. Antes de la creación, se verifica si ya existe
     * un RUT asociado al usuario y, en caso afirmativo, se retorna una respuesta con código 409 (conflicto).
     * Si no existe, se procede a crear el registro dentro de una transacción para asegurar la integridad de los datos.
     * Si se adjunta un archivo (por ejemplo, el PDF del RUT), se guarda mediante el servicio `ArchivoService`.
     * En caso de error durante la creación o almacenamiento del archivo, se captura una excepción y se retorna una
     * respuesta con el mensaje de error correspondiente.
     *
     * @param CrearRutRequest $request Solicitud validada con los datos del RUT y archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function crearRut(CrearRutRequest $request)
    {
        try {

            $usuarioId = $request->user()->id; // Obtiene el ID del usuario autenticado
            $rutExistente = Rut::where('user_id', $usuarioId)->first(); // Verifica si ya existe un RUT para el usuario

            if ($rutExistente) { // Si ya hay un RUT, se devuelve un mensaje de conflicto (409)
                return response()->json([
                    'message' => 'Ya tienes un RUT registrado. No puedes crear otro.',
                ], 409);
            }

            DB::transaction(function () use ($request) { // Se crea el RUT dentro de una transacción de base de datos

                $datos = $request->validated(); // Obtiene los datos validados desde la solicitud
                $datos['user_id'] = $request->user()->id; // Asigna el ID del usuario autenticado
                $rut = Rut::create($datos); // Crea el nuevo registro de RUT en la base de datos

                if ($request->hasFile('archivo')) { // Si el usuario adjunta un archivo, se guarda
                    $this->archivoService->guardarArchivoDocumento($request->file('archivo'), $rut, 'Rut');
                }
            });
            // Respuesta exitosa con el objeto creado y código 201 (creado)
            return response()->json([
                'message' => 'RUT creado exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([ // Captura errores y devuelve respuesta con código 500
                'message' => 'Error al crear el RUT',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener el RUT registrado por el usuario autenticado.
     *
     * Este método busca y retorna el registro de RUT asociado al usuario actual, incluyendo los documentos 
     * relacionados. Si no existe un RUT registrado, se responde con un mensaje informativo y un valor nulo.
     * Para cada documento encontrado, se genera una URL pública al archivo almacenado.
     * En caso de ocurrir un error durante la consulta, se captura la excepción y se retorna una respuesta adecuada.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el RUT y sus documentos, o mensaje de error.
     */
    public function obtenerRut(Request $request)
    {
        try {

            $rut = Rut::where('user_id', $request->user()->id) // Busca el RUT asociado al usuario actual y carga sus documentos relacionados
                ->with(['documentosRut:id_documento,documentable_id,archivo,estado'])
                ->first(); // Si no se encuentra, lanza una excepción

            if (!$rut) {
                return response()->json([
                    'message' => 'No tienes RUT registrada aún.',
                    'rut' => null
                ], 200); // No es error, simplemente no tiene Rut aún
            }

            foreach ($rut->documentosRut as $documento) {
                if (!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo);
                }
            }

            return response()->json(['rut' => $rut], 200); // Devuelve el RUT encontrado junto con sus archivos

        } catch (\Exception $e) {
            return response()->json([ // Captura errores y responde con código 500
                'message' => 'Error al obtener el RUT',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar el RUT del usuario autenticado.
     *
     * Este método permite modificar el registro de RUT existente del usuario actual. La operación se ejecuta
     * dentro de una transacción para garantizar la integridad de los datos. Si se adjunta un nuevo archivo,
     * este se actualiza utilizando el servicio `ArchivoService`. En caso de que el RUT no exista o se genere 
     * un error durante la operación, se captura la excepción y se retorna una respuesta con el mensaje de error.
     *
     * @param ActualizarRutRequest $request Solicitud validada con los datos actualizados y archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarRut(ActualizarRutRequest $request)
    {
        try {

            DB::transaction(function () use ($request) { // Ejecuta la actualización dentro de una transacción
                $rut = Rut::where('user_id', $request->user()->id)->firstOrFail(); // Busca el RUT del usuario autenticado
                $rut->update($request->validated()); // Actualiza el registro con los datos validados de la solicitud

                if ($request->hasFile('archivo')) { // Si el usuario proporciona un nuevo archivo, se actualiza
                    $this->archivoService->actualizarArchivoDocumento($request->file('archivo'), $rut, 'Rut');
                }
            });
            // Devuelve respuesta con el RUT actualizado (se utiliza fresh() para obtener los datos actuales desde la DB)
            return response()->json([
                'message' => 'RUT actualizado exitosamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores con respuesta 500
                'message' => 'Error al actualizar el RUT',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
