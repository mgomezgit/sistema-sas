@php
    // El mensaje se adapta al nuevo estado de la reserva.
    $titulos = [
        'confirmada' => 'Tu reserva fue confirmada',
        'cancelada' => 'Tu reserva fue cancelada',
        'completada' => '¡Gracias por tu visita!',
    ];

    $mensajes = [
        'confirmada' => 'Hola '.$reserva['nombre_cliente'].', te confirmamos que tu reserva quedó agendada. Aquí tienes los detalles:',
        'cancelada' => 'Hola '.$reserva['nombre_cliente'].', te informamos que tu reserva fue cancelada. Estos eran los datos de la cita:',
        'completada' => 'Hola '.$reserva['nombre_cliente'].', esperamos que hayas disfrutado tu visita. Este fue el servicio que recibiste:',
    ];

    $cierres = [
        'confirmada' => 'Si necesitas cambiar o cancelar tu cita, comunícate con nosotros con anticipación. ¡Te esperamos!',
        'cancelada' => 'Si quieres reagendar, contáctanos y con gusto buscamos un nuevo horario para ti.',
        'completada' => 'Nos encantaría verte de nuevo. ¡Gracias por confiar en nosotros!',
    ];

    $titulo = $titulos[$estadoReserva] ?? 'Actualización de tu reserva';
    $mensaje = $mensajes[$estadoReserva] ?? 'Hola '.$reserva['nombre_cliente'].', el estado de tu reserva cambió. Estos son los datos:';
    $cierre = $cierres[$estadoReserva] ?? 'Cualquier duda, comunícate con nosotros.';
    $esCancelada = $estadoReserva === 'cancelada';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Actualización de tu reserva</title>
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
                            <h2 style="margin:0 0 12px 0; font-size:18px; color:{{ $esCancelada ? '#b03a48' : '#1f1f23' }};">{{ $titulo }}</h2>
                            <p style="margin:0 0 18px 0; font-size:15px; line-height:1.6; color:#555555;">{{ $mensaje }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 8px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa; border:1px solid #eeeeee; border-radius:6px;">
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; width:40%;">Servicio</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold;{{ $esCancelada ? ' text-decoration:line-through;' : '' }}">{{ $reserva['nombre_recurso'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee;">Fecha</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee;{{ $esCancelada ? ' text-decoration:line-through;' : '' }}">{{ $reserva['fecha_reserva'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee;">Hora</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee;{{ $esCancelada ? ' text-decoration:line-through;' : '' }}">
                                        {{ substr($reserva['hora_inicio'], 0, 5) }} a {{ substr($reserva['hora_fin'], 0, 5) }}
                                    </td>
                                </tr>
                                @if (! empty($reserva['nombre_empleado']))
                                    <tr>
                                        <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee;">{{ $estadoReserva === 'completada' ? 'Te atendió' : 'Te atenderá' }}</td>
                                        <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee;">{{ $reserva['nombre_empleado'] }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 28px 28px;">
                            <p style="margin:0; font-size:15px; line-height:1.6; color:#555555;">{{ $cierre }}</p>
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
