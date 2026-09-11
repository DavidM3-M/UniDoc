<?php

namespace Database\Seeders;

use App\Models\Ubicacion\Municipio;

/**
 * Resuelve municipios por su código oficial, para que los seeders no dependan de un número.
 *
 * Antes once seeders escribían `'municipio_id' => 703` con el comentario «cambia este valor según
 * el municipio en tu DB». 703 era Popayán solo por el orden en que el CSV anterior cargaba las
 * filas: al regenerar el catálogo desde el archivo del DANE ese número pasó a ser otro municipio,
 * y el fallo habría sido silencioso —los usuarios sembrados habrían quedado en un pueblo al azar
 * sin que nada diera error—.
 *
 * El código DIVIPOLA sí es estable: `19001` es Popayán en el DANE y lo seguirá siendo.
 */
class Ubicaciones
{
    /** Popayán, Cauca. Sede de la institución y municipio por defecto de los usuarios sembrados. */
    public const POPAYAN = '19001';

    /** Cache en memoria: once seeders piden lo mismo dentro de la misma ejecución. */
    private static array $resueltos = [];

    /**
     * Devuelve el id interno del municipio con ese código DIVIPOLA.
     *
     * Si no aparece, devuelve el primer municipio que exista en vez de reventar: un seeder de
     * usuarios de demostración no debería tumbar toda la siembra porque falte una fila del
     * catálogo. Devuelve `null` solo si la tabla está vacía, y entonces el fallo será evidente.
     */
    public static function municipio(string $codigoDivipola = self::POPAYAN): ?int
    {
        if (array_key_exists($codigoDivipola, self::$resueltos)) {
            return self::$resueltos[$codigoDivipola];
        }

        $id = Municipio::where('codigo_divipola', $codigoDivipola)->value('id_municipio')
            ?? Municipio::orderBy('id_municipio')->value('id_municipio');

        return self::$resueltos[$codigoDivipola] = $id;
    }
}
