<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RequestAdmin\RequestFormacionEducativa\ActualizarProgramaFormacionEducativaRequest;
use App\Http\Requests\RequestAdmin\RequestFormacionEducativa\CrearProgramaFormacionEducativaRequest;
use App\Constants\ClavePrimaria;
use App\Models\InstitucionSnies;
use App\Models\ProgramaFormacionEducativa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Administración de "Formación educativa" (Fase 2 del catálogo de Formación académica):
 * programas académicos con nombre propio.
 *
 * Se alimenta de dos vías: la importación masiva del SNIES (`SniesImportacionController`) y el
 * alta manual de este controlador — útil para programas que todavía no aparecen en el archivo
 * oficial. El alta manual busca/crea la institución **por nombre** (sin código SNIES), porque no
 * viene de la importación.
 *
 * `listar()` pagina en el servidor a propósito: con la importación masiva esta tabla llega
 * fácilmente a decenas de miles de filas (~32.000 en la primera carga real), y devolverlas todas
 * de una vez tardaba ~22s en serializar un JSON de ~29MB — el navegador hacía timeout. Los demás
 * catálogos de este admin (Niveles, Tipos de experiencia, Producción académica) sí devuelven todo
 * sin paginar porque tienen decenas de filas, no decenas de miles.
 */
class FormacionEducativaController
{
    private function buscar($id): ?ProgramaFormacionEducativa
    {
        if (ClavePrimaria::fueraDeRango($id)) {
            return null;
        }

        return ProgramaFormacionEducativa::with(['institucion', 'nivelFormacionAcademica'])->find($id);
    }

    public function listar(Request $request)
    {
        try {
            $porPagina = min(100, max(1, (int) $request->query('por_pagina', 20)));
            $busqueda = trim((string) $request->query('buscar', ''));

            $query = ProgramaFormacionEducativa::with(['institucion', 'nivelFormacionAcademica']);

            if ($busqueda !== '') {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('nombre_programa', 'ilike', "%{$busqueda}%")
                        ->orWhereHas(
                            'institucion',
                            fn ($iq) => $iq->where('nombre_institucion', 'ilike', "%{$busqueda}%")
                        );
                });
            }

            // Laravel busca el número de página en el query param "page" por defecto; el
            // frontend manda "pagina" (consistente con el resto de nombres en español), así que
            // hay que decírselo explícitamente — si no, paginate() siempre resuelve a la página 1.
            $paginado = $query->orderBy('nombre_programa')->paginate($porPagina, ['*'], 'pagina');

            return response()->json([
                'status' => 'success',
                'data' => $paginado->items(),
                'meta' => [
                    'total' => $paginado->total(),
                    'pagina_actual' => $paginado->currentPage(),
                    'ultima_pagina' => $paginado->lastPage(),
                    'por_pagina' => $paginado->perPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar los programas de formación educativa: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar los programas de formación educativa.',
            ], 500);
        }
    }

    public function crear(CrearProgramaFormacionEducativaRequest $request)
    {
        try {
            $datos = $request->validated();

            $institucion = InstitucionSnies::firstOrCreate(
                ['nombre_institucion' => $datos['institucion_nombre']]
            );

            $programa = ProgramaFormacionEducativa::create([
                'institucion_id' => $institucion->id_institucion,
                'nivel_formacion_academica_id' => $datos['nivel_formacion_academica_id'],
                'nombre_programa' => $datos['nombre_programa'],
                'titulo_otorgado' => $datos['titulo_otorgado'] ?? null,
                'modalidad' => $datos['modalidad'] ?? null,
                'estado_programa' => ($datos['activo'] ?? true) ? 'Activo' : 'Inactivo',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Programa creado correctamente.',
                'data' => $programa->load(['institucion', 'nivelFormacionAcademica']),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear el programa de formación educativa: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el programa.',
            ], 500);
        }
    }

    public function actualizar(ActualizarProgramaFormacionEducativaRequest $request, $id)
    {
        try {
            $programa = $this->buscar($id);

            if (!$programa) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Programa no encontrado.',
                ], 404);
            }

            $datos = $request->validated();
            $actualizacion = [];

            if (isset($datos['institucion_nombre'])) {
                $institucion = InstitucionSnies::firstOrCreate(
                    ['nombre_institucion' => $datos['institucion_nombre']]
                );
                $actualizacion['institucion_id'] = $institucion->id_institucion;
            }

            foreach (['nivel_formacion_academica_id', 'nombre_programa', 'titulo_otorgado', 'modalidad'] as $campo) {
                if (array_key_exists($campo, $datos)) {
                    $actualizacion[$campo] = $datos[$campo];
                }
            }

            if (array_key_exists('activo', $datos)) {
                $actualizacion['estado_programa'] = $datos['activo'] ? 'Activo' : 'Inactivo';
            }

            $programa->update($actualizacion);

            return response()->json([
                'status' => 'success',
                'message' => 'Programa actualizado correctamente.',
                'data' => $programa->fresh(['institucion', 'nivelFormacionAcademica']),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar el programa de formación educativa: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar el programa.',
            ], 500);
        }
    }

    public function eliminar($id)
    {
        try {
            $programa = $this->buscar($id);

            if (!$programa) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Programa no encontrado.',
                ], 404);
            }

            // `estudios.programa_formacion_educativa_id` está en SET NULL: borrar el programa no
            // falla, simplemente desvincula en silencio los estudios que lo eligieron del catálogo.
            $estudios = DB::table('estudios')
                ->where('programa_formacion_educativa_id', $programa->id_programa)
                ->count();

            if ($estudios > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "No se puede eliminar: {$estudios} estudio(s) registrados apuntan a este programa. Puede marcarlo como inactivo para retirarlo de los formularios.",
                    'estudios_asociados' => $estudios,
                ], 409);
            }

            $programa->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Programa eliminado correctamente.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar el programa de formación educativa: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar el programa.',
            ], 500);
        }
    }
}
