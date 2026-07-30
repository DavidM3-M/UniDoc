<?php

namespace App\Constants\ConstTalentoHumano;

class Aprobaciones
{
    public const COORDINADOR = 'Coordinador';
    public const TALENTO_HUMANO = 'Talento Humano';
    public const VICERRECTORIA = 'Vicerrectoría';
    public const RECTORIA = 'Rectoría';

    public static function all(): array
    {
        return [
            self::COORDINADOR,
            self::TALENTO_HUMANO,
            self::VICERRECTORIA,
            self::RECTORIA,
        ];
    }

    public static function databaseKeys(): array
    {
        return [
            self::TALENTO_HUMANO => 'talento_humano',
            self::COORDINADOR => 'coordinador',
            self::VICERRECTORIA => 'vicerrectoria',
            self::RECTORIA => 'rectoria',
        ];
    }

    public static function toDatabaseKey(string $name): ?string
    {
        return self::databaseKeys()[$name] ?? null;
    }
}
