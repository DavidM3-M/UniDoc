<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de tipos de experiencia profesional, administrable por el rol Administrador.
 *
 * Antes esta lista vivía en la constante PHP `App\Constants\ConstAgregarExperiencia\TiposExperiencia`,
 * lo que obligaba a editar código y desplegar para agregar un tipo.
 *
 * `experiencias.tipo_experiencia` y `convocatorias.tipo_experiencia_requerida` siguen guardando
 * el **nombre** como string (no se normaliza a FK, para no tocar exportaciones ni filtros).
 * Esta tabla es la fuente de verdad contra la que se valida ese string.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipo_experiencias', function (Blueprint $table) {
            $table->smallIncrements('id_tipo_experiencia');
            $table->string('nombre_tipo_experiencia', 100)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_experiencias');
    }
};
