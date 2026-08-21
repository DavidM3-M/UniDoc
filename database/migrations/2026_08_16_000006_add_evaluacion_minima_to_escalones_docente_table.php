<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluación docente mínima exigida por cada escalón. Reemplaza el umbral único y global que
 * administraba `UmbralEvaluacionController` (eliminado): cada escalón define la suya, en vez de
 * que todos compartan un solo valor.
 *
 * Se sigue comparando contra el mismo campo real que ya asigna Apoyo Profesoral
 * (`evaluacion_docentes.promedio_evaluacion_docente`) — lo único que cambia es contra qué umbral
 * se compara ese valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escalones_docente', function (Blueprint $table) {
            $table->decimal('evaluacion_minima', 3, 2)->nullable()->after('meses_minimos');
        });
    }

    public function down(): void
    {
        Schema::table('escalones_docente', function (Blueprint $table) {
            $table->dropColumn('evaluacion_minima');
        });
    }
};
