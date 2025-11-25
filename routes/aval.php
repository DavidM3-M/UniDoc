<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AvalController extends Controller
{
    /**
     * Registrar aval de hoja de vida según el rol del usuario autenticado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function avalHojaVida(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $role = $request->user()->getRoleNames()->first();

        switch ($role) {
            case 'Rectoría':
                $user->update([
                    'aval_rectoria' => true,
                    'aval_rectoria_by' => $request->user()->id,
                    'aval_rectoria_at' => now(),
                ]);
                break;

            case 'Vicerrectoría':
                $user->update([
                    'aval_vicerrectoria' => true,
                    'aval_vicerrectoria_by' => $request->user()->id,
                    'aval_vicerrectoria_at' => now(),
                ]);
                break;

            case 'Talento Humano':
                $user->update([
                    'aval_talento_humano' => true,
                    'aval_talento_humano_by' => $request->user()->id,
                    'aval_talento_humano_at' => now(),
                ]);
                break;

            default:
                return response()->json(['error' => 'Rol no autorizado'], 403);
        }

        return response()->json(['message' => "Aval registrado por {$role}"]);
    }
}