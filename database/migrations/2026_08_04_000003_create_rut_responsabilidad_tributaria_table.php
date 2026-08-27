<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rut_responsabilidad_tributaria', function (Blueprint $table) {
            $table->unsignedSmallInteger('rut_id');
            $table->unsignedBigInteger('responsabilidad_tributaria_id');

            $table->primary(['rut_id', 'responsabilidad_tributaria_id']);

            $table->foreign('rut_id')->references('id_rut')->on('ruts')->onDelete('cascade');
            $table->foreign('responsabilidad_tributaria_id')->references('id')->on('responsabilidades_tributarias')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rut_responsabilidad_tributaria');
    }
};
