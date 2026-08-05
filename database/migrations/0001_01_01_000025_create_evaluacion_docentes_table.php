<?php
// Importa las constantes relacionadas con el estado de la evaluación docente
use App\Constants\ConstDocente\EstadoEvaluacionDocente;
// Importa las clases necesarias para la migración
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
        // Crea la tabla 'evaluacion_docentes'
        Schema::create('evaluacion_docentes', function (Blueprint $table) {
            $table->smallIncrements('id_evaluacion_docente');// Identificador único de la evaluación docente (pequeño entero autoincremental)
            $table->unsignedBigInteger('user_id');// ID del usuario asociado a la evaluación docente
            $table->decimal('promedio_evaluacion_docente', 3, 1); // Promedio de la evaluación docente con un máximo de 3 dígitos y 1 decimal
            $table->string('estado_evaluacion_docente')->default('Pendiente'); // Crea las columnas 'created_at' y 'updated_at' para marcas de tiempo
            $table->timestamps();
            // Relación con la tabla de usuarios
            $table->foreign('user_id')// Define una clave foránea para 'user_id'
            ->references('id')// Hace referencia a la columna 'id' de la tabla 'users'
            ->on('users'); // Especifica la tabla relacionada
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {        // Elimina la tabla 'evaluacion_docentes' si existe

        Schema::dropIfExists('evaluacion_docentes');
    }
};
