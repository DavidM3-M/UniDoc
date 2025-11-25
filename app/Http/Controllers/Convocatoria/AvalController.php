<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AvalController extends Controller
{
    /**
     * Registrar aval de hoja de vida según el rol del usuario autenticado.
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function avalHojaVida(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            $role = $request->user()->getRoleNames()->first();

            DB::transaction(function () use ($request, $user, $role) {
                switch ($role) {
                    case 'Rectoría':
                        if ($user->aval_rectoria) {
                            throw new \Exception('El aval de Rectoría ya fue registrado.');
                        }
                        $user->update([
                            'aval_rectoria' => true,
                            'aval_rectoria_by' => $request->user()->id,
                            'aval_rectoria_at' => now(),
                        ]);
                        break;

                    case 'Vicerrectoría':
                        if ($user->aval_vicerrectoria) {
                            throw new \Exception('El aval de Vicerrectoría ya fue registrado.');
                        }
                        $user->update([
                            'aval_vicerrectoria' => true,
                            'aval_vicerrectoria_by' => $request->user()->id,
                            'aval_vicerrectoria_at' => now(),
                        ]);
                        break;

                    case 'Talento Humano':
                        if ($user->aval_talento_humano) {
                            throw new \Exception('El aval de Talento Humano ya fue registrado.');
                        }
                        $user->update([
                            'aval_talento_humano' => true,
                            'aval_talento_humano_by' => $request->user()->id,
                            'aval_talento_humano_at' => now(),
                        ]);
                        break;

                    default:
                        throw new \Exception('Rol no autorizado');
                }
            });

            return response()->json([
                'message' => "Aval registrado exitosamente por {$role}",
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar aval.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver avales de un usuario.
     */
    public function verAvales($userId)
    {
        try {
            $user = User::findOrFail($userId);

            return response()->json([
                'data' => [
                    'aval_rectoria' => $user->aval_rectoria,
                    'aval_vicerrectoria' => $user->aval_vicerrectoria,
                    'aval_talento_humano' => $user->aval_talento_humano,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener avales.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}