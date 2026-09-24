<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva solicitud de cita</title>
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
                            <h2 style="margin:0 0 12px 0; font-size:18px; color:#1f1f23;">Tienes una nueva solicitud de cita</h2>
                            <p style="margin:0 0 18px 0; font-size:15px; line-height:1.6; color:#555555;">
                                Alguien pidió una cita desde tu página pública. Queda <strong>pendiente</strong> hasta que
                                la revises: el cliente todavía no ha recibido ninguna confirmación.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 8px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa; border:1px solid #eeeeee; border-radius:6px;">
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; width:40%;">Cliente</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold;">{{ $solicitud['nombre_cliente'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee;">Teléfono</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee;">{{ $solicitud['telefono_cliente'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee;">Servicio</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee;">{{ $solicitud['nombre_recurso'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee;">Fecha</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee;">{{ $solicitud['fecha_reserva'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee;">Horario</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee;">{{ $solicitud['hora_inicio'] }} - {{ $solicitud['hora_fin'] }}</td>
                                </tr>
                                @if (! empty($solicitud['notas']))
                                    <tr>
                                        <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee;">Notas</td>
                                        <td style="padding:12px 16px; font-size:14px; color:#1f1f23; border-top:1px solid #eeeeee;">{{ $solicitud['notas'] }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 28px 28px;">
                            <p style="margin:0; font-size:14px; line-height:1.6; color:#555555;">
                                Entra a tu calendario de reservas para asignarle un empleado y confirmarla.
                                Cuando cambies su estado, el cliente sí recibirá el aviso.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#fafafa; padding:16px 28px; text-align:center; border-top:1px solid #eeeeee;">
                            <p style="margin:0; font-size:12px; color:#999999;">
                                Este mensaje se generó automáticamente desde tu página pública.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
