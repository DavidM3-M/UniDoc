<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalises role-name strings stored in JSON columns so they match the
 * Spatie role names used throughout the application (no accent marks).
 *
 * Before: 'Vicerrectoría', 'Rectoría'
 * After : 'Vicerrectoria', 'Rectoria'
 */
return new class extends Migration
{
    public function up(): void
    {
        // convocatorias.avales_establecidos  (JSON — cast a TEXT para LIKE en PostgreSQL)
        DB::statement("
            UPDATE convocatorias
            SET    avales_establecidos = REPLACE(avales_establecidos::text, 'Vicerrectoría', 'Vicerrectoria')::json
            WHERE  avales_establecidos::text LIKE '%Vicerrectoría%'
        ");
        DB::statement("
            UPDATE convocatorias
            SET    avales_establecidos = REPLACE(avales_establecidos::text, 'Rectoría', 'Rectoria')::json
            WHERE  avales_establecidos::text LIKE '%Rectoría%'
        ");

        // convocatoria_avales.aval  (plain string column)
        DB::statement("
            UPDATE convocatoria_avales
            SET    aval = 'Vicerrectoria'
            WHERE  aval = 'Vicerrectoría'
        ");
        DB::statement("
            UPDATE convocatoria_avales
            SET    aval = 'Rectoria'
            WHERE  aval = 'Rectoría'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE convocatorias
            SET    avales_establecidos = REPLACE(avales_establecidos::text, 'Vicerrectoria', 'Vicerrectoría')::json
            WHERE  avales_establecidos::text LIKE '%Vicerrectoria%'
        ");
        DB::statement("
            UPDATE convocatorias
            SET    avales_establecidos = REPLACE(avales_establecidos::text, 'Rectoria', 'Rectoría')::json
            WHERE  avales_establecidos::text LIKE '%Rectoria%'
        ");
        DB::statement("UPDATE convocatoria_avales SET aval = 'Vicerrectoría' WHERE aval = 'Vicerrectoria'");
        DB::statement("UPDATE convocatoria_avales SET aval = 'Rectoría'      WHERE aval = 'Rectoria'");
    }
};
