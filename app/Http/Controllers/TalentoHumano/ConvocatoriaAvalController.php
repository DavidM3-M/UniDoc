<?php

namespace App\Http\Controllers\TalentoHumano;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\TalentoHumano\ConvocatoriaAval;
use Illuminate\Support\Facades\Auth;

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
            'estado' => 'required|in:pending,aprobado,rechazado',
            'comentario' => 'nullable|string',
        ]);

        $aval = ConvocatoriaAval::findOrFail($id);
        // Verificar permisos: solo usuarios con rol igual al nombre del aval pueden aprobar/rechazar
        $user = Auth::user();
        /** @var \App\Models\Usuario\User|null $user */
        // proteger por si el objeto no implementa hasRole (análisis estático y runtime)
        if (! $user || ! (method_exists($user, 'hasRole') && $user->hasRole($aval->aval))) {
            return response()->json(['message' => 'No autorizado para aprobar/rechazar este aval.'], 403);
        }

        $aval->estado = $data['estado'];
        $aval->comentario = $data['comentario'] ?? null;
        $aval->aprobador_id = Auth::id();
        if ($data['estado'] === 'aprobado') $aval->fecha_aprobacion = now();
        $aval->save();

        return response()->json(['aval' => $aval], 200);
    }
}
