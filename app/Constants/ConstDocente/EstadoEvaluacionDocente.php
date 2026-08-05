<?php
// Se define el espacio de nombres para mantener organizada la estructura del proyecto.
namespace App\Constants\ConstDocente;
// Esta clase contiene constantes que representan los posibles estados de una evaluación docente.
class EstadoEvaluacionDocente
{ // Estado cuando la evaluación aún no ha sido revisada.
    public const PENDIENTE = 'Pendiente';
    // Estado cuando la evaluación ha sido revisada y aprobada.
    // Nota: Hay un pequeño error de ortografía en "APROVADO", debería ser "APROBADO".
    public const APROVADO = 'Aprobado';
    // Estado cuando la evaluación ha sido revisada y rechazada.
    public const RECHAZADO  = 'Rechazado';

    // Método que retorna todos los estados definidos en forma de arreglo.
    // Es útil para desplegar opciones en formularios o validar datos.
    public static function all(): array
    {
        return [
            self::PENDIENTE,
            self::APROVADO,
            self::RECHAZADO
        ];
    }
}
