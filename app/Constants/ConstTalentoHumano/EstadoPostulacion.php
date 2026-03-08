<?php

namespace App\Constants\ConstTalentoHumano;
// Esta clase define los posibles estados de una postulación en el proceso de contratación
class EstadoPostulacion
{
    // Estado cuando la postulación ha sido enviada pero aún no ha sido evaluada
    public const ENVIADA = 'Enviada';

    // Estado cuando la postulación requiere documentos adicionales
    public const FALTANDOCUMENTOS = 'Faltan documentos';

    // Estado cuando la postulación ha sido rechazada
    public const RECHAZADA = 'Rechazada';

    // Estado cuando la postulación ha sido aprobada
    public const APROBADA = 'Aprobada';

    // Retorna todos los estados de postulación disponibles como un arreglo
    public static function all(): array
    {
        return [
            self::ENVIADA,
            self::FALTANDOCUMENTOS,
            self::RECHAZADA,
            self::APROBADA,
        ];
    }
}