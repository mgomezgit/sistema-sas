<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de pago {{ $pago['consecutivo'] }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, Helvetica, sans-serif; color:#333333;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5e5e5;">

                    <tr>
                        <td style="background-color:{{ $acentoHex }}; padding:24px; text-align:center;">
                            <h1 style="margin:0; font-size:20px; color:#ffffff; font-weight:bold;">Comprobante de pago</h1>
                            <p style="margin:6px 0 0 0; font-size:14px; color:#ffffff;">{{ $pago['consecutivo'] }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 28px 8px 28px;">
                            <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6; color:#555555;">
                                {{ $nombrePlataforma }} recibió tu pago. Este es tu comprobante:
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 8px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa; border:1px solid #eeeeee; border-radius:6px;">
                                @foreach ([
                                    'Comprobante' => $pago['consecutivo'],
                                    'Fecha' => $pago['fecha'],
                                    'Negocio' => $pago['nombre_negocio'],
                                    'Concepto' => $pago['concepto'],
                                    'Periodo' => $pago['periodo'],
                                    'Monto' => $pago['monto_formato'],
                                    'Metodo' => $pago['metodo'],
                                    'Referencia' => $pago['referencia'] ?: 'Sin referencia',
                                ] as $etiqueta => $valor)
                                    <tr>
                                        <td style="padding:10px 16px; font-size:13px; color:#777777; border-bottom:1px solid #eeeeee; width:40%;">{{ $etiqueta }}</td>
                                        <td style="padding:10px 16px; font-size:14px; color:#1f1f23; font-weight:bold; border-bottom:1px solid #eeeeee;">{{ $valor }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    @if ($contacto['tiene'])
                        <tr>
                            <td style="padding:18px 28px 0 28px;">
                                <p style="margin:0; font-size:14px; line-height:1.6; color:#555555;">
                                    ¿Dudas con tu pago? Comunícate con {{ $contacto['nombre'] }}:
                                    @if ($contacto['whatsapp'] !== '')
                                        <br>WhatsApp: <strong>{{ $contacto['whatsapp'] }}</strong>
                                    @endif
                                    @if ($contacto['correo'] !== '')
                                        <br>Correo: <strong>{{ $contacto['correo'] }}</strong>
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="background-color:#fafafa; padding:18px 28px; text-align:center; border-top:1px solid #eeeeee;">
                            <p style="margin:0; font-size:12px; color:#999999; line-height:1.5;">
                                Este documento es un comprobante de pago y no reemplaza una factura de venta.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
