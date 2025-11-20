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
        // Crea la tabla 'notifications' para almacenar notificaciones
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('id');// Identificador único de la notificación
            $table->string('type'); // Tipo de notificación (por ejemplo, clase o categoría)
            $table->morphs('notifiable');// Define columnas 'notifiable_id' y 'notifiable_type' para relaciones polimórficas
            $table->text('data'); // Datos de la notificación en formato JSON o texto
            $table->timestamp('read_at')->nullable(); // Marca de tiempo para cuando la notificación fue leída (puede ser nula)
            $table->timestamps();// Crea las columnas 'created_at' y 'updated_at' para marcas de tiempo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Elimina la tabla 'notifications' si existe
        Schema::dropIfExists('notifications');
    }
};
