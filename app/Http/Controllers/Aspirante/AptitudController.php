<?php

namespace App\Http\Controllers\Aspirante;

use App\Http\Requests\RequestAspirante\RequestAptitud\ActualizarAptitudRequest;
use App\Http\Requests\RequestAspirante\RequestAptitud\CrearAptitudRequest;
use Illuminate\Http\Request;
use App\Models\Aspirante\Aptitud;


// Este controlador maneja las operaciones relacionadas con las aptitudes de los aspirantes.
// Permite crear, obtener, actualizar y eliminar aptitudes asociadas a un usuario autenticado.
class AptitudController
{

    /**
     * Crea una nueva aptitud para el usuario autenticado.
     *
     * Este método recibe los datos validados desde la solicitud mediante
     * la clase `CrearAptitudRequest`, y automáticamente asocia la aptitud
     * al usuario que está autenticado. Una vez creada la aptitud en la base
     * de datos, retorna una respuesta JSON con un mensaje de éxito y el código
     * HTTP 201 (creado).
     *
     * @param  \App\Http\Requests\Aptitud\CrearAptitudRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function crearAptitud(CrearAptitudRequest $request)
    {
        try {
            $datosAptitudCrear = $request->validated(); // Obtener los datos validados de la solicitud
            $datosAptitudCrear['user_id'] = $request->user()->id; // Asignar el ID del usuario autenticado
            Aptitud::create($datosAptitudCrear); // Crear la nueva aptitud
            return response()->json(['mensaje' => 'Aptitud creada exitosamente.'], 201); // Retornar respuesta con la aptitud creada

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'mensaje' => 'Error al crear la aptitud.',
                'error'   => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500); // Retorna el código del error o 500 si no hay código
        }
    }

    /**
     * Obtener las aptitudes del usuario autenticado.
     *
     * Este método recupera todas las aptitudes asociadas al usuario que realiza la solicitud,
     * utilizando su ID autenticado. Se emplea un bloque try-catch para capturar cualquier excepción
     * inesperada durante la consulta o la generación de la respuesta JSON.
     *
     * @param Request $request La solicitud HTTP que contiene la información del usuario autenticado.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de aptitudes del usuario o un mensaje de error.
     */
    public function obtenerAptitudes(Request $request)
    {
        try {
            $user = $request->user(); // Obtener usuario autenticado desde el request
            $aptitudes = Aptitud::where('user_id', $user->id)->get(); // Obtener todas las aptitudes del usuario
            return response()->json(['aptitudes' => $aptitudes], 200); // Retornar respuesta con las aptitudes encontradas

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'mensaje' => 'Error al obtener las aptitudes.',
                'error'   => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500); // Retorna el código del error o 500 si no hay código
        }
    }

    /**
     * Obtener una aptitud específica del usuario autenticado por su ID.
     *
     * Este método busca una aptitud que coincida con el ID proporcionado y que pertenezca al usuario autenticado.
     * Si la aptitud no existe o no está asociada al usuario, se retorna una respuesta con código 404.
     * En caso de errores inesperados, se maneja la excepción y se retorna una respuesta con el mensaje de error.
     *
     * @param Request $request La solicitud HTTP que contiene la información del usuario autenticado.
     * @param int $id El ID de la aptitud que se desea consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con la aptitud encontrada o mensaje de error.
     */
    public function obtenerAptitudesPorId(Request $request, $id)
    {
        try {
            $user = $request->user(); // Obtener usuario autenticado
            $aptitud = Aptitud::where('user_id', $user->id) // Buscar la aptitud por ID y que pertenezca al usuario
                ->where('id_aptitud', $id)
                ->first();

            if (!$aptitud) { // Si no se encuentra la aptitud, retornar mensaje 404
                return response()->json(['mensaje' => 'Aptitud no encontrada.'], 404);
            }
            return response()->json(['aptitud' => $aptitud], 200); // Retornar aptitud encontrada

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'mensaje' => 'Error al obtener la aptitud.',
                'error'   => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Actualizar una aptitud específica del usuario autenticado por su ID.
     *
     * Este método permite modificar una aptitud existente asociada al usuario autenticado.
     * Primero verifica que la aptitud exista y pertenezca al usuario. Luego valida y aplica
     * los datos enviados en la solicitud. Si la aptitud no se encuentra, retorna un error 404.
     * Se maneja cualquier excepción para garantizar una respuesta clara en caso de error.
     *
     * @param ActualizarAptitudRequest $request Solicitud validada con los nuevos datos para la aptitud.
     * @param int $id ID de la aptitud que se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito o mensaje de error.
     */
    public function actualizarAptitudPorId(ActualizarAptitudRequest $request, $id)
    {
        try {
            $user = $request->user(); // Obtener usuario autenticado
            $aptitud = Aptitud::where('user_id', $user->id) // Buscar la aptitud por ID y usuario
                ->where('id_aptitud', $id)
                ->first();

            if (!$aptitud) { // Si no se encuentra, retornar error 404
                return response()->json(['mensaje' => 'Aptitud no encontrada.'], 404);
            }

            $datosAptitudActualizar = $request->validated(); // Obtener los datos validados de la solicitud
            $aptitud->update($datosAptitudActualizar); // Actualizar la aptitud con los nuevos datos

            return response()->json([ // Retornar mensaje de éxito
                'mensaje' => 'Aptitud actualizada correctamente.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'mensaje' => 'Error al actualizar la aptitud.',
                'error'   => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

    /**
     * Eliminar una aptitud específica del usuario autenticado por su ID.
     *
     * Este método busca una aptitud asociada al usuario autenticado mediante su ID. Si se encuentra,
     * procede a eliminarla de la base de datos. Si no existe o no pertenece al usuario, retorna un error 404.
     * En caso de ocurrir una excepción durante el proceso, se captura y se devuelve una respuesta con el error.
     *
     * @param Request $request La solicitud HTTP que contiene la información del usuario autenticado.
     * @param int $id El ID de la aptitud que se desea eliminar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON indicando el resultado de la operación.
     */
    public function eliminarAptitudPorId(Request $request, $id)
    {
        try {
            $user = $request->user(); // Obtener usuario autenticado
            $aptitud = Aptitud::where('user_id', $user->id) // Buscar la aptitud por ID y usuario
                ->where('id_aptitud', $id)
                ->first();

            if (!$aptitud) { // Si no se encuentra, retornar mensaje 404
                return response()->json(['mensaje' => 'Aptitud no encontrada.'], 404);
            }

            $aptitud->delete(); // Eliminar la aptitud
            return response()->json(['mensaje' => 'Aptitud eliminada correctamente.'], 200); // Retornar mensaje de éxito

        } catch (\Exception $e) {
            return response()->json([ // Manejo de errores
                'mensaje' => 'Error al eliminar la aptitud.',
                'error'   => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }
}
