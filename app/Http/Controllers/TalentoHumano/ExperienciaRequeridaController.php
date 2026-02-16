<?php

namespace App\Http\Controllers\TalentoHumano;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestTalentoHumano\RequestExperiencia\StoreExperienciaRequeridaRequest;
use App\Models\ExperienciaRequerida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExperienciaRequeridaController extends Controller
{
    public function index()
    {
        $items = ExperienciaRequerida::orderBy('horas_minimas', 'asc')->get();
        return response()->json(['experiencias_requeridas' => $items], 200);
    }

    public function store(StoreExperienciaRequeridaRequest $request)
    {
        $data = $request->validated();
        $item = ExperienciaRequerida::create($data);
        return response()->json(['mensaje' => 'Experiencia requerida creada', 'experiencia' => $item], 201);
    }

    public function show($id)
    {
        $item = ExperienciaRequerida::find($id);
        if (!$item) {
            return response()->json(['mensaje' => 'No encontrada'], 404);
        }
        return response()->json(['experiencia' => $item], 200);
    }

    public function update(StoreExperienciaRequeridaRequest $request, $id)
    {
        $item = ExperienciaRequerida::find($id);
        if (!$item) {
            return response()->json(['mensaje' => 'No encontrada'], 404);
        }
        $item->update($request->validated());
        return response()->json(['mensaje' => 'Actualizada', 'experiencia' => $item], 200);
    }

    public function destroy($id)
    {
        $item = ExperienciaRequerida::find($id);
        if (!$item) {
            return response()->json(['mensaje' => 'No encontrada'], 404);
        }
        $item->delete();
        return response()->json(['mensaje' => 'Eliminada'], 200);
    }
}
