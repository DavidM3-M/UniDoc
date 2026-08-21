<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conecta `estudios.tipo_estudio` con el catálogo administrable `niveles_formacion_academica`
 * en vez de la constante fija `TiposEstudio`. `tipo_estudio` se mantiene como string (lo sigue
 * leyendo `MotorEscalafonDocenteService::tieneFormacionAprobada()` sin cambios): cuando se
 * selecciona un nivel real, el servidor autocompleta `tipo_estudio` desde
 * `niveles_formacion_academica.nivel_formacion` — mismo patrón que ya usa
 * `tipo_experiencias`/`Experiencia.tipo_experiencia`.
 *
 * Nullable y sin backfill a propósito: los estudios existentes (texto libre contra la constante
 * vieja) se quedan exactamente igual. Solo lo nuevo/editado puede usar el catálogo real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudios', function (Blueprint $table) {
            $table->unsignedSmallInteger('nivel_formacion_academica_id')->nullable()->after('tipo_estudio');

            $table->foreign('nivel_formacion_academica_id')
                ->references('id_nivel_formacion_academica')->on('niveles_formacion_academica')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('estudios', function (Blueprint $table) {
            $table->dropForeign(['nivel_formacion_academica_id']);
            $table->dropColumn('nivel_formacion_academica_id');
        });
    }
};
