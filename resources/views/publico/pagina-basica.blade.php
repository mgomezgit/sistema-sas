@extends('layout.publico')

{{--
    Página pública BÁSICA: la que ve un negocio sin el módulo "pagina_publica".

    Recibe SOLO la lista blanca de SvcPaginaPublica::informacionBasica()
    (nombre, días y horas de atención, WhatsApp) y no pinta nada más: ni
    servicios, ni equipo, ni promociones, ni frase, ni política, ni teléfono,
    ni el formulario de cita, ni el chat. Todo texto del negocio va con {{ }}.
    Si falta el horario o el número, esa parte no se pinta (sin huecos).
--}}

@section('titulo', $negocio['nombre_negocio'])

@php
    $inicialNegocio = mb_strtoupper(mb_substr(trim($negocio['nombre_negocio']), 0, 1)) ?: '?';

    $resumenDias = \App\Support\HorarioPublico::resumenDias(\App\Support\HorarioPublico::diasActivos($negocio['dias_atencion'] ?? null));
    $horaApertura = \App\Support\HorarioPublico::hora($negocio['hora_apertura'] ?? null);
    $horaCierre = \App\Support\HorarioPublico::hora($negocio['hora_cierre'] ?? null);
    $horas = ($horaApertura && $horaCierre) ? 'de '.$horaApertura.' a '.$horaCierre : null;

    // Una sola frase: "Lunes a Sábado, de 9:00 AM a 7:00 PM" (o la parte que haya).
    $textoHorario = $resumenDias && $horas ? $resumenDias.', '.$horas : ($resumenDias ?: ($horas ? ucfirst($horas) : null));

    $whatsappLink = \App\Support\HorarioPublico::whatsappLink($negocio['whatsapp_numero'] ?? null, 'Hola, quiero hacerles una consulta');
@endphp

@section('content')
<div class="lienzo lienzo-basico">
    <div class="mancha mancha1"></div>
    <div class="mancha mancha2"></div>

    <main class="basica" aria-labelledby="nombre-negocio-basica">
        <div class="basica-tarjeta" id="pagina-basica">
            <div class="basica-logo" aria-hidden="true">{{ $inicialNegocio }}</div>

            <h1 class="basica-nombre" id="nombre-negocio-basica">{{ $negocio['nombre_negocio'] }}</h1>

            @if ($textoHorario)
                <div class="basica-horario" id="horario-basica">
                    <h2 class="basica-subtitulo">Horario de atención</h2>
                    <p class="basica-texto-horario">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        <span>{{ $textoHorario }}</span>
                    </p>
                </div>
            @endif

            @if ($whatsappLink)
                <a href="{{ $whatsappLink }}" class="basica-whatsapp" id="whatsapp-basica" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i>
                    Escríbenos por WhatsApp
                </a>
            @endif
        </div>
    </main>
</div>
@endsection
