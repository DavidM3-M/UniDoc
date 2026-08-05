<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // crea la tabla departamentos
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->smallIncrements('id_departamento');
            $table->string('nombre')->unique();
            $table->unsignedSmallInteger('pais_id');
            $table->timestamps();

            $table->foreign('pais_id')
                ->references('id_pais')
                ->on('paises');
        });
    }

    /**
     * Reverse the migrations.
     */
    // elimina la tabla departamentos
    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};
