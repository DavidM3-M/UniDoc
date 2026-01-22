<?php

namespace App\Http\Controllers\Convocatoria;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuario\User;
use Illuminate\Support\Facades\DB;

class RectoriaController extends Controller
{
    // Listar usuarios aprobados por Talento Humano
    public function index()
    {
        try {
            $usuarios = User::where('aval_talento_humano', true)->get();

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

            if (! $user->aval_talento_humano) {
                return response()->json(['message' => 'Usuario no aprobado por Talento Humano.'], 403);
            }

            return response()->json(['data' => $user], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el usuario.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Registrar aval de Rectoría
    public function registrarAval(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);

            if (! $user->aval_talento_humano) {
                return response()->json(['message' => 'Usuario no aprobado por Talento Humano.'], 403);
            }

            DB::transaction(function () use ($request, $user) {
                if ($user->aval_rectoria) {
                    throw new \Exception('El aval de Rectoría ya fue registrado.');
                }

                $user->update([
                    'aval_rectoria' => true,
                    'aval_rectoria_by' => $request->user()->id,
                    'aval_rectoria_at' => now(),
                ]);
            });

            return response()->json(['message' => 'Aval de Rectoría registrado correctamente.'], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar aval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Revocar aval de Rectoría
    public function revocarAval($userId)
    {
        try {
            $user = User::findOrFail($userId);

            if (! $user->aval_rectoria) {
                return response()->json(['message' => 'El usuario no tiene aval de Rectoría.'], 400);
            }

            $user->update([
                'aval_rectoria' => false,
                'aval_rectoria_by' => null,
                'aval_rectoria_at' => null,
            ]);

            return response()->json(['message' => 'Aval de Rectoría revocado correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al revocar aval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
