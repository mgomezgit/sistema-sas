<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identidad y contacto de la plataforma
    |--------------------------------------------------------------------------
    |
    | Datos que usan los correos de vencimiento de módulos y los mensajes de
    | bloqueo para decirle al negocio con quién renovar. Viven en el .env, nunca
    | en el repositorio: por defecto vacíos. Si no hay contacto configurado, los
    | correos y mensajes usan un texto genérico ("comunícate con soporte").
    |
    | Variables del .env:
    |   PLATAFORMA_NOMBRE             Nombre visible de la plataforma.
    |   PLATAFORMA_CONTACTO_WHATSAPP  WhatsApp de renovación (solo dígitos o texto).
    |   PLATAFORMA_CONTACTO_CORREO    Correo de renovación.
    |
    */

    'nombre' => env('PLATAFORMA_NOMBRE', 'la plataforma'),

    'contacto_whatsapp' => env('PLATAFORMA_CONTACTO_WHATSAPP', ''),

    'contacto_correo' => env('PLATAFORMA_CONTACTO_CORREO', ''),

    /*
    |--------------------------------------------------------------------------
    | Textos de presentación de los módulos de pago
    |--------------------------------------------------------------------------
    |
    | SOLO textos: el plan que muestra la pantalla de "no disponible", la frase
    | que explica el módulo y a qué pantalla real volver si ya está activo. NO
    | hay planes en el sistema todavía: estos nombres de plan son de marketing,
    | no activan nada. La clave de cada entrada es la 'clave' del módulo en
    | modulos_plataforma.
    |
    | - plan:        nombre comercial del plan donde se ofrece (null = no se vende).
    | - descripcion: frase corta de lo que hace, para la pantalla de bloqueo.
    | - destino:     pantalla real del módulo (ruta backoffice) a la que redirigir
    |                si el negocio SÍ lo tiene activo; null si no tiene pantalla propia.
    |
    */

    'modulos' => [
        'reportes' => [
            'plan' => 'Pro',
            'descripcion' => 'Informes de ventas y de servicios, con vista previa en pantalla y descarga a Excel.',
            'destino' => 'backoffice/reportes/ventas',
        ],
        'inventario' => [
            'plan' => 'Pro',
            'descripcion' => 'Control de productos, ingreso de stock, códigos SKU y alertas de inventario bajo.',
            'destino' => 'backoffice/productos',
        ],
        'comisiones' => [
            'plan' => 'Premium',
            'descripcion' => 'Cálculo de comisiones por empleado, tarifas por servicio e historial de pagos.',
            'destino' => 'backoffice/comisiones',
        ],
        'ia' => [
            'plan' => 'Pro',
            'descripcion' => 'Asistente creativo que propone textos para banners, frases y descripciones de servicios.',
            'destino' => null,
        ],
        'chatbot' => [
            'plan' => null,
            'descripcion' => 'Asistente virtual que responde en la página pública las preguntas de los clientes.',
            'destino' => null,
        ],
    ],

];
