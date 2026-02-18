<?php

namespace App\Http\Controllers\Aspirante;

use App\Http\Requests\RequestAspirante\RequestArl\ActualizarArlRequest;
use App\Http\Requests\RequestAspirante\RequestArl\CrearArlRequest;
use App\Models\Aspirante\Arl;
use App\Services\ArchivoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArlController
{
    protected $archivoService;

    public function __construct(ArchivoService $archivoService) // Constructor: inyecta el servicio de archivos
    {
        $this->archivoService = $archivoService;
    }

    public function crearArl(CrearArlRequest $request)
    {
        try{
            $usuarioId = $request->user()->id;
            $arlExistente = Arl::where('user_id', $usuarioId)->first();
            if ($arlExistente) {
                return response()->json([
                    'message' => 'Ya tienes una ARL registrada. No puedes crear otra.',
                ], 409);
            }
            DB::transaction(function () use ($request) {
                $datos = $request->validated();
                $datos['user_id'] = $request->user()->id;
                $arl = Arl::create($datos);

                if ($request->hasFile('archivo')) {
                    $this->archivoService->guardarArchivoDocumento(
                        $request->file('archivo'),
                        $arl,
                        'Arl');
                    }
            });

            return response()->json([
                'message' => 'ARL creada exitosamente.',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la ARL.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function ObtenerArl(Request $request)
    {
        try {
            $user = $request->user();
            $arl = Arl::where('user_id', $user->id)
                ->with(['documentosArl:id_documento,documentable_id,archivo,estado'])
                ->first();

            if(!$arl) {
                return response()->json([
                    'message' => 'No se encontró ninguna ARL para el usuario.',
                    'arl' => null
                ], 200);
            }

            foreach ($arl->documentosArl as $documento) {
                if (!empty($documento->archivo)) {
                    $documento->url_archivo = asset('storage/' . $documento->archivo);
                }
            }
            return response()->json(['arl' => $arl,], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la ARL.',
                'error' => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);

        }

    }


    public function actualizarArl(ActualizarArlRequest $request)
    {
        try{
            DB::transaction(function () use ($request) { // Ejecutar la actualización
                $user = $request->user();
                $arl = Arl::where('user_id', $user->id)->firstOrFail();
                $datos = $request->validated();
                $arl->update($datos);

                if ($request->hasFile('archivo')) {
                    $this->archivoService->actualizarArchivoDocumento(
                        $request->file('archivo'),
                        $arl,
                        'Arl');
                    }
            });

            return response()->json([
                'message' => 'ARL actualizada exitosamente.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la ARL.',
                'error' => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }

}
