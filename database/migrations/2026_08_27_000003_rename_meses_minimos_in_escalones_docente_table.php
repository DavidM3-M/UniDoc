<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `meses_minimos` -> `meses_minimos_escalon_anterior`.
 *
 * No es un cambio cosmético: el requisito cambia de significado por completo. Antes eran los meses
 * **totales** de experiencia en la Universidad Autónoma, acumulados desde el ingreso; el nuevo
 * reglamento exige meses **en el escalón inmediatamente anterior** (4 años como Auxiliar para
 * ascender a Asistente, 10 como Asistente para Asociado...). Los valores no cambian —48, 120, 216—
 * pero se leen contra otra cosa, así que el nombre viejo mentiría en la pantalla del Administrador
 * y en cualquier lectura futura del código.
 *
 * El dato contra el que se compara ya no sale de `MotorEscalafonDocenteService::calcularMesesUniautonoma()`
 * sino de `mesesEnEscalon()`, que cruza `historial_escalon_docente` con las experiencias
 * `es_uniautonoma` aprobadas.
 *
 * Rompe el contrato de `GET /constantes/escalones-docente`, que el frontend ya consume: el campo
 * cambia de nombre en la respuesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escalones_docente', function (Blueprint $table) {
            $table->renameColumn('meses_minimos', 'meses_minimos_escalon_anterior');
        });
    }

    public function down(): void
    {
        Schema::table('escalones_docente', function (Blueprint $table) {
            $table->renameColumn('meses_minimos_escalon_anterior', 'meses_minimos');
        });
    }
};
