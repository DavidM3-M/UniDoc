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
        Schema::create('antecedentes_judiciales', function (Blueprint $table) {
            $table->smallIncrements('id_antecedente');
            $table->unsignedBigInteger('user_id');
            $table->date('fecha_validacion');
            $table->string('estado_antecedentes');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users'); // Eliminar pension si se elimina el usuario
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('antecedentes_judiciales');
    }
};
