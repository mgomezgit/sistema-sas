<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recordatorio de tu reserva</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, Helvetica, sans-serif; color:#333333;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5e5e5;">

                    <tr>
                        <td style="background-color:#1f1f23; padding:24px; text-align:center;">
                            <h1 style="margin:0; font-size:20px; color:#ffffff; font-weight:bold;">{{ $nombreNegocio }}</h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 28px 8px 28px;">
                            <h2 style="margin:0 0 12px 0; font-size:18px; color:#1f1f23;">Te esperamos mañana</h2>
                            <p style="margin:0 0 18px 0; font-size:15px; line-height:1.6; color:#555555;">
                                Hola {{ $reserva['nombre_cliente'] }}, este es un recordatorio de la cita que tienes
                                agendada para mañana. Estos son los datos:
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 8px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa; border:1px solid #eeeeee; border-radius:6px;">
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; width:40%;">Servicio</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold;">{{ $reserva['nombre_recurso'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee;">Fecha</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee;">{{ $reserva['fecha_reserva'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee;">Hora</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee;">
                                        {{ substr($reserva['hora_inicio'], 0, 5) }} a {{ substr($reserva['hora_fin'], 0, 5) }}
                                    </td>
                                </tr>
                                @if (! empty($reserva['nombre_empleado']))
                                    <tr>
                                        <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee;">Te atenderá</td>
                                        <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee;">{{ $reserva['nombre_empleado'] }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 28px 28px;">
                            <p style="margin:0; font-size:15px; line-height:1.6; color:#555555;">
                                Si no puedes asistir, avísanos con tiempo para reagendar tu cita. ¡Nos vemos pronto!
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#fafafa; padding:18px 28px; text-align:center; border-top:1px solid #eeeeee;">
                            <p style="margin:0; font-size:12px; color:#999999; line-height:1.5;">
                                Este es un mensaje automático, no respondas a este correo.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
