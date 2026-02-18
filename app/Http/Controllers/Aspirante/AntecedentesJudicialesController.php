<?php

namespace App\Http\Controllers\Aspirante;

use App\Http\Requests\RequestAspirante\RequestAntecedentesJudiciales\ActualizarAntecedenteJudicialesRequest;
use App\Http\Requests\RequestAspirante\RequestAntecedentesJudiciales\CrearAntecedenteJudicialRequest;
use App\Models\Aspirante\AntecedentesJudiciales;
use App\Services\ArchivoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class AntecedentesJudicialesController
{
    protected $archivoService;

    public function __construct(ArchivoService $archivoService) // Constructor: inyecta el servicio de archivos
    {
        $this->archivoService = $archivoService;
    }

    public function crearAntecedentesJudiciales(CrearAntecedenteJudicialRequest $request)
    {
        try{
            $usuarioId = $request->user()->id; // Obtener el ID del usuario autenticado
            $antecedenteJudicialExistente = AntecedentesJudiciales::where('user_id', $usuarioId)->first();

            if ($antecedenteJudicialExistente) { // Si ya existe un Antecedente Judicial para este usuario, retornar error 409 (conflicto)
                return response()->json([
                    'message' => 'Ya tienes un Antecedente Judicial registrado. No puedes crear otro.',
                ], 409);
            }
            DB::transaction(function () use ($request) { // Ejecutar la creación dentro de una transacción para asegurar consistencia
                $datos = $request->validated(); // Obtener datos validados del request
                $datos['user_id'] = $request->user()->id; // Asociar el Antecedente Judicial al usuario autenticado
                $antecedenteJudicial = AntecedentesJudiciales::create($datos);

                if ($request->hasFile('archivo')) {
                    $this->archivoService->guardarArchivoDocumento(
                        $request->file('archivo'),
                        $antecedenteJudicial,
                        'Antecedentes_judiciales');
                    }
            });

            return response()->json([
                'message' => 'Antecedente Judicial creado exitosamente.',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el Antecedente Judicial.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function ObtenerAntecedentesJudiciales(Request $request)
    {
        try {
            $user = $request->user(); // Obtener el usuario autenticado
            $antecedenteJudicial = AntecedentesJudiciales::where('user_id', $user->id)
                ->with(['documentosAntecedentesJudiciales:id_documento,documentable_id,archivo,estado'])
                ->first();

            if (!$antecedenteJudicial) { // Si no se encuentra un Antecedente Judicial para el usuario, retornar error 404 (no encontrado)
                return response()->json([
                    'message' => 'No se encontró un Antecedente Judicial para este usuario.',
                    'antecedente_judicial' => null,
                ], 200);
            }

            foreach ($antecedenteJudicial->documentosAntecedentesJudiciales as $documento) {
                if(!empty($documento->archivo)) {
                    $documento->archivo_url = asset('storage/' . $documento->archivo); // Generar la URL completa para acceder al archivo
                }
            }
            return response()->json(['antecedente_judicial' => $antecedenteJudicial,], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el Antecedente Judicial.',
                'error' => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);
        }
    }


    public function actualizarAntecedentesJudiciales(ActualizarAntecedenteJudicialesRequest $request)
    {
        try{
            DB::transaction(function () use ($request) { // Ejecutar la actualización
                $user = $request->user(); // Obtener el usuario autenticado
                $antecedenteJudicial = AntecedentesJudiciales::where('user_id', $user->id)->firstOrFail(); // Buscar el Antecedente Judicial asociado al usuario
                $datos = $request->validated(); // Obtener datos validados del request
                $antecedenteJudicial->update($datos); // Actualizar el Antecedente

                if ($request->hasFile('archivo')) { // Si se ha enviado un nuevo archivo, guardarlo y asociarlo al Antecedente Judicial
                    $this->archivoService->guardarArchivoDocumento
                    (
                        $request->file('archivo'),
                        $antecedenteJudicial,
                        'Antecedentes_judiciales');
                    }
        });
            return response()->json([
                'message' => 'Antecedente Judicial actualizado exitosamente.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el Antecedente Judicial.',
                'error' => $e->getMessage(),
            ], is_numeric($e->getCode()) ? (int) $e->getCode() : 500);

        }
    }



























































}
