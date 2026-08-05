<?php
// Importa constantes relacionadas con el estado del puntaje docente
use App\Constants\ConstDocente\EstadoPuntajeDocente;
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
        // Crea la tabla 'puntajes'
        Schema::create('puntajes', function (Blueprint $table) {
            $table->smallIncrements('id_puntaje');// Identificador único del puntaje (pequeño entero autoincremental)
            $table->unsignedBigInteger('user_id');// ID del usuario asociado al puntaje
            $table->integer(('puntaje_total'));// Puntaje total obtenido por el usuario
            $table->timestamps();// Crea las columnas 'created_at' y 'updated_at' para marcas de tiempo
            // Relación con la tabla de usuarios
            $table->foreign('user_id')// Define una clave foránea para 'user_id'
                ->references('id')// Hace referencia a la columna 'id' de la tabla 'users'
                ->on('users'); // Eliminar puntaje si se elimina el usuario
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Elimina la tabla 'puntajes' si existe
        Schema::dropIfExists('puntajes');
    }
};
