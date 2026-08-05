<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // tabla para gestionar los paises
    public function up(): void
    {
        Schema::create('paises', function (Blueprint $table) {
            $table->smallIncrements('id_pais');
            $table->string('nombre')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    // elimina la tabla paises
    public function down(): void
    {
        Schema::dropIfExists('paises');
    }
};
