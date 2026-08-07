<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la auditoría de asignación a las evaluaciones docentes.
     *
     * La evaluación deja de ser una autoevaluación del docente y pasa a ser asignada
     * por el rol "Apoyo Profesoral", por lo que se necesita registrar quién la asignó
     * y en qué momento.
     *
     * Ambas columnas son nullable porque las evaluaciones ya existentes fueron
     * autoreportadas por el propio docente y no tienen un asignador.
     */
    public function up(): void
    {
        Schema::table('evaluacion_docentes', function (Blueprint $table) {
            $table->unsignedBigInteger('asignado_por')->nullable()->after('estado_evaluacion_docente');
            $table->timestamp('fecha_asignacion')->nullable()->after('asignado_por');

            $table->foreign('asignado_por')
                ->references('id')
                ->on('users')
                ->nullOnDelete(); // Si se elimina el usuario que asignó, la evaluación se conserva.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluacion_docentes', function (Blueprint $table) {
            $table->dropForeign(['asignado_por']);
            $table->dropColumn(['asignado_por', 'fecha_asignacion']);
        });
    }
};
