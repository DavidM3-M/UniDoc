<?php

use App\Constants\ConstTalentoHumano\AreasContratacion;
use App\Constants\ConstTalentoHumano\TipoContratacion;
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
        Schema::create('contratacions', function (Blueprint $table) {
            $table->smallIncrements('id_contratacion');
            $table->unsignedBigInteger('user_id');

            // 1. Agregamos la columna para la relación (debe ser unsignedSmallInteger 
            // porque en convocatorias usaste smallIncrements)
            $table->unsignedSmallInteger('id_convocatoria');

            $table->string('tipo_contrato');
            $table->string('area');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->decimal('valor_contrato', 12, 0);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Relación con la tabla de usuarios
            $table->foreign('user_id')->references('id')->on('users');

            // 2. Definimos la relación con la tabla de convocatorias
            $table->foreign('id_convocatoria')
                ->references('id_convocatoria')
                ->on('convocatorias')
                ->onDelete('cascade'); // Si se borra la convocatoria, se borra el contrato (opcional)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contratacions');
    }
};
