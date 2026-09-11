<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RequestAdmin\RequestCatalogoProduccionAcademica\ActualizarAmbitoDivulgacionRequest;
use App\Http\Requests\RequestAdmin\RequestCatalogoProduccionAcademica\CrearAmbitoDivulgacionRequest;
use App\Constants\ClavePrimaria;
use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Administración del catálogo de ámbitos de divulgación.
 *
 * Cada ámbito cuelga de un tipo de producto académico (`ProductoAcademicoController`) y es lo
 * que el aspirante escoge realmente al registrar una producción académica
 * (`produccion_academicas.ambito_divulgacion_id`).
 *
 * El campo `puntaje` es lo que suma `MotorEscalafonDocenteService::calcularPuntaje()` por cada
 * producción académica aprobada que use este ámbito — un ámbito nuevo puntúa desde el día uno,
 * con el valor que se le asigne aquí (o desde la pestaña "Puntajes" de Escalafón docente).
 */
class AmbitoDivulgacionController
{
    /**
     * Busca un ámbito descartando los IDs que la clave primaria no admite.
     *
     * Ver `ClavePrimaria::fueraDeRango()`: consultar un ID fuera del rango de la columna hace
     * fallar la consulta en PostgreSQL, y para la API eso debe ser un 404, no un 500.
     *
     * @param mixed $id
     * @param array $relaciones Relaciones a cargar junto al modelo.
     * @return AmbitoDivulgacion|null
     */
    private function buscar($id, array $relaciones = []): ?AmbitoDivulgacion
    {
        if (ClavePrimaria::fueraDeRango($id)) {
            return null;
        }

        return AmbitoDivulgacion::with($relaciones)->find($id);
    }

    /**
     * Lista los ámbitos de divulgación, activos e inactivos.
     *
     * Acepta el filtro opcional `?producto_academico_id=` para ver solo los de un producto.
     * Incluye el conteo de producciones académicas que los referencian, que es lo que
     * determina si se pueden eliminar.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listar(Request $request)
    {
        try {
            $ambitos = AmbitoDivulgacion::with('productoAcademicoAmbitoDivulgacion:id_producto_academico,nombre_producto_academico')
                ->withCount('produccionAcademicasAmbitoDivulgacion')
                ->when(
                    $request->filled('producto_academico_id'),
                    fn($query) => $query->where('producto_academico_id', $request->input('producto_academico_id'))
                )
                ->orderBy('producto_academico_id')
                ->orderBy('nombre_ambito_divulgacion')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $ambitos,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar los ámbitos de divulgación: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar los ámbitos de divulgación.',
            ], 500);
        }
    }

    /**
     * Consulta un ámbito de divulgación con su producto académico.
     *
     * @param int $id ID del ámbito.
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerPorId($id)
    {
        try {
            $ambito = $this->buscar($id, ['productoAcademicoAmbitoDivulgacion:id_producto_academico,nombre_producto_academico']);

            if (!$ambito) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ámbito de divulgación no encontrado.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $ambito,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener el ámbito de divulgación: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener el ámbito de divulgación.',
            ], 500);
        }
    }

    /**
     * Registra un ámbito de divulgación nuevo bajo un producto académico.
     *
     * @param CrearAmbitoDivulgacionRequest $request Solicitud validada.
     * @return \Illuminate\Http\JsonResponse
     */
    public function crear(CrearAmbitoDivulgacionRequest $request)
    {
        try {
            $ambito = AmbitoDivulgacion::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Ámbito de divulgación creado correctamente.',
                'data' => $ambito->load('productoAcademicoAmbitoDivulgacion:id_producto_academico,nombre_producto_academico'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear el ámbito de divulgación: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el ámbito de divulgación.',
            ], 500);
        }
    }

    /**
     * Actualiza un ámbito de divulgación.
     *
     * Marcarlo con `activo = false` lo retira de los desplegables del aspirante sin borrarlo,
     * de modo que las producciones académicas ya registradas conserven su referencia.
     *
     * @param ActualizarAmbitoDivulgacionRequest $request Solicitud validada.
     * @param int $id ID del ámbito.
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizar(ActualizarAmbitoDivulgacionRequest $request, $id)
    {
        try {
            $ambito = $this->buscar($id);

            if (!$ambito) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ámbito de divulgación no encontrado.',
                ], 404);
            }

            $ambito->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Ámbito de divulgación actualizado correctamente.',
                'data' => $ambito->load('productoAcademicoAmbitoDivulgacion:id_producto_academico,nombre_producto_academico'),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar el ámbito de divulgación: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar el ámbito de divulgación.',
            ], 500);
        }
    }

    /**
     * Elimina un ámbito de divulgación que no esté en uso.
     *
     * Si alguna producción académica lo referencia se responde 409 en lugar de dejar que
     * reviente la llave foránea de `produccion_academicas`. Para retirarlo del desplegable sin
     * perder el histórico está `activo = false`.
     *
     * @param int $id ID del ámbito.
     * @return \Illuminate\Http\JsonResponse
     */
    public function eliminar($id)
    {
        try {
            $ambito = $this->buscar($id);

            if (!$ambito) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ámbito de divulgación no encontrado.',
                ], 404);
            }

            $producciones = $ambito->produccionAcademicasAmbitoDivulgacion()->count();

            if ($producciones > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "No se puede eliminar: hay {$producciones} producción(es) académica(s) que usan este ámbito de divulgación. Puede marcarlo como inactivo para retirarlo de los formularios.",
                    'producciones_asociadas' => $producciones,
                ], 409);
            }

            $ambito->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Ámbito de divulgación eliminado correctamente.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar el ámbito de divulgación: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar el ámbito de divulgación.',
            ], 500);
        }
    }
}
