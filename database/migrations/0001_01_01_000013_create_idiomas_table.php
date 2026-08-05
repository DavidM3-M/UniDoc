<?php

use App\Constants\ConstAgregarIdioma\NivelIdioma;
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
        Schema::create('idiomas', function (Blueprint $table) {
            $table->smallIncrements('id_idioma');
            $table->unsignedBigInteger('user_id');
            $table->string('idioma');
            $table->string('institucion_idioma');
            $table->date('fecha_certificado');
            $table->string('nivel');
            $table->timestamps();
            // Relación con la tabla de usuarios
            $table->foreign('user_id')
                ->references('id')
                ->on('users'); // Eliminar idiomas si se elimina el usuario
        
            $table->unique(['user_id','idioma','institucion_idioma','fecha_certificado'],'idiomas_unique_constraint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idiomas');
    }
};
