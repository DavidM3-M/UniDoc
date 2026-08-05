<?php
// Importa las constantes relacionadas con el estado de las convocatorias
use App\Constants\ConstTalentoHumano\EstadoConvocatoria;
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
        // Crea la tabla 'convocatorias'
        Schema::create('convocatorias', function (Blueprint $table) {
            $table->smallIncrements('id_convocatoria');// Identificador único de la convocatoria (pequeño entero autoincremental)
            $table->string('nombre_convocatoria')->unique();// Nombre único de la convocatoria
            $table->string('tipo');// Tipo de convocatoria
            $table->date('fecha_publicacion');// Fecha de publicación de la convocatoria
            $table->date('fecha_cierre'); // Fecha de cierre de la convocatoria
            $table->text('descripcion');// Descripción detallada de la convocatoria
            $table->string('estado_convocatoria');
            $table->timestamps();// Crea las columnas 'created_at' y 'updated_at' para marcas de tiempo

            
        });

        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Elimina la tabla 'convocatorias' si existe
        Schema::dropIfExists('convocatorias');
    }
};
