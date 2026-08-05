<?php
// Define el namespace o espacio de nombres donde se encuentra esta clase,
// lo que ayuda a organizar el código dentro del proyecto.
namespace App\Constants\ConstEps;
// Clase que agrupa los diferentes estados posibles de afiliación a una EPS.

class EstadoAfiliacion
{
    // Estados de afiliacion
    // Estado que indica que la afiliación se encuentra activa.
    public const ACTIVO = 'Activo';
    // Estado que indica que la afiliación está inactiva, probablemente por retiro o terminación.
    public const INACTIVO = 'Inactivo';
    // Estado que indica que la afiliación está suspendida temporalmente por alguna razón administrativa o legal.
    public const SUSPENDIDO = 'Suspendido';

    // Método que retorna todos los estados definidos como un arreglo.
    // Útil para formularios, validaciones o lógica de negocio donde se requiera listar las opciones.
    public static function all(): array
    {
        return [
            self::ACTIVO,
            self::INACTIVO,
            self::SUSPENDIDO
        ];
    }
}
