<?php
// Define el espacio de nombres para organizar mejor el código.
namespace App\Constants\ConstEps;
// Clase que contiene los distintos tipos de afiliados al sistema de salud.

class TipoAfiliado
{
    // Persona que realiza aportes directamente al sistema.
    public const COTIZANTE = 'Cotizante';
    // Persona que depende del cotizante (hijo, cónyuge, etc.).
    public const BENEFICIARIO = 'Beneficiario';
    // Persona registrada como cabeza del núcleo familiar.
    public const CABEZA_FAMILIA = 'Cabeza de familia';
    // Afiliado adicional vinculado al cotizante por afinidad o convenio.
    public const AFILIADO_ADICIONAL_O_ADHERENTE = 'Afiliado adicional o adherente';
    // Persona que recibe pensión y continúa afiliado al sistema.
    public const PENSIONADO = 'Pensionado';
    // Persona sin empleo que conserva o gestiona su afiliación.
    public const DESEMPLEADO = 'Desempleado';
    // Persona en condición de desplazamiento forzado.
    public const DESPLAZADO = 'Desplazado';
    // Persona afiliada al sistema de salud mediante un tratado internacional.
    public const AFILIADO_POR_CONVENIO_INTERNACIONAL = 'Afiliado por convenio internacional';
    
    // Método que devuelve todos los tipos de afiliado como un arreglo.
    public static function all(): array
    {
        return [
            self::COTIZANTE,
            self::BENEFICIARIO,
            self::CABEZA_FAMILIA,
            self::AFILIADO_ADICIONAL_O_ADHERENTE,
            self::PENSIONADO,
            self::DESEMPLEADO,
            self::DESPLAZADO,
            self::AFILIADO_POR_CONVENIO_INTERNACIONAL
        ];
    }

}