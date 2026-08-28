<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * En qué escalón ha estado cada docente y desde cuándo. Es la fuente de verdad de la categoría
 * vigente y del tiempo acumulado en ella.
 *
 * Hasta ahora la categoría vivía en `puntajes.categoria_lograda`, una sola fila que el motor
 * reescribía en cada evaluación. Con eso era imposible responder "¿cuántos años lleva como
 * Auxiliar?", que es exactamente lo que exige el nuevo reglamento: 4 años **como Auxiliar** para
 * ascender a Asistente, 10 **como Asistente** para Asociado, etc. `categoria_otorgada_at` tampoco
 * servía: se movía en cada cambio y no guardaba los tramos anteriores.
 *
 * `desde` del periodo vigente es la fecha clave de todo el diseño:
 * - origen del conteo de antigüedad del escalón (`MotorEscalafonDocenteService::mesesEnEscalon()`),
 * - límite inferior de la ventana de producción académica que sí puntúa
 *   (`MotorEscalafonDocenteService::calcularPuntaje()`), que es lo que implementa "la producción no
 *   es acumulable entre escalones" sin tener que marcar ni consumir productos.
 *
 * Solo escribe aquí `AscensoEscalafonService`: el ascenso dejó de ser algo que el motor otorgaba
 * solo cuando el docente pedía su evaluación, y pasó a ser un acto de Apoyo Profesoral firmado en
 * `otorgado_por`. El tramo de ingreso es la excepción y no lleva firma: lo crea automáticamente
 * `ContratacionObserver` cuando el docente pasa a ser de planta.
 *
 * Los periodos de un mismo escalón **se acumulan** aunque haya interrupciones (retiro y reingreso),
 * y por eso esta tabla es una lista de tramos y no una columna en `users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_escalon_docente', function (Blueprint $table) {
            $table->bigIncrements('id_historial_escalon');
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('escalon_id');

            // Nulo en el acto de ingreso al escalafón, que no pasa por un periodo de ascenso.
            $table->unsignedSmallInteger('periodo_ascenso_id')->nullable();

            $table->date('desde');

            // Null = periodo vigente. Solo puede haber uno sin cerrar por docente, y eso lo
            // garantiza `AscensoEscalafonService` dentro de su transacción: como la condición es
            // "null y no revertido", no hay índice único que la exprese.
            $table->date('hasta')->nullable();

            // 'ingreso' | 'requisitos' | 'excepcion'. Vocabulario controlado por código, igual que
            // `reglas_excepcion_escalon.tipo_condicion`.
            $table->string('via', 20);
            $table->text('motivo')->nullable();

            // Null en el ingreso: no lo firma nadie, lo dispara la contratación de planta.
            $table->unsignedBigInteger('otorgado_por')->nullable();

            // Revertir NO borra la fila: la marca. Un ascenso mal otorgado tiene que seguir siendo
            // visible junto con quién lo revirtió y por qué; borrarlo dejaría el expediente
            // contando una historia que no ocurrió.
            $table->timestamp('revertido_en')->nullable();
            $table->unsignedBigInteger('revertido_por')->nullable();
            $table->text('motivo_reversion')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // restrictOnDelete y no cascade: borrar un escalón no puede llevarse por delante el
            // historial de quienes lo tuvieron. Para retirarlo de la evaluación está `activo`.
            $table->foreign('escalon_id')->references('id_escalon')->on('escalones_docente')->restrictOnDelete();

            $table->foreign('periodo_ascenso_id')->references('id_periodo_ascenso')->on('periodos_ascenso')->nullOnDelete();
            $table->foreign('otorgado_por')->references('id')->on('users')->nullOnDelete();
            $table->foreign('revertido_por')->references('id')->on('users')->nullOnDelete();

            $table->index(['user_id', 'desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_escalon_docente');
    }
};
