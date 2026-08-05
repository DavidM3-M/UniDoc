<?php
// Define el espacio de nombres donde se ubica esta clase, lo que permite
// organizar el código y evitar conflictos con otras clases del mismo nombre.
namespace App\Constants\ConstEps;
// Clase que representa los diferentes tipos de afiliación al sistema de salud.

class TipoAfiliacion
{
    // Afiliación contributiva: para personas que realizan aportes económicos al sistema.
    public const CONTRIBUTIVO = 'Contributivo';
    // Afiliación subsidiada: para personas de bajos recursos, el Estado cubre el costo.
    public const SUBSIDIADO = 'Subsidiado';
    // Afiliación vinculada: para personas sin capacidad de pago, generalmente temporal.
    public const VINCULADO = 'Vinculado';
    // Afiliación especial: casos excepcionales regulados por normativas específicas.
    public const ESPECIAL = 'Especial';
    // Afiliación por excepción: cubre casos particulares como ciertos regímenes de excepción.
    public const EXCEPCION = 'Excepción';
    
    // Retorna todos los tipos de afiliacion
    public static function all(): array
    {
        return [
            self::CONTRIBUTIVO,
            self::SUBSIDIADO,
            self::VINCULADO,
            self::ESPECIAL,
            self::EXCEPCION
        ];
    }
}
