<?php

namespace App\Constants\ConstTalentoHumano;
// Esta clase define los tipos de contratación que pueden existir en el proceso de contratación
class TipoContratacion
{
    // Contratación de tipo "Planta", implica una relación laboral permanente
    public const PLANTA = 'Planta';
    // Contratación de tipo "Ocasional", generalmente temporal y por un periodo específico
    public const OCASIONAL = 'Ocasional';
    // Contratación de tipo "Cátedra", específica para profesores con un contrato por horas de clase
    public const CATEDRA = 'Cátedra';

    // Retorna todos los tipos de contratación disponibles como un arreglo
    public static function all(): array
    {
        return [
            self::PLANTA,
            self::OCASIONAL,
            self::CATEDRA
        ];
    }
}
