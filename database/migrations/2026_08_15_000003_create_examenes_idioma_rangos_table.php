<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rangos de puntaje de cada examen y su nivel MCER equivalente (ej. IELTS 5.5–6.5 = B2).
 *
 * Tabla de equivalencia que el Administrador define y mantiene por examen. Se borran en cascada
 * con el examen: un rango no tiene sentido sin él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examenes_idioma_rangos', function (Blueprint $table) {
            $table->smallIncrements('id_rango_examen_idioma');
            $table->unsignedSmallInteger('examen_idioma_id');
            $table->decimal('puntaje_min', 6, 2);
            $table->decimal('puntaje_max', 6, 2);
            $table->string('nivel_mcer', 2);
            $table->timestamps();

            $table->foreign('examen_idioma_id')
                ->references('id_examen_idioma')->on('examenes_idioma')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examenes_idioma_rangos');
    }
};
