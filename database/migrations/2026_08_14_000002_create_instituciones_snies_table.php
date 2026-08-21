<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instituciones de educación superior, provenientes del archivo de "Oferta y Programas" del
 * SNIES (importación masiva) o creadas a mano al agregar un programa manualmente.
 *
 * `codigo_institucion` es nullable: las instituciones creadas manualmente (sin pasar por el
 * importador) no tienen código SNIES, y se buscan/crean por nombre en ese caso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instituciones_snies', function (Blueprint $table) {
            $table->increments('id_institucion');
            $table->string('codigo_institucion', 20)->nullable()->unique();
            $table->string('codigo_institucion_padre', 20)->nullable();
            $table->string('nombre_institucion');
            $table->string('estado_institucion', 50)->nullable();
            $table->string('caracter_academico', 100)->nullable();
            $table->string('sector', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instituciones_snies');
    }
};
