<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conecta los idiomas del aspirante/docente (`idiomas`, no confundir con el catálogo
 * `App\Models\Idioma`/`catalogo_idiomas`) con dos catálogos administrables:
 *
 * - `idioma_catalogo_id` → `catalogo_idiomas`: de qué idioma se trata.
 * - `examen_idioma_id` → `examenes_idioma`: qué examen/certificación lo acredita (IELTS,
 *   TOEFL, Cambridge FCE...). El campo `institucion_idioma` ya capturaba exactamente este dato
 *   como texto libre — no es un campo nuevo, solo se conecta a un catálogo real.
 *
 * `idioma` y `institucion_idioma` se mantienen como strings (`MotorEscalafonDocenteService`
 * sigue comparando por nombre de idioma sin cambios): cuando se elige del catálogo, el servidor
 * los autocompleta. Nullable y sin backfill: los idiomas existentes (texto libre) quedan igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idiomas', function (Blueprint $table) {
            $table->unsignedSmallInteger('idioma_catalogo_id')->nullable()->after('idioma');
            $table->unsignedSmallInteger('examen_idioma_id')->nullable()->after('institucion_idioma');

            $table->foreign('idioma_catalogo_id')
                ->references('id_idioma_catalogo')->on('catalogo_idiomas')
                ->nullOnDelete();

            $table->foreign('examen_idioma_id')
                ->references('id_examen_idioma')->on('examenes_idioma')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('idiomas', function (Blueprint $table) {
            $table->dropForeign(['idioma_catalogo_id']);
            $table->dropForeign(['examen_idioma_id']);
            $table->dropColumn(['idioma_catalogo_id', 'examen_idioma_id']);
        });
    }
};
