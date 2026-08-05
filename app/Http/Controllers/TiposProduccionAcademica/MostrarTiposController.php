<?php

namespace App\Http\Controllers\TiposProduccionAcademica;

use App\Models\TiposProductoAcademico\AmbitoDivulgacion;
use App\Models\TiposProductoAcademico\ProductoAcademico;

class MostrarTiposController
{
  //metodo para obtener productos academicos
  public function obtenerProductosAcademicos()
  {
    return response()->json(ProductoAcademico::all(), 200);
  }

  // Método para obtener los tipos de productos académicos
  public function obtenerAmbitoDivulgacion()
  {
    return response()->json(AmbitoDivulgacion::all(), 200);
  }

  //obtener ambito de divulgacion por producto academico
  public function obtenerAmbitoDivulgacionPorProductoAcademico($id_producto_academico)
  {
    $ambitos = AmbitoDivulgacion::where('producto_academico_id', $id_producto_academico)->get();
    return response()->json($ambitos, 200);
  }

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
