{{--
    Cuerpo compartido de los 3 correos que le llegan al CLIENTE sobre una
    reserva (confirmada, estado actualizado, recordatorio). Documento
    completo (doctype/head/body): cada vista de nivel superior solo calcula
    sus variables y hace @include de este archivo, para no repetir la franja
    superior, la tabla de detalle ni el pie tres veces.

    RESTRICCIÓN DE EMAIL — no tocar sin releer esto primero:
    Outlook de escritorio (el más estricto) no soporta CSS moderno: nada de
    flexbox, grid, ni var(--x). Todo el layout va en <table>/<tr>/<td> con
    estilos inline en cada elemento. Nunca una hoja de estilos aparte.

    Variables esperadas (todas ya resueltas por quien incluye este archivo):
    - $tituloDocumento    string  <title> del documento
    - $nombreNegocio      string  Nombre del negocio (dato del usuario: {{ }})
    - $colorAcento        string  Hex del acento, ya resuelto por ColorAcento::hex()
    - $colorAcentoSuave   string  Hex del tono suave, ColorAcento::hexSuave()
    - $icono              string  Un solo carácter (✓ / ✕ / !), fijo, no es dato de usuario
    - $titulo             string  Título del cuerpo (fijo, de un mapa en PHP)
    - $parrafo             string  Saludo ya armado (incluye nombre_cliente, se escapa aquí)
    - $reserva            array   nombre_recurso, fecha_reserva, hora_inicio, hora_fin, nombre_empleado
    - $tachado            bool    Tachar los datos de la tabla (reserva cancelada)
    - $etiquetaEmpleado   string  "Te atenderá" / "Te atendió"
    - $cierre             string  Párrafo de cierre antes del pie
    - $politicaCancelacion string|null  Si hay, sale en cursiva en el pie
    - $urlBoton           string|null  Si es null, el botón se omite (nunca un link roto)
    - $textoBoton         string  Texto del botón, si se muestra
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tituloDocumento }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, 'Segoe UI', Helvetica, sans-serif; color:#333333;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f5; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5e5e5;">

                    {{-- Franja superior: nombre del negocio en su color de acento --}}
                    <tr>
                        <td style="background-color:{{ $colorAcento }}; padding:26px 24px; text-align:center;">
                            <span style="font-size:20px; line-height:1.3; color:#ffffff; font-weight:bold; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">{{ $nombreNegocio }}</span>
                        </td>
                    </tr>

                    {{-- Icono de estado, en un círculo tenue del mismo acento --}}
                    <tr>
                        <td style="padding:28px 24px 0 24px; text-align:center;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto;">
                                <tr>
                                    <td width="56" height="56" style="width:56px; height:56px; border-radius:50%; background-color:{{ $colorAcentoSuave }}; text-align:center; vertical-align:middle; font-size:26px; line-height:56px; font-weight:bold; color:{{ $colorAcento }}; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">
                                        {{ $icono }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Título y párrafo de saludo --}}
                    <tr>
                        <td style="padding:16px 28px 8px 28px; text-align:center;">
                            <h2 style="margin:0 0 12px 0; font-size:18px; color:#1f1f23; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">{{ $titulo }}</h2>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 8px 28px;">
                            <p style="margin:0; font-size:15px; line-height:1.6; color:#555555; text-align:left; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">{{ $parrafo }}</p>
                        </td>
                    </tr>

                    {{-- Tabla de detalle, fondo gris suave --}}
                    <tr>
                        <td style="padding:18px 28px 8px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fafafa; border:1px solid #eeeeee; border-radius:6px;">
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; width:40%; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">Servicio</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;{{ $tachado ? ' text-decoration:line-through;' : '' }}">{{ $reserva['nombre_recurso'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">Fecha</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;{{ $tachado ? ' text-decoration:line-through;' : '' }}">{{ $reserva['fecha_reserva'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">Hora</td>
                                    <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;{{ $tachado ? ' text-decoration:line-through;' : '' }}">
                                        {{ substr($reserva['hora_inicio'], 0, 5) }} a {{ substr($reserva['hora_fin'], 0, 5) }}
                                    </td>
                                </tr>
                                @if (! empty($reserva['nombre_empleado']))
                                    <tr>
                                        <td style="padding:12px 16px; font-size:14px; color:#777777; border-top:1px solid #eeeeee; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">{{ $etiquetaEmpleado }}</td>
                                        <td style="padding:12px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-top:1px solid #eeeeee; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">{{ $reserva['nombre_empleado'] }}</td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    {{-- Párrafo de cierre --}}
                    <tr>
                        <td style="padding:18px 28px 8px 28px;">
                            <p style="margin:0; font-size:15px; line-height:1.6; color:#555555; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">{{ $cierre }}</p>
                        </td>
                    </tr>

                    {{-- Botón: enlaza a la página pública del negocio. Sin slug, se omite
                         entero (nunca un link roto) en vez de generar un href vacío. --}}
                    @if (! empty($urlBoton))
                        <tr>
                            <td style="padding:8px 28px 28px 28px; text-align:center;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto;">
                                    <tr>
                                        <td style="border-radius:6px; background-color:{{ $colorAcento }};">
                                            <a href="{{ $urlBoton }}" target="_blank" style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">{{ $textoBoton }}</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @else
                        <tr><td style="padding:0 28px 8px 28px;"></td></tr>
                    @endif

                    {{-- Pie: política de cancelación en cursiva (si el negocio la escribió) --}}
                    <tr>
                        <td style="background-color:#fafafa; padding:18px 28px; text-align:center; border-top:1px solid #eeeeee;">
                            @if (! empty($politicaCancelacion))
                                <p style="margin:0 0 10px 0; font-size:12px; color:#777777; font-style:italic; line-height:1.5; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">{{ $politicaCancelacion }}</p>
                            @endif
                            <p style="margin:0; font-size:12px; color:#999999; line-height:1.5; font-family: Arial, 'Segoe UI', Helvetica, sans-serif;">
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
