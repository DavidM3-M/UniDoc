<?php
// Define el espacio de nombres donde se agrupan las constantes relacionadas con la información de contacto.
namespace App\Constants\ConstInformacionContacto;
// Tipos de constantes de informacion de contacto, contiene los diferentes tipos de seleccion que usamos en  informacion de contacto
// si desea agregar uno nuevo hagalo desde aqui, y se reflejara en la base de datos

class CategoriaLibretaMilitar
{
    // Libreta militar de primera clase, usualmente para quienes prestaron servicio militar obligatorio completo.
    public const PRIMERA_CLASE = 'Primera clase';
    // Libreta militar de segunda clase, generalmente otorgada por razones especiales (estudio, salud, etc.).
    public const SEGUNDA_CLASE = 'Segunda clase';
    // Opción para personas que no poseen libreta militar.
    public const NO_TIENE = 'No tiene';
    
    // Retorna todos los tipos de categorias de libreta militar
    public static function all(): array
    {
        return [
            self::PRIMERA_CLASE,
            self::SEGUNDA_CLASE,
            self::NO_TIENE
        ];
    }
}
