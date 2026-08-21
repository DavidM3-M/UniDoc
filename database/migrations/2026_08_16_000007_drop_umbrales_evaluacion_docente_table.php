<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina la tabla del umbral de evaluación docente único y global. Reemplazado por
 * `escalones_docente.evaluacion_minima`: cada escalón exige la suya en vez de que todos
 * compartan un solo valor administrado aparte (ver migración
 * `2026_08_16_000006_add_evaluacion_minima_to_escalones_docente_table`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('umbrales_evaluacion_docente');
    }

    public function down(): void
    {
        Schema::create('umbrales_evaluacion_docente', function (Blueprint $table) {
            $table->smallIncrements('id_umbral_evaluacion');
            $table->decimal('valor_minimo', 3, 1);
            $table->date('vigencia_desde');
            $table->date('vigencia_hasta')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->string('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('creado_por')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('vigencia_hasta');
        });
    }
};
