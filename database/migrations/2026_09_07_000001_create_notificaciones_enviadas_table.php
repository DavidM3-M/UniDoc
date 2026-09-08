<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de las notificaciones que ya salieron, para no repetirlas y para que sus fallos dejen
 * de ser invisibles.
 *
 * Dos problemas comprobados por ejecución que esta tabla resuelve:
 *
 * 1. **Se repetían.** La condición de carrera de `AscensoEscalafonService::revertir()` —seis
 *    reversiones simultáneas del mismo tramo pasaban todas la guarda— disparaba una notificación
 *    por cada una. En el log quedaron los siete intentos de un único acto. El índice único sobre
 *    `clave` hace que el segundo intento no llegue a enviarse aunque el candado fallara.
 *
 * 2. **Fallaban en silencio.** Cada envío está envuelto en `try/catch` con `Log::error` y la
 *    ejecución continúa, que es lo correcto —un correo caído no puede tumbar un ascenso ya
 *    escrito— pero nadie mira ese log. Durante meses el SMTP no autenticó y ninguna de las 18
 *    notificaciones del sistema llegó a un buzón, sin que nada lo delatara. `intentos`,
 *    `enviado_en` y `ultimo_error` convierten eso en algo consultable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones_enviadas', function (Blueprint $table) {
            $table->bigIncrements('id_notificacion_enviada');

            /**
             * Identifica el hecho notificable, no el envío. Se construye con el tipo y la entidad:
             * `escalafon.ascenso:tramo:64`. Dos peticiones concurrentes sobre el mismo acto generan
             * la misma clave, y el índice único deja pasar solo a una.
             */
            $table->string('clave', 190)->unique();

            $table->string('tipo', 60);

            // Destinatario. Nullable porque hay avisos dirigidos a un rol completo —«45 elegibles
            // esperando» va a todos los de Apoyo Profesoral— donde la clave no depende de a quién.
            $table->unsignedBigInteger('user_id')->nullable();

            // Null mientras no haya salido. Es lo que distingue «pendiente» de «entregado».
            $table->timestamp('enviado_en')->nullable();

            $table->unsignedSmallInteger('intentos')->default(0);
            $table->text('ultimo_error')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            // Para la consulta que hace falta cuando alguien pregunta "¿por qué no me llegó?":
            // los envíos de un usuario, y los que quedaron sin salir.
            $table->index(['user_id', 'tipo']);
            $table->index('enviado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_enviadas');
    }
};
