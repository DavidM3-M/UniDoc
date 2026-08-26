<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién revisó cada documento y cuándo.
 *
 * `documentos.estado` cambiaba sin dejar rastro de su autor. En producción académica eso es
 * especialmente grave: `MotorEscalafonDocenteService::calcularPuntaje()` suma el puntaje del
 * ámbito de cada producción con documento aprobado, así que aprobar un documento otorga
 * categoría de escalafón, y hasta hoy nadie quedaba registrado como responsable de otorgarla.
 *
 * `documentos` es polimórfica, así que estas dos columnas dan trazabilidad también a estudios,
 * idiomas y experiencia: las pueblan tanto `VerificacionDocumentosController` (Apoyo Profesoral)
 * como `EvaluadorProduccionController`.
 *
 * Ambas nacen nulas y así se quedan en los registros históricos. Una decisión anterior a este
 * cambio se distingue precisamente por tener `revisado_por` en null aunque su estado no sea
 * `pendiente`, y la interfaz la etiqueta «aval anterior al cambio» en vez de inventar un autor.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->unsignedBigInteger('revisado_por')->nullable()->after('motivo_rechazo');

            // `updated_at` no sirve para esto: cualquier escritura posterior sobre el documento
            // lo mueve y se pierde la fecha real de la decisión.
            $table->timestamp('revisado_en')->nullable()->after('revisado_por');

            // nullOnDelete y no cascade: si se borra el usuario que revisó, el documento y su
            // estado deben sobrevivir; lo que se pierde es únicamente la atribución.
            $table->foreign('revisado_por')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign(['revisado_por']);
            $table->dropColumn(['revisado_por', 'revisado_en']);
        });
    }
};
