<?php

namespace App\Constants;

/**
 * Límites de las claves primarias de la aplicación.
 *
 * Casi todas las tablas del expediente y de los catálogos declaran su clave primaria con
 * `smallIncrements` o `tinyIncrements`, que en PostgreSQL se traducen a `smallint`.
 *
 * Eso tiene una consecuencia poco intuitiva: cuando llega un ID mayor que el máximo de la
 * columna —típicamente un cliente probando con `999999`— PostgreSQL **no** responde "no hay
 * filas". Aborta la consulta con `SQLSTATE 22003` (numeric value out of range), la excepción
 * cae en el `catch (\Exception)` genérico de los controladores y la API devuelve un 500 donde
 * correspondía un 404. Lo mismo ocurre con un ID no numérico.
 *
 * Descartar esos IDs antes de consultar es correcto por definición: un valor que la columna no
 * puede almacenar tampoco puede identificar a ninguna fila existente.
 */
class ClavePrimaria
{
    /**
     * Mayor valor que admite una columna `smallint`.
     */
    public const SMALLINT_MAXIMO = 32767;

    /**
     * Indica si un ID no puede corresponder a ninguna fila por estar fuera del rango de la clave.
     *
     * @param mixed $id ID recibido en la ruta.
     */
    public static function fueraDeRango($id): bool
    {
        return !is_numeric($id) || $id < 1 || $id > self::SMALLINT_MAXIMO;
    }
}
