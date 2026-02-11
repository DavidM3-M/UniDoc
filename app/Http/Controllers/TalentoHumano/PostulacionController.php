<?php

namespace App\Http\Controllers\TalentoHumano;

use App\Constants\ConstTalentoHumano\EstadoPostulacion;
use App\Models\TalentoHumano\Postulacion;
use App\Models\TalentoHumano\Convocatoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\GeneradorHojaDeVidaPDFService;
use Illuminate\Validation\Rule;

class PostulacionController
{
    protected $generadorHojaDeVidaPDFService;
    /**
     * constructor del controlador.
     * se utilizar para inyectar el servicio de generador de hoja de vida a PDF.
     */
    public function __construct(GeneradorHojaDeVidaPDFService $generadorHojaDeVidaPDFService)
    {
        $this->generadorHojaDeVidaPDFService = $generadorHojaDeVidaPDFService;
    }

    /**
     * Crear una postulación del usuario autenticado a una convocatoria.
     *
     * Este método permite que un usuario autenticado se postule a una convocatoria específica.
     * La operación se ejecuta dentro de una transacción para garantizar la integridad de los datos.
     * Se valida que:
     * - La convocatoria exista.
     * - La convocatoria esté abierta (no cerrada).
     * - El usuario no se haya postulado previamente a la misma convocatoria.
     *
     * Si esta correcto, se registra la postulación con estado inicial "Enviada".
     * En caso de errores (convocatoria cerrada, duplicidad de postulación u otros),
     * se lanza una excepción y se retorna una respuesta con el mensaje adecuado.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @param int $convocatoriaId ID de la convocatoria a la que el usuario desea postularse.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function crearPostulacion(Request $request, $convocatoriaId)
    {
        try {
            $convocatoria = Convocatoria::findOrFail($convocatoriaId);
             // Verificar si la convocatoria está cerrada
            if ($convocatoria->estado_convocatoria === 'Cerrada') {
                return response()->json([
                    'mensaje' => 'Esta convocatoria ya está cerrada'
                ], 403);
            }

            // Verificar si la fecha de cierre ya pasó
            if (now()->greaterThan($convocatoria->fecha_cierre)) {
                return response()->json([
                    'mensaje' => 'La fecha de cierre de esta convocatoria ya ha pasado'
                ], 403);
            }
            
            DB::transaction(function () use ($request, $convocatoriaId) { // Validar el ID de la convocatoria
                $user = $request->user(); // Obtener el usuario autenticado

                $convocatoria = Convocatoria::findOrFail($convocatoriaId); // Verificar si la convocatoria existe

                if ($convocatoria->estado_convocatoria === 'Cerrada') { // Verificar si la convocatoria está cerrada
                    throw new \Exception('Esta convocatoria está cerrada y no admite más postulaciones.', 403); // Lanzar excepción si la convocatoria está cerrada
                }

                $existe = Postulacion::where('user_id', $user->id) // Verificar si el usuario ya está postulado
                    ->where('convocatoria_id', $convocatoriaId)
                    ->exists();

                if ($existe) {
                    throw new \Exception('Ya te has postulado a esta convocatoria', 409);
                }

                Postulacion::create([ // Crear la postulación
                    'user_id' => $user->id,
                    'convocatoria_id' => $convocatoriaId,
                    'estado_postulacion' => 'Enviada'
                ]);
            });

            return response()->json([ // Crear la respuesta JSON
                'message' => 'Postulación enviada correctamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al crear la postulación.',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Obtener todas las postulaciones registradas en el sistema.
     *
     * Este método recupera todas las postulaciones realizadas por los usuarios, incluyendo
     * la información del usuario postulante (`usuarioPostulacion`) y de la convocatoria
     * correspondiente (`convocatoriaPostulacion`). Las postulaciones se ordenan de forma
     * descendente según su fecha de creación.
     * En caso de producirse un error durante la consulta, se captura la excepción y se
     * retorna una respuesta adecuada con el mensaje de error.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de postulaciones o mensaje de error.
     */
    public function obtenerPostulaciones()
    {
        try {
            $postulaciones = Postulacion::with('usuarioPostulacion', 'convocatoriaPostulacion') // Obtener todas las postulaciones
                ->orderBy('created_at', 'desc') // Ordenar por fecha de creación
                ->get();

            return response()->json(['postulaciones' => $postulaciones], 200); // Retornar las postulaciones en formato JSON

        } catch (\Exception $e) {
            return response()->json([ // Manejar excepciones
                'message' => 'Ocurrió un error al obtener las postulaciones.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener las postulaciones asociadas a una convocatoria específica.
     *
     * Este método recupera todas las postulaciones realizadas a una convocatoria determinada,
     * identificada por su ID. Cada postulación incluye la información del usuario postulante
     * gracias a la relación `usuarioPostulacion`.
     * En caso de error durante la consulta, se captura una excepción y se retorna una respuesta adecuada.
     *
     * @param int $idConvocatoria ID de la convocatoria cuyas postulaciones se desean consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de postulaciones o mensaje de error.
     */
    // public function obtenerPorConvocatoria($idConvocatoria)
    // {
    //     try {
    //         $postulaciones = Postulacion::where('convocatoria_id', $idConvocatoria) // Obtener las postulaciones por ID de convocatoria
    //             ->with('usuarioPostulacion') // Incluir la relación con el usuario postulante
    //             ->get();

    //         return response()->json(['postulaciones' => $postulaciones], 200); // Retornar las postulaciones en formato JSON

    //     } catch (\Exception $e) { // Manejar excepciones
    //         return response()->json([
    //             'message' => 'Ocurrió un error al obtener las postulaciones por convocatoria.', // Retornar un mensaje de error
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Obtener las postulaciones del usuario autenticado.
     *
     * Este método recupera todas las postulaciones realizadas por el usuario que ha iniciado sesión.
     * Cada postulación incluye la información relacionada con la convocatoria a la que se postuló,
     * gracias a la relación `convocatoriaPostulacion`.
     * En caso de error durante la consulta, se captura una excepción y se retorna una respuesta adecuada.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de postulaciones del usuario o mensaje de error.
     */
    public function obtenerPostulacionesUsuario(Request $request)
    {
        try {
            $postulaciones = Postulacion::where('user_id', $request->user()->id) // Obtener las postulaciones del usuario autenticado
                ->with('convocatoriaPostulacion') // Incluir la relación con la convocatoria
                ->get();

            return response()->json(['postulaciones' => $postulaciones], 200); // Retornar las postulaciones en formato JSON

        } catch (\Exception $e) { // Manejar excepciones
            return response()->json([ // Retornar un mensaje de error
                'message' => 'Ocurrió un error al obtener las postulaciones del usuario.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar la hoja de vida en PDF de un usuario postulado a una convocatoria específica.
     *
     * Este método verifica que el usuario esté postulado a la convocatoria indicada. Si la postulación existe,
     * se utiliza el servicio `GeneradorHojaDeVidaPDFService` para generar el PDF de la hoja de vida.
     * Si el usuario no está postulado a la convocatoria, se retorna una respuesta con código 404.
     * En caso de error durante el proceso, se captura la excepción y se responde con un mensaje adecuado.
     *
     * @param int $idConvocatoria ID de la convocatoria.
     * @param int $idUsuario ID del usuario cuya hoja de vida se desea generar.
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     * Respuesta JSON con mensaje de error o archivo PDF generado exitosamente.
     */
    public function generarHojaDeVidaPDF($idConvocatoria, $idUsuario)
    {
        try {
            $postulacion = Postulacion::where('convocatoria_id', $idConvocatoria) // Obtener la postulación del usuario a la convocatoria
                ->where('user_id', $idUsuario) // Verificar que el usuario esté postulado a la convocatoria
                ->first();

            if (!$postulacion) {
                return response()->json([ // Retornar un mensaje de error si el usuario no está postulado
                    'message' => 'El usuario no está postulado a esta convocatoria.'
                ], 404);
            }

            return $this->generadorHojaDeVidaPDFService->generar($idUsuario); // Generar la hoja de vida en PDF

        } catch (\Exception $e) { // Manejar excepciones
            return response()->json([
                'message' => 'Ocurrió un error al generar la hoja de vida.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar el estado de una postulación.
     *
     * Este método permite modificar el estado de una postulación específica, validando primero que el nuevo estado
     * esté dentro de los valores definidos en la enumeración `EstadoPostulacion`.
     * La operación se realiza dentro de una transacción para asegurar la integridad de los datos.
     * Si la postulación no existe, se lanza una excepción con código 404.
     * En caso de error durante la validación o actualización, se captura la excepción y se retorna una respuesta adecuada.
     *
     * @param Request $request Solicitud HTTP que contiene el nuevo estado de la postulación.
     * @param int $idPostulacion ID de la postulación que se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarEstadoPostulacion(Request $request, $idPostulacion)
    {
        try {
            $request->validate([
                'estado_postulacion' => ['required', 'string', Rule::in(EstadoPostulacion::all())], // Validar el estado de la postulación
            ]);
            DB::transaction(function () use ($request, $idPostulacion) { // Iniciar una transacción para garantizar la integridad de los datos
                $postulacion = Postulacion::find($idPostulacion); // Buscar la postulación por su ID

                if (!$postulacion) { // Verificar si la postulación existe
                    throw new \Exception('No se encontro una postulación.', 404);
                }

                $postulacion->estado_postulacion = $request->estado_postulacion; // Actualizar el estado de la postulación
                $postulacion->save(); // Guardar los cambios en la base de datos

            });

            return response()->json([
                'message' => 'Estado de postulación actualizado correctamente.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al actualizar el estado de la postulación.',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Eliminar una postulación específica.
     *
     * Este método permite eliminar una postulación del sistema, identificada por su ID.
     * La operación se ejecuta dentro de una transacción para asegurar la integridad de los datos.
     * Si la postulación no existe, se lanza una excepción con código 404.
     * En caso de ocurrir un error durante el proceso de eliminación, se captura la excepción
     * y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param int $idPostulacion ID de la postulación que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function eliminarPostulacion($idPostulacion)
    {
        try {
            DB::transaction(function () use ($idPostulacion) { // Iniciar una transacción para garantizar la integridad de los datos
                $postulacion = Postulacion::find($idPostulacion); // Buscar la postulación por su ID

                if (!$postulacion) { // Verificar si la postulación existe
                    throw new \Exception('Postulación no encontrada.', 404);
                }

                $postulacion->delete(); // Eliminar la postulación
            });

            return response()->json([ // Retornar un mensaje de éxito
                'message' => 'Postulación eliminada correctamente.'
            ]);

        } catch (\Exception $e) { // Manejar excepciones
            return response()->json([ // Retornar un mensaje de error
                'message' => 'Ocurrió un error al eliminar la postulación.',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Eliminar una postulación realizada por el usuario autenticado.
     *
     * Este método permite que un usuario elimine su propia postulación, identificada por su ID.
     * Se valida que la postulación exista y que pertenezca al usuario autenticado para evitar accesos no autorizados.
     * Si la validación es exitosa, se elimina la postulación. En caso contrario, se lanza una excepción con el código correspondiente.
     * Si ocurre cualquier error durante el proceso, se retorna una respuesta con el mensaje adecuado.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @param int $id ID de la postulación que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function eliminarPostulacionUsuario(Request $request, $id)
    {
        try {
            $postulacion = Postulacion::find($id); // Buscar la postulación por su ID

            if (!$postulacion) {
                throw new \Exception('Postulación no encontrada.', 404);
            }

            if ($postulacion->user_id !== $request->user()->id) { // Verificar si el usuario autenticado es el propietario de la postulación
                throw new \Exception('No tienes permiso para eliminar esta postulación.', 403);
            }

            $postulacion->delete(); // Eliminar la postulación

            return response()->json([ // Retornar un mensaje de éxito
                'message' => 'Postulación eliminada correctamente.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al eliminar la postulación del usuario.',
                'error' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }
    /**
 * Generar la hoja de vida en PDF de un usuario (para Rectoría/Vicerrectoría).
 *
 * Este método genera el PDF de la hoja de vida de un usuario sin necesidad de verificar
 * una postulación específica. Es utilizado por roles administrativos como Rectoría.
 *
 * @param int $idUsuario ID del usuario cuya hoja de vida se desea generar.
 * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
 * Respuesta JSON con mensaje de error o archivo PDF generado exitosamente.
 */
public function generarHojaDeVidaPDFSimple($idUsuario)
{
    try {
        return $this->generadorHojaDeVidaPDFService->generar($idUsuario);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Ocurrió un error al generar la hoja de vida.',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
