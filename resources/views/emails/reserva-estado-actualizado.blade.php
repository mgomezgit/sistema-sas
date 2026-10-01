@php
    // $negocio: ['nombre_negocio', 'color_acento', 'slug', 'politica_cancelacion'].
    $negocio = $negocio ?? [];
    $colorAcento = \App\Service\ColorAcento::hex($negocio['color_acento'] ?? null);
    $colorAcentoSuave = \App\Service\ColorAcento::hexSuave($negocio['color_acento'] ?? null);

    // El mensaje se adapta al nuevo estado de la reserva. El acento de marca
    // (franja superior y círculo del icono) es SIEMPRE el mismo, sin importar
    // el estado: lo que cambia de un estado a otro es el icono, el título, el
    // texto y si los datos de la tabla salen tachados.
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

    // "completada" reutiliza el check: también es un desenlace positivo, y el
    // boceto solo define tres iconos (check/X/reloj) para los tres correos,
    // no uno distinto por cada estado de este.
    $iconos = [
        'confirmada' => '✓',
        'cancelada' => '✕',
        'completada' => '✓',
    ];

    $textosBoton = [
        'confirmada' => 'Ver mi reserva',
        'cancelada' => 'Agendar de nuevo',
        'completada' => 'Reservar otra cita',
    ];

    $titulo = $titulos[$estadoReserva] ?? 'Actualización de tu reserva';
    $parrafo = $mensajes[$estadoReserva] ?? ('Hola '.$reserva['nombre_cliente'].', el estado de tu reserva cambió. Estos son los datos:');
    $cierre = $cierres[$estadoReserva] ?? 'Cualquier duda, comunícate con nosotros.';
    $icono = $iconos[$estadoReserva] ?? '✓';
    $textoBoton = $textosBoton[$estadoReserva] ?? 'Ver mi reserva';
    $tachado = $estadoReserva === 'cancelada';
    $etiquetaEmpleado = $estadoReserva === 'completada' ? 'Te atendió' : 'Te atenderá';
@endphp
@include('emails.partials.cuerpo-reserva', [
    'tituloDocumento' => 'Actualización de tu reserva',
    'nombreNegocio' => $negocio['nombre_negocio'] ?? '',
    'colorAcento' => $colorAcento,
    'colorAcentoSuave' => $colorAcentoSuave,
    'icono' => $icono,
    'titulo' => $titulo,
    'parrafo' => $parrafo,
    'reserva' => $reserva,
    'tachado' => $tachado,
    'etiquetaEmpleado' => $etiquetaEmpleado,
    'cierre' => $cierre,
    'politicaCancelacion' => $negocio['politica_cancelacion'] ?? null,
    'urlBoton' => ! empty($negocio['slug']) ? url('reservar/'.$negocio['slug']) : null,
    'textoBoton' => $textoBoton,
])
