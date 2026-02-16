<?php

namespace App\Http\Controllers\TalentoHumano;

use App\Http\Requests\RequestTalentoHumano\RequestContratacion\CrearContratacionRequest;
use App\Http\Requests\RequestTalentoHumano\RequestContratacion\ActualizarContratacionRequest;
use App\Models\Usuario\User;
use Illuminate\Support\Facades\DB;
use App\Models\TalentoHumano\Contratacion;
use App\Models\TalentoHumano\Convocatoria;
use App\Models\TalentoHumano\ConvocatoriaAval;
use Illuminate\Support\Facades\Auth;
use App\Services\AprobarDocumentosService;
use App\Services\RevertirDocumentosService;

class ContratacionController
{
    protected $aprobarDocumentosService;
    protected $revertirDocumentosService;

    /**
     * Constructor del controlador.
     *
     * Inyecta los servicios encargados de la aprobación y reversión de documentos.
     * - `AprobarDocumentosService`: se utiliza para aprobar documentos académicos u otros tipos relacionados.
     * - `RevertirDocumentosService`: se encarga de revertir el estado de los documentos previamente aprobados o rechazados.
     *
     * @param AprobarDocumentosService $aprobarDocumentosService Servicio para aprobar documentos.
     * @param RevertirDocumentosService $revertirDocumentosService Servicio para revertir documentos.
     */
    public function __construct(AprobarDocumentosService $aprobarDocumentosService, RevertirDocumentosService $revertirDocumentosService)
    {
        $this->aprobarDocumentosService = $aprobarDocumentosService;
        $this->revertirDocumentosService = $revertirDocumentosService;
    }

    /**
     * Crear una contratación para un usuario y asignarle el rol de docente.
     *
     * Este método registra una contratación para un usuario específico, siempre y cuando no tenga
     * una contratación existente. La operación se realiza dentro de una transacción para garantizar
     * la consistencia de los datos. Además, se actualiza el rol del usuario a "Docente" y se aprueban
     * automáticamente todos sus documentos mediante el servicio `AprobarDocumentosService`.
     * En caso de que ya exista una contratación o se produzca un error durante el proceso, se lanza
     * una excepción con el código correspondiente.
     *
     * @param CrearContratacionRequest $request Solicitud validada con los datos de contratación.
     * @param int $user_id ID del usuario al que se le va a crear la contratación.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function crearContratacion(CrearContratacionRequest $request, $user_id)
    {
        try {
            DB::transaction(function () use ($request, $user_id) { // Inicia una transacción para asegurar la atomicidad de las operaciones
                $datosContratacion = $request->validated(); // Validar los datos de la solicitud
                // Si se envía una convocatoria, verificar que los avales requeridos estén aprobados para este usuario
                if (!empty($datosContratacion['convocatoria_id'])) {
                    $conv = Convocatoria::find($datosContratacion['convocatoria_id']);
                    if ($conv && !empty($conv->avales_establecidos)) {
                        $faltantes = [];
                        foreach ($conv->avales_establecidos as $avalRequerido) {
                            $aprobado = ConvocatoriaAval::where('convocatoria_id', $conv->id_convocatoria)
                                ->where('user_id', $user_id)
                                ->where('aval', $avalRequerido)
                                ->where('estado', 'aprobado')
                                ->exists();
                            if (!$aprobado) $faltantes[] = $avalRequerido;
                        }
                        if (!empty($faltantes)) {
                            throw new \Exception('Faltan avales necesarios: ' . implode(', ', $faltantes), 403);
                        }
                    }
                }
                $datosContratacion['user_id'] = $user_id; // Asignar el user_id a los datos de contratación

                $existeContratacion = Contratacion::where('user_id', $user_id)->exists(); // Verificar si ya existe una contratación para el usuario
                if ($existeContratacion) {
                    throw new \Exception('El usuario ya tiene una contratación existente.', 409);
                }

                $usuario = User::findOrFail($user_id); // Buscar el usuario por su ID
                Contratacion::create($datosContratacion); // Crear la contratación en la base de datos

                $usuario->syncRoles(['Docente']); // Cambiar el rol del usuario a 'Docente'

                $this->aprobarDocumentosService->aprobarDocumentosDeUsuario($usuario); // Aprobar los documentos del usuario

            });

            return response()->json([ // Respuesta exitosa
                'message' => 'Contratación creada y rol actualizado a docente.',
            ], 201);
        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Actualizar una contratación existente.
     *
     * Este método permite modificar los datos de una contratación ya registrada, identificada por su ID.
     * La operación se realiza dentro de una transacción para garantizar la consistencia de los datos durante la actualización.
     * En caso de que no se encuentre la contratación o se produzca un error durante el proceso,
     * se captura la excepción y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param ActualizarContratacionRequest $request Solicitud validada con los nuevos datos de la contratación.
     * @param int $id_contratacion ID de la contratación que se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarContratacion(ActualizarContratacionRequest $request, $id_contratacion)
    {
        try {
            DB::transaction(function () use ($request, $id_contratacion) { // Inicia una transacción para asegurar la atomicidad de las operaciones
                $contratacion = Contratacion::findOrFail($id_contratacion); // Buscar la contratación por su ID

                $datosActualizarContratacion = $request->validated(); // Validar los datos de la solicitud
                $contratacion->update($datosActualizarContratacion); // Actualizar los datos de la contratación

            });

            return response()->json([
                'message' => 'Contratación actualizada correctamente.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la contratación.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Eliminar una contratación y revertir el estado del usuario.
     *
     * Este método elimina una contratación existente identificada por su ID.
     * Una vez eliminada, si el usuario asociado existe, su rol se revierte a "Aspirante"
     * y todos sus documentos aprobados o gestionados previamente son revertidos
     * mediante el servicio `RevertirDocumentosService`.
     * La operación se realiza dentro de una transacción para asegurar la coherencia del sistema.
     * En caso de error, se captura la excepción y se retorna un mensaje adecuado.
     *
     * @param int $id ID de la contratación que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function eliminarContratacion($id)
    {
        try {
            DB::transaction(function () use ($id) { // Inicia una transacción para asegurar la atomicidad de las operaciones
                $contratacion = Contratacion::findOrFail($id); // Buscar la contratación por su ID
                $usuario = $contratacion->UsuarioContratacion; // Obtener el usuario relacionado con la contratación

                $contratacion->delete(); // Eliminar la contratación

                if ($usuario) { // Verificar si el usuario existe
                    $usuario->syncRoles(['Aspirante']);

                    $this->revertirDocumentosService->revertirDocumentosDeUsuario($usuario); // Revertir los documentos del usuario
                }
            });

            return response()->json([ // Respuesta exitosa
                'message' => 'Contratación eliminada y rol cambiado a aspirante.'
            ], 200);
        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al eliminar la contratación.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Obtener todas las contrataciones registradas.
     *
     * Este método recupera todas las contrataciones almacenadas en la base de datos,
     * incluyendo la información del usuario relacionado con cada una de ellas.
     * Las contrataciones se ordenan de forma descendente según su fecha de inicio.
     * En caso de error durante la consulta, se captura una excepción y se retorna una respuesta con el mensaje correspondiente.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de contrataciones o mensaje de error.
     */
    public function obtenerTodasLasContrataciones()
    {
        try {
            $contrataciones = Contratacion::with('UsuarioContratacion') // obtener las contrataciones
                ->orderBy('fecha_inicio', 'desc') // ordenar por fecha de inicio
                ->get();

            return response()->json([ // Respuesta exitosa
                'message' => 'Contrataciones obtenidas correctamente.',
                'contrataciones' => $contrataciones
            ], 200);
        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al obtener las contrataciones.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una contratación específica por su ID.
     *
     * Este método busca y devuelve la información de una contratación determinada,
     * incluyendo los datos del usuario relacionado mediante la relación `UsuarioContratacion`.
     * Si la contratación no existe, se lanza una excepción y se responde con un mensaje de error adecuado.
     *
     * @param int $id_contratacion ID de la contratación que se desea consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con los datos de la contratación o mensaje de error.
     */
    public function obtenerContratacionPorId($id_contratacion)
    {
        try {

            $contratacion = Contratacion::with('UsuarioContratacion') // Si tienes relación con el modelo User
                ->findOrFail($id_contratacion); // Buscar la contratación por su ID

            return response()->json([ // Respuesta exitosa
                'message' => 'Información de contratación obtenida correctamente.',
                'contratacion' => $contratacion
            ], 200);
        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al obtener la información de la contratación.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }



    // public function obtenerContratacionesPorUsuario($user_id)
    // {
    //     try {
    //         $contrataciones = Contratacion::where('user_id', $user_id)
    //             ->orderBy('fecha_inicio', 'desc')
    //             ->get();

    //         if ($contrataciones->isEmpty()) {
    //             return response()->json([
    //                 'message' => 'No se encontraron contrataciones para este usuario.'
    //             ], 404);
    //         }

    //         return response()->json([
    //             'message' => 'Contrataciones obtenidas correctamente.',
    //             'contrataciones' => $contrataciones
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Error al obtener las contrataciones del usuario.',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    /**
     * Obtener las contrataciones del usuario autenticado.
     *
     * Este método consulta y retorna todas las contrataciones asociadas al usuario actualmente autenticado,
     * ordenadas por fecha de inicio en orden descendente. Si el usuario no tiene contrataciones registradas,
     * se lanza una excepción con código 404. En caso de error durante el proceso de consulta, se captura
     * la excepción y se retorna una respuesta con el mensaje correspondiente.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las contrataciones del usuario o mensaje de error.
     */
    public function obtenerContratacionUsuario()
    {
        try {
            $usuario = Auth::user(); // Obtener el usuario autenticado

            $contrataciones = Contratacion::where('user_id', $usuario->id) // Filtrar por el ID del usuario autenticado
                ->orderBy('fecha_inicio', 'desc') // Ordenar por fecha de inicio
                ->get();

            if ($contrataciones->isEmpty()) {
                throw new  \Exception('No se encontraron contrataciones para el usuario autenticado.', 404);
            }

            return response()->json([
                'message' => 'Contrataciones del usuario autenticado obtenidas correctamente.',
                'contrataciones' => $contrataciones
            ], 200);
        } catch (\Exception $e) { // Manejo de excepciones
            return response()->json([
                'message' => 'Error al obtener las contrataciones del usuario autenticado.',
                'error' => $e->getMessage()
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }
}
