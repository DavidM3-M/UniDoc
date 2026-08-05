<?php
// Se define el espacio de nombres de esta clase dentro de la estructura del proyecto.
namespace App\Constants\ConstDocumentos;
// Clase que define los posibles estados que puede tener un documento en el sistema.

class EstadoDocumentos
{
    // Estado cuando el documento ha sido cargado pero aún no ha sido revisado.
    public const PENDIENTE = 'pendiente';
    // Estado cuando el documento ha sido revisado y aceptado.
    public const APROBADO = 'aprobado';
    // Estado cuando el documento ha sido revisado pero rechazado.
    public const RECHAZADO  = 'rechazado';

    // Método que devuelve todos los estados posibles como un arreglo.
    // Esto permite acceder fácilmente a los valores definidos, por ejemplo, para validaciones o formularios.
    public static function all(): array
    {
        return [
            self::PENDIENTE,
            self::APROBADO,
            self::RECHAZADO
        ];
    }
}
