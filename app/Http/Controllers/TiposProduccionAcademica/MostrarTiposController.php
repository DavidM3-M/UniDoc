<?php

namespace App\Http\Controllers\TiposProduccionAcademica;

use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use App\Models\TiposProductoAcademico\ProductoAcademico;

/**
 * Catálogos de producción académica que alimentan los desplegables del formulario.
 *
 * Los listados devuelven solo los registros activos: un catálogo que el Administrador retiró
 * (`activo = false`) deja de ofrecerse en formularios nuevos, pero sigue existiendo en la base
 * para no romper las producciones académicas ya registradas con él.
 * El CRUD de estos catálogos vive en `App\Http\Controllers\Admin\ProductoAcademicoController`
 * y `App\Http\Controllers\Admin\AmbitoDivulgacionController`.
 */
class MostrarTiposController
{
  //metodo para obtener productos academicos
  public function obtenerProductosAcademicos()
  {
    return response()->json(ProductoAcademico::activos()->get(), 200);
  }

  // Método para obtener los tipos de productos académicos
  public function obtenerAmbitoDivulgacion()
  {
    return response()->json(AmbitoDivulgacion::activos()->get(), 200);
  }

  //obtener ambito de divulgacion por producto academico
  public function obtenerAmbitoDivulgacionPorProductoAcademico($id_producto_academico)
  {
    $ambitos = AmbitoDivulgacion::activos()
      ->where('producto_academico_id', $id_producto_academico)
      ->get();
    return response()->json($ambitos, 200);
  }

  // Consulta puntual por ID: no filtra por `activo` a propósito, porque la usa el histórico
  // para resolver el nombre de ámbitos que ya fueron retirados del catálogo.
  public function obterProduccionPorAmbitoDivulgacion($id_ambito_divulgacion)
  {
    $ambitoDivulgacion = AmbitoDivulgacion::with('productoAcademicoAmbitoDivulgacion')->find($id_ambito_divulgacion);

    if (!$ambitoDivulgacion) {
      return response()->json(['error' => 'Ambito de divulgacion no encontrado'], 404);
    }
    return response()->json([
      'id_ambito_divulgacion' => $ambitoDivulgacion->id_ambito_divulgacion,
      'nombre_ambito_divulgacion' => $ambitoDivulgacion->nombre_ambito_divulgacion,
      'producto_academico_id' => $ambitoDivulgacion->productoAcademicoAmbitoDivulgacion?->id_producto_academico,
      'nombre_producto_academico' => $ambitoDivulgacion->productoAcademicoAmbitoDivulgacion?->nombre_producto_academico,
    ], 200);
  }
}
