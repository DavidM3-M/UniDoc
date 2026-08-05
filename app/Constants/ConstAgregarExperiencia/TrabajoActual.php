<?php

// Define el espacio de nombres donde está ubicada la clase.
// Esto ayuda a organizar el código y evitar conflictos con otras clases del mismo nombre.
namespace App\Constants\ConstAgregarExperiencia;
// Esta clase contiene constantes para representar si un aspirante actualmente se encuentra laborando o no.

class TrabajoActual
{
    // Constante que representa que el aspirante SÍ tiene un trabajo actualmente.
    public const SI = 'Si';
    // Constante que representa que el aspirante NO tiene un trabajo actualmente.
    public const NO = 'No';

    // Método estático que retorna todas las opciones posibles (sí o no) en forma de array.
    // Este método es útil para cargar estas opciones en formularios, validaciones o listas desplegables.
    public static function all(): array
    {
        return [
            self::SI,
            self::NO,
        ];
    }
}
