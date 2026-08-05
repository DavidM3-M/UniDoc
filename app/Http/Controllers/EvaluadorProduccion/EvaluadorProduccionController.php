<?php

namespace App\Http\Controllers\EvaluadorProduccion;

use App\Constants\ConstDocumentos\EstadoDocumentos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Usuario\User;
use App\Models\Aspirante\Documento;
use Illuminate\Validation\Rule;

// Aquí se define la clase EvaluadorProduccionController
// Esta clase se encarga de manejar las solicitudes relacionadas con la producción académica de los usuarios

class EvaluadorProduccionController
{
    /**
     * Obtener las producciones académicas pendientes de todos los usuarios.
     *
     * Este método consulta a todos los usuarios que tengan al menos una producción académica con documentos
     * en estado "pendiente". Carga las producciones académicas y sus documentos asociados, filtrando únicamente
     * aquellos que están pendientes. Además, para cada documento pendiente, se genera una URL pública del archivo
     * utilizando el sistema de almacenamiento.
     * En caso de error durante la consulta, se captura la excepción y se retorna una respuesta con el mensaje correspondiente.
     *
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las producciones académicas pendientes o mensaje de error.
     */
    public function obtenerProducciones()
    {
        try {
            $usuarios = User::with(['produccionAcademicaUsuario.documentosProduccionAcademica' => function ($query) { // Aquí se filtran los documentos por estado
                $query->where('estado', 'pendiente'); // se obtienen solo los documentos pendientes
            }])
                ->whereHas('produccionAcademicaUsuario.documentosProduccionAcademica', function ($query) { // Aquí se filtran los documentos por estado
                    $query->where('estado', 'pendiente'); // se obtienen solo los documentos pendientes
                })
                ->get();

            foreach ($usuarios as $usuario) { // Añadir URL del archivo a cada documento
                foreach ($usuario->produccionAcademicaUsuario as $produccion) {
                    foreach ($produccion->documentosProduccionAcademica as $documento) {
                        $documento->archivo_url = Storage::url($documento->archivo);
                    }
                }
            }
            return response()->json([ // Aquí se devuelve la respuesta
                'data' => $usuarios,
            ], 200);
        } catch (\Exception $e) { // Aquí se maneja la excepción
            return response()->json([
                'message' => 'Error al obtener las producciones académicas pendientes.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver las producciones académicas pendientes de un usuario específico.
     *
     * Este método obtiene las producciones académicas asociadas a un usuario identificado por su ID,
     * cargando únicamente los documentos cuyo estado sea "pendiente". Para cada documento, se genera una URL pública
     * del archivo utilizando el sistema de almacenamiento. Si el usuario no existe o ocurre un error durante el proceso,
     * se captura una excepción y se retorna una respuesta con el mensaje de error correspondiente.
     *
     * @param int $user_id ID del usuario cuyas producciones académicas pendientes se desean consultar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con las producciones pendientes del usuario o mensaje de error.
     */
    public function verProduccionesPorUsuario($user_id)
    {
        try {
            $user = User::with(['produccionAcademicaUsuario.documentosProduccionAcademica' => function ($query) { // Aquí se filtran los documentos por estado
                $query->where('estado', 'pendiente'); // se obtienen solo los documentos pendientes
            }])->findOrFail($user_id); // Aquí se busca el usuario por ID

            foreach ($user->produccionAcademicaUsuario as $produccion) { // Añadir URL del archivo a cada documento
                foreach ($produccion->documentosProduccionAcademica as $documento) {
                    $documento->archivo_url = Storage::url($documento->archivo);
                }
            }

            return response()->json([
                'data' => $user,
            ], 200);
        } catch (\Exception $e) { // Aquí se maneja la excepción
            return response()->json([
                'message' => 'Error al obtener las producciones académicas del usuario.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar el estado de un documento asociado a una producción académica.
     *
     * Este método permite cambiar el estado de un documento específico, validando previamente que el nuevo
     * estado esté dentro de los valores permitidos definidos en la enumeración `EstadoDocumentos`.
     * Además, se verifica que el documento esté asociado a una entidad del tipo `ProduccionAcademica`
     * para evitar actualizaciones en documentos no relacionados. Si la validación es exitosa, se actualiza
     * el estado y se guarda el cambio. En caso de error o conflicto, se captura la excepción y se devuelve
     * una respuesta con el mensaje correspondiente.
     *
     * @param Request $request Solicitud HTTP que contiene el nuevo estado a aplicar.
     * @param int $documento_id ID del documento cuyo estado se desea actualizar.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON indicando el resultado de la operación.
     */
    public function actualizarEstadoDocumento(Request $request, $documento_id)
    {
        try {

            $request->validate([ // Validar estado
                'estado' => ['required', Rule::in(EstadoDocumentos::all())],
            ]);

            $documento = Documento::findOrFail($documento_id); // Buscar el documento

            if (!str_contains($documento->documentable_type, 'ProduccionAcademica')) { // Asegurarse de que pertenece a una Producción Académica
                return response()->json([
                    'message' => 'Este documento no pertenece a una producción académica.'
                ], 403);
            }

            $documento->estado = $request->estado; // Actualizar estado
            $documento->save(); // Guardar cambios

            return response()->json([ // Aquí se devuelve la respuesta
                'message' => "El documento actualizado de estado exitosamente.",
            ]);
        } catch (\Exception $e) { // Aquí se maneja la excepción
            return response()->json([
                'message' => 'Error al actualizar el estado del documento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
