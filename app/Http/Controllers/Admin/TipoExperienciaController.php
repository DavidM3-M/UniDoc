<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RequestAdmin\RequestTipoExperiencia\ActualizarTipoExperienciaRequest;
use App\Http\Requests\RequestAdmin\RequestTipoExperiencia\CrearTipoExperienciaRequest;
use App\Constants\ClavePrimaria;
use App\Models\TipoExperiencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Administración del catálogo de tipos de experiencia profesional.
 *
 * Antes esta lista era la constante PHP `App\Constants\ConstAgregarExperiencia\TiposExperiencia`
 * y agregar un tipo obligaba a editar código y desplegar.
 *
 * **El catálogo se referencia por nombre, no por ID.** `experiencias.tipo_experiencia` y
 * `convocatorias.tipo_experiencia_requerida` guardan el string. Eso condiciona dos operaciones:
 * renombrar (hay que propagar el cambio) y eliminar (hay que buscar el uso por nombre).
 */
class TipoExperienciaController
{
    /**
     * Busca un tipo de experiencia descartando los IDs que la clave primaria no admite.
     *
     * Ver `ClavePrimaria::fueraDeRango()`: consultar un ID fuera del rango de la columna hace
     * fallar la consulta en PostgreSQL, y para la API eso debe ser un 404, no un 500.
     *
     * @param mixed $id
     * @return TipoExperiencia|null
     */
    private function buscar($id): ?TipoExperiencia
    {
        if (ClavePrimaria::fueraDeRango($id)) {
            return null;
        }

        return TipoExperiencia::find($id);
    }

    /**
     * Lista los tipos de experiencia, activos e inactivos.
     *
     * Incluye cuántas experiencias usan cada tipo, que es lo que determina si se puede eliminar.
     * El conteo se hace con una subconsulta por nombre porque no existe FK entre ambas tablas.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listar()
    {
        try {
            $tipos = TipoExperiencia::query()
                ->select('tipo_experiencias.*')
                ->selectSub(
                    DB::table('experiencias')
                        ->selectRaw('count(*)')
                        ->whereColumn('experiencias.tipo_experiencia', 'tipo_experiencias.nombre_tipo_experiencia'),
                    'experiencias_count'
                )
                ->orderBy('nombre_tipo_experiencia')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $tipos,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar los tipos de experiencia: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar los tipos de experiencia.',
            ], 500);
        }
    }

    /**
     * Consulta un tipo de experiencia por su ID.
     *
     * @param int $id ID del tipo de experiencia.
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerPorId($id)
    {
        try {
            $tipo = $this->buscar($id);

            if (!$tipo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tipo de experiencia no encontrado.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $tipo,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener el tipo de experiencia: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener el tipo de experiencia.',
            ], 500);
        }
    }

    /**
     * Registra un tipo de experiencia nuevo.
     *
     * Queda disponible de inmediato en `GET /constantes/tipos-experiencia` y por tanto en el
     * formulario de experiencia del docente y del aspirante.
     *
     * @param CrearTipoExperienciaRequest $request Solicitud validada.
     * @return \Illuminate\Http\JsonResponse
     */
    public function crear(CrearTipoExperienciaRequest $request)
    {
        try {
            $tipo = TipoExperiencia::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de experiencia creado correctamente.',
                'data' => $tipo,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear el tipo de experiencia: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el tipo de experiencia.',
            ], 500);
        }
    }

    /**
     * Actualiza el nombre o el estado de un tipo de experiencia.
     *
     * **Propaga el renombrado.** Como `experiencias.tipo_experiencia` y
     * `convocatorias.tipo_experiencia_requerida` guardan el nombre y no un ID, cambiarlo aquí sin
     * más dejaría huérfanos todos los registros históricos: dejarían de coincidir con cualquier
     * tipo del catálogo y no pasarían la validación al editarlos. Por eso el rename se replica a
     * ambas tablas dentro de una transacción — o cambian las tres cosas, o no cambia ninguna.
     *
     * Esta es la contrapartida de referenciar el catálogo por nombre en vez de normalizarlo con FK.
     *
     * @param ActualizarTipoExperienciaRequest $request Solicitud validada.
     * @param int $id ID del tipo de experiencia.
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizar(ActualizarTipoExperienciaRequest $request, $id)
    {
        try {
            $tipo = $this->buscar($id);

            if (!$tipo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tipo de experiencia no encontrado.',
                ], 404);
            }

            $datos = $request->validated();
            $nombreAnterior = $tipo->nombre_tipo_experiencia;
            $nombreNuevo = $datos['nombre_tipo_experiencia'] ?? $nombreAnterior;
            $experienciasActualizadas = 0;
            $convocatoriasActualizadas = 0;

            DB::transaction(function () use ($tipo, $datos, $nombreAnterior, $nombreNuevo, &$experienciasActualizadas, &$convocatoriasActualizadas) {
                $tipo->update($datos);

                if ($nombreNuevo !== $nombreAnterior) {
                    $experienciasActualizadas = DB::table('experiencias')
                        ->where('tipo_experiencia', $nombreAnterior)
                        ->update(['tipo_experiencia' => $nombreNuevo]);

                    $convocatoriasActualizadas = DB::table('convocatorias')
                        ->where('tipo_experiencia_requerida', $nombreAnterior)
                        ->update(['tipo_experiencia_requerida' => $nombreNuevo]);
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de experiencia actualizado correctamente.',
                'data' => $tipo->fresh(),
                'registros_renombrados' => [
                    'experiencias' => $experienciasActualizadas,
                    'convocatorias' => $convocatoriasActualizadas,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar el tipo de experiencia: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar el tipo de experiencia.',
            ], 500);
        }
    }

    /**
     * Elimina un tipo de experiencia que no esté en uso.
     *
     * No hay FK que proteja estas tablas, así que el uso se comprueba por nombre: si alguna
     * experiencia o alguna convocatoria lo referencia, se responde 409 en lugar de dejar
     * registros apuntando a un tipo inexistente. Para retirarlo del formulario sin perder el
     * histórico está `activo = false`.
     *
     * @param int $id ID del tipo de experiencia.
     * @return \Illuminate\Http\JsonResponse
     */
    public function eliminar($id)
    {
        try {
            $tipo = $this->buscar($id);

            if (!$tipo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tipo de experiencia no encontrado.',
                ], 404);
            }

            $experiencias = DB::table('experiencias')
                ->where('tipo_experiencia', $tipo->nombre_tipo_experiencia)
                ->count();

            $convocatorias = DB::table('convocatorias')
                ->where('tipo_experiencia_requerida', $tipo->nombre_tipo_experiencia)
                ->count();

            if ($experiencias > 0 || $convocatorias > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "No se puede eliminar: {$experiencias} experiencia(s) y {$convocatorias} convocatoria(s) usan este tipo. Puede marcarlo como inactivo para retirarlo de los formularios.",
                    'experiencias_asociadas' => $experiencias,
                    'convocatorias_asociadas' => $convocatorias,
                ], 409);
            }

            $tipo->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de experiencia eliminado correctamente.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar el tipo de experiencia: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar el tipo de experiencia.',
            ], 500);
        }
    }
}
