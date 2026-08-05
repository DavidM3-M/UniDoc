<?php

namespace App\Constants\ConstUsuario;
//Esta clase define los diferentes géneros disponibles
class Genero
{
    // Género "Masculino"
    public const MASCULINO = 'Masculino';
    // Género "Femenino"
    public const FEMENINO = 'Femenino';
    // Género "Otro", para incluior géneros que no son masculinos ni femeninos
    public const OTRO = 'Otro';

    // Retorna todos los tipos de generos disponibles como un arreglo
    public static function all(): array
    {
        return [
            self::MASCULINO,
            self::FEMENINO,
            self::OTRO
        ];
    }
}
