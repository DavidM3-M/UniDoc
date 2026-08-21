<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RequestAdmin\RequestNivelFormacionAcademica\ActualizarNivelFormacionAcademicaRequest;
use App\Http\Requests\RequestAdmin\RequestNivelFormacionAcademica\CrearNivelFormacionAcademicaRequest;
use App\Constants\ClavePrimaria;
use App\Models\NivelFormacionAcademica;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Administración del catálogo de niveles de formación académica.
 *
 * `nivel_academico` y `nivel_formacion` son texto libre a propósito (decisión del Administrador):
 * no hay lista fija SNIES ni validación cruzada entre ambos campos.
 *
 * Catálogo independiente por ahora: nada lo referencia todavía, así que `eliminar` no necesita el
 * chequeo de "en uso" que sí tiene TipoExperienciaController. Cuando exista Fase 2 ("Formación
 * educativa", catálogo de programas con FK hacia aquí), ese chequeo se agrega ahí.
 */
class NivelFormacionAcademicaController
{
    private function buscar($id): ?NivelFormacionAcademica
    {
        if (ClavePrimaria::fueraDeRango($id)) {
            return null;
        }

        return NivelFormacionAcademica::find($id);
    }

    public function listar()
    {
        try {
            $niveles = NivelFormacionAcademica::query()
                ->orderBy('nivel_academico')
                ->orderBy('nivel_formacion')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $niveles,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar los niveles de formación académica: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar los niveles de formación académica.',
            ], 500);
        }
    }

    public function obtenerPorId($id)
    {
        try {
            $nivel = $this->buscar($id);

            if (!$nivel) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Nivel de formación académica no encontrado.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $nivel,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener el nivel de formación académica: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener el nivel de formación académica.',
            ], 500);
        }
    }

    public function crear(CrearNivelFormacionAcademicaRequest $request)
    {
        try {
            $nivel = NivelFormacionAcademica::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Nivel de formación académica creado correctamente.',
                'data' => $nivel,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear el nivel de formación académica: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el nivel de formación académica.',
            ], 500);
        }
    }

    public function actualizar(ActualizarNivelFormacionAcademicaRequest $request, $id)
    {
        try {
            $nivel = $this->buscar($id);

            if (!$nivel) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Nivel de formación académica no encontrado.',
                ], 404);
            }

            $datos = $request->validated();

            // El nombre de este catálogo viaja COPIADO como texto a `escalones_docente.formacion_minima`,
            // `reglas_excepcion_escalon.valor_condicion` y `estudios.tipo_estudio`, y el motor del
            // escalafón los compara literalmente. Renombrar aquí sin propagar dejaba esas referencias
            // apuntando a un texto inexistente: la regla no fallaba, simplemente no la cumplía nadie.
            $nombreAnterior = $nivel->nivel_formacion;
            $nombreNuevo = $datos['nivel_formacion'] ?? $nombreAnterior;
            $renombrado = $nombreNuevo !== $nombreAnterior;

            DB::transaction(function () use ($nivel, $datos, $renombrado, $nombreAnterior, $nombreNuevo) {
                $nivel->update($datos);

                if (!$renombrado) {
                    return;
                }

                DB::table('escalones_docente')
                    ->where('formacion_minima', $nombreAnterior)
                    ->update(['formacion_minima' => $nombreNuevo]);

                DB::table('reglas_excepcion_escalon')
                    ->where('valor_condicion', $nombreAnterior)
                    ->update(['valor_condicion' => $nombreNuevo]);

                DB::table('estudios')
                    ->where('tipo_estudio', $nombreAnterior)
                    ->update(['tipo_estudio' => $nombreNuevo]);
            });

            return response()->json([
                'status' => 'success',
                'message' => $renombrado
                    ? 'Nivel actualizado. El nuevo nombre se propagó a los escalones, reglas de excepción y estudios que lo usaban.'
                    : 'Nivel de formación académica actualizado correctamente.',
                'data' => $nivel,
                'nombre_propagado' => $renombrado,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar el nivel de formación académica: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar el nivel de formación académica.',
            ], 500);
        }
    }

    public function eliminar($id)
    {
        try {
            $nivel = $this->buscar($id);

            if (!$nivel) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Nivel de formación académica no encontrado.',
                ], 404);
            }

            // Este catálogo se referencia por NOMBRE desde tres sitios, así que borrarlo no solo
            // rompe la FK de programas (RESTRICT, reventaría con un 500): deja además escalones y
            // reglas apuntando a un texto inexistente, y el motor del escalafón los compara
            // literalmente (MotorEscalafonDocenteService::tieneFormacionAprobada).
            $programas = DB::table('programas_formacion_educativa')
                ->where('nivel_formacion_academica_id', $nivel->id_nivel_formacion_academica)
                ->count();

            $estudios = DB::table('estudios')
                ->where('nivel_formacion_academica_id', $nivel->id_nivel_formacion_academica)
                ->orWhere('tipo_estudio', $nivel->nivel_formacion)
                ->count();

            $escalones = DB::table('escalones_docente')
                ->where('formacion_minima', $nivel->nivel_formacion)
                ->count();

            $reglas = DB::table('reglas_excepcion_escalon')
                ->where('valor_condicion', $nivel->nivel_formacion)
                ->count();

            if ($programas > 0 || $estudios > 0 || $escalones > 0 || $reglas > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "No se puede eliminar: {$programas} programa(s), {$estudios} estudio(s), {$escalones} escalón(es) y {$reglas} regla(s) de excepción dependen de este nivel. Puede marcarlo como inactivo para retirarlo de los formularios.",
                    'programas_asociados' => $programas,
                    'estudios_asociados' => $estudios,
                    'escalones_asociados' => $escalones,
                    'reglas_asociadas' => $reglas,
                ], 409);
            }

            $nivel->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Nivel de formación académica eliminado correctamente.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar el nivel de formación académica: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar el nivel de formación académica.',
            ], 500);
        }
    }
}
