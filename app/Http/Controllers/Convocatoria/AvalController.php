<?php

namespace App\Http\Controllers\Convocatoria;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TalentoHumano\NotificacionController;
use App\Services\PuntajeAspiranteService;
use Illuminate\Http\Request;
use App\Models\Usuario\User;
use App\Models\TalentoHumano\ConvocatoriaAval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class AvalController extends Controller
{
    public function __construct(private readonly PuntajeAspiranteService $puntajeService) {}

    /** Mapea rol → nombre de aval almacenado en convocatoria_avales */
    private const ROLE_AVAL = [
        'Talento Humano' => 'talento_humano',
        'Coordinador'    => 'coordinador',
        'Vicerrectoria'  => 'vicerrectoria',
        'Rectoria'       => 'rectoria',
    ];

    /** Devuelve true si el aspirante tiene el aval aprobado para la convocatoria dada. */
    private function tieneAval(int $userId, int $convocatoriaId, string $avalNombre): bool
    {
        return ConvocatoriaAval::where('convocatoria_id', $convocatoriaId)
            ->where('user_id', $userId)
            ->where('aval', $avalNombre)
            ->where('estado', 'aprobado')
            ->exists();
    }

    public function listarUsuarios(Request $request)
    {
        try {
            $role       = $request->user()?->getRoleNames()->first();
            $convId     = $request->query('convocatoria_id');

            if ($convId) {
                // Filtrar por avales registrados en convocatoria_avales para esta convocatoria
                $prerequisito = match ($role) {
                    'Vicerrectoria' => 'coordinador',
                    'Coordinador'   => 'talento_humano',
                    'Rectoria'      => 'vicerrectoria',
                    default         => null,
                };

                if ($prerequisito) {
                    $userIds = ConvocatoriaAval::where('convocatoria_id', $convId)
                        ->where('aval', $prerequisito)
                        ->where('estado', 'aprobado')
                        ->pluck('user_id');

                    $usuarios = User::role(['Aspirante', 'Docente'])->whereIn('id', $userIds)->get();
                } else {
                    $usuarios = User::role(['Aspirante', 'Docente'])->get();
                }

                // Anotar aval del rol actual por convocatoria (desde convocatoria_avales, no del flag global)
                $propioAval = match ($role) {
                    'Vicerrectoria' => 'vicerrectoria',
                    'Coordinador'   => 'coordinador',
                    'Rectoria'      => 'rectoria',
                    default         => null,
                };

                if ($propioAval) {
                    $aprobadosSet = ConvocatoriaAval::where('convocatoria_id', $convId)
                        ->where('aval', $propioAval)
                        ->where('estado', 'aprobado')
                        ->pluck('user_id')
                        ->flip()
                        ->toArray();

                    $usuarios = $usuarios->map(function ($u) use ($aprobadosSet, $propioAval) {
                        $arr = $u->toArray();
                        $arr["aval_{$propioAval}"] = isset($aprobadosSet[$u->id]);
                        return $arr;
                    });
                }
            } else {
                // Fallback sin convocatoria: usa flags globales (compatibilidad)
                $usuarios = match ($role) {
                    'Vicerrectoria' => User::role(['Aspirante', 'Docente'])->where('aval_coordinador', true)->get(),
                    'Coordinador'   => User::role(['Aspirante', 'Docente'])->where('aval_talento_humano', true)->get(),
                    'Rectoria'      => User::role(['Aspirante', 'Docente'])->where('aval_vicerrectoria', true)->get(),
                    default         => User::role(['Aspirante', 'Docente'])->get(),
                };

                // Sobreescribir el flag de "propio aval" usando convocatoria_avales (todos los registros aprobados)
                $propioAval = match ($role) {
                    'Vicerrectoria' => 'vicerrectoria',
                    'Coordinador'   => 'coordinador',
                    'Rectoria'      => 'rectoria',
                    default         => null,
                };

                if ($propioAval) {
                    $aprobadosSet = ConvocatoriaAval::where('aval', $propioAval)
                        ->where('estado', 'aprobado')
                        ->pluck('user_id')
                        ->flip()
                        ->toArray();

                    $usuarios = $usuarios->map(function ($u) use ($aprobadosSet, $propioAval) {
                        $arr = $u->toArray();
                        $arr["aval_{$propioAval}"] = isset($aprobadosSet[$u->id]);
                        return $arr;
                    });
                }
            }

            return response()->json(['data' => $this->appendPuntajes($usuarios)]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener usuarios.', 'error' => $e->getMessage()], 500);
        }
    }

    /** Inyecta puntaje_aspirante en cada elemento de la colección / array. */
    private function appendPuntajes($usuarios): array
    {
        $result = [];
        foreach ($usuarios as $u) {
            $arr    = is_array($u) ? $u : $u->toArray();
            $userId = $arr['id'] ?? null;
            if ($userId) {
                $arr['puntaje_aspirante'] = $this->puntajeService->calcular((int) $userId)['total'];
            }
            $result[] = $arr;
        }
        return $result;
    }

    public function avalHojaVida(Request $request, $userId)
    {
        try {
            $request->validate([
                'convocatoria_id' => 'required|integer',
            ]);

            $convocatoriaId = (int) $request->convocatoria_id;
            $user = User::findOrFail($userId);
            $role = $request->user()->getRoleNames()->first();
            $avalNombre = self::ROLE_AVAL[$role] ?? null;

            if (! $avalNombre) {
                return response()->json(['message' => 'Rol no autorizado'], 403);
            }

            DB::transaction(function () use ($request, $user, $role, $convocatoriaId, $avalNombre) {
                // Validar prerequisitos usando convocatoria_avales
                switch ($role) {
                    case 'Rectoria':
                        if (! $this->tieneAval($user->id, $convocatoriaId, 'vicerrectoria')) {
                            throw new \Exception('El aspirante no cuenta con el aval de Vicerrectoría para esta convocatoria.', 403);
                        }
                        break;
                    case 'Coordinador':
                        if (! $this->tieneAval($user->id, $convocatoriaId, 'talento_humano')) {
                            throw new \Exception('El aspirante no cuenta con el aval de Talento Humano para esta convocatoria.', 403);
                        }
                        break;
                    case 'Vicerrectoria':
                        if (! $this->tieneAval($user->id, $convocatoriaId, 'talento_humano')
                            || ! $this->tieneAval($user->id, $convocatoriaId, 'coordinador')) {
                            throw new \Exception('El aspirante no cuenta con el aval de Talento Humano o Coordinación para esta convocatoria.', 403);
                        }
                        break;
                    case 'Talento Humano':
                        // Sin prerequisito
                        break;
                }

                // Verificar que no exista ya
                if ($this->tieneAval($user->id, $convocatoriaId, $avalNombre)) {
                    throw new \Exception("El aval de {$role} ya fue registrado para esta convocatoria.", 409);
                }

                // Registrar en convocatoria_avales
                ConvocatoriaAval::updateOrCreate(
                    [
                        'convocatoria_id' => $convocatoriaId,
                        'user_id'         => $user->id,
                        'aval'            => $avalNombre,
                    ],
                    [
                        'estado'           => 'aprobado',
                        'aprobador_id'     => $request->user()->id,
                        'fecha_aprobacion' => now(),
                    ]
                );

                // También actualizar flags globales para compatibilidad con listarUsuarios fallback
                $flagUpdate = match ($role) {
                    'Talento Humano' => ['aval_talento_humano' => true, 'aval_talento_humano_by' => $request->user()->id, 'aval_talento_humano_at' => now()],
                    'Coordinador'    => ['aval_coordinador' => true, 'aval_coordinador_by' => $request->user()->id, 'aval_coordinador_at' => now()],
                    'Vicerrectoria'  => ['aval_vicerrectoria' => true, 'aval_vicerrectoria_by' => $request->user()->id, 'aval_vicerrectoria_at' => now()],
                    'Rectoria'       => ['aval_rectoria' => true, 'aval_rectoria_by' => $request->user()->id, 'aval_rectoria_at' => now()],
                    default          => [],
                };
                if ($flagUpdate) {
                    $user->update($flagUpdate);
                }
            });

            // Notificaciones por correo
            try {
                $user->refresh();
                switch ($role) {
                    case 'Talento Humano':
                        $coordinadores = User::role('Coordinador')->get();
                        if ($coordinadores->isNotEmpty()) {
                            NotificacionController::listoParaCoordinador($coordinadores, $user);
                        }
                        break;
                    case 'Coordinador':
                        $vicerrectores = User::role('Vicerrectoria')->get();
                        if ($vicerrectores->isNotEmpty()) {
                            NotificacionController::listoParaVicerrectoria($vicerrectores, $user);
                        }
                        break;
                    case 'Vicerrectoria':
                        $rectores = User::role('Rectoria')->get();
                        if ($rectores->isNotEmpty()) {
                            NotificacionController::listoParaRectoria($rectores, $user);
                        }
                        break;
                    case 'Rectoria':
                        NotificacionController::avalFinalCompletado($user);
                        break;
                }
            } catch (\Exception $notifEx) {
                Log::error("Error al enviar notificación de aval [{$role}] para usuario {$user->id}: " . $notifEx->getMessage());
            }

            return response()->json(['message' => "Aval registrado exitosamente por {$role}"], 201);

        } catch (\Exception $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status > 499) {
                $status = 500;
            }
            return response()->json(['message' => $e->getMessage() ?: 'Error al registrar aval.', 'error' => $e->getMessage()], $status);
        }
    }

    /**
     * Ver avales de un usuario.
     * Si se pasa convocatoria_id, retorna los avales específicos de esa convocatoria.
     */
    public function verAvales(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            $convId = $request->query('convocatoria_id');

            if ($convId) {
                $avales = ConvocatoriaAval::where('convocatoria_id', $convId)
                    ->where('user_id', $user->id)
                    ->get()
                    ->keyBy('aval');

                $getEstado = fn(string $aval) => isset($avales[$aval]) && $avales[$aval]->estado === 'aprobado';

                return response()->json([
                    'data' => [
                        'aval_talento_humano' => $getEstado('talento_humano'),
                        'aval_coordinador'    => $getEstado('coordinador'),
                        'aval_vicerrectoria'  => $getEstado('vicerrectoria'),
                        'aval_rectoria'       => $getEstado('rectoria'),
                    ]
                ]);
            }

            // Fallback sin convocatoria: retorna flags globales (compatibilidad)
            return response()->json([
                'data' => [
                    'aval_rectoria'       => $user->aval_rectoria,
                    'aval_vicerrectoria'  => $user->aval_vicerrectoria,
                    'aval_coordinador'    => $user->aval_coordinador,
                    'aval_talento_humano' => $user->aval_talento_humano,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener avales.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Rechazar aval de un aspirante en la cadena, acotado a una convocatoria.
     */
    public function rechazarAval(Request $request, $userId)
    {
        try {
            $request->validate([
                'motivo_rechazo'  => 'required|string|max:1000',
                'convocatoria_id' => 'nullable|integer',
            ]);

            $user           = User::findOrFail($userId);
            $role           = $request->user()->getRoleNames()->first();
            $avalNombre     = self::ROLE_AVAL[$role] ?? null;
            $convocatoriaId = $request->convocatoria_id ? (int) $request->convocatoria_id : null;

            if (! $avalNombre) {
                return response()->json(['message' => 'Rol no autorizado para rechazar avales.'], 403);
            }

            DB::transaction(function () use ($request, $user, $role, $avalNombre, $convocatoriaId) {
                // Validar prerequisitos (usando convocatoria_avales si hay convocatoria_id)
                if ($convocatoriaId) {
                    switch ($role) {
                        case 'Coordinador':
                            if (! $this->tieneAval($user->id, $convocatoriaId, 'talento_humano')) {
                                throw new \Exception('El aspirante no cuenta con el aval de Talento Humano para esta convocatoria.', 403);
                            }
                            break;
                        case 'Vicerrectoria':
                            if (! $this->tieneAval($user->id, $convocatoriaId, 'talento_humano')
                                || ! $this->tieneAval($user->id, $convocatoriaId, 'coordinador')) {
                                throw new \Exception('El aspirante no cuenta con los avales previos para esta convocatoria.', 403);
                            }
                            break;
                        case 'Rectoria':
                            if (! $this->tieneAval($user->id, $convocatoriaId, 'vicerrectoria')) {
                                throw new \Exception('El aspirante no cuenta con el aval de Vicerrectoría para esta convocatoria.', 403);
                            }
                            break;
                    }

                    // Marcar el aval como rechazado en convocatoria_avales
                    ConvocatoriaAval::updateOrCreate(
                        ['convocatoria_id' => $convocatoriaId, 'user_id' => $user->id, 'aval' => $avalNombre],
                        ['estado' => 'rechazado', 'aprobador_id' => $request->user()->id, 'comentario' => $request->motivo_rechazo, 'fecha_aprobacion' => now()]
                    );

                    // Marcar la postulación de ESA convocatoria como rechazada
                    $user->postulacionesUsuario()
                        ->where('convocatoria_id', $convocatoriaId)
                        ->whereIn('estado_postulacion', ['Enviada', 'Faltan documentos', 'Aprobada', 'Aceptada'])
                        ->update([
                            'estado_postulacion' => 'Rechazada',
                            'motivo_rechazo'     => $request->motivo_rechazo,
                            'rechazado_por'      => $role,
                        ]);
                } else {
                    // Sin convocatoria_id: rechaza todas las postulaciones activas (comportamiento original)
                    $user->postulacionesUsuario()
                        ->whereIn('estado_postulacion', ['Enviada', 'Faltan documentos', 'Aprobada', 'Aceptada'])
                        ->update([
                            'estado_postulacion' => 'Rechazada',
                            'motivo_rechazo'     => $request->motivo_rechazo,
                            'rechazado_por'      => $role,
                        ]);
                }
            });

            try {
                $user->refresh();
                NotificacionController::avalRechazado($user, $request->motivo_rechazo, $role);
            } catch (\Exception $notifEx) {
                Log::error("Error al enviar notificación de rechazo de aval [{$role}] para usuario {$user->id}: " . $notifEx->getMessage());
            }

            return response()->json(['message' => "Rechazo registrado exitosamente por {$role}."], 200);

        } catch (\Exception $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status > 499) {
                $status = 500;
            }
            return response()->json(['message' => $e->getMessage() ?: 'Error al registrar el rechazo.', 'error' => $e->getMessage()], $status);
        }
    }
}
