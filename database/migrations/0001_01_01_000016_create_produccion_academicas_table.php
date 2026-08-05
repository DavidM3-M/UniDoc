<?php

use App\Constants\ConstAgregarProduccionAcademica\TiposProduccion;
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
        Schema::create('produccion_academicas', function (Blueprint $table) {
            $table->smallIncrements('id_produccion_academica');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('ambito_divulgacion_id');
            $table->string('titulo');
            $table->tinyInteger('numero_autores')->default(1);
            $table->string('medio_divulgacion');
            $table->date('fecha_divulgacion');
            $table->timestamps();

            // llave foranea
            $table->foreign('ambito_divulgacion_id')
                ->references('id_ambito_divulgacion')
                ->on('ambito_divulgacions');
            // Relación con la tabla de usuarios
            $table->foreign('user_id')
                ->references('id')
                ->on('users'); // Eliminar produccion_academica si se elimina el usuario

            // clave unica para evitar duplicados
            $table->unique(
                ['user_id', 'titulo', 'medio_divulgacion', 'fecha_divulgacion'],
                'produccion_academicas_unique_constraint'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produccion_academicas');
    }
};
