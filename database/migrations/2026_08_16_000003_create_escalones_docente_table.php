<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de escalones del escalafón docente (Auxiliar, Asistente, Asociado, Titular...),
 * administrable por el rol Administrador. Reemplaza la constante
 * `CalculoPuntajeDocenteService::CATEGORIAS`.
 *
 * Cada columna de requisito es nullable a propósito: un escalón sin requisito en ese campo no lo
 * exige (así queda modelado Auxiliar, la categoría base, sin ningún requisito propio).
 * `meses_minimos` en vez de años: evita que alguien con, por ejemplo, 3 años y 11 meses quede
 * excluido de un requisito de "4 años" por un solo mes de diferencia.
 *
 * Los casos como "tiene Doctorado → mínimo Asociado" NO viven aquí como requisito: son reglas de
 * excepción (`reglas_excepcion_escalon`), separadas a propósito para que el motor de evaluación
 * no necesite lógica especial por escalón.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalones_docente', function (Blueprint $table) {
            $table->smallIncrements('id_escalon');
            $table->string('nombre', 50)->unique();
            $table->smallInteger('orden');
            $table->string('formacion_minima', 100)->nullable();
            $table->string('nivel_mcer_minimo', 2)->nullable();
            $table->smallInteger('puntaje_minimo')->nullable();
            $table->smallInteger('meses_minimos')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalones_docente');
    }
};
