<?php

namespace App\Http\Controllers\Docente;

use App\Services\EscalafonDocenteService;
use Illuminate\Http\Request;

// Este controlador maneja la evaluación y el puntaje de los docentes.
// Permite evaluar el puntaje del docente y guardar el resultado en la base de datos.
class PuntajeController
{
    /**
     * Evaluar al docente autenticado y guardar su puntaje y categoría.
     *
     * El cálculo usa el umbral de evaluación vigente, que configura el Administrador.
     * `EscalafonDocenteService` aplica además la regla de no retroactividad: si el umbral
     * subió después de que al docente se le otorgó su categoría, la conserva mientras
     * siga cumpliendo los requisitos que regían en ese momento.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @param EscalafonDocenteService $escalafon Resuelve y persiste la categoría efectiva.
     * @return \Illuminate\Http\JsonResponse Respuesta JSON con el resultado de la evaluación.
     */
    public function evaluarYGuardarPuntaje(Request $request, EscalafonDocenteService $escalafon)
    {
        $user = $request->user(); // Obtener el usuario autenticado
        $user->load([ // Cargar relaciones necesarias para la evaluación
            'contratacionUsuario',
            'estudiosUsuario.documentosEstudio',
            'idiomasUsuario.documentosIdioma',
            'experienciasUsuario.documentosExperiencia',
            'produccionAcademicaUsuario.documentosProduccionAcademica',
            'evaluacionDocenteUsuario',
            'puntajeUsuario', // Categoría ya otorgada, necesaria para la no retroactividad
        ]);

        $resultado = $escalafon->evaluarYPersistir($user); // Evaluar, proteger y guardar

        return response()->json([ // Retornar la respuesta JSON con el resultado de la evaluación
            'mensaje' => 'Evaluación completada.',
            'resultado' => $resultado
        ], 200);
    }
}
