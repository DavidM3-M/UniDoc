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
        // Crea la tabla 'normativas'
        Schema::create('normativas', function (Blueprint $table) {
            $table->smallIncrements('id_normativa');// Identificador único de la normativa (pequeño entero autoincremental)
            $table->string('nombre')->unique();// Nombre de la normativa, debe ser único
            $table->string('descripcion')->nullable();// Descripción de la normativa, puede ser nula
            $table->string('tipo')->nullable();// Tipo de normativa, puede ser nulo
            $table->timestamps(); // Crea las columnas 'created_at' y 'updated_at' para marcas de tiempo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Elimina la tabla 'normativas' si existe
        Schema::dropIfExists('normativas');
    }
};
