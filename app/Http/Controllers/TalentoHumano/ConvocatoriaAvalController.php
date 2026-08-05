<?php

namespace App\Http\Controllers\TalentoHumano;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\TalentoHumano\ConvocatoriaAval;
use App\Models\Usuario\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ConvocatoriaAvalController extends Controller
{
    // List approvals for a given convocatoria and user (or all for admins)
    public function index(Request $request)
    {
        $convocatoria_id = $request->query('convocatoria_id');
        $user_id = $request->query('user_id');

        $query = ConvocatoriaAval::query();
        if ($convocatoria_id) $query->where('convocatoria_id', $convocatoria_id);
        if ($user_id) $query->where('user_id', $user_id);

        $result = $query->get();
        return response()->json(['avales' => $result], 200);
    }

    // Create or upsert an aval record (usually called by staff to register an approval)
    public function store(Request $request)
    {
        $data = $request->validate([
            'convocatoria_id' => 'required|integer',
            'user_id' => 'required|integer',
            'aval' => 'required|string',
        ]);

        $aval = ConvocatoriaAval::updateOrCreate(
            [
                'convocatoria_id' => $data['convocatoria_id'],
                'user_id' => $data['user_id'],
                'aval' => $data['aval'],
            ],
            [
                'estado' => 'pending'
            ]
        );

        return response()->json(['aval' => $aval], 201);
    }

    // Approve or reject an aval
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'estado'         => 'required|in:pending,aprobado,rechazado',
            'comentario'     => 'nullable|string',
            'motivo_rechazo' => 'nullable|string|max:1000',
        ]);

        // El motivo es obligatorio cuando se rechaza
        if ($data['estado'] === 'rechazado' && empty($data['comentario']) && empty($data['motivo_rechazo'])) {
            return response()->json(['message' => 'Debe indicar el motivo del rechazo.'], 422);
        }

        $aval = ConvocatoriaAval::findOrFail($id);

        // Verificar permisos: solo usuarios con rol igual al nombre del aval pueden aprobar/rechazar
        $user = Auth::user();
        /** @var \App\Models\Usuario\User|null $user */
        if (! $user || ! (method_exists($user, 'hasRole') && $user->hasRole($aval->aval))) {
            return response()->json(['message' => 'No autorizado para aprobar/rechazar este aval.'], 403);
        }

        $aval->estado      = $data['estado'];
        $aval->comentario  = $data['motivo_rechazo'] ?? $data['comentario'] ?? null;
        $aval->aprobador_id = Auth::id();
        if ($data['estado'] === 'aprobado') {
            $aval->fecha_aprobacion = now();
        }
        $aval->save();

        // Enviar notificación al postulante si el aval fue rechazado
        if ($data['estado'] === 'rechazado') {
            try {
                $aspirante = User::find($aval->user_id);
                if ($aspirante) {
                    NotificacionController::avalRechazado(
                        $aspirante,
                        $aval->comentario,
                        $aval->aval
                    );
                }
            } catch (\Exception $e) {
                Log::error("Error al notificar rechazo de aval [{$aval->aval}] al usuario {$aval->user_id}: " . $e->getMessage());
            }
        }

        return response()->json(['aval' => $aval], 200);
    }
}
