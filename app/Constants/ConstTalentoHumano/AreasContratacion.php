<?php

namespace App\Constants\ConstTalentoHumano;
// Esta clase contiene las áreas o facultades disponibles para contratación
// en la institución. Cada constante representa una facultad distinta.
class AreasContratacion
{
    // Facultad de Ciencias Administrativas, Contables y Económicas
    public const FACULTAD_DE_CIENCIAS_ADMINISTRATIVAS_CONTABLES_Y_ECONOMICAS = 'Facultad de Ciencias Administrativas, Contables y Economicas';
    // Facultad de Ciencias Ambientales y Desarrollo Sostenible
    public const FACULTAD_DE_CIENCIAS_AMBIENTALES_Y_DESARROLLO_SOSTENIBLE = 'Facultad de Ciencias Ambientales y Desarrollo Sostenible';
    // Facultad de Derecho, Ciencias Sociales y Políticas
    public const FACULTAD_DE_DERECHO_CIENCIAS_SOCIALES_Y_POLITICAS  = 'Facultad de Derecho, Ciencias Sociales y Politicas';
    // Facultad de Educación
    public const FACULTAD_DE_EDUCACION = 'Facultad de Educacion';
    // Facultad de Ingeniería
    public const FACULTAD_DE_INGENIERIA = 'Facultad de Ingenieria';

    // Retorna todas las áreas disponibles como un arreglo
    public static function all(): array
    {
        return [
            self::FACULTAD_DE_CIENCIAS_ADMINISTRATIVAS_CONTABLES_Y_ECONOMICAS,
            self::FACULTAD_DE_CIENCIAS_AMBIENTALES_Y_DESARROLLO_SOSTENIBLE,
            self::FACULTAD_DE_DERECHO_CIENCIAS_SOCIALES_Y_POLITICAS,
            self::FACULTAD_DE_EDUCACION,
            self::FACULTAD_DE_INGENIERIA
        ];
    }
}
