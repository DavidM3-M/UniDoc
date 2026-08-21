<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `intensidad_horaria` era `tinyInteger` (máximo 127) mientras `CrearExperienciaRequest` y
 * `ActualizarExperienciaRequest` aceptaban hasta 168 (las horas de una semana). Un valor entre
 * 128 y 168 pasaba la validación y desbordaba al insertar.
 *
 * Se amplía la columna en vez de bajar el request a 127: 168 es el tope correcto para horas
 * semanales y es el que ya validaba el backend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiencias', function (Blueprint $table) {
            $table->unsignedSmallInteger('intensidad_horaria')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('experiencias', function (Blueprint $table) {
            $table->tinyInteger('intensidad_horaria')->nullable()->change();
        });
    }
};
