<?php

namespace App\Constants\ConstRut;
// Esta clase define los tipos de personas según su naturaleza jurídica.
// Se utiliza generalmente en contextos donde se necesita diferenciar entre personas naturales y jurídicas.

class TipoPersona
{
    // Persona natural: un individuo con derechos y deberes.
    const NATURAL = 'Natural';
    // Persona jurídica: una entidad (empresa, fundación, etc.) con personalidad legal.
    const JURIDICA = 'Juridica';
    
    // Retorna todos los tipos de persona disponibles como un arreglo.
    public static function all(): array
    {
        return [
            self::NATURAL,
            self::JURIDICA,
        ];
    }
}
