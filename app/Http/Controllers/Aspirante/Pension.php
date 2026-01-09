<?php

namespace App\Http\Controllers\Aspirante;

use App\Http\Requests\RequestAspirante\RequestPension\CrearPensionRequest;
use App\Http\Requests\RequestAspirante\RequestPension\ActualizarPensionRequest;
use App\Services\ArchivoService;
use Illuminate\Support\Facades\DB;
use App\Models\Aspirante\Pension;
use Illuminate\Http\Request;

class PensionController
{
    protected $archivoService;

    public function __construct(ArchivoService $archivoService) // Constructor: inyecta el servicio de archivos
    {
        $this->archivoService = $archivoService;
    }

    public function crearPension(CrearPensionRequest $request)
    {
        try{
            $usuarioId = $request->user()->id;
            $pensionExistente = Pension::where('user_id', $usuarioId)->first();

            if ($pensionExistente) { // Si ya existe una Pension para este usuario, retornar error 409 (conflicto)
                return response()->json([
                    'message' => 'Ya tienes una Pension registrada. No puedes crear otra.',
                ], 409);
            }

            DB::transaction(function () use ($request) { // Ejecutar la creación dentro de una transacción para asegurar consistencia
                $datos = $request->validated(); // Obtener datos validados del request
                $datos['user_id'] = $request->user()->id; // Asociar la Pension al usuario autenticado
                $pension = Pension::create($datos);

                if ($request->hasFile('archivo')) {
                    $this->archivoService->guardarArchivoDocumento(
                        $request->file('archivo'),
                        $pension,
                        'Pension');
                    }
            });

            return response()->json([
                'message' => 'Pension creada exitosamente.',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la Pension.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function ObtenerPension(Request $request)
    {
        try {
            $user = $request->user();
            $pension = Pension::where('user_id', $user->id)
                ->with(['documentosPension:id_documento,documentable_id,archivo,estado']) // Cargar la relación del archivo asociado
                ->first();

            if (!$pension) {
                return response()->json([
                    'message' => 'No se encontró ninguna Pension para este usuario.',
                    'pension' => null
                ], 200);
            }

            foreach ($pension->documentosPension as $documento) {
                if (!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo);
                }
            }
            return response()->json(['pension' => $pension], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la Pension.',
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    public function actualizarPension( ActualizarPensionRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $user = $request->user();
                $pension = Pension::where('user_id', $user->id)->firstorFail();
                $datos = $request->validated();
                $pension->update($datos);

                if ($request->hasFile('archivo')) {
                    $this->archivoService->actualizarArchivoDocumento(
                        $request->file('archivo'),
                        $pension,
                        'Pension');
                    }
        });
            return response()->json([
                'message' => 'Pension actualizada exitosamente.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la Pension.',
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }






}
