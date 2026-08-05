<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // crea la tabla municipios
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->smallIncrements('id_municipio');
            $table->string('nombre');
            $table->unsignedSmallInteger('departamento_id');
            $table->timestamps();

            $table->foreign('departamento_id')
                ->references('id_departamento')
                ->on('departamentos');
        });
    }

    /**
     * Reverse the migrations.
     */
    // elimina la tabla municipios
    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
