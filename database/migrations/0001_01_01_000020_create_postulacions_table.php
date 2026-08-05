<?php

use App\Constants\ConstTalentoHumano\EstadoPostulacion;
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
        Schema::create('postulacions', function (Blueprint $table) {
            $table->smallIncrements('id_postulacion');
            $table->unsignedSmallInteger('convocatoria_id');
            $table->unsignedBigInteger('user_id');
            $table->string('estado_postulacion');
            $table->timestamps();

            // Relación con la tabla de convocatorias
            $table->foreign('convocatoria_id')
                ->references('id_convocatoria')
                ->on('convocatorias'); // Eliminar postulacion si se elimina la convocatoria

            // Relación con la tabla de usuarios
            $table->foreign('user_id')
                ->references('id')
                ->on('users'); // Eliminar postulacion si se elimina el usuario

           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postulacions');
    }
};
