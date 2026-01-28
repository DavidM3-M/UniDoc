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
        Schema::create('coordinador_evaluaciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('aspirante_user_id');
            $table->unsignedBigInteger('coordinador_user_id');
            $table->unsignedBigInteger('plantilla_id')->nullable();
            $table->string('prueba_psicotecnica');
            $table->boolean('validacion_archivos')->default(false);
            $table->boolean('clase_organizada')->default(false);
            $table->json('formulario')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('aspirante_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('coordinador_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['aspirante_user_id', 'coordinador_user_id'], 'coord_eval_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coordinador_evaluaciones');
    }
};
