<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecimiento de Contraseña</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; padding: 20px;">
    <h1 style="color: #3498db;">UniDoc</h1>
    <h2 style="color: #2c3e50;">Hola, {{ $user->primer_nombre }}</h2>

    <p>Recibimos una solicitud para <strong>restablecer tu contraseña</strong>.</p>

    <p>
        <a href="{{ $resetLink }}" style="
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
        ">Haz clic aquí para restablecer tu contraseña</a>
    </p>

    <p style="margin-top: 20px;">O copia y pega este enlace en tu navegador:</p>
    <p><a href="{{ $resetLink }}" style="color: #3498db;">{{ $resetLink }}</a></p>

    <p style="margin-top: 30px;">Si no solicitaste este cambio, puedes ignorar este correo.</p>

    <p>Gracias,<br>El equipo de UniDoc</p>
</body>
</html>