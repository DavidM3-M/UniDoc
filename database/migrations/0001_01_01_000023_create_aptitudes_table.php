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
        Schema::create('aptitudes', function (Blueprint $table) {
            $table->smallIncrements('id_aptitud');
            $table->unsignedBigInteger('user_id');
            $table->string('nombre_aptitud');
            $table->string('descripcion_aptitud');
            $table->timestamps();
            // Relación con la tabla de usuarios
            $table->foreign('user_id')
                ->references('id')
                ->on('users');

           //clave unica para evitar duplicados
           $table->unique(['user_id', 'nombre_aptitud'], 'aptitudes_unique_constraint');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aptitudes');
    }
};
