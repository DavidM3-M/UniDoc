<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resincroniza las secuencias de los catálogos de producción académica.
 *
 * `ProductoAcademicoSeeder` inserta las filas del CSV con su `id_producto_academico` explícito.
 * En PostgreSQL eso **no** avanza la secuencia asociada a la columna, así que la secuencia
 * quedó muy por detrás del máximo real (por ejemplo en 12 con un máximo de 28).
 *
 * Mientras esos catálogos solo se sembraban daba igual. Ahora que el Administrador puede crear
 * registros por API, el primer INSERT sin ID explícito toma el siguiente valor de la secuencia y
 * choca contra una fila ya sembrada: "duplicate key value violates unique constraint".
 *
 * Esta migración pone cada secuencia justo después del máximo existente. Solo aplica a
 * PostgreSQL: MySQL sí avanza el AUTO_INCREMENT al insertar IDs explícitos.
 */
return new class extends Migration
{
    /**
     * Tablas afectadas y su columna de identidad.
     */
    private const CATALOGOS = [
        'producto_academicos' => 'id_producto_academico',
        'ambito_divulgacions' => 'id_ambito_divulgacion',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CATALOGOS as $tabla => $columna) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }

            // setval(..., max, false) deja la secuencia lista para entregar exactamente `max`
            // como próximo valor, así que se parte de max+1 para no repetir el último ID usado.
            DB::statement("
                SELECT setval(
                    pg_get_serial_sequence('{$tabla}', '{$columna}'),
                    (SELECT COALESCE(MAX({$columna}), 0) + 1 FROM {$tabla}),
                    false
                )
            ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * No se revierte: dejar la secuencia atrás a propósito solo reintroduciría el error de
     * clave duplicada.
     */
    public function down(): void
    {
        //
    }
};
