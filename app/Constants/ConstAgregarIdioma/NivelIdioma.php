<?php
// Define el espacio de nombres (namespace) para mantener el código organizado y evitar conflictos con otras clases.
namespace App\Constants\ConstAgregarIdioma;
// Esta clase contiene constantes que representan los niveles de dominio de un idioma según el MCER (Marco Común Europeo de Referencia para las Lenguas).
class NivelIdioma
{
    // Nivel A1: Usuario básico (principiante).
    public const A1 = 'A1';
    // Nivel A2: Usuario básico (elemental).
    public const A2 = 'A2';
    // Nivel B1: Usuario independiente (intermedio).
    public const B1 = 'B1';
    // Nivel B2: Usuario independiente (intermedio alto).
    public const B2 = 'B2';
    // Nivel C1: Usuario competente (avanzado).
    public const C1 = 'C1';
    // Nivel C2: Usuario competente (maestría).
    public const C2 = 'C2';

    // Este método estático retorna todos los niveles definidos como un arreglo.
    // Es útil para usarlos en formularios, validaciones o mostrar listas de selección.
    public static function all(): array
    {
        return [
            self::A1,
            self::A2,
            self::B1,
            self::B2,
            self::C1,
            self::C2,
        ];
    }
}
