<?php

namespace App\Constants\ConstTalentoHumano;

/**
 * Tipo de vinculación que determina el rol asignado al usuario
 * en su primera contratación (tipo_proceso = 'Contratacion').
 *
 * - Docente       : el usuario pasa a tener el rol Docente.
 * - Administrativo: el usuario pasa a tener el rol Administrativo.
 */
class TipoVinculacion
{
    public const DOCENTE        = 'Docente';
    public const ADMINISTRATIVO = 'Administrativo';

    public static function all(): array
    {
        return [
            self::DOCENTE,
            self::ADMINISTRATIVO,
        ];
    }
}
