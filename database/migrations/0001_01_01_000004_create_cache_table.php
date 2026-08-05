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
        // Crea la tabla 'cache'
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();// Define la columna 'key' como clave primaria
            $table->mediumText('value'); // Define la columna 'value' para almacenar datos en formato texto
            $table->integer('expiration');// Define la columna 'expiration' para almacenar el tiempo de expiración
        });
        // Crea la tabla 'cache_locks'
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary(); // Define la columna 'key' como clave primaria
            $table->string('owner');// Define la columna 'owner' para almacenar el propietario del bloqueo
            $table->integer('expiration');// Define la columna 'expiration' para almacenar el tiempo de expiración del bloqueo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Elimina la tabla 'cache' si existe
        Schema::dropIfExists('cache');
        // Elimina la tabla 'cache_locks' si existe
        Schema::dropIfExists('cache_locks');
    }
};
