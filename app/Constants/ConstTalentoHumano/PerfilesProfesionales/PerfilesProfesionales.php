<?php

namespace App\Constants\ConstTalentoHumano\PerfilesProfesionales;

use App\Constants\ConstAgregarEstudio\TiposEstudio;

class PerfilesProfesionales
{
    // Lista pública de perfiles profesionales para desplegable
    public static function all(): array
    {
        return [
            'Profesor Tiempo Completo',
            'Profesor Tiempo Parcial',
            'Investigador',
            'Coordinador Académico',
            'Administrador Académico',
            'Auxiliar Docente',
            'Especialista',
            'Conductor',
            'Recreacionista',
            'Psicólogo',
            'Enfermero',
            'Técnico de Laboratorio',
            'Bibliotecario',
            'Gestor de Calidad',
            'Analista de Sistemas',
            'Diseñador Instruccional',
            'Tutor Virtual',
            'Asistente Administrativo',
            'Coordinador de Prácticas',
            'Ingeniero de Medios Audiovisuales',
            'Otro',
        ];
    }

    // Retorna palabras clave asociadas a cada perfil para la validación de títulos
    public static function getPalabrasClavePerfil(string $perfil): array
    {
        $map = [
            'Profesor Tiempo Completo' => ['profesor', 'docente', 'tiempo completo', 'full time'],
            'Profesor Tiempo Parcial' => ['profesor', 'docente', 'tiempo parcial', 'part time'],
            'Investigador' => ['investigador', 'investigacion', 'research'],
            'Coordinador Académico' => ['coordinador', 'coordinacion', 'director', 'jefe'],
            'Administrador Académico' => ['administrador', 'administracion', 'gestion'],
            'Auxiliar Docente' => ['auxiliar', 'asistente', 'docente', 'ayudante'],
            'Especialista' => ['especialista', 'especializacion'],
            'Conductor' => ['conductor', 'chofer', 'piloto'],
            'Recreacionista' => ['recreacionista', 'recreacion', 'animador', 'monitor'],
            'Psicólogo' => ['psicologo', 'psicologia'],
            'Enfermero' => ['enfermero', 'enfermeria', 'nurse'],
            'Técnico de Laboratorio' => ['laboratorio', 'tecnico de laboratorio', 'técnico laboratorio'],
            'Bibliotecario' => ['bibliotecario', 'biblioteca'],
            'Gestor de Calidad' => ['calidad', 'gestor de calidad', 'quality'],
            'Analista de Sistemas' => ['analista', 'sistemas', 'informatico', 'desarrollador'],
            'Diseñador Instruccional' => ['diseñador instruccional', 'instruccional', 'e-learning', 'diseño instruccional'],
            'Tutor Virtual' => ['tutor', 'tutor virtual', 'tutor online'],
            'Asistente Administrativo' => ['asistente', 'administrativo', 'secretaria'],
            'Coordinador de Prácticas' => ['practicas', 'coordinador de prácticas', 'prácticas'],
            'Ingeniero de Medios Audiovisuales' => ['audiovisual', 'medios', 'ingeniero', 'multimedia'],
        ];

        return $map[$perfil] ?? [];
    }

    // Retorna los niveles mínimos de estudio aceptables para el perfil
    // Devuelve un array con valores de TiposEstudio
    public static function getNivelMinimoEstudio(string $perfil): array
    {
        $map = [
            'Profesor Tiempo Completo' => [TiposEstudio::MAESTRIA, TiposEstudio::DOCTORADO],
            'Profesor Tiempo Parcial' => [TiposEstudio::PREGRADO, TiposEstudio::MAESTRIA],
            'Investigador' => [TiposEstudio::MAESTRIA, TiposEstudio::DOCTORADO, TiposEstudio::POSTDOCTORADO],
            'Coordinador Académico' => [TiposEstudio::ESPECIALIZACION, TiposEstudio::MAESTRIA, TiposEstudio::DOCTORADO],
            'Administrador Académico' => [TiposEstudio::PREGRADO, TiposEstudio::ESPECIALIZACION],
            'Auxiliar Docente' => [TiposEstudio::TECNICO, TiposEstudio::TECNOLOGICO, TiposEstudio::PREGRADO],
            'Especialista' => [TiposEstudio::ESPECIALIZACION, TiposEstudio::MAESTRIA],
            'Conductor' => [TiposEstudio::TECNICO, TiposEstudio::CERTIFICACION],
            'Recreacionista' => [TiposEstudio::TECNICO, TiposEstudio::CERTIFICACION],
            'Psicólogo' => [TiposEstudio::PREGRADO, TiposEstudio::MAESTRIA],
            'Enfermero' => [TiposEstudio::TECNICO, TiposEstudio::PREGRADO],
            'Técnico de Laboratorio' => [TiposEstudio::TECNICO, TiposEstudio::TECNOLOGICO],
            'Bibliotecario' => [TiposEstudio::TECNICO, TiposEstudio::PREGRADO],
            'Gestor de Calidad' => [TiposEstudio::PREGRADO, TiposEstudio::ESPECIALIZACION],
            'Analista de Sistemas' => [TiposEstudio::PREGRADO, TiposEstudio::TECNOLOGICO],
            'Diseñador Instruccional' => [TiposEstudio::PREGRADO, TiposEstudio::ESPECIALIZACION],
            'Tutor Virtual' => [TiposEstudio::TECNICO, TiposEstudio::PREGRADO],
            'Asistente Administrativo' => [TiposEstudio::TECNICO, TiposEstudio::PREGRADO],
            'Coordinador de Prácticas' => [TiposEstudio::PREGRADO, TiposEstudio::ESPECIALIZACION],
            'Ingeniero de Medios Audiovisuales' => [TiposEstudio::PREGRADO, TiposEstudio::TECNOLOGICO],
        ];

        return $map[$perfil] ?? [TiposEstudio::PREGRADO];
    }
}
