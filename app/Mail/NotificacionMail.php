<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $asunto;
    public string $mensaje;
    public string $nombreDestinatario;
    public array $detalles;

    /**
     * @param string $asunto              Asunto del correo.
     * @param string $mensaje             Cuerpo del mensaje.
     * @param string $nombreDestinatario  Nombre del destinatario para el saludo.
     * @param array  $detalles            Pares clave-valor con información adicional (ej. datos de la convocatoria).
     */
    public function __construct(string $asunto, string $mensaje, string $nombreDestinatario = '', array $detalles = [])
    {
        $this->asunto             = $asunto;
        $this->mensaje            = $mensaje;
        $this->nombreDestinatario = $nombreDestinatario;
        $this->detalles           = $detalles;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        $saludo = $this->nombreDestinatario
            ? "<h2 style='color: #2c3e50; margin-bottom: 6px;'>Hola, {$this->nombreDestinatario}</h2>"
            : '';

        $detallesHtml = '';
        if (!empty($this->detalles)) {
            $filas = '';
            foreach ($this->detalles as $clave => $valor) {
                $valorEsc = nl2br(htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'));
                $filas .= "<tr>
                    <td style='padding: 9px 14px; font-weight: 600; color: #555; width: 38%;
                               background-color: #f7f9fc; border-bottom: 1px solid #e8ecf0;
                               vertical-align: top;'>{$clave}</td>
                    <td style='padding: 9px 14px; color: #333; border-bottom: 1px solid #e8ecf0;
                               vertical-align: top;'>{$valorEsc}</td>
                </tr>";
            }
            $detallesHtml = "
            <h3 style='color: #2c3e50; margin-top: 28px; margin-bottom: 8px;'>Detalles</h3>
            <table style='width: 100%; border-collapse: collapse; font-size: 14px;
                          border: 1px solid #e8ecf0; border-radius: 4px;'>
                {$filas}
            </table>";
        }

        $html = "
        <div style='font-family: Arial, sans-serif; color: #333; padding: 24px; max-width: 620px;'>
            <h1 style='color: #3498db; margin-bottom: 4px;'>UniDoc</h1>
            <hr style='border: 1px solid #e0e0e0; margin-bottom: 20px;'>
            {$saludo}
            <p style='font-size: 15px; line-height: 1.6; margin-top: 8px;'>{$this->mensaje}</p>
            {$detallesHtml}
            <hr style='border: 1px solid #e0e0e0; margin-top: 30px;'>
            <p style='color: #999; font-size: 12px;'>
                Este es un correo automático, por favor no respondas a este mensaje.<br>
                El equipo de UniDoc
            </p>
        </div>";

        return new Content(htmlString: $html);
    }
}

