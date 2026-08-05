<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;
    // Usa los traits `Queueable` y `SerializesModels` para habilitar la encolación y la serialización de modelos.
    public $resetLink;
    // Propiedad pública para almacenar el enlace de restablecimiento de contraseña.
    public $user;
    // Propiedad pública para almacenar la información del usuario.

    public function __construct($user, $resetLink)
    // Constructor de la clase que recibe el usuario y el enlace de restablecimiento como parámetros.
    {
        $this->user = $user;
        // Asigna el usuario a la propiedad `$user`.
        $this->resetLink = $resetLink;
        // Asigna el enlace de restablecimiento a la propiedad `$resetLink`.
    }

    public function build()
    // Método que construye el correo electrónico.
    {
        return $this->subject('Restablecimiento de Contraseña')
            ->html("
            <div style='font-family: Arial, sans-serif; color: #333; padding: 20px;'>
                <h1 style='color: #3498db;'>UniDoc</h1>
                <h2 style='color: #2c3e50;'>Hola, {$this->user->primer_nombre}</h2>
                <p>Recibimos una solicitud para <strong>restablecer tu contraseña</strong>.</p>
                <p>
                    <a href='{$this->resetLink}' style='
                        background-color: #3498db;
                        color: white;
                        padding: 10px 20px;
                        text-decoration: none;
                        border-radius: 5px;
                        display: inline-block;
                    '>Haz clic aquí para restablecer tu contraseña</a>
                </p>
                <p style='margin-top: 20px;'>O copia y pega este enlace en tu navegador:</p>
                <p><a href='{$this->resetLink}' style='color: #3498db;'>{$this->resetLink}</a></p>
                <p style='margin-top: 30px;'>Si no solicitaste este cambio, puedes ignorar este correo.</p>
                <p>Gracias,<br>El equipo de UniDoc</p>
            </div>
        ");
    }
}
