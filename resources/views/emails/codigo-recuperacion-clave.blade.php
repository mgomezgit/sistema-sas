{{-- Correo de plataforma: tablas + estilos inline, sin var(--x) ni hojas de
     estilo, igual que el resto de los correos. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Código para recuperar tu clave</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, 'Segoe UI', Helvetica, sans-serif; color:#333333;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f5; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5e5e5;">
                    <tr>
                        <td style="background-color:#1f1f23; padding:24px; text-align:center;">
                            <span style="font-size:20px; color:#ffffff; font-weight:bold;">Plataforma Reservas</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 28px 8px 28px;">
                            <h2 style="margin:0 0 12px 0; font-size:18px; color:#1f1f23;">Tu código para recuperar la clave</h2>
                            <p style="margin:0; font-size:15px; line-height:1.6; color:#555555;">
                                Escribe este código en la pantalla de recuperación, junto con tu clave nueva:
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px; text-align:center;">
                            <span style="display:inline-block; font-size:32px; font-weight:bold; letter-spacing:8px; color:#1f1f23; background-color:#fafafa; border:1px solid #eeeeee; border-radius:8px; padding:14px 22px;">{{ $codigo }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 8px 28px; text-align:center;">
                            <a href="{{ $urlRecuperacion }}" target="_blank" style="font-size:14px; color:#e11d2e; text-decoration:underline;">Ir a la pantalla de recuperación</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 28px 24px 28px;">
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#777777;">
                                El código vence en {{ $minutosVigencia }} minutos y sirve una sola vez. Al cambiar la clave se
                                cierran todas las sesiones abiertas de tu cuenta. Si no pediste este código, ignora este
                                correo: tu clave sigue igual.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#fafafa; padding:18px 28px; text-align:center; border-top:1px solid #eeeeee;">
                            <p style="margin:0; font-size:12px; color:#999999; line-height:1.5;">Este es un mensaje automático, no respondas a este correo.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
