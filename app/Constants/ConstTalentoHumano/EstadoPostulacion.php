<?php

namespace App\Constants\ConstTalentoHumano;
// Esta clase define los posibles estados de una postulación en el proceso de contratación
class EstadoPostulacion
{
    // Estado cuando la postulación ha sido enviada pero aún no ha sido evaluada
    public const FALTANDOCUMENTOS = 'Faltan documentos';

    // Retorna todos los estados de postulación disponibles como un arreglo
    public static function all(): array
    {
        return [
            self::FALTANDOCUMENTOS
        ];
    }
}