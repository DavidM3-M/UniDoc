<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\RequestAdmin\RequestCatalogoProduccionAcademica\ActualizarProductoAcademicoRequest;
use App\Http\Requests\RequestAdmin\RequestCatalogoProduccionAcademica\CrearProductoAcademicoRequest;
use App\Constants\ClavePrimaria;
use App\Models\TiposProductoAcademico\ProductoAcademico;
use Illuminate\Support\Facades\Log;

/**
 * Administración del catálogo de tipos de producto académico.
 *
 * Antes este catálogo se poblaba una sola vez desde `database/data/tipo_producto_academico.csv`
 * y agregar un tipo exigía editar el CSV y volver a sembrar. Ahora lo gestiona el Administrador.
 *
 * Cada producto académico agrupa a sus ámbitos de divulgación
 * (ver `AmbitoDivulgacionController`), que son los que el aspirante escoge al registrar
 * una producción académica.
 *
 * **Limitación conocida:** `CalculoPuntajeDocenteService::clasificacionPorAmbito()` mapea IDs de
 * ámbito hardcodeados a puntaje. Los ámbitos creados desde aquí suman 0 puntos hasta que se
 * actualice ese servicio.
 */
class ProductoAcademicoController
{
    /**
     * Busca un producto académico descartando los IDs que la clave primaria no admite.
     *
     * Ver `ClavePrimaria::fueraDeRango()`: consultar un ID fuera del rango de la columna hace
     * fallar la consulta en PostgreSQL, y para la API eso debe ser un 404, no un 500.
     *
     * @param mixed $id
     * @param array $relaciones Relaciones a cargar junto al modelo.
     * @return ProductoAcademico|null
     */
    private function buscar($id, array $relaciones = []): ?ProductoAcademico
    {
        if (ClavePrimaria::fueraDeRango($id)) {
            return null;
        }

        return ProductoAcademico::with($relaciones)->find($id);
    }

    /**
     * Lista todos los tipos de producto académico, activos e inactivos.
     *
     * Incluye el conteo de ámbitos de cada uno, que es lo que determina si se puede eliminar.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listar()
    {
        try {
            $productos = ProductoAcademico::withCount('ambitoDivulgacionsProductoAcademico')
                ->orderBy('nombre_producto_academico')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $productos,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al listar los tipos de producto académico: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al listar los tipos de producto académico.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta un tipo de producto académico con sus ámbitos de divulgación.
     *
     * @param int $id ID del producto académico.
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerPorId($id)
    {
        try {
            $producto = $this->buscar($id, ['ambitoDivulgacionsProductoAcademico']);

            if (!$producto) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tipo de producto académico no encontrado.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $producto,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener el tipo de producto académico: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener el tipo de producto académico.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registra un tipo de producto académico nuevo.
     *
     * @param CrearProductoAcademicoRequest $request Solicitud validada.
     * @return \Illuminate\Http\JsonResponse
     */
    public function crear(CrearProductoAcademicoRequest $request)
    {
        try {
            $producto = ProductoAcademico::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de producto académico creado correctamente.',
                'data' => $producto,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear el tipo de producto académico: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al crear el tipo de producto académico.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el nombre o el estado de un tipo de producto académico.
     *
     * Marcarlo con `activo = false` lo retira de los desplegables del aspirante sin borrarlo,
     * de modo que las producciones académicas ya registradas conserven su clasificación.
     *
     * @param ActualizarProductoAcademicoRequest $request Solicitud validada.
     * @param int $id ID del producto académico.
     * @return \Illuminate\Http\JsonResponse
     */
    public function actualizar(ActualizarProductoAcademicoRequest $request, $id)
    {
        try {
            $producto = $this->buscar($id);

            if (!$producto) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tipo de producto académico no encontrado.',
                ], 404);
            }

            $producto->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de producto académico actualizado correctamente.',
                'data' => $producto,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar el tipo de producto académico: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al actualizar el tipo de producto académico.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina un tipo de producto académico que no tenga ámbitos asociados.
     *
     * Si tiene ámbitos se responde 409 en lugar de dejar que reviente la llave foránea:
     * borrarlo arrastraría ámbitos que a su vez pueden estar referenciados por producciones
     * académicas de docentes. Para retirarlo del desplegable sin perder el histórico está
     * `activo = false`.
     *
     * @param int $id ID del producto académico.
     * @return \Illuminate\Http\JsonResponse
     */
    public function eliminar($id)
    {
        try {
            $producto = $this->buscar($id);

            if (!$producto) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tipo de producto académico no encontrado.',
                ], 404);
            }

            $ambitos = $producto->ambitoDivulgacionsProductoAcademico()->count();

            if ($ambitos > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "No se puede eliminar: hay {$ambitos} ámbito(s) de divulgación asociados a este tipo de producto académico. Puede marcarlo como inactivo para retirarlo de los formularios.",
                    'ambitos_asociados' => $ambitos,
                ], 409);
            }

            $producto->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de producto académico eliminado correctamente.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al eliminar el tipo de producto académico: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar el tipo de producto académico.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
