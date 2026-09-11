<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programas académicos ("Formación educativa" — Fase 2 del catálogo de Formación académica).
 *
 * Se alimenta de dos vías: importación masiva del Excel del SNIES (`codigo_snies_programa`
 * presente, upsert por ese código) o alta manual desde el admin (`codigo_snies_programa` null).
 *
 * `nivel_formacion_academica_id` enlaza con la tabla `niveles_formacion_academica` construida
 * en la Fase 1 de este catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programas_formacion_educativa', function (Blueprint $table) {
            $table->increments('id_programa');
            $table->string('codigo_snies_programa', 30)->nullable()->unique();

            $table->unsignedInteger('institucion_id');
            $table->foreign('institucion_id')
                ->references('id_institucion')->on('instituciones_snies')
                ->onDelete('cascade');

            $table->unsignedSmallInteger('nivel_formacion_academica_id');
            $table->foreign('nivel_formacion_academica_id')
                ->references('id_nivel_formacion_academica')->on('niveles_formacion_academica')
                ->onDelete('restrict');

            $table->string('nombre_programa');
            $table->string('titulo_otorgado')->nullable();
            $table->string('estado_programa', 50)->default('Activo');
            $table->string('modalidad', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programas_formacion_educativa');
    }
};
