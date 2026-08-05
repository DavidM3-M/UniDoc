<?php
// Define el espacio de nombres donde se ubica esta clase constante
namespace App\Constants\ConstAgregarEstudio;
// Esta clase contiene constantes relacionadas con el estado de graduación (Sí o No)
class Graduado
{
    // Constante que representa que el usuario está graduado
    const SI = 'Si';
    // Constante que representa que el usuario NO está graduado
    const NO = 'No';
    // Método estático que retorna todas las opciones disponibles de graduación

    public static function all(): array
    {
        return [
            self::SI, // Devuelve 'Si'
            self::NO, // Devuelve 'No'
        ];
    }
}
