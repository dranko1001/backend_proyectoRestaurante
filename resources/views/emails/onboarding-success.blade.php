<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu restaurante está listo</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.5; color: #1c1917; background: #f5f5f4; margin: 0; padding: 24px;">
    <div style="max-width: 520px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; border: 1px solid #d1fae5;">
        <p style="margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #059669;">Activación completada</p>
        <h1 style="margin: 0 0 12px; font-size: 22px; color: #065f46;">¡Tu restaurante está listo!</h1>
        <p style="margin: 0 0 16px; font-size: 15px;">
            <strong>{{ $nombreComercial }}</strong> quedó activo. Guarda este correo: aquí tienes los accesos y los próximos pasos.
        </p>
        <p style="margin: 0 0 24px; font-size: 14px;">
            Sitio principal:<br>
            <a href="{{ $tenantUrl }}" style="color: #6d28d9; font-weight: 600; word-break: break-all;">{{ $tenantUrl }}</a>
        </p>

        <div style="background: #fafaf9; border: 1px solid #e7e5e4; border-radius: 10px; padding: 20px; margin-bottom: 24px;">
            <p style="margin: 0 0 16px; font-size: 14px; font-weight: 700;">Próximos pasos</p>

            <p style="margin: 0 0 12px; font-size: 14px;">
                <strong>1. Entrar al panel de administración</strong><br>
                <span style="font-size: 13px; color: #57534e;">
                    Correo: <strong>{{ $adminCorreo }}</strong>. Usa la contraseña que creaste en el onboarding.
                </span><br>
                <a href="{{ $adminLogin }}" style="font-size: 13px; color: #6d28d9;">{{ $adminLogin }}</a>
            </p>

            <p style="margin: 0 0 12px; font-size: 14px;">
                <strong>2. Configura tu carta</strong><br>
                <span style="font-size: 13px; color: #57534e;">
                    En Admin → Productos, crea categorías y platos que verán tus clientes.
                </span><br>
                <a href="{{ $adminProductosUrl }}" style="font-size: 13px; color: #6d28d9;">{{ $adminProductosUrl }}</a>
            </p>

            <p style="margin: 0 0 12px; font-size: 14px;">
                <strong>3. Crea mesas y personal</strong><br>
                <span style="font-size: 13px; color: #57534e;">
                    Registra mesas, meseros, cocineros y cajeros desde el panel.
                </span><br>
                <a href="{{ $staffUrl }}" style="font-size: 13px; color: #6d28d9;">{{ $staffUrl }}</a>
            </p>

            <p style="margin: 0; font-size: 14px;">
                <strong>4. Comparte el sitio público</strong><br>
                <span style="font-size: 13px; color: #57534e;">
                    Tus comensales pueden ver la carta y reservar desde aquí.
                </span><br>
                <a href="{{ $clienteUrl }}" style="font-size: 13px; color: #6d28d9;">{{ $clienteUrl }}</a>
            </p>
        </div>

        <p style="margin: 0; font-size: 12px; color: #a8a29e;">
            Si no solicitaste esta activación, contacta a quien te proporcionó el software.
        </p>
    </div>
</body>
</html>
