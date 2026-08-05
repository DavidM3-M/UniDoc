<?php
namespace App\Notifications;
// Define el namespace de la notificación, organizando el código y evitando conflictos de nombres.

use Illuminate\Bus\Queueable;
// Importa el trait `Queueable` para permitir que la notificación sea encolada.

use Illuminate\Notifications\Notification;
// Importa la clase base `Notification` de Laravel, que proporciona la funcionalidad principal para las notificaciones.

class NotificacionGeneral extends Notification
// Define la clase `NotificacionGeneral`, que extiende la funcionalidad de la clase base `Notification`.

{
    use Queueable;
    // Usa el trait `Queueable` para habilitar la encolación de esta notificación.

    protected $mensaje;
    // Declara una propiedad protegida `$mensaje` para almacenar el contenido del mensaje de la notificación.

    public function __construct($mensaje)
    // Constructor de la clase que recibe el mensaje como parámetro.
    {
        $this->mensaje = $mensaje;
        // Asigna el mensaje recibido al atributo `$mensaje` de la clase.

    }
    public function via($notifiable)
// Define los canales a través de los cuales se enviará la notificación.

    {
        return ['database'];
    // Especifica que la notificación solo se enviará a través del canal de base de datos.

    }

    public function toDatabase($notifiable)
        // Define cómo se estructurará la notificación cuando se almacene en la base de datos.

    {
        return [
            'mensaje' => $this->mensaje
                        // Retorna un arreglo con el mensaje que se almacenará en la base de datos.

        ];
    }
}
