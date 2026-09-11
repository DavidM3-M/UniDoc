<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EvaluadorProduccion\EvaluadorProduccionController;

/**
 * Rutas del rol Evaluador de Producción, que avala la producción académica de los docentes.
 *
 * Reemplazan a las tres rutas anteriores (`obtener-producciones`,
 * `ver-producciones-por-usuario/{user_id}` y `actualizar-produccion/{documento_id}`). No se dejan
 * como alias obsoletos porque no las consumía ningún cliente: el rol nunca tuvo frontend.
 *
 * El cambio de fondo está en el sujeto de la URL. Las rutas de decisión ya no reciben un
 * `documento_id` sino el id de la producción: el aval es de la publicación, no de cada archivo
 * suelto, y `AvalProduccionService` lo aplica a todos sus documentos en una transacción.
 */
Route::group([
    'middleware' => ['api', 'auth:api', 'role:Evaluador Produccion'],
    'prefix' => 'evaluadorProduccion',
], function () {
    // Contadores de la cabecera de la bandeja: pendientes, avaladas y rechazadas del mes,
    // y cuántas producciones no tienen con qué verificarse.
    Route::get('resumen', [EvaluadorProduccionController::class, 'resumen']);

    // Bandeja. Sin `estado` en la query devuelve todas las producciones, no solo las pendientes.
    // Filtros: estado, producto, ambito, docente, desde, hasta, sin_enlace, q, por_pagina.
    Route::get('producciones', [EvaluadorProduccionController::class, 'obtenerProducciones']);

    // Ficha completa: la producción, sus documentos, los seis enlaces de consulta y el impacto
    // que el aval tendría sobre el escalafón del docente.
    Route::get('producciones/{id}', [EvaluadorProduccionController::class, 'verProduccion']);

    // Decisión sobre la producción completa.
    Route::put('producciones/{id}/avalar', [EvaluadorProduccionController::class, 'avalar']);
    // Rechazar y revertir un aval son la misma escritura; `motivo` es obligatorio en ambos casos.
    Route::put('producciones/{id}/rechazar', [EvaluadorProduccionController::class, 'rechazar']);

    // Docentes con producción registrada y cuántas tienen en cada estado. Es la entrada al
    // expediente; va antes de la ruta con parámetro para que "docentes" no se tome por un userId.
    Route::get('docentes', [EvaluadorProduccionController::class, 'docentes']);

    // Expediente del docente: todas sus producciones, el puntaje avalado y posibles duplicados.
    Route::get('docentes/{userId}/producciones', [EvaluadorProduccionController::class, 'produccionesPorDocente']);
});
