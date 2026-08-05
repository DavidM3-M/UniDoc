<?php

namespace App\Http\Controllers\Docente;

use App\Services\CalculoPuntajeDocenteService;
use Illuminate\Http\Request;

// Este controlador maneja la evaluación y el puntaje de los docentes.
// Permite evaluar el puntaje del docente y guardar el resultado en la base de datos.
class PuntajeController
{
    /**
     * Evaluar al docente autenticado y guardar su puntaje total.
     *
     * Este método utiliza el servicio `CalculoPuntajeDocenteService` para calcular el puntaje total
     * del docente autenticado, basándose en la información relacionada cargada previamente
     * (estudios, idiomas, experiencias, producción académica, evaluación docente, etc.).
     * Luego, guarda o actualiza el puntaje total en la base de datos mediante la relación `puntajeUsuario`.
     * Finalmente, retorna una respuesta JSON con el resultado de la evaluación.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @param CalculoPuntajeDocenteService $servicio Servicio responsable de realizar el cálculo del puntaje docente.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con mensaje de éxito y resultado de la evaluación.
     */
    public function evaluarYGuardarPuntaje(Request $request, CalculoPuntajeDocenteService $servicio)
    {
        $user = $request->user(); // Obtener el usuario autenticado
        $user->load([ // Cargar relaciones necesarias para la evaluación
            'contratacionUsuario',
            'estudiosUsuario.documentosEstudio',
            'idiomasUsuario.documentosIdioma',
            'experienciasUsuario.documentosExperiencia',
            'produccionAcademicaUsuario.documentosProduccionAcademica',
            'evaluacionDocenteUsuario',
        ]);

        $resultado = $servicio->evaluar($user); // Evaluar el puntaje del docente utilizando el servicio

        $user->puntajeUsuario()->updateOrCreate( // Actualizar o crear el puntaje del usuario
            ['user_id' => $user->id],
            ['puntaje_total' => $resultado['puntaje_total']]
        );

        return response()->json([ // Retornar la respuesta JSON con el resultado de la evaluación
            'mensaje' => 'Evaluación completada.',
            'resultado' => $resultado
        ], 200);
    }
}
