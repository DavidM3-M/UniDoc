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
        // Crea la tabla 'personal_access_tokens' para almacenar tokens de acceso personal
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();// Identificador único del token
            $table->morphs('tokenable');// Define columnas 'tokenable_id' y 'tokenable_type' para relaciones polimórficas
            $table->string('name');// Nombre del token
            $table->string('token', 64)->unique(); // Token único de 64 caracteres
            $table->text('abilities')->nullable();// Lista de habilidades o permisos asociados al token
            $table->timestamp('last_used_at')->nullable();// Marca de tiempo de la última vez que se usó el token
            $table->timestamp('expires_at')->nullable();// Fecha de expiración del token
            $table->timestamps();// Crea las columnas 'created_at' y 'updated_at'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Elimina la tabla 'personal_access_tokens' si existe
        Schema::dropIfExists('personal_access_tokens');
    }
};
