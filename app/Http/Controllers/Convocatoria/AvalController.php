<?php

namespace App\Http\Controllers\Convocatoria;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TalentoHumano\NotificacionController;
use Illuminate\Http\Request;
use App\Models\Usuario\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class AvalController extends Controller
{
    /**
     * Registrar aval de hoja de vida según el rol del usuario autenticado.
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarUsuarios(Request $request)
    {
        try {
            $role = $request->user()?->getRoleNames()->first();

            if ($role === 'Vicerrectoria') {
                $usuarios = User::role('Aspirante')
                    ->where('aval_talento_humano', true)
                    ->get();
            } elseif ($role === 'Coordinador') {
                $usuarios = User::role('Aspirante')
                    ->where('aval_talento_humano', true)
                    ->get();
            } elseif ($role === 'Rectoria') {
                $usuarios = User::role('Aspirante')
                    ->where('aval_vicerrectoria', true)
                    ->get();
            } else {
                $usuarios = User::all();
            }

            return response()->json([
                'data' => $usuarios
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener usuarios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function avalHojaVida(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            $role = $request->user()->getRoleNames()->first();

            DB::transaction(function () use ($request, $user, $role) {
                switch ($role) {
                    case 'Rectoria':
                        if (! $user->aval_vicerrectoria) {
                            throw new \Exception('Usuario no aprobado por Vicerrectoría.', 403);
                        }

                        if ($user->aval_rectoria) {
                            throw new \Exception('El aval de Rectoría ya fue registrado.', 409);
                        }
                        $user->update([
                            'aval_rectoria' => true,
                            'aval_rectoria_by' => $request->user()->id,
                            'aval_rectoria_at' => now(),
                        ]);
                        break;

                    case 'Coordinador':
                        if (! $user->aval_talento_humano) {
                            throw new \Exception('Usuario no aprobado por Talento Humano.', 403);
                        }

                        if ($user->aval_coordinador) {
                            throw new \Exception('El aval de Coordinación ya fue registrado.', 409);
                        }

                        $user->update([
                            'aval_coordinador' => true,
                            'aval_coordinador_by' => $request->user()->id,
                            'aval_coordinador_at' => now(),
                        ]);
                        break;

                    case 'Vicerrectoria':
                        if (! $user->aval_talento_humano || ! $user->aval_coordinador) {
                            throw new \Exception('Usuario no aprobado por Talento Humano o Coordinador.', 403);
                        }

                        if ($user->aval_vicerrectoria) {
                            throw new \Exception('El aval de Vicerrectoría ya fue registrado.', 409);
                        }
                        $user->update([
                            'aval_vicerrectoria' => true,
                            'aval_vicerrectoria_by' => $request->user()->id,
                            'aval_vicerrectoria_at' => now(),
                        ]);
                        break;

                    case 'Talento Humano':
                        if ($user->aval_talento_humano) {
                            throw new \Exception('El aval de Talento Humano ya fue registrado.', 409);
                        }
                        $user->update([
                            'aval_talento_humano' => true,
                            'aval_talento_humano_by' => $request->user()->id,
                            'aval_talento_humano_at' => now(),
                        ]);
                        break;

                    default:
                        throw new \Exception('Rol no autorizado', 403);
                }
            });

            // Notificaciones por correo según el rol que acaba de otorgar el aval
            try {
                $user->refresh();
                switch ($role) {
                    case 'Talento Humano':
                        // Notificar a los Coordinadores
                        $coordinadores = User::role('Coordinador')->get();
                        if ($coordinadores->isNotEmpty()) {
                            NotificacionController::listoParaCoordinador($coordinadores, $user);
                        }
                        break;

                    case 'Coordinador':
                        // Notificar a Vicerrectoría
                        $vicerrectores = User::role('Vicerrectoria')->get();
                        if ($vicerrectores->isNotEmpty()) {
                            NotificacionController::listoParaVicerrectoria($vicerrectores, $user);
                        }
                        break;

                    case 'Vicerrectoria':
                        // Notificar a Rectoría
                        $rectores = User::role('Rectoria')->get();
                        if ($rectores->isNotEmpty()) {
                            NotificacionController::listoParaRectoria($rectores, $user);
                        }
                        break;

                    case 'Rectoria':
                        // Notificar al aspirante que el proceso está completo
                        NotificacionController::avalFinalCompletado($user);
                        break;
                }
            } catch (\Exception $notifEx) {
                Log::error("Error al enviar notificación de aval [{$role}] para usuario {$user->id}: " . $notifEx->getMessage());
            }

            return response()->json([
                'message' => "Aval registrado exitosamente por {$role}",
            ], 201);

        } catch (\Exception $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status > 499) {
                $status = 500;
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'Error al registrar aval.',
                'error' => $e->getMessage(),
            ], $status);
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
                    'aval_coordinador' => $user->aval_coordinador,
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