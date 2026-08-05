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
        Schema::create('certificaciones_bancarias', function (Blueprint $table) {
            $table->smallIncrements('id_certificacion_bancaria');
            $table->unsignedBigInteger('user_id');
            $table->string('nombre_banco');
            $table->string('tipo_cuenta');
            $table->string('numero_cuenta');
            $table->date('fecha_emision')->nullable();
            $table->timestamps();
            // Relación con la tabla de usuarios
            $table->foreign('user_id')
                ->references('id')
                ->on('users'); // Eliminar certificaciones bancarias si se elimina el usuario
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificaciones_bancarias');
    }
};
