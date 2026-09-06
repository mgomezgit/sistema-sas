<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen de inventario</title>
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
                            <h2 style="margin:0 0 12px 0; font-size:18px; color:#1f1f23;">Productos con stock bajo</h2>
                            <p style="margin:0 0 18px 0; font-size:15px; line-height:1.6; color:#555555;">
                                Estos productos de tu inventario están por debajo de la cantidad mínima que definiste:
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 8px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa; border:1px solid #eeeeee; border-radius:6px;">
                                <tr>
                                    <td style="padding:10px 16px; font-size:13px; color:#777777; font-weight:bold; border-bottom:1px solid #eeeeee;">Producto</td>
                                    <td style="padding:10px 16px; font-size:13px; color:#777777; font-weight:bold; border-bottom:1px solid #eeeeee; text-align:right;">Existencias</td>
                                </tr>
                                @foreach ($productos as $producto)
                                    <tr>
                                        <td style="padding:10px 16px; font-size:14px; color:#1f1f23; font-weight:bold; @unless ($loop->last) border-bottom:1px solid #eeeeee; @endunless">{{ $producto['nombre'] }}</td>
                                        <td style="padding:10px 16px; font-size:14px; color:#c2596a; text-align:right; @unless ($loop->last) border-bottom:1px solid #eeeeee; @endunless">
                                            {{ $producto['cantidad_actual'] }} de {{ $producto['cantidad_minima'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 28px 28px;">
                            <p style="margin:0; font-size:15px; line-height:1.6; color:#555555;">
                                Ingresa a tu panel para reponer el inventario cuando puedas.
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
