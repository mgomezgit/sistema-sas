{{-- Correo de plataforma: tablas + estilos inline. No repite ningún dato del
     formulario (nombre, negocio): quien lo llenó puede no ser el dueño del
     correo, y no debe poder hacerle llegar texto suyo. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Intento de registro con tu correo</title>
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
                            <h2 style="margin:0 0 12px 0; font-size:18px; color:#1f1f23;">Ya tienes una cuenta con este correo</h2>
                            <p style="margin:0 0 14px 0; font-size:15px; line-height:1.6; color:#555555;">
                                Alguien intentó crear una cuenta nueva con este correo, pero ya existe una cuenta activa
                                asociada a él. No se creó nada nuevo y tu cuenta sigue igual.
                            </p>
                            <p style="margin:0; font-size:15px; line-height:1.6; color:#555555;">
                                Si fuiste tú, puedes entrar directamente con tu cuenta de siempre. Si no fuiste tú, puedes
                                ignorar este correo.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px 8px 28px; text-align:center;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto;">
                                <tr>
                                    <td style="border-radius:6px; background-color:#e11d2e;">
                                        <a href="{{ $urlLogin }}" target="_blank" style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">Iniciar sesión</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 28px 24px 28px; text-align:center;">
                            @if (! empty($urlRecuperarClave))
                                <p style="margin:0 0 10px 0; font-size:13px; line-height:1.6; color:#777777;">¿No recuerdas tu clave?</p>
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto;">
                                    <tr>
                                        <td style="border-radius:6px; border:1px solid #e11d2e;">
                                            <a href="{{ $urlRecuperarClave }}" target="_blank" style="display:inline-block; padding:10px 24px; font-size:14px; font-weight:bold; color:#e11d2e; text-decoration:none;">Recuperar mi clave</a>
                                        </td>
                                    </tr>
                                </table>
                            @else
                                <p style="margin:0; font-size:13px; line-height:1.6; color:#777777;">
                                    ¿No recuerdas tu clave? Comunícate con soporte para restablecerla.
                                </p>
                            @endif
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
