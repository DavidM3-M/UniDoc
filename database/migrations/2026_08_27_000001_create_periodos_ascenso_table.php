<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fechas de cierre de ascenso del escalafón docente, que fija Apoyo Profesoral.
 *
 * Los docentes NO se postulan y suben sus documentos cuando quieran: por eso no hay
 * `fecha_apertura`. Un periodo corre implícitamente desde el cierre del anterior, y lo único que
 * hace `fecha_cierre` es fijar el corte contra el que se congelan todos los requisitos de ascenso
 * (ver `MotorEscalafonDocenteService::evaluarAscenso()`). Lo que se sube después de un cierre
 * cuenta para el periodo siguiente.
 *
 * Que el corte sea una fecha y no "el día en que Apoyo Profesoral alcanzó a firmar" es lo que hace
 * que dos docentes con el mismo expediente reciban la misma respuesta.
 *
 * El nombre es a propósito distinto de `convocatorias`, que ya existe para contratación (Talento
 * Humano, con postulaciones): aquí no hay nada a lo que postularse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_ascenso', function (Blueprint $table) {
            $table->smallIncrements('id_periodo_ascenso');
            $table->string('nombre', 100);
            $table->date('fecha_cierre');

            // Cuándo se cerró de verdad. Un periodo puede cerrarse antes de su `fecha_cierre`
            // (cierre anticipado), pero el corte de los requisitos sigue siendo `fecha_cierre`:
            // adelantar el cierre administrativo no puede cambiar el expediente de nadie.
            $table->timestamp('cerrado_en')->nullable();

            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamps();

            // nullOnDelete: si se borra al funcionario, el periodo y sus ascensos sobreviven; lo
            // único que se pierde es la atribución. Mismo criterio que `documentos.revisado_por`.
            $table->foreign('creado_por')->references('id')->on('users')->nullOnDelete();

            $table->index('fecha_cierre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_ascenso');
    }
};
