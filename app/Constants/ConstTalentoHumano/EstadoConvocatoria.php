<?php

namespace App\Constants\ConstTalentoHumano;
// Esta clase define los posibles estados de una convocatoria dentro del proceso de contratación
class EstadoConvocatoria
{
    // Estado cuando la convocatoria está abierta y se pueden recibir postulaciones
    public const ABIERTA = 'Abierta';
    // Estado cuando la convocatoria está cerrada y no se aceptan más postulaciones
    public const CERRADA = 'Cerrada';
    // Estado cuando la convocatoria ha finalizado, generalmente después de haber seleccionado un candidato
    public const FINALIZADA  = 'Finalizada';

    // Retorna todos los estados de la convocatoria disponibles como un arreglo
    public static function all(): array
    {
        return [
            self::ABIERTA,
            self::CERRADA,
            self::FINALIZADA
        ];
    }
}
