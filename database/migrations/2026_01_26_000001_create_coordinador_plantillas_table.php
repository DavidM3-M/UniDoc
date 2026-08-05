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
        Schema::create('coordinador_plantillas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->json('estructura');
            $table->unsignedBigInteger('creado_por');
            $table->timestamps();

            $table->foreign('creado_por')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coordinador_plantillas');
    }
};
