<?php
// Define el espacio de nombres donde se ubica esta clase constante
namespace App\Constants\ConstDocente;
// Esta clase contiene constantes relacionadas con el estado de evaluación docente
// y el estado de puntaje docente.

class EstadoPuntajeDocente
{
    // Constante que representa el estado de evaluación docente pendiente
    public const PENDIENTE = 'Pendiente';
    // Constante que representa el estado de evaluación docente aprobado
    public const APROBADO = 'Aprobado';
    // Constante que representa el estado de evaluación docente rechazado
    public const RECHAZADO  = 'Rechazado';

    // Método que retorna todos los estados definidos en forma de arreglo.
    // Es útil para desplegar opciones en formularios o validar datos.
    public static function all(): array
    {
        return [
            self::PENDIENTE,
            self::APROBADO,
            self::RECHAZADO
        ];
    }
}
