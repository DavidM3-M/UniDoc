<?php

namespace App\Http\Controllers\Convocatoria;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuario\User;
use Illuminate\Support\Facades\DB;


class VicerrectoriaController extends Controller
{
    // Obtener postulaciones/convocatorias de un usuario específico
    public function postulacionesPorUsuario($userId)
    {
        try {
            $user = User::with(['postulacionesUsuario.convocatoriaPostulacion'])
                ->findOrFail($userId);


            $postulaciones = $user->postulacionesUsuario->map(function ($postulacion) {
                return [
                    'postulacion_id' => $postulacion->id_postulacion,
                    'estado_postulacion' => $postulacion->estado_postulacion,
                    'convocatoria' => $postulacion->convocatoriaPostulacion,
                ];
            })->filter()->values();

            return response()->json(['data' => $postulaciones], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener postulaciones del usuario.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    // Listar usuarios aprobados por Talento Humano
    public function index()
    {
        try {
            $usuarios = User::where('aval_talento_humano', true)
                ->where('aval_coordinador', true)
                ->with(['postulacionesUsuario.convocatoriaPostulacion'])
                ->get()
                ->map(function ($usuario) {
                    $convocatorias = $usuario->postulacionesUsuario
                        ->map(function ($postulacion) {
                            $convocatoria = $postulacion->convocatoriaPostulacion;
                            if (! $convocatoria) {
                                return null;
                            }
                            return [
                                'id' => $convocatoria->id_convocatoria,
                                'nombre' => $convocatoria->nombre_convocatoria,
                            ];
                        })
                        ->filter()
                        ->values();

                    // Convertir el usuario a array y agregar convocatorias
                    $userArr = $usuario->toArray();
                    $userArr['convocatorias'] = $convocatorias;
                    // Opcional: eliminar relaciones innecesarias
                    unset($userArr['postulaciones_usuario']);
                    return $userArr;
                });

            return response()->json(['data' => $usuarios], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener usuarios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Ver usuario (solo si está aprobado por Talento Humano)
    public function show($userId)
    {
        try {
            $user = User::findOrFail($userId);

            if (! $user->aval_talento_humano || ! $user->aval_coordinador) {
                return response()->json(['message' => 'Usuario no aprobado por Talento Humano o Coordinador.'], 403);
            }

            return response()->json(['data' => $user], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el usuario.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Registrar aval de Vicerrectoría
    public function registrarAval(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);

            if (! $user->aval_talento_humano || ! $user->aval_coordinador) {
                return response()->json(['message' => 'Usuario no aprobado por Talento Humano o Coordinador.'], 403);
            }

            DB::transaction(function () use ($request, $user) {
                if ($user->aval_vicerrectoria) {
                    throw new \Exception('El aval de Vicerrectoría ya fue registrado.');
                }

                $user->update([
                    'aval_vicerrectoria' => true,
                    'aval_vicerrectoria_by' => $request->user()->id,
                    'aval_vicerrectoria_at' => now(),
                ]);
            });

            return response()->json(['message' => 'Aval de Vicerrectoría registrado correctamente.'], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar aval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Revocar aval de Vicerrectoría
    public function revocarAval($userId)
    {
        try {
            $user = User::findOrFail($userId);

            if (! $user->aval_vicerrectoria) {
                return response()->json(['message' => 'El usuario no tiene aval de Vicerrectoría.'], 400);
            }

            $user->update([
                'aval_vicerrectoria' => false,
                'aval_vicerrectoria_by' => null,
                'aval_vicerrectoria_at' => null,
            ]);

            return response()->json(['message' => 'Aval de Vicerrectoría revocado correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al revocar aval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
