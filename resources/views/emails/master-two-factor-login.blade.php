<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Código Master</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.5; color: #1c1917; background: #f5f5f4; margin: 0; padding: 24px;">
    <div style="max-width: 520px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; border: 1px solid #e7e5e4;">
        <p style="margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #78716c;">Plataforma Master</p>
        <h1 style="margin: 0 0 16px; font-size: 22px;">Tu código de verificación</h1>
        <p style="margin: 0 0 16px; font-size: 15px;">
            Hola {{ $userName }}, alguien intentó iniciar sesión en el panel Master con tu cuenta.
            Usa este código de 6 dígitos para completar el acceso:
        </p>
        <p style="margin: 0 0 24px; text-align: center;">
            <span style="display: inline-block; font-size: 32px; font-weight: 700; letter-spacing: 0.35em; padding: 16px 24px; border-radius: 12px; background: #f5f3ff; color: #5b21b6; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
                {{ $code }}
            </span>
        </p>
        <p style="margin: 0 0 12px; font-size: 13px; color: #78716c;">
            Es el mismo código que muestra tu app de autenticación (Google Authenticator, Authy, etc.).
            Caduca en unos {{ $validSeconds }} segundos; si expira, espera al siguiente o solicita reenvío en el login.
        </p>
        <p style="margin: 0; font-size: 12px; color: #a8a29e;">
            Si no fuiste tú, cambia tu contraseña Master y revisa la seguridad de tu correo.
        </p>
    </div>
</body>
</html>
