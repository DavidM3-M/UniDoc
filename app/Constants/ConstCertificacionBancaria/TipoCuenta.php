<?php

namespace App\Constants\ConstCertificacionBancaria;

class TipoCuenta
{
    public const AHORRO = 'Cuenta de Ahorros';
    public const CORRIENTE = 'Cuenta Corriente';

    public static function all(): array
    {
        return [
            self::AHORRO,
            self::CORRIENTE
        ];
    }
}

