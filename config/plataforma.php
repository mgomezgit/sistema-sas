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

];
