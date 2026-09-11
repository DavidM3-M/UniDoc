<?php

namespace App\Http\Controllers\Docente;

use App\Services\MotorEscalafonDocenteService;
use Illuminate\Http\Request;

/**
 * Consulta de escalafón del docente autenticado.
 *
 * **Ya no persiste nada.** Antes este endpoint llamaba a `EscalafonDocenteService::evaluarYPersistir()`
 * y le escribía la categoría al docente en el momento en que la consultaba: el docente se
 * autoascendía. Con el nuevo reglamento el ascenso es un acto de Apoyo Profesoral
 * (`ApoyoProfesoral\EscalafonDocenteController`), así que aquí solo se informa.
 *
 * Lo que devuelve es el avance hacia el **siguiente** escalón, medido contra la fecha de cierre del
 * periodo de ascenso vigente: qué le falta, cuántos meses lleva en su categoría actual y cuánto
 * puntaje de producción académica acumula desde que la tiene.
 */
class PuntajeController
{
    /**
     * Estado del escalafón del docente autenticado.
     *
     * La ruta sigue siendo `GET /docente/evaluar-puntaje` para no romper el frontend, aunque el
     * nombre ya no describa bien lo que hace: no evalúa para otorgar, informa.
     *
     * @param Request $request Solicitud HTTP con el usuario autenticado.
     * @param MotorEscalafonDocenteService $motor Resuelve la elegibilidad, sin escribir.
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarEstadoEscalafon(Request $request, MotorEscalafonDocenteService $motor)
    {
        $user = $request->user();
        $user->load([
            'estudiosUsuario.documentosEstudio',
            'idiomasUsuario.documentosIdioma',
            'experienciasUsuario.documentosExperiencia', // Antigüedad Uniautónoma
            'produccionAcademicaUsuario.documentosProduccionAcademica',
            'evaluacionDocenteUsuario',
            'historialEscalonUsuario.escalon', // Escalón vigente y desde cuándo lo tiene
        ]);

        return response()->json([
            'mensaje' => 'Consulta completada.',
            'resultado' => $motor->evaluarAscenso($user),
        ], 200);
    }
}
