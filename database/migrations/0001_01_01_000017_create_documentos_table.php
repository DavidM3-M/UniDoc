<?php

use App\Constants\ConstDocumentos\EstadoDocumentos;
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
        Schema::create('documentos', function (Blueprint $table) {
            $table->smallIncrements('id_documento');
            $table->string('archivo'); // Ruta del archivo
            $table->enum('estado', EstadoDocumentos::all())->default('pendiente'); // Estado del documento
            $table->morphs('documentable'); // Relación polimórfica
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
