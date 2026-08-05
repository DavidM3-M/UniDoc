<?php

use App\Constants\ConstRut\CodigoCiiu;
use App\Constants\ConstRut\TipoPersona;
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
        Schema::create('ruts', function (Blueprint $table) {
            $table->smallIncrements('id_rut');
            $table->unsignedBigInteger('user_id');
            $table->string('numero_rut')->unique();
            $table->string('razon_social');
            $table->string('tipo_persona');
            $table->string('codigo_ciiu');
            $table->string('responsabilidades_tributarias');
            $table->timestamps();
            // Relación con la tabla de usuarios
            $table->foreign('user_id')
                ->references('id')
                ->on('users'); // Eliminar ruts si se elimina el usuario
           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ruts');
    }
};
