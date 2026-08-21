<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca si una experiencia fue en la Universidad Autónoma, autodeclarada por quien la registra
 * y verificada por Apoyo Profesoral junto con el documento adjunto (mismo flujo de
 * aprobado/rechazado que ya existe para toda experiencia).
 *
 * Resuelve el pendiente que dejó un desarrollador anterior en
 * `0001_01_01_000014_create_experiencias_table`: "queda pendiente lo de ver que cada docente
 * tenga experiencia uniautonoma". `EscalafonDocenteService` usa esta bandera —no el
 * nombre libre de la institución— para sumar los meses de antigüedad exigidos por escalón, porque
 * coincidir por texto ("autónoma") sería frágil: el SNIES real tiene varias universidades
 * distintas con "Autónoma" en el nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiencias', function (Blueprint $table) {
            $table->boolean('es_uniautonoma')->default(false)->after('institucion_experiencia');
        });
    }

    public function down(): void
    {
        Schema::table('experiencias', function (Blueprint $table) {
            $table->dropColumn('es_uniautonoma');
        });
    }
};
