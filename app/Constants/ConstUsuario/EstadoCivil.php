<?php

namespace App\Constants\ConstUsuario;
// Esta clase define los diferentes estados civiles que un usuario puede tener
class EstadoCivil
{
    // Estado civil "Soltero", cuando la persona no está casada ni tiene otra relación legal
    public const SOLTERO = 'Soltero';
    // Estado civil "Casado", cuando la persona está legalmente casada
    public const CASADO = 'Casado';
    // Estado civil "Divorciado", cuando la persona ha disuelto legalmente su matrimonio
    public const DIVORCIADO = 'Divorciado';
    // Estado civil "Viudo", cuando la persona ha quedado sin pareja debido al fallecimiento de su cónyuge
    public const VIUDO = 'Viudo';
    
    // Retorna todos los tipos de estados civiles
    public static function all(): array
    {
        return [
            self::SOLTERO,
            self::CASADO,
            self::DIVORCIADO,
            self::VIUDO
        ];
    }
}