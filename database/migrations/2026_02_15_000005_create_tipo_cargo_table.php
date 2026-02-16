<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipo_cargo', function (Blueprint $table) {
            $table->increments('id_tipo_cargo');
            $table->string('nombre_tipo_cargo');
            $table->text('descripcion')->nullable();
            $table->boolean('es_administrativo')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_cargo');
    }
};
