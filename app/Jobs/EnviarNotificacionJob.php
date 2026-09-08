<?php

namespace App\Jobs;

use App\Http\Controllers\TalentoHumano\NotificacionController;
use App\Models\NotificacionEnviada;
use App\Models\Usuario\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envía una notificación fuera de la transacción que la originó.
 *
 * Antes, `AscensoEscalafonService::ascender()` llamaba al envío **dentro** del
 * `DB::transaction()`, justo antes del `return`. Dos consecuencias:
 *
 * - Si la transacción se revertía después, el correo ya había salido: el docente recibía la
 *   noticia de un ascenso que nunca ocurrió.
 * - El envío es una operación de red. Mientras el servidor de correo tardaba, la transacción
 *   seguía abierta y la fila bloqueada.
 *
 * Se despacha con `->afterCommit()`, así que el trabajo solo entra a la cola si la transacción
 * confirma. Y corre en el worker, que ya vive dentro del contenedor del backend desde que
 * `docker-entrypoint.sh` lo arranca, así que no hace falta infraestructura nueva.
 *
 * No transporta modelos sino identificadores: un `User` serializado dentro del payload de la cola
 * se recarga con `SerializesModels`, pero un identificador es más barato y sobrevive a que el
 * registro cambie entre el despacho y la ejecución.
 */
class EnviarNotificacionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tres intentos porque el fallo típico del correo es transitorio —el servidor SMTP rechaza por
     * saturación, la red parpadea—. Un fallo permanente, como credenciales inválidas, agota los
     * tres y queda registrado en `notificaciones_enviadas.ultimo_error`.
     */
    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param string $clave  Identifica el hecho notificable, no el envío: `escalafon.ascenso:tramo:64`.
     * @param string $metodo Método estático de `NotificacionController` que hace el envío.
     * @param array  $args   Argumentos **sin** el destinatario: el job lo resuelve y lo antepone.
     */
    public function __construct(
        private readonly string $clave,
        private readonly string $tipo,
        private readonly string $metodo,
        private readonly array $args,
        private readonly ?int $destinatarioId = null,
    ) {
    }

    public function handle(): void
    {
        // Reservar antes de enviar. Si otro proceso ya reservó esta misma clave —el caso de las
        // reversiones concurrentes— aquí se sale sin tocar el servidor de correo.
        $registro = NotificacionEnviada::reservar($this->clave, $this->tipo, $this->destinatarioId);

        if (!$registro) {
            // La clave ya existía. Hay dos motivos posibles y no dan lo mismo:
            //
            // - Otro job concurrente la reservó. Este sobra: salir.
            // - Es **este mismo job reintentando** tras un fallo de envío. La reserva la escribió
            //   el intento anterior, así que hay que recuperarla y volver a intentar; si no, el
            //   reintento se descartaría en silencio y la notificación no saldría nunca.
            if ($this->attempts() <= 1) {
                Log::info("Notificación {$this->clave} descartada: otro proceso ya la tomó.");

                return;
            }

            $registro = NotificacionEnviada::where('clave', $this->clave)->first();

            if (!$registro || $registro->enviado_en !== null) {
                return;
            }
        }

        try {
            // Todos los métodos de notificación reciben el destinatario como primer argumento y
            // esperan el modelo, no su id. Se resuelve aquí y no en el despachador para que el
            // payload de la cola siga siendo un identificador: un `User` serializado envejece mal
            // —si el registro cambia entre el despacho y la ejecución, se enviaría el estado viejo—.
            $args = $this->args;

            if ($this->destinatarioId !== null) {
                $destinatario = User::find($this->destinatarioId);

                if (!$destinatario) {
                    // El usuario se borró entre el acto y el envío. No es un error que reintentar.
                    $registro->marcarFallida("El destinatario {$this->destinatarioId} ya no existe.");
                    Log::warning("Notificación {$this->clave} descartada: destinatario inexistente.");

                    return;
                }

                array_unshift($args, $destinatario);
            }

            NotificacionController::{$this->metodo}(...$args);
            $registro->marcarEnviada();
        } catch (\Throwable $e) {
            $registro->marcarFallida($e->getMessage());

            Log::error("Error al enviar la notificación {$this->clave}: " . $e->getMessage());

            // Se relanza para que la cola reintente. La reserva queda escrita con su contador, así
            // que el reintento la encuentra y no duplica el envío.
            throw $e;
        }
    }

    /**
     * Se ejecuta cuando se agotaron los tres intentos.
     *
     * No vuelve a escribir en `notificaciones_enviadas`: el último `marcarFallida()` ya dejó el
     * motivo. Esto solo asegura que el fallo definitivo aparezca en el log con su clave, para que
     * se pueda cruzar con la tabla.
     */
    public function failed(\Throwable $e): void
    {
        Log::error("La notificación {$this->clave} agotó sus reintentos: " . $e->getMessage());
    }
}
