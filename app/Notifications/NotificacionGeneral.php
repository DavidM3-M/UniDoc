<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación interna: la que alimenta la campana del encabezado.
 *
 * Durante mucho tiempo guardó un único campo, `mensaje`. Eso bastaba mientras nadie la leyera
 * —ninguna pantalla consultaba `/notificaciones`—, pero deja la campana inservible: sin un título
 * no se puede escanear la lista de un vistazo, sin `tipo` todas las filas se ven iguales y sin
 * `enlace` el usuario lee "rechazaron tu documento" y no tiene a dónde ir.
 *
 * Los cuatro campos se escriben en `notifications.data`, que es JSON, así que ampliarlo no requiere
 * migración. Las 12 filas anteriores solo tienen `mensaje`; el controlador las devuelve con los
 * campos nuevos en null y la campana las pinta como genéricas.
 */
class NotificacionGeneral extends Notification
{
    use Queueable;

    public function __construct(
        protected string $mensaje,
        protected ?string $titulo = null,
        protected string $tipo = self::TIPO_GENERAL,
        protected ?string $enlace = null,
    ) {
    }

    // Agrupan la notificación por naturaleza, no por remitente: la campana los usa para elegir
    // icono y color, y son los mismos para los cuatro roles que la tienen.
    public const TIPO_GENERAL     = 'general';
    /** Algo salió adelante: aval otorgado, ascenso, contratación. */
    public const TIPO_EXITO       = 'exito';
    /** Algo se devolvió: documento o postulación rechazada, aval revertido. */
    public const TIPO_RECHAZO     = 'rechazo';
    /** Hay una fecha encima: cierre de periodo, cola pendiente. */
    public const TIPO_PLAZO       = 'plazo';
    /** Requiere que el usuario haga algo en la plataforma. */
    public const TIPO_ACCION      = 'accion';

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'mensaje' => $this->mensaje,
            'titulo'  => $this->titulo,
            'tipo'    => $this->tipo,
            'enlace'  => $this->enlace,
        ];
    }
}
