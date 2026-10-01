@php
    // $negocio: ['nombre_negocio', 'color_acento', 'slug', 'politica_cancelacion'].
    $negocio = $negocio ?? [];
    $colorAcento = \App\Service\ColorAcento::hex($negocio['color_acento'] ?? null);
    $colorAcentoSuave = \App\Service\ColorAcento::hexSuave($negocio['color_acento'] ?? null);

    $parrafo = 'Hola '.$reserva['nombre_cliente'].', este es un recordatorio de la cita que tienes agendada para mañana. Estos son los datos:';
@endphp
@include('emails.partials.cuerpo-reserva', [
    'tituloDocumento' => 'Recordatorio de tu reserva',
    'nombreNegocio' => $negocio['nombre_negocio'] ?? '',
    'colorAcento' => $colorAcento,
    'colorAcentoSuave' => $colorAcentoSuave,
    // Sin un glifo de reloj universalmente seguro (ver el reporte final,
    // punto 1), "!" marca la atención de "no lo olvides" sin depender de un
    // carácter que algunos clientes no tengan en su fuente.
    'icono' => '!',
    'titulo' => 'Te esperamos mañana',
    'parrafo' => $parrafo,
    'reserva' => $reserva,
    'tachado' => false,
    'etiquetaEmpleado' => 'Te atenderá',
    'cierre' => 'Si no puedes asistir, avísanos con tiempo para reagendar tu cita. ¡Nos vemos pronto!',
    'politicaCancelacion' => $negocio['politica_cancelacion'] ?? null,
    'urlBoton' => ! empty($negocio['slug']) ? url('reservar/'.$negocio['slug']) : null,
    'textoBoton' => 'Ver mi cita',
])
