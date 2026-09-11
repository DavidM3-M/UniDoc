<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exámenes de certificación de idioma con puntaje numérico (IELTS, TOEFL iBT, Cambridge FCE...),
 * cada uno perteneciente a un idioma del catálogo.
 *
 * `vigencia_meses` nulo significa que el certificado no vence (ej. Cambridge); con valor, vence
 * esa cantidad de meses después de emitido (ej. IELTS = 24). Por ahora solo se captura el dato:
 * qué se hace con la vigencia del lado de Docente/Aspirante es una fase posterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examenes_idioma', function (Blueprint $table) {
            $table->smallIncrements('id_examen_idioma');
            $table->unsignedSmallInteger('idioma_catalogo_id');
            $table->string('nombre_examen', 150);
            $table->unsignedSmallInteger('vigencia_meses')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('idioma_catalogo_id')
                ->references('id_idioma_catalogo')->on('catalogo_idiomas');

            $table->unique(['idioma_catalogo_id', 'nombre_examen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examenes_idioma');
    }
};
