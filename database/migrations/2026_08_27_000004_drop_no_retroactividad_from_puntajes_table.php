<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retira `umbral_aplicado` y `categoria_otorgada_at` de `puntajes`.
 *
 * Ambas columnas existían para la regla de no retroactividad de `EscalafonDocenteService`, que
 * protegía al docente cuando el Administrador subía la evaluación mínima de su escalón después de
 * habérsela otorgado. Esa regla deja de tener sentido: la categoría ya no la calcula el motor cada
 * vez que el docente pide su evaluación, sino que la otorga Apoyo Profesoral mediante un acto que
 * queda escrito en `historial_escalon_docente`. Una categoría otorgada por acto no se cae sola
 * cuando cambian los requisitos; para quitarla hay que revertir el acto, con firma y motivo.
 *
 * `categoria_otorgada_at` la sustituye `historial_escalon_docente.desde`, que además guarda los
 * tramos anteriores en vez de solo el último.
 *
 * `categoria_lograda` se queda: pasa a ser una caché denormalizada del escalón vigente, escrita
 * solo por `AscensoEscalafonService`, para que los listados no tengan que unir contra el historial
 * fila por fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puntajes', function (Blueprint $table) {
            $table->dropColumn(['umbral_aplicado', 'categoria_otorgada_at']);
        });
    }

    public function down(): void
    {
        Schema::table('puntajes', function (Blueprint $table) {
            $table->decimal('umbral_aplicado', 3, 1)->nullable()->after('categoria_lograda');
            $table->timestamp('categoria_otorgada_at')->nullable()->after('umbral_aplicado');
        });
    }
};
