<?php

namespace App\Http\Controllers\Publico;

use App\Models\InstitucionSnies;
use App\Models\ProgramaFormacionEducativa;
use Illuminate\Http\Request;

/**
 * Versión pública (sin autenticación de Admin) del catálogo de Formación educativa importado
 * del SNIES, para la cascada Institución → Programa que usa el Aspirante/Docente al registrar
 * un estudio (ver `Aspirante\EstudioController`).
 *
 * `instituciones_snies` tiene ~368 filas: se devuelve completa, mismo patrón que
 * `InstitucionController::obtenerInstituciones()` (catálogo del MEN) — el frontend filtra en el
 * cliente. `programas_formacion_educativa` tiene ~32.000 filas: NO se puede devolver completa,
 * por eso `buscarProgramas()` pagina server-side igual que
 * `Admin\FormacionEducativaController::listar()`, pero público, de solo lectura, y limitado a
 * `estado_programa = 'Activo'` (sin exponer programas dados de baja).
 */
class FormacionEducativaPublicoController
{
    public function obtenerInstituciones()
    {
        return response()->json([
            'instituciones' => InstitucionSnies::where('estado_institucion', 'Activa')
                ->orderBy('nombre_institucion')
                ->get(['id_institucion', 'nombre_institucion']),
        ]);
    }

    public function buscarProgramas(Request $request)
    {
        $institucionId = $request->query('institucion_id');
        $nivelFormacionAcademicaId = $request->query('nivel_formacion_academica_id');
        $busqueda = trim((string) $request->query('q', ''));

        $query = ProgramaFormacionEducativa::query()
            ->where('estado_programa', 'Activo')
            ->when($institucionId, fn ($q) => $q->where('institucion_id', $institucionId))
            ->when($nivelFormacionAcademicaId, fn ($q) => $q->where('nivel_formacion_academica_id', $nivelFormacionAcademicaId))
            ->when($busqueda !== '', fn ($q) => $q->where(function ($w) use ($busqueda) {
                $w->where('nombre_programa', 'ilike', "%{$busqueda}%")
                    ->orWhere('titulo_otorgado', 'ilike', "%{$busqueda}%");
            }));

        // Trae el nombre del nivel de formación (no solo su id) para que el frontend pueda
        // autocompletar `tipo_estudio` sin una segunda consulta al elegir un programa.
        return response()->json([
            'programas' => $query->with('nivelFormacionAcademica:id_nivel_formacion_academica,nivel_formacion')
                ->orderBy('nombre_programa')
                ->limit(20)
                ->get(['id_programa', 'nombre_programa', 'titulo_otorgado', 'nivel_formacion_academica_id']),
        ]);
    }
}
