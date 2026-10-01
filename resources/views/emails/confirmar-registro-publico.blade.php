{{-- Correo de plataforma (no de un negocio): tablas + estilos inline, sin
     var(--x) ni hojas de estilo, igual que el resto de los correos.
     nombreAdmin y nombreNegocio los escribió quien se registra: van con {{ }}. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirma tu correo</title>
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
                            <h2 style="margin:0 0 12px 0; font-size:18px; color:#1f1f23;">Confirma tu correo para crear tu cuenta</h2>
                            <p style="margin:0 0 14px 0; font-size:15px; line-height:1.6; color:#555555;">
                                Hola {{ $nombreAdmin }}, recibimos la solicitud para crear la cuenta de
                                <strong>{{ $nombreNegocio }}</strong>. Para terminar, confirma que este correo es tuyo.
                            </p>
                            <p style="margin:0; font-size:15px; line-height:1.6; color:#555555;">
                                Al confirmar se crea tu cuenta y entras directo a tu panel.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px; text-align:center;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto;">
                                <tr>
                                    <td style="border-radius:6px; background-color:#e11d2e;">
                                        <a href="{{ $urlConfirmacion }}" target="_blank" style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">Confirmar mi correo</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 24px 28px;">
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#777777;">
                                El enlace vence en {{ $horasVigencia }} horas y sirve una sola vez. Si no fuiste tú, ignora este
                                correo: sin confirmar, no se crea ninguna cuenta.
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
