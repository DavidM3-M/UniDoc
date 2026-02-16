<?php

namespace App\Http\Controllers\Aspirante;

use Illuminate\Http\Request;
use App\Models\Aspirante\InformacionContacto;
use App\Http\Requests\RequestAspirante\RequestInformacionContacto\ActualizarInformacionContactoRequest;
use App\Http\Requests\RequestAspirante\RequestInformacionContacto\CrearInformacionContactoRequest;
use App\Services\ArchivoService;
use Illuminate\Support\Facades\DB;


// Controlador para manejar la información de contacto de los aspirantes
// Este controlador permite crear, obtener y actualizar la información de contacto
class InformacionContactoController
{
    protected $archivoService;

    /**
     * Constructor del controlador.
     *
     * Inyecta el servicio `ArchivoService`, utilizado para gestionar las operaciones de carga,
     * actualización y eliminación de archivos asociados a los registros de informacion de contacto del usuario.
     *
     * @param ArchivoService $archivoService Servicio responsable de la gestión de archivos adjuntos.
     */
    public function __construct(ArchivoService $archivoService)
    {
        $this->archivoService = $archivoService;
    }

    /**
     * Crear información de contacto para el usuario autenticado.
     *
     * Este método permite registrar un único registro de información de contacto para el usuario autenticado.
     * Antes de crear el nuevo registro, verifica si ya existe uno y, de ser así, retorna un error 409 (conflicto).
     * Si no existe, procede a crear el registro dentro de una transacción y, si se adjunta un archivo (libreta militar),
     * se guarda utilizando el servicio `ArchivoService`. En caso de errores durante el proceso, se captura la excepción
     * y se retorna una respuesta con el mensaje de error.
     *
     * @param CrearInformacionContactoRequest $request Solicitud validada con los datos de contacto y archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function crearInformacionContacto(CrearInformacionContactoRequest $request)
    {
        try {

            $usuarioId = $request->user()->id; // Se obtiene el ID del usuario autenticado
            $informacionContactoExistente = InformacionContacto::where('user_id', $usuarioId)->first(); // Se verifica si ya existe información de contacto para ese usuario

            if ($informacionContactoExistente) { // Si ya existe, se devuelve un error 409 (conflicto)
                return response()->json([
                    'message' => 'Ya tienes un registro de información de contacto. No puedes crear otro.',
                ], 409);
            }

            DB::transaction(function () use ($request) { // Se inicia una transacción para crear el registro y guardar el archivo
                $datos = $request->validated();
                $datos['user_id'] = $request->user()->id; // Se asocia el registro al usuario autenticado
                $informacionContacto = InformacionContacto::create($datos); // Se crea el nuevo registro

                if ($request->hasFile('archivo')) { // Si se envió un archivo, se guarda mediante el servicio
                    $this->archivoService->guardarArchivoDocumento($request->file('archivo'), $informacionContacto, 'LibretaMilitar');
                }
            });

            return response()->json([ // Respuesta exitosa
                'message' => 'Información de contacto y documento guardados correctamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([ // En caso de error, se devuelve un mensaje y el detalle del error
                'message' => 'Error al crear la información de contacto o subir el archivo.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener la información de contacto del usuario autenticado.
     *
     * Este método recupera el registro de información de contacto asociado al usuario autenticado,
     * incluyendo los documentos relacionados (por ejemplo, libreta militar). Si no existe ningún registro,
     * se retorna una respuesta exitosa con valor nulo. Para cada documento, se genera una URL accesible
     * al archivo almacenado. En caso de ocurrir un error durante el proceso, se captura la excepción
     * y se responde con el mensaje de error correspondiente.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la información de contacto o mensaje de error.
     */
    public function obtenerInformacionContacto(Request $request)
    {
        try {

            $user = $request->user(); // Se obtiene el usuario autenticado

            $informacionContacto = InformacionContacto::where('user_id', $user->id) // Se obtiene la información de contacto del usuario junto con los documentos relacionados
                ->with(['documentosInformacionContacto:id_documento,documentable_id,archivo,estado'])
                ->first();

            if (!$informacionContacto) {
                return response()->json([
                    'message' => 'No tienes EPS registrada aún.',
                    'informacionContacto' => null
                ], 200); // No es error, simplemente no tiene InformacionContacto aún
            }

            foreach ($informacionContacto->documentosInformacionContacto as $documento) { // Se genera la URL completa de cada archivo si existe
                if (!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo);
                }
            }

            return response()->json(['informacion_contacto' => $informacionContacto], 200); // Se retorna la información encontrada

        } catch (\Exception $e) {
            return response()->json([ // En caso de error, se devuelve el mensaje y el código correspondiente
                'message' => 'Error al obtener la información de contacto',
                'error'   => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Actualizar la información de contacto del usuario autenticado.
     *
     * Este método permite modificar el registro de información de contacto existente del usuario. La operación
     * se realiza dentro de una transacción para garantizar la consistencia de los datos. Si se adjunta un nuevo
     * archivo (como una versión actualizada de la libreta militar), se reemplaza utilizando el servicio `ArchivoService`.
     * En caso de que el registro no exista o se produzca un error durante el proceso, se captura la excepción
     * y se retorna una respuesta adecuada.
     *
     * @param ActualizarInformacionContactoRequest $request Solicitud validada con los nuevos datos y archivo opcional.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarInformacionContacto(ActualizarInformacionContactoRequest $request)
    {
        try {

            DB::transaction(function () use ($request) { // Se utiliza una transacción para actualizar el registro y el archivo
                $user = $request->user(); // Se obtiene el usuario autenticado
                $informacionContacto = InformacionContacto::where('user_id', $user->id)->firstOrFail(); // Se busca el registro actual de información de contacto del usuario
                $datos = $request->validated(); // Se validan los nuevos datos
                $informacionContacto->update($datos); // Se actualiza el registro con los nuevos datos

                if ($request->hasFile('archivo')) { // Si hay un nuevo archivo, se actualiza mediante el servicio
                    $this->archivoService->actualizarArchivoDocumento($request->file('archivo'), $informacionContacto, 'LibretaMilitar');
                }
            });

            return response()->json([ // Respuesta exitosa con los datos frescos (refrescados de la base de datos)
                'message' => 'Información de contacto actualizada correctamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([ // En caso de error, se devuelve el mensaje y el detalle del error
                'message' => 'Error al actualizar la información de contacto o subir el archivo.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
