<?php

/*
 * Asistente creativo (módulo de pago "ia").
 *
 * La clave de la API NO vive aquí: el SDK (laravel/ai) la lee de
 * OPENAI_API_KEY en .env. Proveedor, modelo y esfuerzo de razonamiento tienen
 * variables propias (IA_*); si no están definidas, caen a las del chatbot y,
 * si tampoco, al mismo valor por defecto que usa el chatbot.
 */
return [

    // Interruptor global de la plataforma.
    'habilitado' => (bool) env('IA_HABILITADO', true),

    // Generaciones al mes por negocio, salvo que el negocio tenga su propio
    // límite en negocios.ia_limite_mensual.
    'limite_mensual' => (int) env('IA_LIMITE_MENSUAL', 40),

    // Tope de generaciones al mes sumando TODOS los negocios.
    'tope_global_mensual' => (int) env('IA_TOPE_GLOBAL_MENSUAL', 1000),

    // Tope de tokens de salida por generación (3 opciones cortas en JSON).
    'tokens_max_salida' => (int) env('IA_TOKENS_MAX_SALIDA', 400),

    'proveedor' => env('IA_PROVEEDOR', env('CHATBOT_PROVEEDOR', 'openai')),
    'modelo' => env('IA_MODELO', env('CHATBOT_MODELO', 'gpt-6-luna')),
    'esfuerzo_razonamiento' => env('IA_ESFUERZO_RAZONAMIENTO', env('CHATBOT_ESFUERZO_RAZONAMIENTO', 'none')),

    // Segundos máximos de espera al proveedor.
    'timeout' => (int) env('IA_TIMEOUT', 30),

    // Segundos que una petición espera el bloqueo del cupo antes de rendirse.
    'espera_bloqueo_segundos' => (int) env('IA_ESPERA_BLOQUEO', 5),

    // Límites por usuario del endpoint de generar.
    'generaciones_por_minuto_usuario' => (int) env('IA_POR_MINUTO_USUARIO', 10),
    'generaciones_por_hora_usuario' => (int) env('IA_POR_HORA_USUARIO', 100),

    // Términos que hacen que una opción llegue con advertencia ("parece
    // prometer un resultado"). No se descarta: el admin decide.
    'terminos_riesgo' => [
        'cura', 'curar', 'elimina', 'adelgaza', 'garantizado', 'garantizamos',
        '100%', 'milagro', 'sin riesgo', 'sin efectos secundarios',
    ],
];
