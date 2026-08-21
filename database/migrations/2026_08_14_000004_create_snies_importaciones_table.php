<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de cada corrida del importador de programas SNIES: progreso mientras el Job en cola
 * procesa el archivo, e historial una vez termina. El frontend hace polling de una fila mientras
 * `estado` es `procesando`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snies_importaciones', function (Blueprint $table) {
            $table->increments('id_importacion');
            $table->string('nombre_archivo');
            $table->string('ruta_archivo');
            // pendiente -> procesando -> completado | fallido
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedInteger('total_filas')->nullable();
            $table->unsignedInteger('filas_procesadas')->default(0);
            $table->unsignedInteger('programas_creados')->default(0);
            $table->unsignedInteger('programas_actualizados')->default(0);
            $table->unsignedInteger('niveles_creados')->default(0);
            $table->text('mensaje_error')->nullable();
            $table->timestamp('iniciado_en')->nullable();
            $table->timestamp('finalizado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snies_importaciones');
    }
};
