<?php

namespace App\Constants\ConstAgregarExperiencia;

// Esta clase define constantes para representar los diferentes tipos de **experiencia laboral o profesional**
// que un aspirante puede registrar, especialmente en el contexto académico o educativo.
class TiposExperiencia
{
    // Constante para experiencia en **proyectos de investigación**
    public const INVESTIGACION = 'Investigación';
    // Constante para experiencia en **docencia universitaria** (en instituciones de educación superior)
    public const DOCENCIA_UNIVERSITARIA = 'Docencia universitaria';
    // Constante para experiencia en **docencia no universitaria** (colegios, institutos, etc.)
    public const DOCENCIA_NO_UNIVERSITARIA = 'Docencia no universitaria';
    // Constante para experiencia en funciones **profesorales**, no necesariamente en universidades
    public const PROFESORAL = 'Profesoral';
    // Constante para experiencia en **dirección académica** (como coordinador, director de programa, decano, etc.)
    public const DIRECCION_ACADEMICA = 'Dirección académica';
    // Constante para cualquier otro tipo de experiencia que no encaje en las categorías anteriores
    public const OTRA = 'Otra';
    

    // Método estático que retorna un array con todos los tipos de experiencia definidos anteriormente.
    // Este método facilita cargar estas opciones en listas desplegables o validaciones.
    public static function all(): array
    {
        return [
            self::INVESTIGACION,
            self::DOCENCIA_UNIVERSITARIA,
            self::DOCENCIA_NO_UNIVERSITARIA,
            self::PROFESORAL,
            self::DIRECCION_ACADEMICA,
            self::OTRA,
        ];
    }
}
