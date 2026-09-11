<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Umbral mínimo de evaluación docente exigido para ascender de categoría.
     *
     * Hasta ahora el valor (4.0) estaba hardcodeado en CalculoPuntajeDocenteService.
     * Se historiza en vez de sobrescribirse: cada cambio cierra el registro anterior
     * con `vigencia_hasta` y crea uno nuevo, de modo que siempre se pueda saber qué
     * umbral regía cuando se le otorgó una categoría a un docente.
     */
    public function up(): void
    {
        Schema::create('umbrales_evaluacion_docente', function (Blueprint $table) {
            $table->smallIncrements('id_umbral_evaluacion');
            $table->decimal('valor_minimo', 3, 1); // Mismo formato que evaluacion_docentes.promedio_evaluacion_docente
            $table->date('vigencia_desde');
            $table->date('vigencia_hasta')->nullable(); // NULL = registro vigente
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->string('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('creado_por')
                ->references('id')
                ->on('users')
                ->nullOnDelete(); // El histórico sobrevive a la baja del administrador.

            $table->index('vigencia_hasta'); // Se consulta el vigente (vigencia_hasta IS NULL) en cada cálculo.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('umbrales_evaluacion_docente');
    }
};
