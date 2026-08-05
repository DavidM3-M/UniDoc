<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convocatoria_avales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('convocatoria_id');
            $table->unsignedBigInteger('user_id');
            $table->string('aval');
            $table->string('estado')->default('pending'); // pending|aprobado|rechazado
            $table->unsignedBigInteger('aprobador_id')->nullable();
            $table->text('comentario')->nullable();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['convocatoria_id','user_id']);
            $table->unique(['convocatoria_id','user_id','aval']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocatoria_avales');
    }
};
