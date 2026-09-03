<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rastro inmutable de las intervenciones manuales del Administrador sobre el historial de escalafón.
 *
 * `historial_escalon_docente` sabe firmar los dos actos que ya existían: quién otorgó
 * (`otorgado_por`) y quién revirtió (`revertido_por`, `motivo_reversion`). Lo que no tiene es dónde
 * registrar una **edición**, y ese es justamente el acto que esta tanda de trabajo habilita. Un
 * tramo puede corregirse más de una vez —primero la fecha, meses después el escalón— así que no
 * basta con añadirle un par de columnas `corregido_por` / `corregido_en`: solo sobrevivirá la
 * última corrección y el expediente perderá las anteriores.
 *
 * Se calca el patrón de `contratacion_bitacoras` (ver `2026_05_03_000002`), que el proyecto ya usa
 * para el mismo problema —modificar a mano un registro con efectos legales— con sus mismas tres
 * decisiones: fila inmutable (solo `created_at`), snapshot JSON antes/después, y motivo declarado.
 *
 * Tres diferencias con aquella tabla, todas deliberadas:
 *
 * 1. **`docente_id` aparte.** `historial_escalon_docente.user_id` es `cascadeOnDelete`: al borrar un
 *    usuario desaparecen sus tramos y estas filas se quedarían con la FK en null y un snapshot
 *    huérfano que ya no se puede atribuir a nadie. Guardar el docente por separado también permite
 *    responder "todas las correcciones de este docente" sin pasar por el historial.
 * 2. **No hay `eliminacion`** en `tipo_modificacion`: de esta tabla no se borra nunca. Deshacer un
 *    ingreso manual equivocado es una reversión, que se firma en el propio tramo.
 * 3. **`motivo` es NOT NULL.** En contrataciones es opcional porque el alta no lo exige; aquí las
 *    dos operaciones son intervenciones manuales sobre el expediente de alguien y ninguna se acepta
 *    sin explicación.
 *
 * Los ascensos y las reversiones NO se duplican aquí: ya quedan firmados en el propio tramo y
 * `EscalafonDocenteController::verDocente()` los devuelve. Esta tabla cubre lo que hoy no deja rastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_escalon_bitacoras', function (Blueprint $table) {
            $table->bigIncrements('id_bitacora');

            // nullOnDelete y no cascade: si el tramo desaparece, el rastro de que se tocó a mano
            // tiene que sobrevivir. El snapshot sigue diciendo qué había.
            $table->unsignedBigInteger('historial_escalon_id')->nullable();

            // Ver la nota 1 del docblock: redundante con el tramo a propósito.
            $table->unsignedBigInteger('docente_id')->nullable();

            $table->unsignedBigInteger('user_modifico_id')->nullable();

            $table->string('tipo_modificacion', 20)
                ->comment('creacion | actualizacion');

            // Null en la creación: no hay estado anterior que retratar.
            $table->jsonb('datos_anteriores')->nullable();
            $table->jsonb('datos_nuevos')->nullable();

            $table->text('motivo');

            // Registro inmutable: solo created_at, nunca se actualiza.
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('historial_escalon_id')
                ->references('id_historial_escalon')
                ->on('historial_escalon_docente')
                ->nullOnDelete();

            $table->foreign('docente_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('user_modifico_id')->references('id')->on('users')->nullOnDelete();
        });

        // Las tres consultas que la pantalla hace: por tramo, por docente y el listado cronológico.
        DB::statement('CREATE INDEX idx_hist_escalon_bitacora_tramo ON historial_escalon_bitacoras (historial_escalon_id)');
        DB::statement('CREATE INDEX idx_hist_escalon_bitacora_docente ON historial_escalon_bitacoras (docente_id)');
        DB::statement('CREATE INDEX idx_hist_escalon_bitacora_fecha ON historial_escalon_bitacoras (created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_escalon_bitacoras');
    }
};
