@php
    // $negocio: ['nombre_negocio', 'color_acento', 'slug', 'politica_cancelacion'].
    // Ver ReservaController::datosParaNotificar() y ColorAcento (traduce el
    // nombre del acento guardado en negocios.color_acento a un hex plano: un
    // correo no puede leer var(--accent)).
    $negocio = $negocio ?? [];
    $colorAcento = \App\Service\ColorAcento::hex($negocio['color_acento'] ?? null);
    $colorAcentoSuave = \App\Service\ColorAcento::hexSuave($negocio['color_acento'] ?? null);

    // El párrafo se arma aquí con el nombre del cliente y se escapa entero al
    // pintarse en el partial ({{ $parrafo }}): htmlspecialchars() actúa sobre
    // el string final, sin importar cómo se construyó.
    $parrafo = 'Hola '.$reserva['nombre_cliente'].', recibimos tu reserva y ya quedó registrada. Aquí tienes los detalles:';
@endphp
@include('emails.partials.cuerpo-reserva', [
    'tituloDocumento' => 'Confirmación de tu reserva',
    'nombreNegocio' => $negocio['nombre_negocio'] ?? '',
    'colorAcento' => $colorAcento,
    'colorAcentoSuave' => $colorAcentoSuave,
    'icono' => '✓',
    'titulo' => '¡Tu reserva está confirmada!',
    'parrafo' => $parrafo,
    'reserva' => $reserva,
    'tachado' => false,
    'etiquetaEmpleado' => 'Te atenderá',
    'cierre' => 'Si necesitas cambiar o cancelar tu cita, comunícate con nosotros con anticipación. ¡Te esperamos!',
    'politicaCancelacion' => $negocio['politica_cancelacion'] ?? null,
    'urlBoton' => ! empty($negocio['slug']) ? url('reservar/'.$negocio['slug']) : null,
    'textoBoton' => 'Ver mi reserva',
])
