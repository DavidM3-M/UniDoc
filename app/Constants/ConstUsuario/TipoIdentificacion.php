<?php

namespace App\Constants\ConstUsuario;
// Esta clase define los diferentes tipos de identificación que pueden tener los usuarios
// en el sistema. Cada constante representa un tipo específico de documento de identificación
class TipoIdentificacion
{
    // Tipos de identificacion
    public const CEDULA_DE_CIUDADANIA = 'Cédula de ciudadanía'; // Documento de identificación principal en Colombia
    public const CEDULA_DE_EXTRANJERIA = 'Cédula de extranjería'; // Documento para extranjeros residentes en Colombia
    public const NUMERO_UNICO_IDENTIFICACION_PERSONAL = 'Número único de identificación personal'; // Identificación para extranjeros sin cédula de extranjería
    public const PASAPORTE = 'Pasaporte'; // Documento de identificación internacional
    public const REGISTRO_CIVIL = 'Registro civil'; // Documento que certifica el nacimiento
    public const NUMERO_POR_SECRETARIA_DE_EDUCACION = 'Número por secretaría de educación'; // Número asignado a través de la secretaría de educación
    public const SERVICIO_NACIONAL_DE_PRUEBAS = 'Servicio nacional de pruebas'; // Documento relacionado con pruebas nacionales
    public const TARJETA_DE_IDENTIDAD = 'Tarjeta de identidad'; // Documento para menores de edad en Colombia
    public const TARJETA_PROFESIONAL = 'Tarjeta profesional'; // Documento que acredita la profesión

    // Retorna todos los tipos de identificacion
    public static function all(): array
    {
        return [
            self::CEDULA_DE_CIUDADANIA,
            self::CEDULA_DE_EXTRANJERIA,
            self::NUMERO_UNICO_IDENTIFICACION_PERSONAL,
            self::PASAPORTE,
            self::REGISTRO_CIVIL,
            self::NUMERO_POR_SECRETARIA_DE_EDUCACION,
            self::SERVICIO_NACIONAL_DE_PRUEBAS,
            self::TARJETA_DE_IDENTIDAD,
            self::TARJETA_PROFESIONAL
        ];
    }
}
