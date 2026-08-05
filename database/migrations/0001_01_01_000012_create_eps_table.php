<?php

use App\Constants\ConstEps\EstadoAfiliacion;
use App\Constants\ConstEps\TipoAfiliacion;
use App\Constants\ConstEps\TipoAfiliado;
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
        Schema::create('eps', function (Blueprint $table) {
            $table->smallIncrements('id_eps');
            $table->unsignedBigInteger('user_id');
            $table->string('nombre_eps');
            $table->string('tipo_afiliacion');
            $table->string('estado_afiliacion');
            $table->date('fecha_afiliacion_efectiva');
            $table->date('fecha_finalizacion_afiliacion')->nullable();
            $table->string('tipo_afiliado');
            $table->string('numero_afiliado')->nullable();
            $table->timestamps();
            // Relación con la tabla de usuarios
            $table->foreign('user_id')
                ->references('id')
                ->on('users'); // Eliminar eps si se elimina el usuario
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eps');
    }
};
