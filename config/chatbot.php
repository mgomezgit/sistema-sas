<?php

/*
 * Chatbot de la página pública (módulo de pago "chatbot").
 *
 * La clave de la API NO vive aquí: el SDK (laravel/ai) la lee de
 * OPENAI_API_KEY en .env, a través de config/ai.php del propio paquete.
 * Nunca se escribe una clave en un archivo versionado.
 */
return [

    // Interruptor global de la plataforma. Apagado, ningún negocio tiene
    // chatbot aunque tenga el módulo activo.
    'habilitado' => (bool) env('CHATBOT_HABILITADO', true),

    // Mensajes al mes por negocio, salvo que el negocio tenga su propio
    // límite en negocios.chatbot_limite_mensual.
    'limite_mensual' => (int) env('CHATBOT_LIMITE_MENSUAL', 100),

    // Tope de mensajes al mes sumando TODOS los negocios: protege el saldo
    // prepagado de la cuenta del proveedor.
    'tope_global_mensual' => (int) env('CHATBOT_TOPE_GLOBAL_MENSUAL', 1500),

    // Caracteres máximos de un mensaje del visitante.
    'longitud_maxima_mensaje' => (int) env('CHATBOT_LONGITUD_MAXIMA_MENSAJE', 500),

    // Turnos (pregunta + respuesta) que se recuerdan de una conversación.
    'max_turnos_recordados' => (int) env('CHATBOT_MAX_TURNOS', 6),

    // Mensajes que admite una sola conversación antes de pedir que se siga
    // por WhatsApp (además del límite por IP de la ruta).
    'max_mensajes_por_conversacion' => (int) env('CHATBOT_MAX_MENSAJES_CONVERSACION', 20),

    // Minutos que vive una conversación en la caché del servidor.
    'vida_conversacion_minutos' => (int) env('CHATBOT_VIDA_CONVERSACION', 30),

    // Tope de tokens de salida por respuesta.
    'tokens_max_salida' => (int) env('CHATBOT_TOKENS_MAX_SALIDA', 300),

    // Proveedor y modelo. gpt-6-luna: el modelo pequeño y barato de OpenAI,
    // confirmado en developers.openai.com/api/docs/models/gpt-6-luna.
    'proveedor' => env('CHATBOT_PROVEEDOR', 'openai'),
    'modelo' => env('CHATBOT_MODELO', 'gpt-6-luna'),

    // Esfuerzo de razonamiento. gpt-6-luna razona por defecto ("medium"), y
    // esos tokens se cobran como salida y cuentan dentro del tope de arriba:
    // con 300 tokens podría gastarse todo pensando y no responder nada. Para
    // preguntas de folleto no hace falta razonar.
    'esfuerzo_razonamiento' => env('CHATBOT_ESFUERZO_RAZONAMIENTO', 'none'),

    // Segundos que una petición espera el bloqueo del cupo mensual antes de
    // rendirse (y derivar a WhatsApp sin llamar a la IA).
    'espera_bloqueo_segundos' => (int) env('CHATBOT_ESPERA_BLOQUEO', 5),

    // Segundos máximos de espera al proveedor.
    'timeout' => (int) env('CHATBOT_TIMEOUT', 20),

    // Límites por IP de la ruta del mensaje.
    'mensajes_por_minuto_ip' => (int) env('CHATBOT_MENSAJES_MINUTO_IP', 6),
    'mensajes_por_hora_ip' => (int) env('CHATBOT_MENSAJES_HORA_IP', 40),
];
