<?php

use App\Constants\ConstAgregarEstudio\Graduado;
use App\Constants\ConstAgregarEstudio\TiposEstudio;
use App\Constants\ConstAgregarEstudio\TituloConvalidado;
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
        //falta hacer relacion con la tabla de usuarios
        //falta hacer relacion con la tabla de municipios
        Schema::create('estudios', function (Blueprint $table) {
            $table->smallIncrements('id_estudio');
            $table->unsignedBigInteger('user_id');
            $table->string('tipo_estudio');
            $table->string('graduado');
            $table->string('institucion');
            $table->date('fecha_graduacion')->nullable();
            $table->string('titulo_convalidado');
            $table->date('fecha_convalidacion')->nullable();
            $table->string('resolucion_convalidacion')->nullable();
            $table->date('posible_fecha_graduacion')->nullable();
            $table->string('titulo_estudio');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamps();

            // Relación con la tabla de usuarios
            $table->foreign('user_id')
                ->references('id')
                ->on('users');
            
            $table->unique(['user_id', 'tipo_estudio', 'titulo_estudio', 'institucion'], 'estudios_unique_constraint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estudios');
    }
};
