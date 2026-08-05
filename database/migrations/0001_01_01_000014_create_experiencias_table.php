<?php

use App\Constants\ConstAgregarExperiencia\TiposExperiencia;
use App\Constants\ConstAgregarExperiencia\TrabajoActual;
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
        // Create the 'experiencias' table
        //queda pendiente lo de ver que cada docente tenga experiencia uniautonoma.
        //falta hacer relacion con la tabla de usuarios
        Schema::create('experiencias', function (Blueprint $table) {
            $table->smallIncrements('id_experiencia');
            $table->unsignedBigInteger('user_id');
            $table->string('tipo_experiencia');
            $table->string('institucion_experiencia');
            $table->string('cargo');
            $table->string('trabajo_actual')->nullable();
            $table->tinyInteger('intensidad_horaria')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_finalizacion')->nullable();
            $table->date('fecha_expedicion_certificado')->nullable();
            $table->timestamps();

            // Relación con la tabla de usuarios
            $table->foreign('user_id')
                ->references('id')
                ->on('users'); // Eliminar experiencias si se elimina el usuario
            
            $table->unique(['user_id','tipo_experiencia','institucion_experiencia','cargo','fecha_inicio'],'experiencias_unique_constraint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiencias');
    }
};
