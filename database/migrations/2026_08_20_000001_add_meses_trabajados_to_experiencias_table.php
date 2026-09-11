<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meses efectivamente trabajados, declarados por el docente y respaldados por el certificado.
 *
 * Hasta ahora los meses no se pedían: `MotorEscalafonDocenteService::calcularMesesUniautonoma()`
 * los deriva restando `fecha_inicio` de `fecha_finalizacion`. Eso vale para un contrato continuo
 * y falla para lo común en docencia — contratos por horas, semestres sueltos, vinculaciones con
 * interrupciones. Un certificado que dice "34 meses efectivos" entre 2020 y 2026 hoy se cuenta
 * como 68.
 *
 * El formulario prellena este campo con el cálculo por fechas y deja corregirlo; si el docente
 * pone otro número, se le avisa que el certificado tiene que respaldarlo. Apoyo Profesoral
 * contrasta el valor declarado contra el documento al aprobarlo.
 *
 * **El motor del escalafón NO cambia en esta migración**: sigue calculando por fechas. Pasar a
 * usar el valor declarado mueve docentes de escalón y es una decisión aparte.
 *
 * Nullable y sin backfill: los registros existentes no lo tienen y no hay con qué rellenarlos de
 * forma confiable. `unsignedSmallInteger` (hasta 65 535) cubre de sobra el máximo de 1200 meses
 * que valida el request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiencias', function (Blueprint $table) {
            $table->unsignedSmallInteger('meses_trabajados')->nullable()->after('intensidad_horaria');
        });
    }

    public function down(): void
    {
        Schema::table('experiencias', function (Blueprint $table) {
            $table->dropColumn('meses_trabajados');
        });
    }
};
