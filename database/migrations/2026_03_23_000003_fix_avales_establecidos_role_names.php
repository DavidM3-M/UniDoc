<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalises role-name strings stored in JSON columns so they match the
 * Spatie role names used throughout the application (no accent marks).
 *
 * Before: 'Vicerrectoría', 'Rectoría'
 * After : 'Vicerrectoria', 'Rectoria'
 *
 * MariaDB no soporta CAST(... AS JSON) — se asigna el resultado de REPLACE
 * directamente (MariaDB almacena JSON como texto internamente).
 */
return new class extends Migration
{
    public function up(): void
    {
        // convocatorias.avales_establecidos (columna JSON)
        DB::statement("
            UPDATE convocatorias
            SET avales_establecidos = REPLACE(CAST(avales_establecidos AS CHAR), 'Vicerrectoría', 'Vicerrectoria')
            WHERE CAST(avales_establecidos AS CHAR) LIKE '%Vicerrectoría%'
        ");
        DB::statement("
            UPDATE convocatorias
            SET avales_establecidos = REPLACE(CAST(avales_establecidos AS CHAR), 'Rectoría', 'Rectoria')
            WHERE CAST(avales_establecidos AS CHAR) LIKE '%Rectoría%'
        ");

        // convocatoria_avales.aval (columna de texto plano)
        DB::statement("UPDATE convocatoria_avales SET aval = 'Vicerrectoria' WHERE aval = 'Vicerrectoría'");
        DB::statement("UPDATE convocatoria_avales SET aval = 'Rectoria' WHERE aval = 'Rectoría'");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE convocatorias
            SET avales_establecidos = REPLACE(CAST(avales_establecidos AS CHAR), 'Vicerrectoria', 'Vicerrectoría')
            WHERE CAST(avales_establecidos AS CHAR) LIKE '%Vicerrectoria%'
        ");
        DB::statement("
            UPDATE convocatorias
            SET avales_establecidos = REPLACE(CAST(avales_establecidos AS CHAR), 'Rectoria', 'Rectoría')
            WHERE CAST(avales_establecidos AS CHAR) LIKE '%Rectoria%'
        ");

        DB::statement("UPDATE convocatoria_avales SET aval = 'Vicerrectoría' WHERE aval = 'Vicerrectoria'");
        DB::statement("UPDATE convocatoria_avales SET aval = 'Rectoría' WHERE aval = 'Rectoria'");
    }
};