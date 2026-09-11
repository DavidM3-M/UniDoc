<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conecta `estudios.institucion`/`titulo_estudio` con el catálogo de programas importado del
 * SNIES (`programas_formacion_educativa`), vía una selección en cascada Institución → Programa.
 *
 * `programas_formacion_educativa.id_programa` es un `increments()` normal (no smallint: la
 * tabla tiene ~32.000 filas por la importación masiva), así que la FK aquí es
 * `unsignedInteger`, no `unsignedSmallInteger` como el resto de catálogos de este módulo.
 *
 * Cuando se elige un programa real, el servidor autocompleta `institucion`, `titulo_estudio` y,
 * transitivamente (`programa->nivelFormacionAcademica`), también `tipo_estudio` y
 * `nivel_formacion_academica_id` (ver migración `2026_08_17_000001`) — un solo campo resuelve
 * los tres. No es obligatorio: si el programa del aspirante no está todavía en lo importado por
 * SNIES, el formulario sigue aceptando institución/título como texto libre (fallback), igual
 * que hace hoy `SelectInstitucion.tsx` con el catálogo del MEN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudios', function (Blueprint $table) {
            $table->unsignedInteger('programa_formacion_educativa_id')
                ->nullable()
                ->after('nivel_formacion_academica_id');

            $table->foreign('programa_formacion_educativa_id')
                ->references('id_programa')->on('programas_formacion_educativa')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('estudios', function (Blueprint $table) {
            $table->dropForeign(['programa_formacion_educativa_id']);
            $table->dropColumn('programa_formacion_educativa_id');
        });
    }
};
