<?php

namespace App\Constants\ConstPension;

class RegimenPensional
{
    public const REGIMEN_1 = 'Régimen de Prima Media (RPM)';
    public const REGIMEN_2 = 'Régimen de Ahorro Individual con Solidaridad (RAIS)';

    public static function all(): array
    {
        return [
            self::REGIMEN_1,
            self::REGIMEN_2
        ];
    }
}
