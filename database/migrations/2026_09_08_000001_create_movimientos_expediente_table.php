<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de lo que se mueve en el expediente de un docente, para poder avisarle de todo junto.
 *
 * Hoy el rechazo de un documento manda un correo inmediato que dice «uno de tus documentos ha sido
 * rechazado» —sin decir cuál— y la aprobación no manda nada. Con Apoyo Profesoral revisando el
 * expediente completo de un docente en una misma sesión, eso son cuatro correos idénticos y sin
 * dato útil en la misma tarde, y ninguna noticia de lo que sí salió bien.
 *
 * Cada revisión escribe aquí en vez de enviar. Un comando diario agrupa por docente y manda un solo
 * correo con todo: qué se aprobó, qué se rechazó y por qué. `notificado_en` marca lo ya incluido en
 * un resumen para que el del día siguiente no lo repita.
 *
 * Nada se pierde si el planificador se cae: los movimientos se acumulan y salen en la siguiente
 * ejecución. Lo que cambia es cuándo llega el aviso, no si llega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_expediente', function (Blueprint $table) {
            $table->bigIncrements('id_movimiento');

            $table->unsignedBigInteger('user_id');

            // `aprobado` | `rechazado`. El resumen los separa: lo que exige acción va primero.
            $table->string('accion', 20);

            // Qué se revisó, en palabras del docente: «Estudio», «Producción académica», «Idioma».
            $table->string('categoria', 60);

            // El registro concreto: «Maestría en Ingeniería de Sistemas». Es lo que hoy falta en el
            // correo de rechazo y obliga al docente a entrar a buscar cuál de sus documentos es.
            $table->string('descripcion', 255);

            $table->text('motivo')->nullable();

            // Quién lo revisó, por rol. No se guarda el id: el resumen no nombra a la persona.
            $table->string('revisado_por_rol', 60)->nullable();

            // Null mientras no se haya incluido en ningún resumen.
            $table->timestamp('notificado_en')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // La consulta del comando diario: los movimientos de cada docente sin notificar.
            $table->index(['user_id', 'notificado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_expediente');
    }
};
