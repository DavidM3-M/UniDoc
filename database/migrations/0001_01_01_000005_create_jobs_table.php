<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Retorna una nueva clase anónima que extiende de Migration
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Crea la tabla 'jobs' para almacenar trabajos en cola
        Schema::create('jobs', function (Blueprint $table) {
            $table->id(); // Identificador único de la tabla
            $table->string('queue')->index();// Nombre de la cola, indexado para mejorar el rendimiento
            $table->longText('payload'); // Datos del trabajo en cola
            $table->unsignedTinyInteger('attempts');// Número de intentos realizados para ejecutar el trabajo
            $table->unsignedInteger('reserved_at')->nullable(); // Marca de tiempo cuando el trabajo fue reservado
            $table->unsignedInteger('available_at');// Marca de tiempo cuando el trabajo estará disponible
            $table->unsignedInteger('created_at'); // Marca de tiempo cuando el trabajo fue creado
        });
        // Crea la tabla 'job_batches' para manejar lotes de trabajos
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();// Identificador único del lote
            $table->string('name');// Nombre del lote
            $table->integer('total_jobs'); // Número total de trabajos en el lote
            $table->integer('pending_jobs');// Número de trabajos pendientes
            $table->integer('failed_jobs'); // Número de trabajos fallidos
            $table->longText('failed_job_ids'); // IDs de los trabajos fallidos
            $table->mediumText('options')->nullable();// Opciones adicionales para el lote
            $table->integer('cancelled_at')->nullable(); // Marca de tiempo cuando el lote fue cancelado
            $table->integer('created_at'); // Marca de tiempo cuando el lote fue creado
            $table->integer('finished_at')->nullable(); // Marca de tiempo cuando el lote fue finalizado
        });
        // Crea la tabla 'failed_jobs' para almacenar trabajos fallidos
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();// Identificador único del trabajo fallido
            $table->string('uuid')->unique();// UUID único para identificar el trabajo fallido
            $table->text('connection');// Conexión utilizada para el trabajo
            $table->text('queue');// Cola donde estaba el trabajo
            $table->longText('payload');// Datos del trabajo fallido
            $table->longText('exception'); // Detalles de la excepción que causó el fallo
            $table->timestamp('failed_at')->useCurrent();// Marca de tiempo cuando el trabajo falló
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Elimina la tabla 'jobs' si existe
        Schema::dropIfExists('jobs');
        // Elimina la tabla 'job_batches' si existe
        Schema::dropIfExists('job_batches');
        // Elimina la tabla 'failed_jobs' si existe
        Schema::dropIfExists('failed_jobs');
    }
};
