<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de niveles de formación académica, administrable por el rol Administrador.
 *
 * A propósito NO valida contra una lista fija (SNIES define Pregrado/Posgrado y sus niveles de
 * formación, pero el Administrador pidió texto libre en ambos campos). Es un catálogo
 * independiente por ahora: nada lo referencia todavía, ni `estudios.tipo_estudio` (que sigue
 * usando la constante legacy `TiposEstudio`) ni el motor de puntaje. Fase 2 (fuera de esta
 * migración) sería un catálogo de programas ("Formación educativa") con FK hacia esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveles_formacion_academica', function (Blueprint $table) {
            $table->smallIncrements('id_nivel_formacion_academica');
            $table->string('nivel_academico', 100);
            $table->string('nivel_formacion', 100);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['nivel_academico', 'nivel_formacion'], 'niveles_formacion_academica_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveles_formacion_academica');
    }
};
