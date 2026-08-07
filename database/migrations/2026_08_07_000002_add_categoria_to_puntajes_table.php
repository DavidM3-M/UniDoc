<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persiste la categoría lograda por el docente y bajo qué umbral se le otorgó.
     *
     * Hasta ahora la categoría (Auxiliar/Asistente/Asociado/Titular) se calculaba al
     * vuelo y solo viajaba en el JSON de respuesta: la tabla `puntajes` únicamente
     * guardaba `puntaje_total`.
     *
     * Guardar `umbral_aplicado` es lo que permite la regla de no retroactividad: al
     * recalcular se re-evalúa al docente con el umbral que regía cuando obtuvo su
     * categoría, para distinguir una degradación causada por un cambio de umbral
     * (que no debe afectarlo) de una causada por perder un requisito real
     * (que sí debe bajarlo).
     */
    public function up(): void
    {
        Schema::table('puntajes', function (Blueprint $table) {
            $table->string('categoria_lograda')->nullable()->after('puntaje_total');
            // Umbral con el que se otorgó la categoría actual. Es el ancla de la regla
            // de no retroactividad: no se reescribe mientras la categoría esté protegida.
            $table->decimal('umbral_aplicado', 3, 1)->nullable()->after('categoria_lograda');
            $table->timestamp('categoria_otorgada_at')->nullable()->after('umbral_aplicado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('puntajes', function (Blueprint $table) {
            $table->dropColumn(['categoria_lograda', 'umbral_aplicado', 'categoria_otorgada_at']);
        });
    }
};
