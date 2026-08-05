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
        Schema::create('ambito_divulgacions', function (Blueprint $table) {
            $table->tinyIncrements('id_ambito_divulgacion');
            $table->unsignedTinyInteger('producto_academico_id');
            $table->string('nombre_ambito_divulgacion');
            $table->timestamps();
            $table->foreign('producto_academico_id')
                ->references('id_producto_academico')
                ->on('producto_academicos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ambito_divulgacions');
    }
};
