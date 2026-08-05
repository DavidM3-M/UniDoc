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
        Schema::create('arl', function (Blueprint $table) {
            $table->smallIncrements('id_arl');
            $table->unsignedBigInteger('user_id');
            $table->String('nombre_arl');
            $table->date('fecha_afiliacion');
            $table->date('fecha_retiro')->nullable();
            $table->string('estado_afiliacion');
            $table->unsignedBigInteger('clase_riesgo');
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
        Schema::dropIfExists('arl');
    }
};
