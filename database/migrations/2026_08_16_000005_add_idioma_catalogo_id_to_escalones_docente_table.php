<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A qué idioma del catálogo (`catalogo_idiomas`) aplica el requisito `nivel_mcer_minimo` de un
 * escalón. Sin esto, "nivel B1" quedaba ambiguo: ¿de qué idioma? Van siempre juntos: un escalón
 * con `nivel_mcer_minimo` debe tener también `idioma_catalogo_id` (ver
 * ActualizarEscalonDocenteRequest/CrearEscalonDocenteRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escalones_docente', function (Blueprint $table) {
            $table->unsignedSmallInteger('idioma_catalogo_id')->nullable()->after('nivel_mcer_minimo');

            $table->foreign('idioma_catalogo_id')
                ->references('id_idioma_catalogo')->on('catalogo_idiomas');
        });
    }

    public function down(): void
    {
        Schema::table('escalones_docente', function (Blueprint $table) {
            $table->dropForeign(['idioma_catalogo_id']);
            $table->dropColumn('idioma_catalogo_id');
        });
    }
};
