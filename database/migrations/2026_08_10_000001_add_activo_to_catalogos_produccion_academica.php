<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la bandera `activo` a los catálogos de producción académica.
 *
 * Un catálogo que ya está referenciado por registros históricos no se puede borrar
 * (la FK lo impide y el CRUD responde 409). `activo = false` es la salida: retira la
 * opción de los desplegables sin tocar el histórico ni los puntajes ya calculados.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('producto_academicos', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('nombre_producto_academico');
        });

        Schema::table('ambito_divulgacions', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('nombre_ambito_divulgacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('producto_academicos', function (Blueprint $table) {
            $table->dropColumn('activo');
        });

        Schema::table('ambito_divulgacions', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
