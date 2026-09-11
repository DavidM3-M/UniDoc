<?php

namespace App\Constants\ConstTalentoHumano;

class Aprobaciones
{
    public const COORDINADOR = 'Coordinador';
    public const TALENTO_HUMANO = 'Talento Humano';
    public const VICERRECTORIA = 'Vicerrectoría';
    public const RECTORIA = 'Rectoría';
    public const DECANATO = 'Decanato';

    public static function all(): array
    {
        return [
            self::COORDINADOR,
            self::TALENTO_HUMANO,
            self::VICERRECTORIA,
            self::RECTORIA,
            self::DECANATO,
        ];
    }

    public static function databaseKeys(): array
    {
        return [
            self::TALENTO_HUMANO => 'talento_humano',
            self::COORDINADOR => 'coordinador',
            self::VICERRECTORIA => 'vicerrectoria',
            self::RECTORIA => 'rectoria',
            self::DECANATO => 'decanato',
        ];
    }

    public static function toDatabaseKey(string $name): ?string
    {
        return self::databaseKeys()[$name] ?? null;
    }
}
