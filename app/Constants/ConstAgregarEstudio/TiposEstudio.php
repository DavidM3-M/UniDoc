<?php

namespace App\Constants\ConstAgregarEstudio;

// Esta clase contiene una lista de constantes que representan los diferentes tipos de estudios que un usuario puede registrar.
class TiposEstudio
{ 
    // Cada constante representa un tipo específico de estudio o formación académica.
    
    // Cursos o capacitaciones cortas
    public const CURSO_PROGRAMADO_O_CAPACITACION = 'Curso programado o capacitación';
    // Carrera universitaria básica
    public const PREGRADO = 'Pregrado';
    // Pregrados específicos en áreas como medicina o música (que suelen tener una duración o requisitos distintos)
    public const PREGRADO_EN_MEDICINA_HUMANA_O_COMPOSICION_MUSICAL = 'Pregrado en medicina humana o composición musical';
    // Programas de especialización
    public const ESPECIALIZACION = 'Especialización';
    // Especializaciones particulares en salud (más exigentes en regulación)
    public const ESPECIALIZACION_EN_MEDICINA_HUMANA_Y_ODONTOLOGIA = 'Especialización en medicina humana y odontología';
    // Programas de maestría (posgrado académico)
    public const MAESTRIA = 'Maestría';
    // Programas de doctorado (el nivel académico más alto regular)
    public const DOCTORADO = 'Doctorado';
    // Estudios de investigación o académicos posteriores al doctorado
    public const POSTDOCTORADO = 'Postdoctorado';
    // Estudios técnicos (enfocados en habilidades específicas)
    public const TECNICO = 'Técnico';
    // Estudios tecnológicos (más avanzados que los técnicos, pueden durar más)
    public const TECNOLOGICO = 'Tecnológico';
    // Programas de diplomado (cursos de especialización sin título profesional)
    public const DIPLOMADO = 'Diplomado';
    // Certificaciones (avaladas por entidades, suelen tener una validez laboral)
    public const CERTIFICACION = 'Certificación';
    

     // Método estático que devuelve todas las constantes anteriores en forma de arreglo.
    // Es útil para llenar listas desplegables (selects), validar datos, o centralizar lógica de negocio.
    public static function all(): array
    {
        return [
            self::CURSO_PROGRAMADO_O_CAPACITACION,
            self::PREGRADO,
            self::PREGRADO_EN_MEDICINA_HUMANA_O_COMPOSICION_MUSICAL,
            self::ESPECIALIZACION,
            self::ESPECIALIZACION_EN_MEDICINA_HUMANA_Y_ODONTOLOGIA,
            self::MAESTRIA,
            self::DOCTORADO,
            self::POSTDOCTORADO,
            self::TECNICO,
            self::TECNOLOGICO,
            self::DIPLOMADO,
            self::CERTIFICACION
        ];
    }
}
