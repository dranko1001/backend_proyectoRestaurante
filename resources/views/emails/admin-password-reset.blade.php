<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restablecer contraseña</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.5; color: #1c1917; background: #f5f5f4; margin: 0; padding: 24px;">
    <div style="max-width: 520px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; border: 1px solid #e7e5e4;">
        <p style="margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #78716c;">Administración</p>
        <h1 style="margin: 0 0 16px; font-size: 22px;">Restablece tu contraseña</h1>
        <p style="margin: 0 0 16px; font-size: 15px;">
            Recibimos una solicitud para restablecer la contraseña de tu cuenta de administrador
            @if($nombreComercial)
                en <strong>{{ $nombreComercial }}</strong>
            @endif
            .
        </p>
        <p style="margin: 0 0 24px;">
            <a href="{{ $resetUrl }}" style="display: inline-block; background: #c2410c; color: #fff; text-decoration: none; font-weight: 600; padding: 12px 20px; border-radius: 8px; font-size: 15px;">
                Crear nueva contraseña
            </a>
        </p>
        <p style="margin: 0 0 12px; font-size: 13px; color: #78716c; word-break: break-all;">
            Si el botón no funciona, copia este enlace:<br>
            <a href="{{ $resetUrl }}" style="color: #c2410c;">{{ $resetUrl }}</a>
        </p>
        <p style="margin: 0; font-size: 12px; color: #a8a29e;">
            El enlace caduca en {{ $expireMinutes }} minutos. Si no solicitaste esto, ignora este correo.
        </p>
    </div>
</body>
</html>
