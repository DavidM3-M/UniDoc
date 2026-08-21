<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de idiomas administrable por el rol Administrador (ej. Inglés, Francés).
 *
 * Independiente por ahora: `idiomas.idioma` (el registro del aspirante/docente) sigue siendo
 * texto libre sin FK hacia aquí. Conectar ambos es una fase posterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_idiomas', function (Blueprint $table) {
            $table->smallIncrements('id_idioma_catalogo');
            $table->string('nombre_idioma', 100)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_idiomas');
    }
};
