<?php

namespace App\Constants\ConstTalentoHumano;

/**
 * Tipos de proceso que puede representar una contratación.
 *
 * - Contratacion : primera vinculación; el usuario pasa de Aspirante a Docente.
 * - Ascenso      : el docente mejora funciones/categoría dentro de la institución.
 * - CambioCargo  : el docente cambia de área/cargo sin implicar ascenso jerárquico.
 */
class TipoProceso
{
    public const CONTRATACION  = 'Contratacion';
    public const ASCENSO       = 'Ascenso';
    public const CAMBIO_CARGO  = 'CambioCargo';

    public static function all(): array
    {
        return [
            self::CONTRATACION,
            self::ASCENSO,
            self::CAMBIO_CARGO,
        ];
    }
}
