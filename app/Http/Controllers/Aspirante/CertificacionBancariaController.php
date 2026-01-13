<?php

namespace App\Http\Controllers\Aspirante;

use App\Http\Requests\RequestAspirante\RequestCertificacionBancaria\ActualizarCertificacionBancariaRequest;
use App\Http\Requests\RequestAspirante\RequestCertificacionBancaria\CrearCertificacionBancariaRequest;
use App\Services\ArchivoService;
use Illuminate\Support\Facades\DB;
use App\Models\Aspirante\CertificacionBancaria;

use Illuminate\Http\Request;

class CertificacionBancariaController
{
    protected $archivoService;

    public function __construct(ArchivoService $archivoService) // Constructor: inyecta el servicio de archivos
    {
        $this->archivoService = $archivoService;
    }

    public function crearCertificacionBancaria(CrearCertificacionBancariaRequest $request)
    {
        try{
            $usuarioId = $request->user()->id; // Obtener el ID del usuario autenticado
            $certificacionBancariaExistente = CertificacionBancaria::where('user_id', $usuarioId)->first();

            if ($certificacionBancariaExistente) { // Si ya existe una Certificacion Bancaria para este usuario, retornar error 409 (conflicto)
                return response()->json([
                    'message' => 'Ya tienes una Certificacion Bancaria registrada. No puedes crear otra.',
                ], 409);
            }
            DB::transaction(function () use ($request) { // Ejecutar la creación dentro de una transacción para asegurar consistencia
                $datos = $request->validated(); // Obtener datos validados del request
                $datos['user_id'] = $request->user()->id; // Asociar la Certificacion Bancaria al usuario autenticado
                $certificacionBancaria = CertificacionBancaria::create($datos);

                if ($request->hasFile('archivo')) {
                    $this->archivoService->guardarArchivoDocumento(
                        $request->file('archivo'),
                        $certificacionBancaria,
                        'Certificacion_bancaria');
                    }
            });

            return response()->json([
                'message' => 'Certificacion Bancaria creada exitosamente.',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la Certificacion Bancaria.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function ObtenerCertificacionBancaria(Request $request)
    {
        try {
            $user = $request->user();
            $certificacionBancaria = CertificacionBancaria::where('user_id', $user->id)
                ->with(['documentosCertificacionBancaria:id_documento,documentable_id,archivo,estado'])
                ->first();

            if (!$certificacionBancaria) {
                return response()->json([
                    'message' => 'No se encontró ninguna Certificación Bancaria para el usuario.',
                    'eps' => null
                ], 200);
            }

            foreach ($certificacionBancaria->documentosCertificacionBancaria as $documento) {
                if (!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo);
                }
            }
            return response()->json(['certificacion_bancaria' => $certificacionBancaria,], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la Certificación Bancaria.',
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    

    public function actualizarCertificacionBancaria(  ActualizarCertificacionBancariaRequest $request)
    {

        try {
            DB::transaction(function () use ($request) { // Ejecutar la actualización
                $user = $request->user();
                $certificacionBancaria = CertificacionBancaria::where('user_id', $user->id)->firstOrFail();
                $datos = $request->validated();
                $certificacionBancaria->update($datos);

                if ($request->hasFile('archivo')) {
                    $this->archivoService->actualizarArchivoDocumento(
                        $request->file('archivo'),
                        $certificacionBancaria,
                        'Certificacion_bancaria');
                }
        });
            return response()->json([
                'message' => 'Certificación Bancaria actualizada exitosamente.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la Certificación Bancaria.',
                'error' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }




}
