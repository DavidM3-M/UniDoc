<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas de excepción del escalafón docente: si un docente cumple la condición, queda como
 * mínimo en `escalon_otorgado_id`, sin importar si cumple el resto de requisitos de ese escalón
 * o de los intermedios (ver `MotorEscalafonDocenteService`).
 *
 * `tipo_condicion` es un vocabulario controlado por código (hoy solo entiende 'formacion': el
 * docente tiene un estudio aprobado de `valor_condicion`, ej. 'Doctorado'). Agregar una regla
 * nueva con un tipo ya soportado es solo una fila; enseñarle al motor un tipo de condición nuevo
 * sí requiere código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas_excepcion_escalon', function (Blueprint $table) {
            $table->smallIncrements('id_regla_excepcion');
            $table->string('tipo_condicion', 50);
            $table->string('valor_condicion', 100);
            $table->unsignedSmallInteger('escalon_otorgado_id');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('escalon_otorgado_id')
                ->references('id_escalon')->on('escalones_docente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_excepcion_escalon');
    }
};
