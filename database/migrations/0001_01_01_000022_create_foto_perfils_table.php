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
        Schema::create('foto_perfils', function (Blueprint $table) {
            $table->smallIncrements('id_foto_perfil');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            
            // Relación con la tabla de usuarios
            $table->foreign('user_id')
            ->references('id')
            ->on('users'); // Eliminar contratacion si se elimina el usuario
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foto_perfils');
    }
};
