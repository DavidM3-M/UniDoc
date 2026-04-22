<?php

namespace App\Http\Controllers\TalentoHumano;

use App\Http\Requests\RequestTalentoHumano\RequestAscensos\CrearAscensosRequest;
use App\Http\Requests\RequestTalentoHumano\RequestAscensos\ActualizarAscensosRequest;
use App\Models\TalentoHumano\Ascenso;
use App\Models\Usuario\User;
use App\Models\TalentoHumano\Convocatoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AscensoController
{
    /**
     * Crear un ascenso para un usuario.
     * 
     * @param CrearAscensosRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function crearAscenso(CrearAscensosRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $datosAscenso = $request->validated();
                
                // Verificar que el usuario exista
                $usuario = User::findOrFail($datosAscenso['user_id']);
                
                // Verificar que la convocatoria exista
                Convocatoria::findOrFail($datosAscenso['id_convocatoria']);
                
                // Verificar que no tenga un ascenso activo en la misma convocatoria
                $ascensoExistente = Ascenso::where('user_id', $datosAscenso['user_id'])
                    ->where('id_convocatoria', $datosAscenso['id_convocatoria'])
                    ->exists();
                
                if ($ascensoExistente) {
                    throw new \Exception('El usuario ya tiene un ascenso registrado en esta convocatoria.', 409);
                }
                
                // Crear el ascenso
                $ascenso = Ascenso::create($datosAscenso);
                
                return response()->json([
                    'success' => true,
                    'message' => $usuario->name . ' ha sido ascendido satisfactoriamente.',
                    'data' => $ascenso->load(['usuarioAscenso', 'convocatoria'])
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Error al registrar ascenso: ' . $e->getMessage());
            $codigo = (int) $e->getCode() ?: 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Error al registrar el ascenso'
            ], $codigo);
        }
    }

    /**
     * Obtener todos los ascensos.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerAscensos()
    {
        try {
            $ascensos = Ascenso::with(['usuarioAscenso', 'convocatoria'])
                ->orderBy('fecha_ascenso', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Ascensos obtenidos correctamente.',
                'data' => $ascensos
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener ascensos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los ascensos'
            ], 500);
        }
    }

    /**
     * Obtener un ascenso por ID.
     * 
     * @param int $id_ascenso
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerAscenso($id_ascenso)
    {
        try {
            $ascenso = Ascenso::findOrFail($id_ascenso)
                ->load(['usuarioAscenso', 'convocatoria']);
            
            return response()->json([
                'success' => true,
                'message' => 'Ascenso obtenido correctamente.',
                'data' => $ascenso
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener ascenso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ascenso no encontrado'
            ], 404);
        }
    }

    /**
     * Actualizar un ascenso.
     * 
     * @param ActualizarAscensosRequest $request
     * @param int $id_ascenso
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizarAscenso(ActualizarAscensosRequest $request, $id_ascenso)
    {
        try {
            return DB::transaction(function () use ($request, $id_ascenso) {
                $ascenso = Ascenso::findOrFail($id_ascenso);
                $datosAscenso = $request->validated();
                
                // Si se intenta cambiar la convocatoria, verificar que no exista otro ascenso igual
                if (isset($datosAscenso['id_convocatoria']) && $datosAscenso['id_convocatoria'] != $ascenso->id_convocatoria) {
                    $ascensoExistente = Ascenso::where('user_id', $ascenso->user_id)
                        ->where('id_convocatoria', $datosAscenso['id_convocatoria'])
                        ->where('id_ascenso', '!=', $id_ascenso)
                        ->exists();
                    
                    if ($ascensoExistente) {
                        throw new \Exception('Ya existe un ascenso para este usuario en esa convocatoria.', 409);
                    }
                }
                
                $ascenso->update($datosAscenso);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Ascenso actualizado correctamente.',
                    'data' => $ascenso->load(['usuarioAscenso', 'convocatoria'])
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('Error al actualizar ascenso: ' . $e->getMessage());
            $codigo = (int) $e->getCode() ?: 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Error al actualizar el ascenso'
            ], $codigo);
        }
    }

    /**
     * Eliminar un ascenso.
     * 
     * @param int $id_ascenso
     * @return \Illuminate\Http\JsonResponse
     */
    public function eliminarAscenso($id_ascenso)
    {
        try {
            return DB::transaction(function () use ($id_ascenso) {
                $ascenso = Ascenso::findOrFail($id_ascenso);
                $usuario = $ascenso->usuarioAscenso;
                
                $ascenso->delete();
                
                return response()->json([
                    'success' => true,
                    'message' => "El ascenso de {$usuario->name} ha sido eliminado correctamente."
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('Error al eliminar ascenso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ascenso no encontrado'
            ], 404);
        }
    }

    /**
     * Obtener ascensos de un usuario específico.
     * 
     * @param int $user_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerAscensosUsuario($user_id)
    {
        try {
            $usuario = User::findOrFail($user_id);
            
            $ascensos = Ascenso::where('user_id', $user_id)
                ->with(['convocatoria'])
                ->orderBy('fecha_ascenso', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => "Ascensos de {$usuario->name} obtenidos correctamente.",
                'data' => $ascensos
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener ascensos del usuario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }
    }
}